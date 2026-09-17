<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Cart>
 */
final class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ttl = (int) config('kotiva.cart.ttl_hours');

        return [
            'token' => (string) Str::uuid(),
            'customer_email' => null,
            'last_activity_at' => now(),
            'expires_at' => now()->addHours($ttl),
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'last_activity_at' => now()->subDays(30),
            'expires_at' => now()->subDay(),
        ]);
    }
}
