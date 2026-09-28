<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A shipping region — "Riyadh", "Eastern Province", "Rest of KSA".
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 */
final class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ShippingCity, $this> */
    public function cities(): HasMany
    {
        return $this->hasMany(ShippingCity::class, 'zone_id');
    }

    /** @return HasMany<ShippingRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'zone_id');
    }

    /**
     * The rate a quote should use. A zone is expected to have exactly one
     * active rate; if history has left more than one, the newest wins so a
     * correction takes effect rather than being shadowed by an old row.
     *
     * @return HasOne<ShippingRate, $this>
     */
    public function activeRate(): HasOne
    {
        return $this->hasOne(ShippingRate::class, 'zone_id')
            ->where('is_active', true)
            ->latestOfMany();
    }

    /** @param Builder<ShippingZone> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
