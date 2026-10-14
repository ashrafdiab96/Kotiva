<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Pages\ImportProducts;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Support\Import\ImportReport;
use App\Support\Import\ProductImporter;
use App\Support\Import\SpreadsheetData;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The product importer while Salla owns price and stock (D-37).
 *
 * What must hold: an old full sheet still parses; its editorial columns
 * update existing products; its price and stock columns are visibly ignored
 * and never written — not to the product, and not to the stock ledger; and
 * it cannot create a product Salla does not know about.
 *
 * The same importer with commerce switched back on is pinned, unchanged, by
 * ProductImportTest and DualPriceImportExportTest.
 */
final class ProductImportSallaModeTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'sku,name,price_excl_vat,price_incl_vat,compare_at_price,stock_qty,low_stock_threshold,category,description';

    protected function setUp(): void
    {
        parent::setUp();

        config(['kotiva.commerce_enabled' => false]);
    }

    private function importer(): ProductImporter
    {
        return app(ProductImporter::class);
    }

    private function report(string $csv): ImportReport
    {
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
        $headers = array_shift($rows);

        $numbered = [];
        foreach ($rows as $i => $row) {
            $numbered[$i + 2] = $row;
        }

        return $this->importer()->validate(new SpreadsheetData($headers, $numbered), $this->importer()->autoMap($headers));
    }

    private function existing(): Product
    {
        $category = Category::factory()->create(['name' => 'Serums', 'slug' => 'serums']);

        return Product::factory()->for($category)->create([
            'sku' => 'KOT-SALLA-1',
            'name' => 'Old Name',
            'description' => 'Old copy.',
            'price_excl_vat' => '100.00',
            'price_incl_vat' => '115.00',
            'compare_at_price' => null,
            'stock_qty' => 9,
            'low_stock_threshold' => 3,
        ]);
    }

    #[Test]
    public function an_existing_product_gets_its_copy_but_keeps_salla_owned_fields(): void
    {
        $product = $this->existing();
        $before = $product->fresh()->only(['price_excl_vat', 'price_incl_vat', 'compare_at_price', 'stock_qty', 'low_stock_threshold']);

        $report = $this->report(self::HEADER."\nKOT-SALLA-1,New Name,200.00,230.00,260.00,40,12,Serums,New copy.\n");

        $this->assertFalse($report->hasErrors(), implode(' ', $report->errorLines()));
        $this->assertSame(5, $report->ignoredCount());

        $result = $this->importer()->import($report);

        $product->refresh();

        $this->assertSame('New Name', $product->name);
        $this->assertSame('New copy.', $product->description);
        $this->assertSame($before, $product->only(array_keys($before)));
        $this->assertSame(0, ProductStockMovement::query()->count(), 'no stock movement may be written while Salla owns stock');

        // Counted as ignored, and the update counted once for the copy — the
        // price and stock are not reported as having been applied.
        $this->assertSame(1, $result->updated);
        $this->assertSame(0, $result->stockAdjusted);
        $this->assertSame(5, $result->ignored);
        $this->assertStringContainsString('5 price/stock values ignored (managed in Salla)', $result->describe());
        $this->assertStringNotContainsString('stock level', $result->describe());
    }

    #[Test]
    public function a_row_that_only_changes_salla_owned_fields_is_unchanged(): void
    {
        $product = $this->existing();

        $result = $this->importer()->import(
            $this->report(self::HEADER."\nKOT-SALLA-1,Old Name,999.00,1148.85,,1,,Serums,Old copy.\n")
        );

        $this->assertSame(0, $result->updated);
        $this->assertSame(1, $result->unchanged);
        $this->assertSame(3, $result->ignored);
        $this->assertSame('100.00', $product->fresh()->price_excl_vat);
        $this->assertSame(9, $product->fresh()->stock_qty);
    }

    #[Test]
    public function an_old_sheet_with_an_unparseable_price_still_updates_the_copy(): void
    {
        // The price is never used, so a bad one must not block the row.
        $product = $this->existing();

        $report = $this->report("sku,name,price,category,description\nKOT-SALLA-1,New Name,call us,Serums,New copy.\n");

        $this->assertFalse($report->hasErrors(), implode(' ', $report->errorLines()));
        $this->importer()->import($report);

        $this->assertSame('New copy.', $product->fresh()->description);
        $this->assertSame('115.00', $product->fresh()->price_incl_vat);
    }

    #[Test]
    public function a_sheet_without_any_price_column_is_complete(): void
    {
        $mapping = $this->importer()->autoMap(['sku', 'name', 'category', 'description']);

        $this->assertSame([], $this->importer()->missingRequired($mapping));
    }

    #[Test]
    public function an_unknown_sku_is_refused_with_the_salla_message_and_creates_nothing(): void
    {
        $this->existing();

        $report = $this->report(self::HEADER."\nKOT-NEW-9,Brand New,150.00,172.50,,10,,Serums,Copy.\n");

        $this->assertTrue($report->hasErrors());
        $message = implode(' ', $report->errorLines());
        $this->assertStringContainsString('KOT-NEW-9 is not in the catalog', $message);
        $this->assertStringContainsString('Create the product in Salla and link it first', $message);
        $this->assertStringContainsString('only updates editorial fields on existing products', $message);

        $this->importer()->import($report);

        $this->assertFalse(Product::withTrashed()->where('sku', 'KOT-NEW-9')->exists());
        $this->assertSame(0, ProductStockMovement::query()->count());
    }

    #[Test]
    public function a_report_assembled_around_validation_still_cannot_create_a_product(): void
    {
        $report = new ImportReport([2 => ['sku' => 'KOT-NEW-8', 'name' => 'Sneaky', 'category' => 'Serums', 'stock_qty' => 5]], []);

        try {
            $this->importer()->import($report);
            $this->fail('import() must refuse to create a product while Salla owns the catalog.');
        } catch (LogicException) {
            // Expected.
        }

        $this->assertFalse(Product::withTrashed()->where('sku', 'KOT-NEW-8')->exists());
        $this->assertSame(0, ProductStockMovement::query()->count());
    }

    #[Test]
    public function the_preview_labels_salla_owned_columns_as_ignored(): void
    {
        Storage::fake('local');
        $product = $this->existing();

        $admin = Admin::create([
            'name' => 'Manager',
            'email' => 'manager@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => AdminRole::Manager,
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $page = Livewire::test(ImportProducts::class)
            ->set('upload', UploadedFile::fake()->createWithContent(
                'products.csv',
                self::HEADER."\nKOT-SALLA-1,New Name,200.00,230.00,260.00,40,12,Serums,New copy.\n"
            ))
            ->assertHasNoErrors()
            ->assertSet('mapping.price_incl_vat', 3)
            ->assertSet('mapping.stock_qty', 5);

        foreach (['Price excl. VAT', 'Price incl. VAT', 'Compare-at price', 'Stock', 'Low-stock threshold'] as $label) {
            $page->assertSee($label.' ('.ProductImporter::IGNORED_LABEL.')');
        }

        $page->assertDontSee('Name ('.ProductImporter::IGNORED_LABEL.')')
            ->assertDontSee('Description ('.ProductImporter::IGNORED_LABEL.')');

        $page->call('check')->assertSet('errorCount', 0)->assertNotified('5 price/stock values will be ignored');

        $page->call('runImport');

        $this->assertSame('New Name', $product->fresh()->name);
        $this->assertSame('115.00', $product->fresh()->price_incl_vat);
        $this->assertSame(9, $product->fresh()->stock_qty);
    }
}
