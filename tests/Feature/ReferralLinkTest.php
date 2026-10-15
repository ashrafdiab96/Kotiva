<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use App\Support\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * /dr/{code}: the doctor's share link (D-38).
 */
final class ReferralLinkTest extends TestCase
{
    use RefreshDatabase;

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    private function doctor(array $state = []): ReferralPartner
    {
        return ReferralPartner::factory()->create(array_merge([
            'name' => 'Dr Ahmed',
            'code' => 'dr-ahmed',
            'salla_affiliate_url' => $this->store().'/?ref=AHMED42',
            'landing_path' => '/science',
        ], $state));
    }

    #[Test]
    public function an_active_code_sets_the_cookie_records_a_visit_and_redirects_to_the_landing_page(): void
    {
        $partner = $this->doctor();

        $response = $this->withHeaders(['Referer' => 'https://wa.me/', 'User-Agent' => 'TestBrowser/1.0'])
            ->get('/dr/dr-ahmed');

        $response->assertStatus(302)->assertRedirect('/science');
        $response->assertCookie('kotiva_ref', 'dr-ahmed');

        $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'kotiva_ref');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertSame('/', $cookie->getPath());
        $this->assertEqualsWithDelta(now()->addDays(90)->getTimestamp(), $cookie->getExpiresTime(), 5);

        $visit = ReferralVisit::query()->sole();
        $this->assertSame($partner->id, $visit->referral_partner_id);
        $this->assertTrue($visit->is_unique_day);
        $this->assertSame('/science', $visit->landing_path);
        $this->assertSame('https://wa.me/', $visit->referer);
        $this->assertSame(hash('sha256', '127.0.0.1'.config('app.key')), $visit->ip_hash);
        $this->assertNotSame('127.0.0.1', $visit->ip_hash);
    }

    #[Test]
    public function the_cookie_is_secure_only_over_https(): void
    {
        $this->doctor();

        foreach (['https://localhost/dr/dr-ahmed' => true, 'http://localhost/dr/dr-ahmed' => false] as $url => $secure) {
            $response = $this->get($url);

            $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === 'kotiva_ref');
            $this->assertNotNull($cookie, $url);
            $this->assertSame($secure, $cookie->isSecure(), $url);
        }
    }

    #[Test]
    public function a_second_visit_the_same_day_from_the_same_ip_is_not_unique(): void
    {
        $this->doctor();

        $this->get('/dr/dr-ahmed')->assertStatus(302);
        $this->get('/dr/dr-ahmed')->assertStatus(302);

        $this->assertSame([true, false], ReferralVisit::query()->orderBy('id')->pluck('is_unique_day')->all());

        $this->get('/dr/dr-ahmed', [])->assertStatus(302);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->get('/dr/dr-ahmed');
        $this->assertTrue(ReferralVisit::query()->latest('id')->first()?->is_unique_day, 'another IP is unique');
    }

    #[Test]
    public function the_same_ip_is_unique_again_the_next_day(): void
    {
        $this->doctor();

        $this->get('/dr/dr-ahmed');
        Carbon::setTestNow(now()->addDay()->startOfDay()->addHour());
        $this->get('/dr/dr-ahmed');
        Carbon::setTestNow();

        $this->assertSame([true, true], ReferralVisit::query()->orderBy('id')->pluck('is_unique_day')->all());
    }

    #[Test]
    public function an_unknown_code_goes_home_quietly_with_no_cookie(): void
    {
        foreach (['/dr/nobody', '/dr/x', '/dr/Not.A.Code', '/dr/'.str_repeat('a', 80)] as $path) {
            $response = $this->get($path);

            $response->assertStatus(302)->assertRedirect('/');
            $response->assertCookieMissing('kotiva_ref');
        }

        $this->assertSame(0, ReferralVisit::query()->count());
    }

    #[Test]
    public function an_inactive_or_deleted_code_goes_home_with_no_cookie(): void
    {
        $this->doctor(['is_active' => false]);
        $deleted = $this->doctor(['code' => 'dr-gone']);
        $deleted->delete();

        foreach (['/dr/dr-ahmed', '/dr/dr-gone'] as $path) {
            $response = $this->get($path);

            $response->assertStatus(302)->assertRedirect('/');
            $response->assertCookieMissing('kotiva_ref');
        }

        $this->assertSame(0, ReferralVisit::query()->count());
    }

    #[Test]
    public function the_code_is_matched_case_insensitively(): void
    {
        $this->doctor();

        $this->get('/dr/DR-Ahmed')->assertRedirect('/science')->assertCookie('kotiva_ref', 'dr-ahmed');
    }

    #[Test]
    public function to_shop_redirects_straight_to_salla_with_the_affiliate_query(): void
    {
        $this->doctor();

        $response = $this->get('/dr/dr-ahmed?to=shop');

        $response->assertStatus(302)->assertRedirect($this->store().'/?ref=AHMED42');
        $response->assertCookie('kotiva_ref', 'dr-ahmed');
        $this->assertSame('salla:store', ReferralVisit::query()->sole()->landing_path);
    }

    #[Test]
    public function the_last_doctor_wins(): void
    {
        $this->doctor();
        $this->doctor(['code' => 'dr-sara', 'name' => 'Dr Sara', 'salla_affiliate_url' => $this->store().'/?ref=SARA7']);

        $response = $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/dr/dr-sara?to=shop');

        $response->assertRedirect($this->store().'/?ref=SARA7');
        $response->assertCookie('kotiva_ref', 'dr-sara');
    }

    #[Test]
    public function an_unsafe_stored_landing_path_falls_back_to_home(): void
    {
        $this->doctor(['landing_path' => '//evil.example']);

        $this->get('/dr/dr-ahmed')->assertRedirect('/');
    }

    #[Test]
    public function the_route_is_rate_limited_per_ip(): void
    {
        $this->doctor();

        for ($i = 0; $i < 60; $i++) {
            $this->get('/dr/dr-ahmed')->assertStatus(302);
        }

        $this->get('/dr/dr-ahmed')->assertStatus(429);
    }

    #[Test]
    public function the_route_is_named(): void
    {
        $this->assertSame(url('/dr/dr-ahmed'), route('referral.enter', ['code' => 'dr-ahmed']));
        $this->assertSame('kotiva_ref', Referral::cookieName());
    }
}
