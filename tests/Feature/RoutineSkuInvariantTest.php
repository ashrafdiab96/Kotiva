<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Routine Finder derives each SKU from the routine-model id
 * (id N ⇔ KOT{N:03}, D-39) instead of looking the database id up in
 * /api/products. These tests pin the two halves of that contract against the
 * real seeded catalog.
 */
final class RoutineSkuInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
    }

    #[Test]
    public function every_products_api_row_has_a_kot_sku(): void
    {
        $rows = $this->getJson('/api/products')->assertOk()->json('data');

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertIsString($row['sku'], "product {$row['id']} has no SKU");
            $this->assertMatchesRegularExpression('/^KOT\d{3}$/', $row['sku']);
        }
    }

    #[Test]
    public function every_routine_model_id_names_the_seeded_product_with_that_sku(): void
    {
        /** @var array{products: array<string, array{name: string}>} $model */
        $model = json_decode((string) file_get_contents(base_path('resources/routine/routine-model.json')), true);

        foreach ($model['products'] as $id => $entry) {
            $sku = 'KOT'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
            $product = Product::query()->where('sku', $sku)->first();

            $this->assertNotNull($product, "routine-model id {$id} has no product {$sku}");
            $this->assertSame($entry['name'], $product->name, "routine-model id {$id} is not {$sku}");
        }
    }
}
