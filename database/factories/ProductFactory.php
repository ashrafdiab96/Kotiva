<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use App\Support\Vat;
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
            // Generated as a whole-riyal net price so the inclusive figure is
            // exact at 2dp, the way the real brochure prices are. A random
            // fractional net price would put half the factory's products into
            // the "prices disagree" state and make every unrelated test look
            // like a VAT bug.
            'price_excl_vat' => $netPrice = (string) $this->faker->numberBetween(80, 400).'.00',
            'price_incl_vat' => Vat::inclusiveOf($netPrice),
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

    /**
     * A product priced from an explicit VAT-exclusive figure, with the
     * inclusive price derived at the configured rate.
     */
    public function pricedAt(string $exclVat): self
    {
        return $this->state(fn (): array => [
            'price_excl_vat' => Money::of($exclVat),
            'price_incl_vat' => Vat::inclusiveOf(Money::of($exclVat)),
        ]);
    }

    /**
     * Both prices set independently — the case where a brochure states a
     * figure the VAT rate does not predict.
     */
    public function pricedWith(string $exclVat, string $inclVat): self
    {
        return $this->state(fn (): array => [
            'price_excl_vat' => Money::of($exclVat),
            'price_incl_vat' => Money::of($inclVat),
        ]);
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
