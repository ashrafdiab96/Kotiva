<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Kotiva '.$this->faker->unique()->words(2, true);

        return [
            'sku' => 'KOT'.$this->faker->unique()->numberBetween(100, 99999),
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'name' => $name,
            'category_id' => Category::factory(),
            'skin_type' => 'All Skin Types',
            'concern' => 'Hydration',
            'action' => 'Hydration',
            'volume' => '200ml',
            'price' => $this->faker->randomFloat(2, 80, 400),
            'compare_at_price' => null,
            'description' => $this->faker->paragraph(),
            'benefits' => [$this->faker->sentence(), $this->faker->sentence()],
            'how_to_use' => $this->faker->sentence(),
            'science' => $this->faker->paragraph(),
            'ingredients' => ['Hyaluronic Acid', 'Niacinamide'],
            'free_from' => [],
            'filter_tags' => ['face'],
            'image' => 'assets/products/placeholder.webp',
            'gallery' => [],
            'is_featured' => false,
            'is_best_seller' => false,
            'is_active' => true,
            'stock_qty' => 50,
            'low_stock_threshold' => 5,
            'meta_title' => $name.' — kotiva™',
            'meta_description' => $this->faker->sentence(),
            'weight_grams' => null,
        ];
    }

    public function soldOut(): self
    {
        return $this->state(fn (): array => ['stock_qty' => 0]);
    }

    public function lowStock(int $qty = 2): self
    {
        return $this->state(fn (): array => ['stock_qty' => $qty, 'low_stock_threshold' => 5]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function bestSeller(): self
    {
        return $this->state(fn (): array => ['is_best_seller' => true]);
    }

    public function withStock(int $qty): self
    {
        return $this->state(fn (): array => ['stock_qty' => $qty]);
    }
}
