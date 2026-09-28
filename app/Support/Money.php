<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Decimal-safe money arithmetic.
 *
 * Every figure in this application is a 2-decimal string, never a float, and
 * every operation on one goes through here. Two reasons this is a class rather
 * than bare bcmath calls at each site:
 *
 * 1. bcmath TRUNCATES, it does not round. `bcmul('135.00', '1.15', 2)` happens
 *    to be exact, but `bcmul('0.15', '155.25', 2)` truncates 23.2875 to 23.28
 *    when the correct VAT figure is 23.29. Extracting VAT, applying a rate and
 *    splitting a total all land on a third decimal, so rounding has to be
 *    explicit and it has to be the same rounding everywhere — otherwise a
 *    breakdown adds up on one page and is a halala out on the next.
 *
 * 2. A float that reaches money maths is a bug that only shows up on one
 *    product at one quantity. Taking and returning strings makes the boundary
 *    visible: normalise() is the single place a float is allowed to become an
 *    amount.
 */
final class Money
{
    /** Halalas. Every stored and displayed amount is at this scale. */
    public const SCALE = 2;

    /**
     * Working scale for intermediate results.
     *
     * A VAT rate is configurable to 6 decimals, so an intermediate product has
     * to keep more digits than the answer or the rate's own precision is lost
     * before rounding ever happens.
     */
    private const GUARD_SCALE = 8;

    public static function zero(): string
    {
        return self::format('0');
    }

    /**
     * Anything numeric, as a rounded amount string.
     *
     * The one sanctioned entry point for a float, an int, a decimal cast or a
     * model attribute. Non-numeric input becomes zero rather than throwing:
     * these values come from views and cast attributes, where a fatal error on
     * a null price would take down a page over a missing figure.
     */
    public static function of(mixed $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return self::round($value);
        }

        if (is_int($value) || is_float($value)) {
            return self::round(number_format((float) $value, self::GUARD_SCALE, '.', ''));
        }

        if ($value === null || $value === '') {
            return self::zero();
        }

        $string = is_scalar($value) ? (string) $value : '';

        return is_numeric($string) ? self::round($string) : self::zero();
    }

    public static function add(string $a, string $b): string
    {
        return self::round(bcadd(self::normalise($a), self::normalise($b), self::GUARD_SCALE));
    }

    public static function sub(string $a, string $b): string
    {
        return self::round(bcsub(self::normalise($a), self::normalise($b), self::GUARD_SCALE));
    }

    public static function mul(string $a, string $b): string
    {
        return self::round(bcmul(self::normalise($a), self::normalise($b), self::GUARD_SCALE));
    }

    public static function div(string $a, string $b): string
    {
        $divisor = self::normalise($b);

        if (bccomp($divisor, '0', self::GUARD_SCALE) === 0) {
            return self::zero();
        }

        return self::round(bcdiv(self::normalise($a), $divisor, self::GUARD_SCALE));
    }

    /**
     * An amount multiplied by a whole quantity.
     *
     * Separate from mul() so a line total reads as what it is, and so the
     * quantity cannot accidentally arrive as a decimal.
     */
    public static function multiplyByQty(string $amount, int $qty): string
    {
        return self::mul($amount, (string) $qty);
    }

    /**
     * Sum of a list of amounts, left to right.
     *
     * @param  iterable<string>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = self::zero();

        foreach ($amounts as $amount) {
            $total = self::add($total, $amount);
        }

        return $total;
    }

    /**
     * -1, 0 or 1, comparing at money scale.
     */
    public static function compare(string $a, string $b): int
    {
        return bccomp(self::normalise($a), self::normalise($b), self::SCALE);
    }

    public static function equals(string $a, string $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    public static function isZero(string $amount): bool
    {
        return self::compare($amount, '0') === 0;
    }

    public static function isPositive(string $amount): bool
    {
        return self::compare($amount, '0') > 0;
    }

    /**
     * Never below zero. Used where a subtraction is only meaningful while it
     * stays positive — "spend X more for free delivery", for instance.
     */
    public static function atLeastZero(string $amount): string
    {
        return self::isPositive($amount) ? self::round($amount) : self::zero();
    }

    /**
     * Half-up rounding at money scale, explicitly.
     *
     * bcmath has no round(), so this adds half of the last kept place and then
     * lets bcadd truncate — the standard trick, with the sign handled
     * separately because truncation on a negative would round the wrong way.
     * Half-up (not half-even) is what an invoice reader expects and what the
     * client's own brochure arithmetic uses.
     */
    public static function round(string $value, int $scale = self::SCALE): string
    {
        $value = self::normalise($value, self::GUARD_SCALE);

        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-+');

        $half = $scale === 0 ? '0.5' : '0.'.str_repeat('0', $scale).'5';

        // bcadd truncates to the requested scale, so this lands on half-up.
        $bumped = bcadd($absolute, $half, $scale + 1);
        $rounded = bcadd($bumped, '0', $scale);

        // "-0.00" is not a number anybody wants to read on a receipt.
        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-'.$rounded;
        }

        return $rounded;
    }

    /**
     * An amount as it is printed: "SAR 155.25".
     */
    public static function format(string $amount, ?string $currency = null): string
    {
        $formatted = self::round($amount);

        return $currency === null ? $formatted : $currency.' '.$formatted;
    }

    /**
     * A numeric string bcmath will accept, at the given scale.
     *
     * Rate strings and decimal casts both arrive here, so this tolerates a
     * leading "+", a bare ".5" and an empty string rather than letting any of
     * them reach bcmath as a warning.
     */
    private static function normalise(string $value, int $scale = self::GUARD_SCALE): string
    {
        $trimmed = trim($value);

        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return bcadd('0', '0', $scale);
        }

        return bcadd($trimmed, '0', $scale);
    }
}
