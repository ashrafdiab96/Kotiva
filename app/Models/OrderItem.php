<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an order, carrying its own copy of what was bought.
 *
 * The snapshots are the point: a product can be renamed, repriced, have its
 * image replaced or be deleted entirely, and this line must still read the way
 * it did on the day. product_id is only a convenience link back to the
 * catalog, and it is allowed to become null.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $sku_snapshot
 * @property string $name_snapshot
 * @property string|null $image_snapshot
 * @property string $unit_price
 * @property int $qty
 * @property string $line_total
 */
final class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'sku_snapshot',
        'name_snapshot',
        'image_snapshot',
        'unit_price',
        'qty',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * May be null once the product has been removed from the catalog.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The snapshotted image, resolved for rendering.
     *
     * Goes through the same resolver as a live product so an order placed
     * before the dashboard existed and one placed after it render identically
     * — the snapshot stores whatever path was current, not a URL.
     */
    public function imageUrl(): ?string
    {
        $path = Product::resolveImagePath($this->image_snapshot);

        return $path === null ? null : asset($path);
    }

    /**
     * True when the product still exists and can be linked to.
     */
    public function isLinkable(): bool
    {
        return $this->product instanceof Product && $this->product->is_active;
    }
}
