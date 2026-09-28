<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
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
 * @property string $unit_price_snapshot VAT-inclusive price when the line was added.
 * @property string $unit_price_excl_vat_snapshot VAT-exclusive price when the line was added.
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
        'unit_price_excl_vat_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price_snapshot' => 'decimal:2',
            'unit_price_excl_vat_snapshot' => 'decimal:2',
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
     * The VAT-exclusive unit price, from the CURRENT product, never the
     * snapshot. This is what the cart displays.
     */
    public function unitPriceExclVat(): string
    {
        return $this->product instanceof Product ? $this->product->priceExclVat() : Money::zero();
    }

    /**
     * The VAT-inclusive unit price, from the CURRENT product. This is what
     * checkout displays and what the customer is charged.
     */
    public function unitPriceInclVat(): string
    {
        return $this->product instanceof Product ? $this->product->priceInclVat() : Money::zero();
    }

    public function lineTotalExclVat(): string
    {
        return Money::multiplyByQty($this->unitPriceExclVat(), $this->qty);
    }

    public function lineTotalInclVat(): string
    {
        return Money::multiplyByQty($this->unitPriceInclVat(), $this->qty);
    }

    /**
     * The VAT this line carries: the two line totals' difference.
     *
     * Not unit VAT × quantity. Both are correct while the brochure prices are
     * exact at the rate, but the subtraction is the one that still reconciles
     * the displayed breakdown when they are not.
     */
    public function lineVat(): string
    {
        return Money::sub($this->lineTotalInclVat(), $this->lineTotalExclVat());
    }

    /**
     * True when EITHER stored price has moved since this line was added.
     *
     * Either alone is enough. An admin who corrects only the exclusive price
     * changes what the cart page shows while the inclusive snapshot still
     * matches — and a shopper watching their subtotal move with no explanation
     * is exactly what the change notice exists to prevent. The checkout review
     * step surfaces this rather than silently re-pricing.
     */
    public function priceHasChanged(): bool
    {
        return $this->inclusivePriceHasChanged() || $this->exclusivePriceHasChanged();
    }

    public function inclusivePriceHasChanged(): bool
    {
        return ! Money::equals($this->unitPriceInclVat(), $this->snapshotInclVat());
    }

    public function exclusivePriceHasChanged(): bool
    {
        return ! Money::equals($this->unitPriceExclVat(), $this->snapshotExclVat());
    }

    /**
     * The VAT-inclusive price when the line was added. Evidence of a change,
     * never the amount charged.
     */
    public function snapshotInclVat(): string
    {
        return Money::of($this->unit_price_snapshot);
    }

    public function snapshotExclVat(): string
    {
        return Money::of($this->unit_price_excl_vat_snapshot);
    }
}
