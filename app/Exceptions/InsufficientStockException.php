<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * A stock request could not be satisfied.
 *
 * Carries the maximum that IS available, because §6.5 requires the response to
 * tell the shopper what they can actually have rather than only that they
 * cannot have what they asked for.
 */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            'Only %d of "%s" available (requested %d).',
            $available,
            $product->name,
            $requested
        ));
    }

    /**
     * The message the storefront shows inline, in the brand's voice.
     */
    public function userMessage(): string
    {
        if ($this->available <= 0) {
            return sprintf('%s is sold out.', $this->product->displayName());
        }

        return sprintf(
            'Only %d of %s %s left.',
            $this->available,
            $this->product->displayName(),
            $this->available === 1 ? 'is' : 'are'
        );
    }
}
