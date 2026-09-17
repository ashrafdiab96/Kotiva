<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The review step's view of the cart, priced live rather than from the cart's
 * snapshots.
 */
final readonly class CheckoutReview
{
    /**
     * @param  list<CheckoutLine>  $lines
     */
    public function __construct(
        public array $lines,
        public string $subtotal,
    ) {}

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
