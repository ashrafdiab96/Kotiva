<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One product line in a cart. Each line holds a live stock reservation for its
 * quantity — see StockService.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property int $qty
 * @property string $unit_price_snapshot
 */
final class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'product_id',
        'qty',
        'unit_price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The reservation movements this line is responsible for. Used to prove a
     * released line leaves no stock held.
     *
     * @return MorphMany<ProductStockMovement, $this>
     */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(ProductStockMovement::class, 'reference');
    }

    /**
     * Priced from the CURRENT product price, never the snapshot.
     */
    public function unitPrice(): string
    {
        return number_format((float) ($this->product->price ?? 0), 2, '.', '');
    }

    public function lineTotal(): string
    {
        return bcmul($this->unitPrice(), (string) $this->qty, 2);
    }

    /**
     * True when the product's price has moved since this line was added. The
     * checkout review step surfaces this rather than silently re-pricing.
     */
    public function priceHasChanged(): bool
    {
        return bccomp($this->unitPrice(), number_format((float) $this->unit_price_snapshot, 2, '.', ''), 2) !== 0;
    }
}
