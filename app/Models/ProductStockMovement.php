<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementReason;
use Database\Factories\ProductStockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An append-only ledger of every change to products.stock_qty.
 *
 * Nothing updates or deletes these rows: a mistake is corrected by writing a
 * compensating movement, so the sum of deltas per product always reconciles
 * against the cached stock_qty.
 */
final class ProductStockMovement extends Model
{
    /** @use HasFactory<ProductStockMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'delta',
        'reason',
        'reference_type',
        'reference_id',
        'note',
        'admin_id',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'reason' => StockMovementReason::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return MorphTo<Model, $this> */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
