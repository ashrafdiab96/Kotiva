<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ReferralPartner;
use App\Support\Salla;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * With the kotiva_ref cookie, every Salla URL carries the doctor's affiliate
 * query and keeps the ?filter= / ?routine= contract; without it nothing
 * changes (D-38).
 */
final class ReferralSallaUrlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['kotiva.salla.redirect_status' => 302, 'kotiva.commerce_enabled' => false]);

        ReferralPartner::factory()->create([
            'code' => 'dr-ahmed',
            'salla_affiliate_url' => $this->store().'/?ref=AHMED42',
        ]);
    }

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    private function micellar(): Product
    {
        return Product::factory()->create([
            'sku' => 'KOT001',
            'slug' => 'micellar-water',
            'salla_product_id' => 1713901761,
            'salla_path' => '/micellar-water/p1713901761',
            'salla_synced_at' => now(),
        ]);
    }

    /** Bind a request carrying the (already decrypted) cookie. */
    private function referredRequest(?string $code = 'dr-ahmed'): void
    {
        $this->app->instance('request', Request::create('/', 'GET', [], $code === null ? [] : ['kotiva_ref' => $code]));
    }

    #[Test]
    public function with_the_cookie_product_and_listing_urls_carry_the_affiliate_query(): void
    {
        $product = $this->micellar();
        $this->referredRequest();

        $this->assertSame($this->store().'/micellar-water/p1713901761?ref=AHMED42', Salla::productUrl($product));

        $listing = Salla::listingUrl('face');
        $this->assertStringContainsString('filter=cleanser', $listing, 'face → cleanser contract preserved');
        $this->assertStringContainsString('ref=AHMED42', $listing);
        $this->assertSame($this->store().'/?filter=cleanser&ref=AHMED42', $listing);

        $this->assertSame(
            $this->store().'/?filter=hair&routine=KOT001,KOT005&ref=AHMED42',
            Salla::listingUrl('hair', ['KOT001', 'KOT005']),
        );
        $this->assertSame($this->store().'/?ref=AHMED42', Salla::storeUrl());
        $this->assertStringContainsString('ref=AHMED42', Salla::cartUrl());
        $this->assertSame('ref=AHMED42', Salla::referralQuery());
    }

    #[Test]
    public function with_the_referral_opted_out_the_url_is_clean(): void
    {
        $product = $this->micellar();
        $this->referredRequest();

        $this->assertSame($this->store(), Salla::storeUrl(withReferral: false));
        $this->assertSame($this->store().'/micellar-water/p1713901761', Salla::productUrl($product, withReferral: false));
        $this->assertSame($this->store().'/?filter=cleanser', Salla::listingUrl('face', withReferral: false));
    }

    #[Test]
    public function without_the_cookie_nothing_changes(): void
    {
        $product = $this->micellar();
        $this->referredRequest(null);

        $this->assertSame($this->store().'/micellar-water/p1713901761', Salla::productUrl($product));
        $this->assertSame($this->store().'/?filter=cleanser', Salla::listingUrl('face'));
        $this->assertSame($this->store(), Salla::storeUrl());
        $this->assertSame('', Salla::referralQuery());
    }

    #[Test]
    public function an_inactive_partner_or_a_junk_cookie_adds_nothing(): void
    {
        ReferralPartner::query()->update(['is_active' => false]);
        $this->referredRequest();
        $this->assertSame($this->store().'/?filter=cleanser', Salla::listingUrl('face'));

        $this->referredRequest('<script>');
        $this->assertSame($this->store().'/?filter=cleanser', Salla::listingUrl('face'));
    }

    #[Test]
    public function the_shop_and_product_redirects_carry_the_referral(): void
    {
        $this->micellar();

        $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/shop?filter=face')
            ->assertRedirect($this->store().'/?filter=cleanser&ref=AHMED42');

        $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/shop?filter=hair&routine=kot001,KOT005')
            ->assertRedirect($this->store().'/?filter=hair&routine=KOT001,KOT005&ref=AHMED42');

        $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/product/micellar-water')
            ->assertRedirect($this->store().'/micellar-water/p1713901761?ref=AHMED42');

        $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/cart')
            ->assertRedirect(Salla::cartUrl(withReferral: false).'?ref=AHMED42');
    }

    #[Test]
    public function the_full_journey_from_the_share_link_to_the_store(): void
    {
        $this->micellar();

        $cookie = collect($this->get('/dr/dr-ahmed')->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === 'kotiva_ref');
        $this->assertNotNull($cookie);

        // The cookie as the browser sends it back: still encrypted.
        $this->withUnencryptedCookie('kotiva_ref', (string) $cookie->getValue())
            ->get('/shop?filter=face')
            ->assertRedirect($this->store().'/?filter=cleanser&ref=AHMED42');
    }

    #[Test]
    public function pages_link_to_salla_with_the_referral_and_mark_the_head(): void
    {
        $html = (string) $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="kotiva-ref" content="dr-ahmed" />', $html);
        $this->assertStringContainsString('href="'.e($this->store().'/?filter=cleanser&ref=AHMED42').'"', $html);
        $this->assertStringContainsString('href="'.e($this->store().'/?ref=AHMED42').'"', $html);
    }

    #[Test]
    public function a_normal_visitor_sees_no_meta_and_no_referral(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('kotiva-ref', $html);
        $this->assertStringNotContainsString('ref=AHMED42', $html);
    }

    #[Test]
    public function the_routine_finder_appends_the_referral_after_the_routine(): void
    {
        $html = (string) $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/routine-finder')->assertOk()->getContent();

        $this->assertStringContainsString('window.SALLA_STORE_URL = '.json_encode($this->store()), $html);
        $this->assertStringContainsString('window.SALLA_REF_QUERY = "ref=AHMED42"', $html);
        $this->assertStringContainsString("SALLA_STORE_URL + '/?routine='", $html);
    }

    #[Test]
    public function the_product_api_carries_the_referral_for_the_routine_finder(): void
    {
        $this->micellar();

        // Same-origin fetch() sends cookies; Laravel's JSON helpers only do with credentials.
        $this->withCredentials()->withCookie('kotiva_ref', 'dr-ahmed')->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.url', $this->store().'/micellar-water/p1713901761?ref=AHMED42');
    }

    #[Test]
    public function crawl_files_stay_clean_for_a_referred_visitor(): void
    {
        $this->micellar();

        $robots = (string) $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/robots.txt')->getContent();
        $llms = (string) $this->withCookie('kotiva_ref', 'dr-ahmed')->get('/llms.txt')->getContent();

        $this->assertStringNotContainsString('AHMED42', $robots.$llms);
        $this->assertStringContainsString($this->store().'/micellar-water/p1713901761', $llms);
    }

    #[Test]
    public function the_partner_is_looked_up_once_per_request(): void
    {
        $this->micellar();
        $this->referredRequest();

        DB::enableQueryLog();
        Salla::storeUrl();
        Salla::listingUrl('face');
        Salla::cartUrl();
        $lookups = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'referral_partners'));
        DB::disableQueryLog();

        $this->assertCount(1, $lookups);
    }
}
