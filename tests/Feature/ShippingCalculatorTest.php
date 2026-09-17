<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Services\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Shipping is money, so the boundaries matter more than the happy path: an
 * order landing exactly on the free-shipping threshold must ship free, and a
 * zone with no rate must refuse to quote rather than quote zero.
 */
final class ShippingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ShippingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(ShippingCalculator::class);
    }

    private function zoneWithRate(string $fee = '25.00', ?string $threshold = '300.00'): ShippingZone
    {
        $zone = ShippingZone::factory()->create();

        ShippingRate::factory()->for($zone, 'zone')->create([
            'fee' => $fee,
            'free_shipping_threshold' => $threshold,
            'estimated_days_min' => 2,
            'estimated_days_max' => 4,
        ]);

        return $zone->fresh();
    }

    #[Test]
    public function no_zone_means_no_quote(): void
    {
        $quote = $this->calculator->quote(null, '100.00');

        $this->assertFalse($quote->available);
        $this->assertSame('Select a delivery area', $quote->feeLabel('SAR'));
    }

    #[Test]
    public function an_inactive_zone_cannot_be_quoted(): void
    {
        $zone = $this->zoneWithRate();
        $zone->update(['is_active' => false]);

        $this->assertFalse($this->calculator->quote($zone->fresh(), '100.00')->available);
    }

    #[Test]
    public function a_zone_with_no_active_rate_is_unavailable_rather_than_free(): void
    {
        // The dangerous default: quoting 0.00 here would ship for nothing the
        // first time someone adds a zone and forgets its rate.
        $zone = ShippingZone::factory()->create();

        $quote = $this->calculator->quote($zone, '100.00');

        $this->assertFalse($quote->available);
        $this->assertNotSame('Free', $quote->feeLabel('SAR'));
    }

    #[Test]
    public function an_inactive_rate_does_not_count(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create(['is_active' => false]);

        $this->assertFalse($this->calculator->quote($zone->fresh(), '100.00')->available);
    }

    #[Test]
    public function the_fee_applies_below_the_threshold(): void
    {
        $quote = $this->calculator->quote($this->zoneWithRate('25.00', '300.00'), '299.99');

        $this->assertTrue($quote->available);
        $this->assertFalse($quote->isFree);
        $this->assertSame('25.00', $quote->fee);
        $this->assertSame('SAR 25.00', $quote->feeLabel('SAR'));
    }

    #[Test]
    public function an_order_exactly_on_the_threshold_ships_free(): void
    {
        // The boundary bccomp exists for: float comparison is precisely where
        // "300.00 >= 300.00" stops being reliable.
        $quote = $this->calculator->quote($this->zoneWithRate('25.00', '300.00'), '300.00');

        $this->assertTrue($quote->isFree);
        $this->assertSame('0.00', $quote->fee);
        $this->assertSame('Free', $quote->feeLabel('SAR'));
    }

    #[Test]
    public function an_order_above_the_threshold_ships_free(): void
    {
        $quote = $this->calculator->quote($this->zoneWithRate('40.00', '300.00'), '512.75');

        $this->assertTrue($quote->isFree);
        $this->assertSame('0.00', $quote->fee);
    }

    #[Test]
    public function a_zone_with_no_threshold_never_ships_free(): void
    {
        $quote = $this->calculator->quote($this->zoneWithRate('40.00', null), '10000.00');

        $this->assertFalse($quote->isFree);
        $this->assertSame('40.00', $quote->fee);
        $this->assertNull($quote->freeShippingThreshold);
    }

    #[Test]
    public function it_reports_how_much_more_earns_free_shipping(): void
    {
        $zone = $this->zoneWithRate('25.00', '300.00');

        $this->assertSame('50.25', $this->calculator->amountToFreeShipping($zone, '249.75'));

        // Already free, or not on offer at all.
        $this->assertNull($this->calculator->amountToFreeShipping($zone, '300.00'));
        $this->assertNull($this->calculator->amountToFreeShipping($this->zoneWithRate('25.00', null), '10.00'));
        $this->assertNull($this->calculator->amountToFreeShipping(null, '10.00'));
    }

    #[Test]
    public function the_grand_total_adds_shipping_without_adding_vat(): void
    {
        // Prices are VAT-inclusive, so nothing is added for tax.
        $this->assertSame('325.00', $this->calculator->grandTotal('300.00', '25.00'));
        $this->assertSame('300.00', $this->calculator->grandTotal('300.00', '0.00'));
        $this->assertSame('315.00', $this->calculator->grandTotal('300.00', '25.00', '10.00'));
    }

    #[Test]
    public function the_delivery_estimate_reads_naturally(): void
    {
        $zone = ShippingZone::factory()->create();
        ShippingRate::factory()->for($zone, 'zone')->create([
            'estimated_days_min' => 2,
            'estimated_days_max' => 4,
        ]);

        $this->assertSame('2-4 working days', $this->calculator->quote($zone->fresh(), '10.00')->estimateLabel);

        $single = ShippingZone::factory()->create();
        ShippingRate::factory()->for($single, 'zone')->create([
            'estimated_days_min' => 1,
            'estimated_days_max' => 1,
        ]);

        $this->assertSame('1 working day', $this->calculator->quote($single->fresh(), '10.00')->estimateLabel);
    }
}
