<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * products.salla_url → products.salla_path keeps every existing link, as a
 * path, and drops (rather than re-homes) a link on another origin.
 */
final class SallaPathMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_14_000001_rename_salla_url_to_salla_path_on_products_table.php';

    #[Test]
    public function existing_full_urls_become_paths_and_foreign_origins_are_cleared(): void
    {
        $store = (string) config('kotiva.salla.store_url');

        $onStore = Product::factory()->create(['sku' => 'KOT001']);
        $foreign = Product::factory()->create(['sku' => 'KOT002']);
        $unlinked = Product::factory()->create(['sku' => 'KOT003']);

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('products', 'salla_url'));

        DB::table('products')->where('id', $onStore->id)->update(['salla_url' => $store.'/micellar-water/p1713901761']);
        DB::table('products')->where('id', $foreign->id)->update(['salla_url' => 'https://another-store.example.test/x/p2']);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertFalse(Schema::hasColumn('products', 'salla_url'));
        $this->assertSame('/micellar-water/p1713901761', DB::table('products')->where('id', $onStore->id)->value('salla_path'));
        $this->assertNull(DB::table('products')->where('id', $foreign->id)->value('salla_path'));
        $this->assertNull(DB::table('products')->where('id', $unlinked->id)->value('salla_path'));
    }

    #[Test]
    public function rolling_back_restores_full_urls(): void
    {
        $store = (string) config('kotiva.salla.store_url');
        $product = Product::factory()->create(['sku' => 'KOT001', 'salla_path' => '/micellar-water/p1713901761']);

        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();

        $this->assertSame($store.'/micellar-water/p1713901761', DB::table('products')->where('id', $product->id)->value('salla_url'));

        // Leave the schema as the rest of the suite expects it.
        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();
    }
}
