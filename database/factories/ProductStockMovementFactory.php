<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\ProductStockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStockMovement>
 */
final class ProductStockMovementFactory extends Factory
{
    protected $model = ProductStockMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'delta' => 50,
            'reason' => StockMovementReason::Restock,
            'reference_type' => null,
            'reference_id' => null,
            'note' => null,
            'admin_id' => null,
        ];
    }

    public function reason(StockMovementReason $reason, int $delta): self
    {
        return $this->state(fn (): array => ['reason' => $reason, 'delta' => $delta]);
    }
}
