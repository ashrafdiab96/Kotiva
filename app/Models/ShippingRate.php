<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * What a zone charges, and how long it takes.
 *
 * Rates are never read back onto an existing order: orders snapshot their
 * shipping_fee at placement (§7.3), so editing a rate changes future quotes
 * only.
 *
 * @property int $id
 * @property int $zone_id
 * @property string $fee
 * @property string|null $free_shipping_threshold
 * @property int $estimated_days_min
 * @property int $estimated_days_max
 * @property bool $is_active
 */
final class ShippingRate extends Model
{
    /** @use HasFactory<ShippingRateFactory> */
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'fee',
        'free_shipping_threshold',
        'estimated_days_min',
        'estimated_days_max',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'free_shipping_threshold' => 'decimal:2',
            'estimated_days_min' => 'integer',
            'estimated_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    /** @param Builder<ShippingRate> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Make this the zone's one active rate, retiring any others.
     *
     * The table deliberately has no unique constraint on zone_id, so a zone
     * keeps its rate history — but a quote reads activeRate(), which is
     * latestOfMany() over the active rows. Two active rates would therefore
     * leave an older one silently shadowed rather than visibly wrong, which is
     * the kind of pricing bug nobody notices until a customer is charged the
     * wrong fee. Retiring the others makes "active" mean exactly one row.
     */
    public function activate(): void
    {
        DB::transaction(function (): void {
            self::query()
                ->where('zone_id', $this->zone_id)
                ->whereKeyNot($this->getKey())
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if (! $this->is_active) {
                $this->forceFill(['is_active' => true])->save();
            }
        });
    }

    /**
     * Whether an order of this subtotal ships free.
     *
     * Compared with bccomp rather than float maths: a threshold of exactly
     * 300.00 must qualify a 300.00 order, and float comparison is precisely
     * where that goes wrong.
     */
    public function qualifiesForFreeShipping(string $subtotal): bool
    {
        if ($this->free_shipping_threshold === null) {
            return false;
        }

        return bccomp($subtotal, (string) $this->free_shipping_threshold, 2) >= 0;
    }

    /**
     * The fee actually charged for a given subtotal.
     */
    public function feeFor(string $subtotal): string
    {
        return $this->qualifiesForFreeShipping($subtotal)
            ? '0.00'
            : number_format((float) $this->fee, 2, '.', '');
    }

    /**
     * "2-4 working days", or "3 working days" when the range is a single day.
     */
    public function estimateLabel(): string
    {
        if ($this->estimated_days_min === $this->estimated_days_max) {
            return $this->estimated_days_min.' working '.($this->estimated_days_min === 1 ? 'day' : 'days');
        }

        return $this->estimated_days_min.'-'.$this->estimated_days_max.' working days';
    }
}
