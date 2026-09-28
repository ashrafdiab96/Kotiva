<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
final class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory(),
            'qty' => 1,
            // Both snapshots, consistent at 15%: checkout compares each against
            // the live product, so a fixture that sets only one would report a
            // price change on every line.
            'unit_price_snapshot' => '100.00',
            'unit_price_excl_vat_snapshot' => '86.96',
        ];
    }

    /**
     * Snapshots taken from a product as it stands now — the normal state of a
     * cart line, and the one where no price-change notice should fire.
     */
    public function snapshotOf(Product $product): self
    {
        return $this->state(fn (): array => [
            'product_id' => $product->getKey(),
            'unit_price_snapshot' => $product->priceInclVat(),
            'unit_price_excl_vat_snapshot' => $product->priceExclVat(),
        ]);
    }
}
