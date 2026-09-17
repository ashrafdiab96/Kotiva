<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShippingCityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A deliverable city, belonging to exactly one zone.
 *
 * @property int $id
 * @property int $zone_id
 * @property string $name_en
 * @property string|null $name_ar
 * @property bool $is_active
 */
final class ShippingCity extends Model
{
    /** @use HasFactory<ShippingCityFactory> */
    use HasFactory;

    protected $fillable = ['zone_id', 'name_en', 'name_ar', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    /** @param Builder<ShippingCity> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The storefront is English today. This is the single place that decides
     * which name is shown, so adding an Arabic storefront later is one change.
     */
    public function displayName(): string
    {
        return $this->name_en;
    }
}
