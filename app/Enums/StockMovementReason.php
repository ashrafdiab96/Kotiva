<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why a product's stock changed.
 *
 * Every row in product_stock_movements carries one of these. The pairing that
 * matters for correctness: `order_reserved` is what a cart holds, and it must
 * be answered by exactly one `order_released` (cart emptied, item removed or
 * cart expired) or one `order_fulfilled` (the order was placed).
 */
enum StockMovementReason: string
{
    case Manual = 'manual';
    case Import = 'import';
    case OrderReserved = 'order_reserved';
    case OrderReleased = 'order_released';
    case OrderFulfilled = 'order_fulfilled';
    case Restock = 'restock';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual adjustment',
            self::Import => 'Import',
            self::OrderReserved => 'Reserved for cart',
            self::OrderReleased => 'Released back',
            self::OrderFulfilled => 'Fulfilled by order',
            self::Restock => 'Restock',
        };
    }

    /**
     * Filament badge colour.
     */
    public function color(): string
    {
        return match ($this) {
            self::Manual => 'gray',
            self::Import => 'info',
            self::OrderReserved => 'warning',
            self::OrderReleased => 'success',
            self::OrderFulfilled => 'primary',
            self::Restock => 'success',
        };
    }

    /**
     * True when this reason may only ever decrease stock.
     */
    public function isDecrement(): bool
    {
        return in_array($this, [self::OrderReserved, self::OrderFulfilled], true);
    }

    /**
     * The reasons an admin may choose when adjusting stock by hand.
     *
     * Deliberately not every case. `import` is written by the importer, and the
     * three `order_*` reasons are written by the cart and checkout flows — an
     * adjustment offering those would let a hand-typed correction masquerade in
     * the ledger as something an order did, which is precisely the attribution
     * the ledger exists to preserve.
     *
     * @return list<self>
     */
    public static function adminSelectable(): array
    {
        return [self::Restock, self::Manual];
    }
}
