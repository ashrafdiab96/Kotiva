<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;

/**
 * The one place that knows what VAT is and how it applies.
 *
 * Products now carry BOTH prices from the brochure — `price_excl_vat` and
 * `price_incl_vat` — and both are authoritative. Nothing here derives a price
 * that a brochure already states; the conversions exist for the figures a
 * brochure does not state: the VAT portion of a line, the VAT on a delivery
 * fee, and the expected counterpart used to FLAG a disagreement for review
 * rather than to overwrite one.
 *
 * The shipping treatment and the free-shipping basis are settings rather than
 * constants because they are business rules the client owns. Their defaults
 * reproduce the behaviour this project shipped with, so nothing moves until
 * somebody deliberately changes it.
 */
final class Vat
{
    /**
     * The delivery fee already contains VAT — the project's existing rule, and
     * the default. The VAT portion is extracted from the fee for the
     * breakdown; the payable total is unchanged.
     */
    public const SHIPPING_INCLUSIVE = 'inclusive';

    /** Delivery carries no VAT at all. Only product VAT is reported. */
    public const SHIPPING_EXEMPT = 'exempt';

    /**
     * The stored fee is net and VAT is added on top of it.
     *
     * This is the one mode that changes the payable total, so it is never the
     * default and switching to it is a pricing decision, not a display one.
     */
    public const SHIPPING_EXCLUSIVE = 'exclusive';

    /** Free-shipping thresholds compare against the VAT-inclusive merchandise total (the existing rule). */
    public const BASIS_INCLUSIVE = 'incl';

    /** Free-shipping thresholds compare against the VAT-exclusive merchandise total. */
    public const BASIS_EXCLUSIVE = 'excl';

    /**
     * The configured rate as a bcmath-safe string, e.g. "0.150000".
     *
     * Read through Setting so the dashboard's VAT field governs live
     * behaviour, with config as the fallback before any row exists — the same
     * precedence the rest of the application uses.
     */
    public static function rate(): string
    {
        $configured = Setting::get('vat_rate', config('kotiva.vat_rate'));

        $rate = is_numeric($configured) ? (float) $configured : (float) config('kotiva.vat_rate');

        // A negative rate is not a tax, and nothing downstream could report it
        // sensibly. Clamped rather than thrown: a bad settings row must not
        // take the storefront down.
        return number_format(max(0.0, $rate), 6, '.', '');
    }

    /**
     * "15" — the rate as a percentage, trailing zeros trimmed, for labels.
     */
    public static function ratePercent(?string $rate = null): string
    {
        $percent = bcmul($rate ?? self::rate(), '100', 4);
        $trimmed = rtrim(rtrim($percent, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    /**
     * "15%" — what the storefront prints next to a VAT line.
     */
    public static function rateLabel(?string $rate = null): string
    {
        return self::ratePercent($rate).'%';
    }

    /* ── conversions ─────────────────────────────────────────── */

    /**
     * excl × (1 + r). Used to state the counterpart of a supplied price, never
     * to replace a supplied one.
     */
    public static function inclusiveOf(string $exclusive, ?string $rate = null): string
    {
        $r = $rate ?? self::rate();

        return Money::mul($exclusive, bcadd('1', $r, 8));
    }

    /**
     * incl ÷ (1 + r).
     */
    public static function exclusiveOf(string $inclusive, ?string $rate = null): string
    {
        $r = $rate ?? self::rate();

        return Money::div($inclusive, bcadd('1', $r, 8));
    }

    /**
     * The VAT contained in a VAT-inclusive amount: total × r / (1 + r).
     *
     * Extracted, never added. Computing it as total × r would overstate the tax
     * and misstate what the customer actually paid.
     */
    public static function portionOfInclusive(string $inclusive, ?string $rate = null): string
    {
        $r = $rate ?? self::rate();

        return Money::sub($inclusive, self::exclusiveOf($inclusive, $r));
    }

    /**
     * The VAT charged on top of a VAT-exclusive amount: net × r.
     */
    public static function onExclusive(string $exclusive, ?string $rate = null): string
    {
        return Money::mul($exclusive, $rate ?? self::rate());
    }

    /* ── shipping ────────────────────────────────────────────── */

    public static function shippingMode(): string
    {
        $mode = Setting::get('shipping_vat_mode', config('kotiva.vat.shipping_mode'));

        return in_array($mode, self::shippingModes(), true)
            ? (string) $mode
            : self::SHIPPING_INCLUSIVE;
    }

    /**
     * @return list<string>
     */
    public static function shippingModes(): array
    {
        return [self::SHIPPING_INCLUSIVE, self::SHIPPING_EXEMPT, self::SHIPPING_EXCLUSIVE];
    }

    /**
     * @return array<string, string> mode => admin-facing label
     */
    public static function shippingModeOptions(): array
    {
        return [
            self::SHIPPING_INCLUSIVE => 'Delivery fee already includes VAT',
            self::SHIPPING_EXEMPT => 'Delivery is not subject to VAT',
            self::SHIPPING_EXCLUSIVE => 'Add VAT on top of the delivery fee',
        ];
    }

    /**
     * The VAT attributable to a delivery fee under the configured treatment.
     */
    public static function onShipping(string $fee, ?string $rate = null): string
    {
        return match (self::shippingMode()) {
            self::SHIPPING_EXEMPT => Money::zero(),
            self::SHIPPING_EXCLUSIVE => self::onExclusive($fee, $rate),
            default => self::portionOfInclusive($fee, $rate),
        };
    }

    /**
     * What the delivery fee actually adds to the payable total.
     *
     * Equal to the fee itself in every mode but `exclusive`, where the stored
     * fee is net and its VAT is a genuine addition.
     */
    public static function shippingPayable(string $fee, ?string $rate = null): string
    {
        return self::shippingMode() === self::SHIPPING_EXCLUSIVE
            ? Money::add($fee, self::onExclusive($fee, $rate))
            : Money::round($fee);
    }

    /* ── free shipping ───────────────────────────────────────── */

    public static function freeShippingBasis(): string
    {
        $basis = Setting::get('free_shipping_basis', config('kotiva.vat.free_shipping_basis'));

        return $basis === self::BASIS_EXCLUSIVE ? self::BASIS_EXCLUSIVE : self::BASIS_INCLUSIVE;
    }

    /**
     * @return array<string, string> basis => admin-facing label
     */
    public static function freeShippingBasisOptions(): array
    {
        return [
            self::BASIS_INCLUSIVE => 'Merchandise total including VAT',
            self::BASIS_EXCLUSIVE => 'Merchandise total excluding VAT',
        ];
    }

    /**
     * Which merchandise total a free-shipping threshold is measured against.
     *
     * The cart shows VAT-exclusive figures and checkout shows VAT-inclusive
     * ones, so "the subtotal" is no longer a single number. Leaving the choice
     * implicit is how a 300.00 threshold quietly becomes a 345.00 one.
     */
    public static function freeShippingAmount(string $merchandiseExclVat, string $merchandiseInclVat): string
    {
        return self::freeShippingBasis() === self::BASIS_EXCLUSIVE
            ? Money::round($merchandiseExclVat)
            : Money::round($merchandiseInclVat);
    }

    /* ── validation ──────────────────────────────────────────── */

    /**
     * How far the two brochure prices may drift from each other before it is
     * worth telling somebody. Rounding on a single unit can only ever be one
     * halala, so the default catches a genuine mistake without crying wolf
     * over the last decimal.
     */
    public static function tolerance(): string
    {
        return Money::of(config('kotiva.vat.price_tolerance', '0.01'));
    }

    /**
     * By how much a supplied inclusive price differs from what the exclusive
     * price implies at the configured rate. Zero when they agree.
     *
     * Signed: positive means the inclusive price is higher than the rate
     * predicts. The caller decides whether to surface it — nothing in this
     * application rewrites either figure on the strength of it.
     */
    public static function discrepancy(string $exclusive, string $inclusive, ?string $rate = null): string
    {
        return Money::sub($inclusive, self::inclusiveOf($exclusive, $rate));
    }

    /**
     * Whether the two prices are consistent at the configured rate, within
     * tolerance.
     */
    public static function pricesAgree(string $exclusive, string $inclusive, ?string $rate = null): bool
    {
        $delta = self::discrepancy($exclusive, $inclusive, $rate);

        return Money::compare(ltrim($delta, '-'), self::tolerance()) <= 0;
    }
}
