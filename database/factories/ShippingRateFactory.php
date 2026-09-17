<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingRate>
 */
final class ShippingRateFactory extends Factory
{
    protected $model = ShippingRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => ShippingZone::factory(),
            'fee' => '25.00',
            'free_shipping_threshold' => '300.00',
            'estimated_days_min' => 2,
            'estimated_days_max' => 4,
            'is_active' => true,
        ];
    }

    public function fee(string $fee): self
    {
        return $this->state(fn (): array => ['fee' => $fee]);
    }

    public function neverFree(): self
    {
        return $this->state(fn (): array => ['free_shipping_threshold' => null]);
    }

    public function freeOver(string $threshold): self
    {
        return $this->state(fn (): array => ['free_shipping_threshold' => $threshold]);
    }
}
