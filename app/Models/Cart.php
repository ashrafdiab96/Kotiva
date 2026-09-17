<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guest shopping cart, identified only by its uuid token.
 *
 * @property int $id
 * @property string $token
 * @property string|null $customer_email
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface|null $expires_at
 */
final class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    protected $fillable = [
        'token',
        'customer_email',
        'last_activity_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @param Builder<Cart> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Totals are computed from the live product price, not the snapshot: the
     * snapshot exists to detect a change, not to price the order.
     */
    public function subtotal(): string
    {
        $total = $this->items->reduce(
            fn (string $carry, CartItem $item): string => bcadd($carry, $item->lineTotal(), 2),
            '0.00'
        );

        return $total;
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }
}
