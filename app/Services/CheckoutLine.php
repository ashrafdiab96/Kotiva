<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;

/**
 * One cart line as the review step sees it: priced from the live product, with
 * whatever the shopper needs to be told before they commit.
 *
 * Lives in its own file because PSR-4 maps one class per file — sharing
 * CheckoutReview.php would have left this class unloadable.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public CartItem $item,
        public Product $product,
        public string $unitPrice,
        public string $lineTotal,
        public bool $priceChanged,
        public string $previousPrice,
        public bool $unavailable,
    ) {}

    /**
     * Whether the current price is higher than what was shown when the item was
     * added. Worth distinguishing from any change: a drop is good news and
     * needs no apology, a rise has to be admitted before the shopper pays.
     */
    public function priceWentUp(): bool
    {
        return $this->priceChanged && bccomp($this->unitPrice, $this->previousPrice, 2) > 0;
    }
}
