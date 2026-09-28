<?php

declare(strict_types=1);

namespace App\Services\Payments;

/**
 * What a gateway hands back when payment is initiated.
 *
 * A readonly value object rather than an array so the checkout cannot silently
 * read a key that a future gateway forgets to set.
 */
final readonly class PaymentResult
{
    private function __construct(
        public bool $completed,
        public ?string $redirectUrl = null,
        public ?string $reference = null,
    ) {}

    /**
     * Nothing further is needed — the order is placed.
     */
    public static function completed(?string $reference = null): self
    {
        return new self(completed: true, reference: $reference);
    }

    /**
     * The shopper must be sent to the provider to pay.
     */
    public static function redirect(string $url, ?string $reference = null): self
    {
        return new self(completed: false, redirectUrl: $url, reference: $reference);
    }

    public function requiresRedirect(): bool
    {
        return $this->redirectUrl !== null;
    }
}
