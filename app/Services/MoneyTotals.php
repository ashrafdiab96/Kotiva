<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;
use App\Support\Vat;

/**
 * One breakdown of what a basket costs, used by the cart, all four checkout
 * steps, order placement and the admin views.
 *
 * There is a single class for all of them on purpose. The cart shows
 * VAT-exclusive figures and checkout shows VAT-inclusive ones, so the two
 * pages legitimately lead with different numbers — but they must be the same
 * numbers underneath, or the shopper watches the total change between clicking
 * "Checkout" and reading the summary. Every figure below is derived here, once,
 * server-side.
 *
 * Product VAT is deliberately `inclusive − exclusive` rather than
 * `exclusive × rate`. Both brochure prices are authoritative, and their
 * difference IS the VAT the customer is charged. Recomputing it from the rate
 * would reintroduce the rounding the brochure already settled, and would drift
 * the moment a product's two prices disagree with the configured rate — which
 * is exactly the case the admin is warned about rather than corrected on.
 */
final readonly class MoneyTotals
{
    private function __construct(
        /** The rate these figures were computed at, for the snapshot. */
        public string $vatRate,
        /** Merchandise only, VAT excluded. The cart's headline figure. */
        public string $merchandiseExclVat,
        /** Merchandise only, VAT included. Checkout's headline figure. */
        public string $merchandiseInclVat,
        /** VAT contained in the merchandise: inclusive − exclusive. */
        public string $productVat,
        /** What delivery adds to the payable total, after the VAT treatment. */
        public string $shippingFee,
        /** VAT attributable to delivery under the configured treatment. */
        public string $shippingVat,
        public string $discountTotal,
        /** All VAT in this basket: product + shipping. */
        public string $vatTotal,
        /** The single payable figure. Nothing else may be presented as one. */
        public string $grandTotal,
        /**
         * Whether delivery has been determined yet.
         *
         * False on the cart and before an area is chosen. Distinct from a fee
         * of 0.00, which is what genuinely free delivery looks like.
         */
        public bool $shippingResolved,
        public bool $shippingIsFree,
    ) {}

    /**
     * Merchandise with no delivery determined — the cart, and the review and
     * shipping steps before an area is chosen.
     */
    public static function merchandise(string $merchandiseExclVat, string $merchandiseInclVat, ?string $rate = null): self
    {
        $rate ??= Vat::rate();

        $excl = Money::round($merchandiseExclVat);
        $incl = Money::round($merchandiseInclVat);
        $productVat = Money::sub($incl, $excl);

        return new self(
            vatRate: $rate,
            merchandiseExclVat: $excl,
            merchandiseInclVat: $incl,
            productVat: $productVat,
            shippingFee: Money::zero(),
            shippingVat: Money::zero(),
            discountTotal: Money::zero(),
            vatTotal: $productVat,
            grandTotal: $incl,
            shippingResolved: false,
            shippingIsFree: false,
        );
    }

    /**
     * The same merchandise with a delivery quote applied.
     *
     * An unavailable quote returns the merchandise-only breakdown rather than
     * a free one: quoting nothing and quoting zero are different answers, and
     * only one of them should ever be shown as a total.
     */
    public static function withShipping(
        string $merchandiseExclVat,
        string $merchandiseInclVat,
        ShippingQuote $quote,
        string $discountTotal = '0.00',
        ?string $rate = null,
    ): self {
        $rate ??= Vat::rate();

        if (! $quote->available) {
            return self::merchandise($merchandiseExclVat, $merchandiseInclVat, $rate);
        }

        $excl = Money::round($merchandiseExclVat);
        $incl = Money::round($merchandiseInclVat);
        $productVat = Money::sub($incl, $excl);

        $shippingPayable = Vat::shippingPayable($quote->fee, $rate);
        $shippingVat = Vat::onShipping($quote->fee, $rate);
        $discount = Money::round($discountTotal);

        return new self(
            vatRate: $rate,
            merchandiseExclVat: $excl,
            merchandiseInclVat: $incl,
            productVat: $productVat,
            shippingFee: $shippingPayable,
            shippingVat: $shippingVat,
            discountTotal: $discount,
            vatTotal: Money::add($productVat, $shippingVat),
            grandTotal: Money::sub(Money::add($incl, $shippingPayable), $discount),
            shippingResolved: true,
            shippingIsFree: $quote->isFree,
        );
    }

    /**
     * The amount a free-shipping threshold is measured against, per the
     * configured business rule.
     */
    public function freeShippingBasisAmount(): string
    {
        return Vat::freeShippingAmount($this->merchandiseExclVat, $this->merchandiseInclVat);
    }

    public function vatRateLabel(): string
    {
        return Vat::rateLabel($this->vatRate);
    }

    /**
     * What the shopper is shown on the delivery row.
     */
    public function shippingLabel(string $currency, string $undetermined = 'Calculated at checkout'): string
    {
        if (! $this->shippingResolved) {
            return $undetermined;
        }

        return Money::isZero($this->shippingFee) ? 'Free' : Money::format($this->shippingFee, $currency);
    }

    public function hasDiscount(): bool
    {
        return ! Money::isZero($this->discountTotal);
    }
}
