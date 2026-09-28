<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\OrderStatus;
use App\Filament\Resources\ShippingCityResource;
use App\Filament\Resources\ShippingZoneResource;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ShippingCity;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\ShippingCalculator;
use App\Support\CityCsvImporter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The shipping dashboard (§7.3).
 *
 * The rate tests matter most. A zone quotes from activeRate(), which is
 * latestOfMany() over the active rows, and the table has no unique constraint —
 * so two active rates leave an older one silently shadowed rather than visibly
 * wrong, and the shop charges a fee nobody chose.
 */
final class ShippingAdminTest extends TestCase
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

    /* ── pages ───────────────────────────────────────────────── */

    #[Test]
    public function the_zone_and_city_pages_render(): void
    {
        $this->actingAsAdmin();

        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create();
        $city = ShippingCity::factory()->for($zone, 'zone')->create();

        $this->assertSame(200, $this->get(ShippingZoneResource::getUrl('index'))->status(), 'zones list');
        $this->assertSame(200, $this->get(ShippingZoneResource::getUrl('create'))->status(), 'zone create');
        $this->assertSame(200, $this->get(ShippingZoneResource::getUrl('edit', ['record' => $zone]))->status(), 'zone edit');

        $this->assertSame(200, $this->get(ShippingCityResource::getUrl('index'))->status(), 'cities list');
        $this->assertSame(200, $this->get(ShippingCityResource::getUrl('edit', ['record' => $city]))->status(), 'city edit');
    }

    /* ── one active rate per zone ────────────────────────────── */

    #[Test]
    public function activating_a_rate_retires_the_previous_one(): void
    {
        $zone = ShippingZone::factory()->create();

        $old = ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '25.00']);
        $new = ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '35.00']);

        $new->activate();

        $this->assertFalse($old->refresh()->is_active, 'the previous rate must be retired');
        $this->assertTrue($new->refresh()->is_active);
        $this->assertSame(1, $zone->rates()->where('is_active', true)->count());

        // Retired, not deleted: the history is the point of keeping rows.
        $this->assertSame(2, $zone->rates()->count());
    }

    #[Test]
    public function a_quote_uses_the_newly_activated_rate(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '25.00', 'free_shipping_threshold' => null]);

        $calculator = app(ShippingCalculator::class);
        $this->assertSame('25.00', $calculator->quote($zone->fresh(), '100.00')->fee);

        ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '40.00', 'free_shipping_threshold' => null])->activate();

        $this->assertSame('40.00', $calculator->quote($zone->fresh(), '100.00')->fee);
    }

    #[Test]
    public function activating_a_rate_does_not_touch_another_zones_rates(): void
    {
        $riyadh = ShippingZone::factory()->create(['name' => 'Riyadh']);
        $eastern = ShippingZone::factory()->create(['name' => 'Eastern']);

        $easternRate = ShippingRate::factory()->for($eastern, 'zone')->create();
        $riyadhRate = ShippingRate::factory()->for($riyadh, 'zone')->create();

        $riyadhRate->activate();

        $this->assertTrue($easternRate->refresh()->is_active, 'zones are independent');
    }

    /* ── the guarantee §7.3 states ───────────────────────────── */

    #[Test]
    public function changing_a_rate_never_alters_an_order_already_placed(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '25.00', 'free_shipping_threshold' => null]);

        $calculator = app(ShippingCalculator::class);

        // Placed at the rate of the day, exactly as CheckoutService captures it.
        $quoted = $calculator->quote($zone->fresh(), '200.00');
        $order = Order::factory()->for(Customer::factory())->status(OrderStatus::Confirmed)->create([
            'shipping_zone_id' => $zone->getKey(),
            'subtotal' => '200.00',
            'shipping_fee' => $quoted->fee,
            'grand_total' => '225.00',
        ]);

        $this->assertSame('25.00', $order->shipping_fee);

        // The client puts the fee up a month later.
        ShippingRate::factory()->for($zone, 'zone')->create(['fee' => '60.00', 'free_shipping_threshold' => null])->activate();

        $order->refresh();

        // The order is a record of what was agreed, not a live view.
        $this->assertSame('25.00', $order->shipping_fee, 'a placed order must keep the fee it was charged');
        $this->assertSame('225.00', $order->grand_total);

        // …while new quotes do move.
        $this->assertSame('60.00', $calculator->quote($zone->fresh(), '200.00')->fee);
    }

    /* ── CSV import ──────────────────────────────────────────── */

    private function importer(): CityCsvImporter
    {
        return app(CityCsvImporter::class);
    }

    #[Test]
    public function the_import_creates_cities_and_skips_the_header_row(): void
    {
        $zone = ShippingZone::factory()->create();

        $summary = $this->importer()->import("name_en,name_ar\nRiyadh,الرياض\nJeddah,جدة\n", $zone);

        $this->assertSame(2, $summary->created);
        $this->assertSame(0, $summary->skipped);
        $this->assertFalse($summary->hasErrors());

        // A city called "name_en" would be the tell that the header leaked in.
        $this->assertDatabaseMissing('shipping_cities', ['name_en' => 'name_en']);
        $this->assertDatabaseHas('shipping_cities', ['zone_id' => $zone->getKey(), 'name_en' => 'Riyadh', 'name_ar' => 'الرياض']);
    }

    #[Test]
    public function re_importing_the_same_file_changes_nothing(): void
    {
        $zone = ShippingZone::factory()->create();
        $csv = "name_en,name_ar\nRiyadh,الرياض\nJeddah,جدة\n";

        $this->importer()->import($csv, $zone);
        $second = $this->importer()->import($csv, $zone);

        // Idempotent like ShippingSeeder. Blind inserts would violate
        // unique(zone_id, name_en) and throw on the second run.
        $this->assertSame(0, $second->created);
        $this->assertSame(2, $second->skipped);
        $this->assertSame(2, ShippingCity::query()->where('zone_id', $zone->getKey())->count());
    }

    #[Test]
    public function the_import_fills_a_blank_arabic_name_but_never_overwrites_one(): void
    {
        $zone = ShippingZone::factory()->create();

        $blank = ShippingCity::factory()->for($zone, 'zone')->create(['name_en' => 'Riyadh', 'name_ar' => null]);
        $typed = ShippingCity::factory()->for($zone, 'zone')->create(['name_en' => 'Jeddah', 'name_ar' => 'جدة المحررة']);

        $summary = $this->importer()->import("Riyadh,الرياض\nJeddah,جدة\n", $zone);

        $this->assertSame('الرياض', $blank->refresh()->name_ar, 'a blank Arabic name should be filled');
        $this->assertSame('جدة المحررة', $typed->refresh()->name_ar, 'an import must not overwrite a hand-typed name');
        $this->assertSame(1, $summary->updated);
        $this->assertSame(1, $summary->skipped);
    }

    #[Test]
    public function the_import_reports_rows_it_could_not_use(): void
    {
        $zone = ShippingZone::factory()->create();

        $summary = $this->importer()->import("Riyadh,الرياض\n,لا اسم\nJeddah\n", $zone);

        $this->assertSame(2, $summary->created);
        $this->assertSame(1, $summary->skipped);
        $this->assertTrue($summary->hasErrors());
        $this->assertStringContainsString('no city name', $summary->errors[0]);
    }

    #[Test]
    public function a_file_saved_from_excel_does_not_create_a_city_with_a_bom_in_its_name(): void
    {
        $zone = ShippingZone::factory()->create();

        // Excel writes a UTF-8 BOM; without stripping it the first city is
        // named "\u{FEFF}Riyadh" and silently duplicates on the next import.
        $this->importer()->import("\u{FEFF}name_en,name_ar\nRiyadh,الرياض\n", $zone);

        $this->assertDatabaseHas('shipping_cities', ['name_en' => 'Riyadh']);
        $this->assertSame(1, ShippingCity::query()->where('zone_id', $zone->getKey())->count());
    }

    #[Test]
    public function an_empty_file_is_reported_rather_than_silently_accepted(): void
    {
        $zone = ShippingZone::factory()->create();

        $summary = $this->importer()->import("name_en,name_ar\n\n", $zone);

        $this->assertSame(0, $summary->total());
        $this->assertSame('The file contained no city rows.', $summary->describe());
    }

    /* ── role gating ─────────────────────────────────────────── */

    #[Test]
    public function staff_cannot_reach_shipping_at_all(): void
    {
        $this->actingAsAdmin(AdminRole::Staff);

        $this->assertFalse(ShippingZoneResource::canViewAny());
        $this->assertFalse(ShippingCityResource::canViewAny());
    }
}
