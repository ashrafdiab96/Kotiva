<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Money;

/**
 * The checkout's view of the cart, priced live rather than from the cart's
 * snapshots.
 *
 * Carries a MoneyTotals rather than a single `subtotal` string. With two
 * authoritative prices there is no longer one number that "the subtotal" could
 * unambiguously mean, and every step of checkout needs the same breakdown:
 * merchandise net, the VAT it contains, and the merchandise gross that
 * delivery is added to.
 */
final readonly class CheckoutReview
{
    /**
     * @param  list<CheckoutLine>  $lines
     */
    public function __construct(
        public array $lines,
        public MoneyTotals $totals,
    ) {}

    /**
     * @param  list<CheckoutLine>  $lines
     */
    public static function fromLines(array $lines): self
    {
        return new self(
            $lines,
            MoneyTotals::merchandise(
                Money::sum(array_map(fn (CheckoutLine $l): string => $l->lineTotalExclVat, $lines)),
                Money::sum(array_map(fn (CheckoutLine $l): string => $l->lineTotal, $lines)),
            )
        );
    }

    /**
     * The same lines with a delivery quote applied.
     *
     * Returns a new instance: the review is a snapshot of a moment, and
     * mutating it in place is how the payment step ends up rendering a total
     * the shipping step never computed.
     */
    public function withTotals(MoneyTotals $totals): self
    {
        return new self($this->lines, $totals);
    }

    /** Merchandise excluding VAT. */
    public function subtotalExclVat(): string
    {
        return $this->totals->merchandiseExclVat;
    }

    /** Merchandise including VAT — what delivery is added to. */
    public function subtotalInclVat(): string
    {
        return $this->totals->merchandiseInclVat;
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * @return list<CheckoutLine>
     */
    public function changedLines(): array
    {
        return array_values(array_filter($this->lines, fn (CheckoutLine $l): bool => $l->priceChanged));
    }

    /**
     * @return list<CheckoutLine>
     */
    public function unavailableLines(): array
    {
        return array_values(array_filter($this->lines, fn (CheckoutLine $l): bool => $l->unavailable));
    }

    public function hasWarnings(): bool
    {
        return $this->changedLines() !== [] || $this->unavailableLines() !== [];
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (CheckoutLine $l): int => $l->item->qty, $this->lines));
    }
}
