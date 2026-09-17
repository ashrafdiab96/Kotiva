<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
final class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'sku_snapshot' => 'KOT'.$this->faker->unique()->numerify('###'),
            'name_snapshot' => 'Kotiva '.$this->faker->words(2, true),
            'image_snapshot' => 'assets/products/placeholder.webp',
            'unit_price' => '150.00',
            'qty' => 1,
            'line_total' => '150.00',
        ];
    }

    /**
     * A line whose product has since been removed from the catalog.
     */
    public function orphaned(): self
    {
        return $this->state(fn (): array => ['product_id' => null]);
    }
}
