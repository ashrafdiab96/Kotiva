<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Support\Money;

/**
 * One cart line as checkout sees it: priced from the live product, in both
 * currencies of the dual-price catalog, with whatever the shopper needs to be
 * told before they commit.
 *
 * Both prices travel together because the two sides of checkout need different
 * ones — the breakdown states merchandise excluding VAT, while the line items
 * and the payable total are VAT-inclusive — and computing either from the other
 * at the point of display is how the two stop agreeing.
 *
 * Lives in its own file because PSR-4 maps one class per file — sharing
 * CheckoutReview.php would have left this class unloadable.
 */
final readonly class CheckoutLine
{
    public function __construct(
        public CartItem $item,
        public Product $product,
        /** VAT-inclusive unit price — what this line is charged at. */
        public string $unitPrice,
        /** VAT-exclusive unit price — what the shop displayed. */
        public string $unitPriceExclVat,
        /** VAT-inclusive line total. */
        public string $lineTotal,
        /** VAT-exclusive line total. */
        public string $lineTotalExclVat,
        /** The VAT this line contains: inclusive − exclusive. */
        public string $lineVat,
        public bool $priceChanged,
        /** The VAT-inclusive price when the item was added. */
        public string $previousPrice,
        /** The VAT-exclusive price when the item was added. */
        public string $previousPriceExclVat,
        public bool $unavailable,
    ) {}

    /**
     * Build a line from a cart item, pricing it live.
     *
     * A single constructor for the review step and for anything else that
     * needs the same view, so the two cannot drift.
     */
    public static function fromCartItem(CartItem $item, Product $product): self
    {
        return new self(
            item: $item,
            product: $product,
            unitPrice: $product->priceInclVat(),
            unitPriceExclVat: $product->priceExclVat(),
            lineTotal: Money::multiplyByQty($product->priceInclVat(), $item->qty),
            lineTotalExclVat: Money::multiplyByQty($product->priceExclVat(), $item->qty),
            lineVat: $item->lineVat(),
            priceChanged: $item->priceHasChanged(),
            previousPrice: $item->snapshotInclVat(),
            previousPriceExclVat: $item->snapshotExclVat(),
            unavailable: ! $product->is_active,
        );
    }

    /**
     * Whether the price is higher than what was shown when the item was added.
     * Worth distinguishing from any change: a drop is good news and needs no
     * apology, a rise has to be admitted before the shopper pays.
     *
     * Judged on the VAT-exclusive price, which is the figure the shopper
     * actually saw on the shop and the cart. If only the inclusive price moved,
     * priceChanged is still true and the notice still shows — it simply reports
     * the direction of the price they were quoted.
     */
    public function priceWentUp(): bool
    {
        if (! $this->priceChanged) {
            return false;
        }

        $exclusiveMove = Money::compare($this->unitPriceExclVat, $this->previousPriceExclVat);

        return $exclusiveMove === 0
            ? Money::compare($this->unitPrice, $this->previousPrice) > 0
            : $exclusiveMove > 0;
    }
}
