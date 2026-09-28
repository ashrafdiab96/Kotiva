<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\MoneyTotals;
use App\Support\Money;
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
     * Merchandise excluding VAT — the cart page's headline figure.
     *
     * Computed from the live product price, not the snapshot: the snapshot
     * exists to detect a change, not to price the order.
     */
    public function subtotalExclVat(): string
    {
        return Money::sum($this->items->map(fn (CartItem $item): string => $item->lineTotalExclVat()));
    }

    /**
     * Merchandise including VAT. Not a payable total on the cart page —
     * delivery has not been quoted yet.
     */
    public function subtotalInclVat(): string
    {
        return Money::sum($this->items->map(fn (CartItem $item): string => $item->lineTotalInclVat()));
    }

    /**
     * The cart's breakdown: merchandise net, the VAT it contains, and the
     * VAT-inclusive merchandise total. Delivery is deliberately unresolved —
     * the cart has no address, so it has no fee to state and must not present
     * one.
     *
     * There is no `subtotal()` any more, on purpose. It used to mean the
     * VAT-inclusive total, and a method of that name whose meaning silently
     * flipped to exclusive is the single most likely way this change could
     * undercharge somebody.
     */
    public function totals(): MoneyTotals
    {
        return MoneyTotals::merchandise($this->subtotalExclVat(), $this->subtotalInclVat());
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }
}
