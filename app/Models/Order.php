<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Money;
use App\Support\Vat;
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
 * @property string $subtotal Merchandise, VAT included.
 * @property string $subtotal_excl_vat Merchandise, VAT excluded.
 * @property string $shipping_fee
 * @property string $discount_total
 * @property string $vat_amount All VAT contained in this order.
 * @property string $product_vat_amount The VAT inside the merchandise.
 * @property string $shipping_vat_amount The VAT attributable to delivery.
 * @property string $vat_rate The rate this order was computed at.
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
        'subtotal', 'subtotal_excl_vat', 'shipping_fee', 'discount_total',
        'vat_amount', 'product_vat_amount', 'shipping_vat_amount', 'vat_rate', 'grand_total',
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
            'subtotal_excl_vat' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'product_vat_amount' => 'decimal:2',
            'shipping_vat_amount' => 'decimal:2',
            'vat_rate' => 'decimal:6',
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
     * The VAT portion OF an inclusive total.
     *
     * Extracted (total × r / (1 + r)), never added. Computing it the other way
     * would overstate the tax and misstate what the customer actually paid.
     *
     * Retained as the arithmetic's public name; the implementation now defers
     * to App\Support\Vat so that every VAT extraction in the application —
     * cart, checkout, order, admin — rounds identically.
     */
    public static function vatPortionOf(string $inclusiveTotal, float $rate): string
    {
        return Vat::portionOfInclusive($inclusiveTotal, number_format($rate, 6, '.', ''));
    }

    /**
     * The rate this order was placed at, as a bcmath-safe string.
     *
     * The order's own snapshot, never today's setting: a receipt reprinted
     * after a rate change must still describe the transaction that happened.
     */
    public function vatRate(): string
    {
        return number_format((float) $this->vat_rate, 6, '.', '');
    }

    /**
     * "15% VAT" — the rate as it was applied to THIS order.
     */
    public function vatRateLabel(): string
    {
        return Vat::rateLabel($this->vatRate());
    }

    /**
     * The merchandise VAT-exclusive total plus delivery net of its own VAT —
     * the "goods and delivery, before tax" figure an invoice shows.
     *
     * Delivery's VAT treatment is configurable, so the net delivery figure is
     * the stored payable fee minus whatever VAT was attributed to it. In the
     * project's default treatment the fee is VAT-inclusive, so this is
     * genuinely less than shipping_fee; under an exempt treatment the two are
     * equal, which is correct.
     */
    public function shippingExclVat(): string
    {
        return Money::sub((string) $this->shipping_fee, (string) $this->shipping_vat_amount);
    }

    /**
     * Whether the stored breakdown still reconciles, on all three invariants:
     *
     *   1. merchandise(excl) + product VAT  = merchandise(incl)
     *   2. product VAT + delivery VAT       = total VAT
     *   3. merchandise(incl) + delivery − discount = grand total
     *
     * Checked as three statements rather than one rearranged sum because that
     * is how they can each be wrong. Note that (3) is the project's original
     * invariant, unchanged — the dual-price work adds figures to the order, it
     * does not move the amount charged.
     */
    public function totalsReconcile(): bool
    {
        $merchandise = Money::add((string) $this->subtotal_excl_vat, (string) $this->product_vat_amount);

        if (! Money::equals($merchandise, (string) $this->subtotal)) {
            return false;
        }

        $vat = Money::add((string) $this->product_vat_amount, (string) $this->shipping_vat_amount);

        if (! Money::equals($vat, (string) $this->vat_amount)) {
            return false;
        }

        $payable = Money::sub(
            Money::add((string) $this->subtotal, (string) $this->shipping_fee),
            (string) $this->discount_total
        );

        return Money::equals($payable, (string) $this->grand_total);
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
