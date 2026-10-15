<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Resources\ReferralPartnerResource;
use App\Filament\Resources\ReferralPartnerResource\Pages\CreateReferralPartner;
use App\Filament\Resources\ReferralPartnerResource\Pages\EditReferralPartner;
use App\Filament\Resources\ReferralPartnerResource\Pages\ListReferralPartners;
use App\Filament\Resources\ReferralPartnerResource\Widgets\PartnerRecentVisits;
use App\Filament\Resources\ReferralPartnerResource\Widgets\PartnerVisitsChart;
use App\Filament\Widgets\ReferralsOverview;
use App\Models\Admin;
use App\Models\ReferralPartner;
use App\Models\ReferralVisit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Marketing → Doctor referrals dashboard (D-38).
 */
final class ReferralPartnerAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    private function visit(ReferralPartner $partner, string $when, bool $unique = true): ReferralVisit
    {
        return ReferralVisit::create([
            'referral_partner_id' => $partner->id,
            'visited_at' => $when,
            'ip_hash' => hash('sha256', 'secret-ip'.$when),
            'user_agent' => 'SecretAgent/9.9',
            'referer' => 'https://wa.me/',
            'landing_path' => '/',
            'is_unique_day' => $unique,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dr Ahmed',
            'code' => 'dr-ahmed',
            'salla_affiliate_url' => $this->store().'/?ref=AHMED42',
            'landing_path' => '/science',
            'is_active' => true,
        ], $overrides);
    }

    #[Test]
    public function the_pages_render(): void
    {
        $this->actingAsAdmin();
        $partner = ReferralPartner::factory()->create();
        $this->visit($partner, now()->toDateTimeString());

        $this->get(ReferralPartnerResource::getUrl('index'))->assertOk()->assertSee($partner->shareUrl());
        $this->get(ReferralPartnerResource::getUrl('create'))->assertOk();
        $this->get(ReferralPartnerResource::getUrl('edit', ['record' => $partner]))->assertOk();

        $view = $this->get(ReferralPartnerResource::getUrl('view', ['record' => $partner]))->assertOk();
        $view->assertDontSee('SecretAgent');
        $view->assertDontSee(hash('sha256', 'secret-ip'.now()->toDateTimeString()));
    }

    #[Test]
    public function a_valid_partner_is_created(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateReferralPartner::class)
            ->fillForm($this->validForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('referral_partners', ['code' => 'dr-ahmed', 'landing_path' => '/science']);
    }

    #[Test]
    public function the_form_rejects_a_non_salla_affiliate_url(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateReferralPartner::class)
            ->fillForm($this->validForm(['salla_affiliate_url' => 'https://evil.example.test/?ref=AHMED42']))
            ->call('create')
            ->assertHasFormErrors(['salla_affiliate_url']);

        $this->assertSame(0, ReferralPartner::query()->count());
    }

    #[Test]
    public function the_form_rejects_an_affiliate_url_without_a_query(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateReferralPartner::class)
            ->fillForm($this->validForm(['salla_affiliate_url' => $this->store().'/']))
            ->call('create')
            ->assertHasFormErrors(['salla_affiliate_url']);
    }

    #[Test]
    public function the_form_rejects_a_bad_code_or_landing_path(): void
    {
        $this->actingAsAdmin();
        ReferralPartner::factory()->create(['code' => 'dr-taken']);

        foreach (['Dr Ahmed', 'ab', 'dr--x', 'dr-taken', str_repeat('a', 33)] as $code) {
            Livewire::test(CreateReferralPartner::class)
                ->fillForm($this->validForm(['code' => $code]))
                ->call('create')
                ->assertHasFormErrors(['code']);
        }

        foreach (['science', '//evil.example', 'https://evil.example/', '/a.b'] as $path) {
            Livewire::test(CreateReferralPartner::class)
                ->fillForm($this->validForm(['landing_path' => $path]))
                ->call('create')
                ->assertHasFormErrors(['landing_path']);
        }
    }

    #[Test]
    public function the_code_is_suggested_from_the_name(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateReferralPartner::class)
            ->fillForm(['name' => 'Dr Ahmed Saleh'])
            ->assertFormSet(['code' => 'dr-ahmed-saleh']);
    }

    #[Test]
    public function the_list_computes_visit_stats_without_n_plus_one(): void
    {
        $this->actingAsAdmin();
        $a = ReferralPartner::factory()->create(['name' => 'A']);
        $b = ReferralPartner::factory()->create(['name' => 'B']);

        $this->visit($a, now()->subDays(2)->toDateTimeString());
        $this->visit($a, now()->subDays(2)->addMinute()->toDateTimeString(), unique: false);
        $this->visit($a, now()->subDays(40)->toDateTimeString());

        $row = ReferralPartnerResource::getEloquentQuery()->whereKey($a->id)->sole();
        $this->assertSame(3, (int) $row->getAttribute('visits_total'));
        $this->assertSame(1, (int) $row->getAttribute('visits_unique_30d'));
        $this->assertNotNull($row->getAttribute('last_visit_at'));

        // By key, so the record is resolved through the table's own query.
        Livewire::test(ListReferralPartners::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->assertTableColumnStateSet('visits_total', 3, (string) $a->id)
            ->assertTableColumnStateSet('visits_unique_30d', 1, (string) $a->id)
            ->assertTableColumnStateSet('visits_total', 0, (string) $b->id)
            ->assertTableColumnStateSet('share_link', url('/dr/'.$a->code), (string) $a->id);
    }

    #[Test]
    public function the_bulk_action_deactivates(): void
    {
        $this->actingAsAdmin();
        $partners = ReferralPartner::factory()->count(2)->create();

        Livewire::test(ListReferralPartners::class)
            ->callTableBulkAction('deactivate', $partners);

        $this->assertSame(0, ReferralPartner::query()->active()->count());
    }

    #[Test]
    public function deletion_is_soft_and_never_forced(): void
    {
        $this->actingAsAdmin();
        $partner = ReferralPartner::factory()->create();
        $this->visit($partner, now()->toDateTimeString());

        Livewire::test(EditReferralPartner::class, ['record' => $partner->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted($partner);
        $this->assertSame(1, ReferralVisit::query()->count(), 'visit history kept');
        $this->assertFalse(ReferralPartnerResource::canForceDelete($partner));
        $this->assertFalse(ReferralPartnerResource::canForceDeleteAny());
    }

    #[Test]
    public function staff_cannot_reach_it(): void
    {
        $this->actingAsAdmin(AdminRole::Staff);

        $this->get(ReferralPartnerResource::getUrl('index'))->assertForbidden();
        $this->assertFalse(ReferralsOverview::canView());
    }

    #[Test]
    public function the_chart_and_recent_visits_cover_the_right_window(): void
    {
        $this->actingAsAdmin();
        $partner = ReferralPartner::factory()->create();
        $other = ReferralPartner::factory()->create();

        for ($i = 0; $i < 105; $i++) {
            $this->visit($partner, now()->subMinutes($i)->toDateTimeString(), unique: $i === 0);
        }
        $this->visit($other, now()->toDateTimeString());

        $series = PartnerVisitsChart::series($partner, 30);
        $this->assertCount(30, $series);
        $this->assertSame(105, array_sum(array_column($series, 'visits')));

        $recent = PartnerRecentVisits::query($partner)->get();
        $this->assertCount(100, $recent);
        $this->assertTrue($recent->every(fn (ReferralVisit $v): bool => $v->referral_partner_id === $partner->id));
        $this->assertArrayNotHasKey('ip_hash', $recent->first()?->getAttributes() ?? []);
        $this->assertArrayNotHasKey('user_agent', $recent->first()?->getAttributes() ?? []);

        Livewire::test(PartnerRecentVisits::class, ['record' => $partner])->assertOk();
        Livewire::test(PartnerVisitsChart::class, ['record' => $partner])->assertOk();
    }

    #[Test]
    public function the_dashboard_widget_reports_the_last_30_days(): void
    {
        $this->actingAsAdmin();
        $a = ReferralPartner::factory()->create(['name' => 'Dr A']);
        $b = ReferralPartner::factory()->create(['name' => 'Dr B']);

        $this->visit($a, now()->toDateTimeString());
        $this->visit($a, now()->subDay()->toDateTimeString(), unique: false);
        $this->visit($b, now()->toDateTimeString());
        $this->visit($b, now()->subDays(45)->toDateTimeString());

        $stats = ReferralsOverview::stats(30);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['unique']);
        $this->assertSame(['Dr A', 'Dr B'], array_column($stats['top'], 'name'));
        $this->assertTrue(ReferralsOverview::canView());

        $this->get('/admin')->assertOk();
        // Lazy-loaded on the dashboard, so rendered on its own here.
        Livewire::test(ReferralsOverview::class)
            ->assertSee('Doctor referrals — last 30 days')
            ->assertSee('Dr A')
            ->assertDontSee('SecretAgent');
    }
}
