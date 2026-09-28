<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use App\Support\Vat;
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
            // 150.00 incl = 130.43 net + 19.57 VAT at 15%.
            'unit_price' => '150.00',
            'unit_price_excl_vat' => '130.43',
            'qty' => 1,
            'line_total' => '150.00',
            'line_total_excl_vat' => '130.43',
            'vat_amount' => '19.57',
            'vat_rate' => '0.150000',
        ];
    }

    /**
     * A line priced from a VAT-exclusive figure, with everything else derived
     * from it consistently. Saves each test restating five money columns to
     * change one number.
     */
    public function pricedAt(string $exclVat, int $qty = 1): self
    {
        return $this->state(function () use ($exclVat, $qty): array {
            $unitExcl = Money::of($exclVat);
            $unitIncl = Vat::inclusiveOf($unitExcl);

            $lineExcl = Money::multiplyByQty($unitExcl, $qty);
            $lineIncl = Money::multiplyByQty($unitIncl, $qty);

            return [
                'unit_price' => $unitIncl,
                'unit_price_excl_vat' => $unitExcl,
                'qty' => $qty,
                'line_total' => $lineIncl,
                'line_total_excl_vat' => $lineExcl,
                'vat_amount' => Money::sub($lineIncl, $lineExcl),
                'vat_rate' => Vat::rate(),
            ];
        });
    }

    /**
     * A line whose product has since been removed from the catalog.
     */
    public function orphaned(): self
    {
        return $this->state(fn (): array => ['product_id' => null]);
    }
}
