<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            // Already normalised, as the checkout stores it.
            'phone' => '+9665'.$this->faker->numerify('########'),
            'marketing_opt_in' => false,
        ];
    }

    public function optedIn(): self
    {
        return $this->state(fn (): array => ['marketing_opt_in' => true]);
    }
}
