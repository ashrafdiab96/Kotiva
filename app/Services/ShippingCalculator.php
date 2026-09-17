<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ShippingRate;
use App\Models\ShippingZone;

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
     * Quote delivery for a zone at a given subtotal.
     *
     * A zone with no active rate returns an unavailable quote rather than a
     * free one. Defaulting to zero would silently ship for nothing the first
     * time someone adds a zone and forgets its rate.
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

        $remaining = bcsub($quote->freeShippingThreshold, $subtotal, 2);

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : null;
    }

    /**
     * Grand total = subtotal + shipping - discount. Prices already include
     * VAT, so nothing is added for tax here.
     */
    public function grandTotal(string $subtotal, string $shippingFee, string $discount = '0.00'): string
    {
        return bcsub(bcadd($subtotal, $shippingFee, 2), $discount, 2);
    }
}
