<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A placed order.
 *
 * Every figure here is a snapshot taken at placement. Nothing recomputes from
 * the catalog or the shipping rates, because an order is a record of what was
 * agreed, not a live view of today's prices.
 *
 * @property int $id
 * @property string $order_no
 * @property int $customer_id
 * @property OrderStatus $status
 * @property PaymentMethod $payment_method
 * @property PaymentStatus $payment_status
 * @property string $currency
 * @property string $subtotal
 * @property string $shipping_fee
 * @property string $discount_total
 * @property string $vat_amount
 * @property string $grand_total
 * @property int|null $shipping_zone_id
 * @property int|null $shipping_city_id
 * @property string $shipping_name
 * @property string $shipping_phone
 * @property string $shipping_address_line1
 * @property string|null $shipping_address_line2
 * @property string|null $shipping_district
 * @property string $shipping_city_name
 * @property string|null $shipping_postal_code
 * @property string|null $customer_note
 * @property string|null $admin_note
 * @property CarbonInterface|null $placed_at
 * @property CarbonInterface|null $confirmed_at
 * @property CarbonInterface|null $shipped_at
 * @property CarbonInterface|null $delivered_at
 * @property CarbonInterface|null $cancelled_at
 */
final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_no', 'customer_id', 'status', 'payment_method', 'payment_status', 'currency',
        'subtotal', 'shipping_fee', 'discount_total', 'vat_amount', 'grand_total',
        'shipping_zone_id', 'shipping_city_id', 'shipping_name', 'shipping_phone',
        'shipping_address_line1', 'shipping_address_line2', 'shipping_district',
        'shipping_city_name', 'shipping_postal_code',
        'customer_note', 'admin_note',
        'placed_at', 'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Orders are addressed by their human-readable number, never by id — the
     * confirmation URL should not invite anyone to try id+1.
     */
    public function getRouteKeyName(): string
    {
        return 'order_no';
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /** @return BelongsTo<ShippingCity, $this> */
    public function shippingCity(): BelongsTo
    {
        return $this->belongsTo(ShippingCity::class, 'shipping_city_id');
    }

    /** @param Builder<Order> $query */
    public function scopePlaced(Builder $query): void
    {
        $query->whereNotNull('placed_at');
    }

    /** @param Builder<Order> $query */
    public function scopeRevenue(Builder $query): void
    {
        $query->whereIn('status', OrderStatus::revenueStatuses());
    }

    public function canTransitionTo(OrderStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('qty');
    }

    /**
     * The VAT portion OF the total, for display on receipts.
     *
     * Prices are VAT-inclusive, so this is extracted (total × r / (1 + r)) and
     * never added. Computing it the other way would overstate the tax and
     * misstate what the customer actually paid.
     */
    public static function vatPortionOf(string $inclusiveTotal, float $rate): string
    {
        $r = (string) $rate;

        return bcdiv(bcmul($inclusiveTotal, $r, 6), bcadd('1', $r, 6), 2);
    }

    /**
     * Full one-line delivery address, as printed on the packing slip.
     */
    public function formattedAddress(): string
    {
        return implode(', ', array_filter([
            $this->shipping_address_line1,
            $this->shipping_address_line2,
            $this->shipping_district,
            $this->shipping_city_name,
            $this->shipping_postal_code,
        ]));
    }
}
