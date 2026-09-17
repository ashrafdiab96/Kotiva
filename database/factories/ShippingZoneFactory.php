<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
final class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zone '.$this->faker->unique()->numberBetween(1, 99999),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
