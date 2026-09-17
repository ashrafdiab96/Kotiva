<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Pages\ManageSettings;
use App\Models\Admin;
use App\Models\Setting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Store settings (§7.6).
 *
 * Two things are worth testing here and neither is the form itself. First,
 * settings are the one thing that can quietly break the whole shop, so only a
 * super admin may reach the page — and hiding a nav entry is not that. Second,
 * a saved value has to be readable immediately: Setting caches its map
 * forever, so a write that failed to flush would leave the dashboard showing
 * one number and the shop using another.
 */
final class SettingsPageTest extends TestCase
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

    /* ── access ──────────────────────────────────────────────── */

    #[Test]
    public function only_a_super_admin_may_reach_the_settings_page(): void
    {
        $this->actingAsAdmin(AdminRole::SuperAdmin);
        $this->assertTrue(ManageSettings::canAccess());
        $this->assertSame(200, $this->get(ManageSettings::getUrl())->status());
    }

    #[Test]
    public function a_manager_is_refused_the_route_not_merely_the_nav_entry(): void
    {
        $this->actingAsAdmin(AdminRole::Manager);

        $this->assertFalse(ManageSettings::canAccess());
        // Typing the URL must 403, which is what canAccess() drives.
        $this->get(ManageSettings::getUrl())->assertForbidden();
    }

    #[Test]
    public function staff_are_refused_too(): void
    {
        $this->actingAsAdmin(AdminRole::Staff);

        $this->assertFalse(ManageSettings::canAccess());
        $this->get(ManageSettings::getUrl())->assertForbidden();
    }

    /* ── saving ──────────────────────────────────────────────── */

    #[Test]
    public function saving_writes_every_setting_and_is_readable_immediately(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'store_email' => 'hello@kotiva.test',
                'low_stock_alert_email' => 'ops@kotiva.test',
                'admin_notification_emails' => ['owner@kotiva.test'],
                'cod_enabled' => false,
                'cart_ttl_hours' => 48,
                'low_stock_threshold' => 7,
                'vat_rate_percent' => 15,
                'announcement_enabled' => true,
                'announcement_text' => 'Free delivery over SAR 300',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Read back through the cached accessor, not the table: a write that
        // did not flush would pass a database assertion and still serve a
        // stale value to the shop.
        $this->assertSame('hello@kotiva.test', Setting::get('store_email'));
        $this->assertSame('ops@kotiva.test', Setting::get('low_stock_alert_email'));
        $this->assertSame(['owner@kotiva.test'], Setting::get('admin_notification_emails'));
        $this->assertFalse((bool) Setting::get('cod_enabled'));
        $this->assertSame(48, (int) Setting::get('cart_ttl_hours'));
        $this->assertSame(7, (int) Setting::get('low_stock_threshold'));
    }

    #[Test]
    public function the_vat_field_is_a_percentage_but_is_stored_as_a_rate(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageSettings::class)
            ->fillForm($this->validForm(['vat_rate_percent' => 15]))
            ->call('save')
            ->assertHasNoFormErrors();

        // An admin typing "15" into a field that stored it raw would set VAT to
        // 1500% and misstate the tax on every order afterwards.
        $this->assertSame(0.15, (float) Setting::get('vat_rate'));
    }

    #[Test]
    public function the_vat_percentage_round_trips_back_into_the_form(): void
    {
        $this->actingAsAdmin();
        Setting::put('vat_rate', 0.05);

        Livewire::test(ManageSettings::class)
            ->assertFormSet(fn (array $state): bool => (float) $state['vat_rate_percent'] === 5.0);
    }

    #[Test]
    public function an_address_that_is_not_an_address_is_refused_rather_than_saved(): void
    {
        $this->actingAsAdmin();
        Setting::put('admin_notification_emails', ['existing@kotiva.test']);

        Livewire::test(ManageSettings::class)
            ->fillForm($this->validForm([
                'admin_notification_emails' => ['ops@kotiva.test', 'not-an-email'],
            ]))
            ->call('save')
            // The rule is recursive, so the error is raised against the
            // offending entry — data.admin_notification_emails.1 — and not
            // against the field as a whole.
            ->assertHasFormErrors(['admin_notification_emails.1']);

        // Refused outright rather than quietly filtered: the admin is told,
        // and the list that was already delivering order mail keeps working.
        // Storing rubbish here would silently stop those notifications.
        $this->assertSame(['existing@kotiva.test'], Setting::get('admin_notification_emails'));

        // A refused save writes none of its other fields either.
        $this->assertNull(Setting::get('store_email'));
    }

    #[Test]
    public function a_blank_entry_is_dropped_without_blocking_the_save(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageSettings::class)
            ->fillForm($this->validForm([
                'admin_notification_emails' => ['ops@kotiva.test', ''],
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        // Laravel's email rule treats an empty string as absent, so validation
        // lets it through untouched. save() drops it rather than storing a
        // blank address that would sit in the list delivering nothing.
        $this->assertSame(['ops@kotiva.test'], Setting::get('admin_notification_emails'));
    }

    #[Test]
    public function the_announcement_bar_persists_its_text_and_switch(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ManageSettings::class)
            ->fillForm($this->validForm([
                'announcement_enabled' => true,
                'announcement_text' => '  Free delivery over SAR 300  ',
            ]))
            ->call('save')
            ->assertHasNoFormErrors();

        $bar = Setting::get('announcement_bar');

        $this->assertIsArray($bar);
        $this->assertTrue($bar['enabled']);
        $this->assertSame('Free delivery over SAR 300', $bar['text'], 'stored trimmed');
    }

    #[Test]
    public function the_page_opens_showing_the_values_already_stored(): void
    {
        $this->actingAsAdmin();

        Setting::put('store_email', 'existing@kotiva.test');
        Setting::put('cart_ttl_hours', 96);

        Livewire::test(ManageSettings::class)
            ->assertFormSet([
                'store_email' => 'existing@kotiva.test',
                'cart_ttl_hours' => 96,
            ]);
    }

    /**
     * A complete valid payload, so a test can vary one field without tripping
     * required-field validation on the others.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validForm(array $overrides = []): array
    {
        return array_merge([
            'store_email' => 'hello@kotiva.test',
            'low_stock_alert_email' => 'ops@kotiva.test',
            'admin_notification_emails' => ['owner@kotiva.test'],
            'cod_enabled' => true,
            'cart_ttl_hours' => 72,
            'low_stock_threshold' => 5,
            'vat_rate_percent' => 15,
            'announcement_enabled' => false,
            'announcement_text' => '',
        ], $overrides);
    }
}
