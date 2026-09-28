<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShippingCity;
use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingCity>
 */
final class ShippingCityFactory extends Factory
{
    protected $model = ShippingCity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => ShippingZone::factory(),
            'name_en' => 'City '.$this->faker->unique()->numberBetween(1, 99999),
            'name_ar' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
