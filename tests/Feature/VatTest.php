<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Vat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The VAT rules: the rate, the conversions, the delivery treatment and the
 * free-shipping basis.
 *
 * A Feature test rather than a Unit one because the rate and both business
 * rules are read through Setting, which is the whole point — a rule that only
 * exists in a config file is not one the client can change.
 */
final class VatTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_rate_comes_from_settings_and_falls_back_to_config(): void
    {
        $this->assertSame('0.150000', Vat::rate(), 'config is the fallback before any row exists');

        Setting::put('vat_rate', 0.05);

        $this->assertSame('0.050000', Vat::rate(), 'the dashboard field governs live behaviour');
    }

    #[Test]
    public function a_nonsense_rate_falls_back_rather_than_breaking_the_storefront(): void
    {
        // A bad settings row must not be able to take the shop down or produce
        // a negative tax.
        Setting::put('vat_rate', 'fifteen percent');
        $this->assertSame('0.150000', Vat::rate());

        Setting::put('vat_rate', -0.2);
        $this->assertSame('0.000000', Vat::rate());
    }

    #[Test]
    public function the_rate_label_trims_to_what_a_person_would_write(): void
    {
        $this->assertSame('15%', Vat::rateLabel());

        Setting::put('vat_rate', 0.155);
        $this->assertSame('15.5%', Vat::rateLabel());

        Setting::put('vat_rate', 0);
        $this->assertSame('0%', Vat::rateLabel());
    }

    #[Test]
    public function conversions_round_trip_on_the_real_brochure_prices(): void
    {
        // Every one of the client's 25 products is exact at 15% in both
        // directions, which is what makes the catalog's two columns agree.
        foreach ([['135.00', '155.25'], ['80.00', '92.00'], ['235.00', '270.25'], ['350.00', '402.50']] as [$excl, $incl]) {
            $this->assertSame($incl, Vat::inclusiveOf($excl));
            $this->assertSame($excl, Vat::exclusiveOf($incl));
        }
    }

    #[Test]
    public function vat_is_extracted_from_an_inclusive_amount_never_added_to_it(): void
    {
        // 155.25 contains 20.25 of VAT. Computing it as 155.25 x 0.15 = 23.29
        // would overstate the tax and misstate what was paid.
        $this->assertSame('20.25', Vat::portionOfInclusive('155.25'));
        $this->assertSame('20.25', Vat::onExclusive('135.00'));
    }

    #[Test]
    public function delivery_vat_follows_the_configured_treatment(): void
    {
        // Inclusive (the default and the project's existing rule): the fee
        // already contains its VAT, so the payable amount does not move.
        Setting::put('shipping_vat_mode', Vat::SHIPPING_INCLUSIVE);
        $this->assertSame('3.26', Vat::onShipping('25.00'));
        $this->assertSame('25.00', Vat::shippingPayable('25.00'));

        // Exempt: no VAT reported, same amount charged.
        Setting::put('shipping_vat_mode', Vat::SHIPPING_EXEMPT);
        $this->assertSame('0.00', Vat::onShipping('25.00'));
        $this->assertSame('25.00', Vat::shippingPayable('25.00'));

        // Exclusive: the fee is net, so its VAT is a genuine addition. The one
        // mode that changes what a customer pays.
        Setting::put('shipping_vat_mode', Vat::SHIPPING_EXCLUSIVE);
        $this->assertSame('3.75', Vat::onShipping('25.00'));
        $this->assertSame('28.75', Vat::shippingPayable('25.00'));
    }

    #[Test]
    public function an_unrecognised_delivery_treatment_falls_back_to_the_existing_rule(): void
    {
        // Never guess on something that changes what people are charged.
        Setting::put('shipping_vat_mode', 'whatever');

        $this->assertSame(Vat::SHIPPING_INCLUSIVE, Vat::shippingMode());
        $this->assertSame('25.00', Vat::shippingPayable('25.00'));
    }

    #[Test]
    public function the_free_shipping_basis_picks_between_the_two_merchandise_totals(): void
    {
        Setting::put('free_shipping_basis', Vat::BASIS_INCLUSIVE);
        $this->assertSame('345.00', Vat::freeShippingAmount('300.00', '345.00'));

        Setting::put('free_shipping_basis', Vat::BASIS_EXCLUSIVE);
        $this->assertSame('300.00', Vat::freeShippingAmount('300.00', '345.00'));

        Setting::put('free_shipping_basis', 'nonsense');
        $this->assertSame('345.00', Vat::freeShippingAmount('300.00', '345.00'), 'falls back to the approved rule');
    }

    #[Test]
    public function a_price_discrepancy_is_measured_and_not_corrected(): void
    {
        // Consistent at 15%.
        $this->assertTrue(Vat::pricesAgree('135.00', '155.25'));
        $this->assertSame('0.00', Vat::discrepancy('135.00', '155.25'));

        // A brochure that rounds to a round number: 1 halala out, inside
        // tolerance, so nobody is bothered about it.
        $this->assertTrue(Vat::pricesAgree('100.01', '115.00'));

        // A real disagreement, reported with its size and its direction.
        $this->assertFalse(Vat::pricesAgree('100.00', '120.00'));
        $this->assertSame('5.00', Vat::discrepancy('100.00', '120.00'));
        $this->assertSame('-15.00', Vat::discrepancy('100.00', '100.00'));
    }

    #[Test]
    public function a_rate_change_makes_previously_agreeing_prices_disagree(): void
    {
        // This is the point of flagging rather than correcting: after a rate
        // change the whole catalog needs a human decision, not a mass rewrite.
        $this->assertTrue(Vat::pricesAgree('135.00', '155.25'));

        Setting::put('vat_rate', 0.20);

        $this->assertFalse(Vat::pricesAgree('135.00', '155.25'));
        $this->assertSame('-6.75', Vat::discrepancy('135.00', '155.25'), '162.00 was expected at 20%');
    }
}
