<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ReferralPartner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralPartner>
 */
final class ReferralPartnerFactory extends Factory
{
    protected $model = ReferralPartner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'dr-'.$this->faker->unique()->numberBetween(100, 999999);

        return [
            'name' => 'Dr '.$this->faker->lastName(),
            'code' => $code,
            // Built from config so no store host is written here either.
            'salla_affiliate_url' => config('kotiva.salla.store_url').'/?ref='.strtoupper(str_replace('-', '', $code)),
            'landing_path' => '/',
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
