<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\Money;
use App\Support\Vat;

/**
 * Works out what delivery costs and how long it takes.
 *
 * Deliberately pure: it reads zones and rates and returns a quote. It never
 * writes to an order — the checkout copies the quote onto the order as a
 * snapshot, so a later rate change cannot rewrite a past sale (§7.3).
 */
final class ShippingCalculator
{
    /**
     * Quote delivery for a zone against a merchandise total.
     *
     * A zone with no active rate returns an unavailable quote rather than a
     * free one. Defaulting to zero would silently ship for nothing the first
     * time someone adds a zone and forgets its rate.
     *
     * `$subtotal` is the FREE-SHIPPING BASIS amount, not "the subtotal" —
     * there are now two merchandise totals and they differ by the VAT. Callers
     * should get this from MoneyTotals::freeShippingBasisAmount() rather than
     * choosing one, so a 300.00 threshold cannot mean 300.00 net on one page
     * and 300.00 gross on the next. See App\Support\Vat::freeShippingBasis().
     */
    public function quote(?ShippingZone $zone, string $subtotal): ShippingQuote
    {
        if (! $zone instanceof ShippingZone || ! $zone->is_active) {
            return ShippingQuote::unavailable();
        }

        $rate = $zone->relationLoaded('activeRate')
            ? $zone->activeRate
            : $zone->activeRate()->first();

        if (! $rate instanceof ShippingRate) {
            return ShippingQuote::unavailable();
        }

        return new ShippingQuote(
            available: true,
            fee: $rate->feeFor($subtotal),
            isFree: $rate->qualifiesForFreeShipping($subtotal),
            estimateLabel: $rate->estimateLabel(),
            estimatedDaysMin: $rate->estimated_days_min,
            estimatedDaysMax: $rate->estimated_days_max,
            freeShippingThreshold: $rate->free_shipping_threshold === null
                ? null
                : number_format((float) $rate->free_shipping_threshold, 2, '.', ''),
        );
    }

    /**
     * How much more is needed to reach free shipping, or null when it is not
     * on offer or already earned. Drives the "spend X more" nudge.
     */
    public function amountToFreeShipping(?ShippingZone $zone, string $subtotal): ?string
    {
        $quote = $this->quote($zone, $subtotal);

        if (! $quote->available || $quote->isFree || $quote->freeShippingThreshold === null) {
            return null;
        }

        $remaining = Money::sub($quote->freeShippingThreshold, $subtotal);

        return Money::isPositive($remaining) ? $remaining : null;
    }

    /**
     * The full breakdown for a basket with this quote applied: merchandise net
     * and gross, product VAT, delivery and its VAT, and the payable total.
     *
     * Replaces the old grandTotal(): with two authoritative prices per product
     * and a configurable delivery treatment, a bare `subtotal + fee - discount`
     * no longer says which subtotal it means, and a caller passing the
     * VAT-exclusive one would undercharge the customer by the VAT. Handing back
     * a breakdown makes that mistake unrepresentable.
     */
    public function totals(
        ?ShippingZone $zone,
        string $merchandiseExclVat,
        string $merchandiseInclVat,
        string $discount = '0.00',
    ): MoneyTotals {
        $basis = Vat::freeShippingAmount($merchandiseExclVat, $merchandiseInclVat);

        return MoneyTotals::withShipping(
            merchandiseExclVat: $merchandiseExclVat,
            merchandiseInclVat: $merchandiseInclVat,
            quote: $this->quote($zone, $basis),
            discountTotal: $discount,
        );
    }
}
