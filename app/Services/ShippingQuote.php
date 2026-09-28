<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The result of a shipping calculation.
 *
 * A readonly object rather than an array, so a caller cannot quietly read a
 * key that does not exist — and so "unavailable" is an explicit state rather
 * than a fee of zero, which is the same value as genuinely free delivery.
 */
final readonly class ShippingQuote
{
    public function __construct(
        public bool $available,
        public string $fee = '0.00',
        public bool $isFree = false,
        public ?string $estimateLabel = null,
        public ?int $estimatedDaysMin = null,
        public ?int $estimatedDaysMax = null,
        public ?string $freeShippingThreshold = null,
    ) {}

    /**
     * No zone chosen yet, zone inactive, or the zone has no active rate.
     */
    public static function unavailable(): self
    {
        return new self(available: false);
    }

    /**
     * What the shopper is shown next to the fee.
     */
    public function feeLabel(string $currency): string
    {
        if (! $this->available) {
            return 'Select a delivery area';
        }

        return $this->isFree ? 'Free' : $currency.' '.$this->fee;
    }
}
