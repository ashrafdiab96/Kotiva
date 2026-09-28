<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\StockMovementReason;
use App\Filament\Pages\ImportProducts;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\ImportProducts as ImportProductsJob;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Services\StockService;
use App\Support\Export\CsvExports;
use App\Support\Import\ImportReport;
use App\Support\Import\ImportResult;
use App\Support\Import\ProductImporter;
use App\Support\Import\SpreadsheetReader;
use Database\Seeders\ProductSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Product import and export (§7.2).
 *
 * The rules worth pinning are the ones a careless import would break quietly:
 * stock must still go through the ledger, an existing product's URL must not
 * move, a partly-filled sheet must not wipe fields it does not mention, and an
 * exported catalog must import straight back without changing anything —
 * least of all the science text, whose rendering is checked byte for byte.
 */
final class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'sku,name,price,category,stock_qty,benefits,filter_tags,description';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function actingAsAdmin(AdminRole $role = AdminRole::SuperAdmin): Admin
    {
        $admin = Admin::create([
            'name' => $role->label(),
            'email' => str_replace('_', '-', $role->value).'@kotiva.test',
            'password' => 'secret-for-tests',
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function file(string $contents, string $extension = 'csv'): string
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'kotiva-import-'.uniqid('', true).'.'.$extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function importer(): ProductImporter
    {
        return app(ProductImporter::class);
    }

    private function report(string $csv): ImportReport
    {
        $data = app(SpreadsheetReader::class)->read($this->file($csv), 'csv');

        return $this->importer()->validate($data, $this->importer()->autoMap($data->headers));
    }

    private function importCsv(string $csv): ImportResult
    {
        return $this->importer()->import($this->report($csv));
    }

    private function body(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }

    /* ── creating ────────────────────────────────────────────── */

    #[Test]
    public function a_valid_file_creates_products_with_lists_split_on_the_bar(): void
    {
        $result = $this->importCsv(self::HEADER."\nKOT900,Kotiva Night Serum,149.50,Serums,12,Hydrates|Plumps| Smooths ,face|serums,A serum.\n");

        $this->assertSame(1, $result->created);

        $product = Product::query()->where('sku', 'KOT900')->firstOrFail();

        $this->assertSame('149.50', $product->price);
        $this->assertSame(['Hydrates', 'Plumps', 'Smooths'], $product->benefits);
        $this->assertSame(['face', 'serums'], $product->filter_tags);
        $this->assertSame('kotiva-night-serum', $product->slug);
        $this->assertTrue($product->is_active);
    }

    #[Test]
    public function a_category_is_matched_by_slug_not_duplicated(): void
    {
        // Seeded categories are keyed on Str::slug(name); an import must find
        // them rather than clone them with a second id.
        $existing = Category::factory()->create(['name' => 'Face Care', 'slug' => 'face-care']);

        $this->importCsv(self::HEADER."\nKOT901,Kotiva Wash,80,Face Care,,,,\nKOT902,Kotiva Tonic,90,face care,,,,\n");

        $this->assertSame(1, Category::query()->where('slug', 'face-care')->count());
        $this->assertSame(
            [$existing->id, $existing->id],
            Product::query()->whereIn('sku', ['KOT901', 'KOT902'])->pluck('category_id')->all()
        );
    }

    #[Test]
    public function two_products_with_the_same_name_get_distinct_slugs(): void
    {
        $this->importCsv(self::HEADER."\nKOT903,Kotiva Serum,100,Serums,,,,\nKOT904,Kotiva Serum,110,Serums,,,,\n");

        $this->assertSame(
            ['kotiva-serum', 'kotiva-serum-2'],
            Product::query()->whereIn('sku', ['KOT903', 'KOT904'])->orderBy('sku')->pluck('slug')->all()
        );
    }

    /* ── stock goes through the ledger ───────────────────────── */

    #[Test]
    public function imported_stock_is_written_as_a_ledger_movement(): void
    {
        $this->importCsv(self::HEADER."\nKOT905,Kotiva Balm,60,Body,12,,,\n");

        $product = Product::query()->where('sku', 'KOT905')->firstOrFail();

        $this->assertSame(12, $product->stock_qty);

        $movement = $product->stockMovements()->sole();
        $this->assertSame(12, $movement->delta);
        $this->assertSame(StockMovementReason::Import, $movement->reason);
    }

    #[Test]
    public function stock_is_a_target_level_applied_as_the_difference(): void
    {
        $product = Product::factory()->withStock(0)->create(['sku' => 'KOT906']);
        app(StockService::class)->adjust($product, 20, StockMovementReason::Restock, 'Opening stock');

        $this->importCsv(self::HEADER."\nKOT906,{$product->name},{$product->price},{$product->category->name},5,,,\n");

        $product->refresh();
        $this->assertSame(5, $product->stock_qty);
        $this->assertSame(-15, $product->stockMovements()->latest('id')->firstOrFail()->delta);

        // The ledger still explains every unit.
        $this->assertSame($product->stock_qty, (int) $product->stockMovements()->sum('delta'));
    }

    #[Test]
    public function an_unchanged_stock_level_writes_no_movement(): void
    {
        $product = Product::factory()->withStock(0)->create(['sku' => 'KOT907']);
        app(StockService::class)->adjust($product, 8, StockMovementReason::Restock, 'Opening stock');

        $result = $this->importCsv(self::HEADER."\nKOT907,{$product->name},{$product->price},{$product->category->name},8,,,\n");

        $this->assertSame(0, $result->stockAdjusted);
        $this->assertSame(1, $product->stockMovements()->count());
    }

    /* ── updating ────────────────────────────────────────────── */

    #[Test]
    public function an_update_leaves_unmentioned_fields_and_the_url_alone(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KOT908', 'slug' => 'indexed-url', 'price' => '100.00', 'description' => 'Keep me.',
        ]);

        // A repricing sheet: only sku, name, price, category.
        $result = $this->importCsv("sku,name,price,category\nKOT908,Renamed,125.00,{$product->category->name}\n");

        $product->refresh();

        $this->assertSame(1, $result->updated);
        $this->assertSame('125.00', $product->price);
        $this->assertSame('Renamed', $product->name);
        $this->assertSame('Keep me.', $product->description, 'an unmapped column must not wipe the field');
        $this->assertSame('indexed-url', $product->slug, 'a live URL must not move because the name changed');
    }

    #[Test]
    public function an_empty_cell_on_update_keeps_the_existing_value(): void
    {
        $product = Product::factory()->create(['sku' => 'KOT909', 'description' => 'Keep me.']);

        $this->importCsv(self::HEADER."\nKOT909,{$product->name},{$product->price},{$product->category->name},,,,\n");

        $this->assertSame('Keep me.', $product->refresh()->description);
    }

    #[Test]
    public function a_soft_deleted_sku_is_restored_rather_than_colliding(): void
    {
        $product = Product::factory()->create(['sku' => 'KOT910']);
        $product->delete();

        $result = $this->importCsv(self::HEADER."\nKOT910,Back Again,99,{$product->category->name},,,,\n");

        $this->assertSame(1, $result->updated);
        $this->assertFalse($product->refresh()->trashed());
        $this->assertSame('Back Again', $product->name);
    }

    /* ── validation ──────────────────────────────────────────── */

    #[Test]
    public function every_row_is_validated_and_errors_name_the_line(): void
    {
        $report = $this->report(self::HEADER
            ."\nKOT911,Good,50,Serums,,,,"
            ."\nKOT912,,abc,Serums,-3,,,"
            ."\nKOT911,Duplicate,50,Serums,,,,\n");

        $this->assertCount(1, $report->valid);
        $this->assertSame([3, 4], array_keys($report->errors));

        $line3 = implode(' ', $report->errors[3]);
        $this->assertStringContainsString('Name is required', $line3);
        $this->assertStringContainsString('"abc" is not a price', $line3);
        $this->assertStringContainsString('whole number', $line3);

        $this->assertStringContainsString('also appears on line 2', implode(' ', $report->errors[4]));
    }

    #[Test]
    public function the_sunscreen_alias_becomes_spf_and_an_unpilled_tag_is_kept(): void
    {
        $this->importCsv(self::HEADER."\nKOT913,Kotiva Shield,70,Sun,,,sunscreen|cleanser,\n");

        // "cleanser" has no pill but seven launch products carry it; the pills
        // are config, so an extra tag cannot invent one.
        $this->assertSame(['spf', 'cleanser'], Product::query()->where('sku', 'KOT913')->firstOrFail()->filter_tags);
    }

    #[Test]
    public function an_image_url_is_refused_because_it_would_render_broken(): void
    {
        $report = $this->report("sku,name,price,category,image\nKOT914,Kotiva X,10,Serums,https://cdn.example.com/x.webp\n");

        $this->assertStringContainsString('not a web address', implode(' ', $report->errors[2] ?? []));
    }

    #[Test]
    public function a_header_with_an_excel_bom_still_maps(): void
    {
        $report = $this->report("\u{FEFF}sku,name,price,category\nKOT915,Kotiva Y,10,Serums\n");

        $this->assertFalse($report->hasErrors());
        $this->assertCount(1, $report->valid);
    }

    #[Test]
    public function an_xlsx_file_is_read_as_well_as_csv(): void
    {
        $path = $this->file('', 'xlsx');
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['sku', 'name', 'price', 'category', 'stock_qty']));
        // Excel stores numbers as floats: 50 arrives as 50.0.
        $writer->addRow(Row::fromValues(['KOT916', 'Kotiva Xlsx', 155.25, 'Serums', 50.0]));
        $writer->close();

        $data = app(SpreadsheetReader::class)->read($path, 'xlsx');
        $report = $this->importer()->validate($data, $this->importer()->autoMap($data->headers));

        $this->assertFalse($report->hasErrors(), implode(' ', $report->errorLines()));
        $this->importer()->import($report);

        $product = Product::query()->where('sku', 'KOT916')->firstOrFail();
        $this->assertSame('155.25', $product->price);
        $this->assertSame(50, $product->stock_qty);
    }

    /* ── the round trip ──────────────────────────────────────── */

    #[Test]
    public function an_exported_catalog_imports_back_without_changing_anything(): void
    {
        $this->seed(ProductSeeder::class);

        $before = Product::query()->orderBy('sku')->get()->map->only([
            'sku', 'slug', 'name', 'price', 'category_id', 'description', 'science',
            'benefits', 'ingredients', 'free_from', 'filter_tags', 'image', 'stock_qty',
        ])->all();
        $movements = ProductStockMovement::query()->count();

        $csv = $this->body(CsvExports::products());
        $result = $this->importCsv($csv);

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->updated, 'an unedited export must not "update" anything');
        $this->assertSame(25, $result->unchanged);
        $this->assertSame(0, $result->stockAdjusted);
        $this->assertSame($movements, ProductStockMovement::query()->count());

        // Science is rendered by ScienceRenderer, whose output is checked
        // byte for byte — the round trip must not touch a single character.
        $after = Product::query()->orderBy('sku')->get()->map->only(array_keys($before[0]))->all();
        $this->assertSame($before, $after);
    }

    #[Test]
    public function exported_cells_cannot_run_as_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", CsvExports::safe('=HYPERLINK("x")'));
        $this->assertSame("'+966500000000", CsvExports::safe('+966500000000'));
        $this->assertSame('Kotiva Serum', CsvExports::safe('Kotiva Serum'));

        // …and the importer reverses it, so the guard survives a round trip.
        $this->assertSame('=HYPERLINK("x")', ProductImporter::unguard("'=HYPERLINK(\"x\")"));
        $this->assertSame("'quoted", ProductImporter::unguard("'quoted"));
    }

    #[Test]
    public function the_template_imports_cleanly(): void
    {
        $report = $this->report($this->body(CsvExports::template()));

        $this->assertFalse($report->hasErrors(), implode(' ', $report->errorLines()));
        $this->assertCount(1, $report->valid);
    }

    /* ── the page ────────────────────────────────────────────── */

    #[Test]
    public function staff_cannot_reach_the_import_page(): void
    {
        $this->actingAsAdmin(AdminRole::Staff);

        $this->get(ImportProducts::getUrl())->assertForbidden();
    }

    #[Test]
    public function uploading_shows_a_mapped_preview_and_importing_writes_the_rows(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin(AdminRole::Manager);

        $page = Livewire::test(ImportProducts::class)
            ->set('upload', UploadedFile::fake()->createWithContent(
                'products.csv',
                self::HEADER."\nKOT920,Kotiva Page,99,Serums,4,,face,\n"
            ))
            ->assertHasNoErrors()
            ->assertSet('totalRows', 1)
            ->assertSet('mapping.sku', 0)
            ->assertSee('Kotiva Page');

        $page->call('runImport');

        $this->assertSame(4, Product::query()->where('sku', 'KOT920')->firstOrFail()->stock_qty);
        // The uploaded copy is not left lying around.
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
    }

    #[Test]
    public function one_bad_row_stops_the_whole_file_unless_skipping_is_ticked(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        $csv = self::HEADER."\nKOT921,Good One,99,Serums,,,,\nKOT922,Bad One,not-a-price,Serums,,,,\n";

        $page = Livewire::test(ImportProducts::class)
            ->set('upload', UploadedFile::fake()->createWithContent('products.csv', $csv))
            ->call('runImport')
            ->assertSet('errorCount', 1);

        $this->assertSame(0, Product::query()->whereIn('sku', ['KOT921', 'KOT922'])->count(), 'all or nothing');

        $page->set('skipInvalid', true)->call('runImport');

        $this->assertTrue(Product::query()->where('sku', 'KOT921')->exists());
        $this->assertFalse(Product::query()->where('sku', 'KOT922')->exists());
    }

    #[Test]
    public function a_file_over_a_hundred_rows_is_queued(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAsAdmin();

        $rows = [self::HEADER];
        for ($i = 1; $i <= 101; $i++) {
            $rows[] = "KOTQ{$i},Queued {$i},10,Serums,,,,";
        }

        Livewire::test(ImportProducts::class)
            ->set('upload', UploadedFile::fake()->createWithContent('big.csv', implode("\n", $rows)))
            ->call('runImport');

        Queue::assertPushed(ImportProductsJob::class, fn (ImportProductsJob $job): bool => $job->originalName === 'big.csv');
        $this->assertSame(0, Product::query()->where('sku', 'like', 'KOTQ%')->count(), 'nothing written inline');
    }

    #[Test]
    public function the_queued_job_imports_and_tells_the_admin_through_the_bell(): void
    {
        Storage::fake('local');
        $admin = $this->actingAsAdmin();

        Storage::disk('local')->put('imports/job.csv', self::HEADER."\nKOT930,Kotiva Job,10,Serums,3,,,\n");

        $data = app(SpreadsheetReader::class)->read(Storage::disk('local')->path('imports/job.csv'), 'csv');
        (new ImportProductsJob('imports/job.csv', 'csv', 'job.csv', $this->importer()->autoMap($data->headers), false, $admin->id))
            ->handle(app(SpreadsheetReader::class), $this->importer());

        $this->assertSame(3, Product::query()->where('sku', 'KOT930')->firstOrFail()->stock_qty);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('finished', (string) json_encode($admin->notifications()->first()?->data));
        $this->assertFalse(Storage::disk('local')->exists('imports/job.csv'), 'the job cleans up its file');
    }

    /* ── the export buttons ──────────────────────────────────── */

    #[Test]
    public function the_products_list_offers_template_and_export_downloads(): void
    {
        $this->actingAsAdmin(AdminRole::Manager);
        Product::factory()->create(['sku' => 'KOT940']);

        Livewire::test(ListProducts::class)
            ->callAction('template')
            ->assertFileDownloaded('kotiva-products-template.csv');

        Livewire::test(ListProducts::class)
            ->callAction('export')
            ->assertFileDownloaded('kotiva-products-'.now()->format('Y-m-d').'.csv');
    }

    #[Test]
    public function the_order_export_is_withheld_from_staff(): void
    {
        // A bulk file of customer names, emails and phones is not something
        // the orders-only role takes home.
        $this->actingAsAdmin(AdminRole::Staff);

        Livewire::test(ListOrders::class)
            ->assertActionHidden('export');
    }

    #[Test]
    public function a_manager_can_export_orders_and_customer_text_is_formula_safe(): void
    {
        $this->actingAsAdmin(AdminRole::Manager);

        $customer = Customer::factory()->create(['first_name' => '=HYPERLINK("http://x")', 'last_name' => 'Evil']);
        Order::factory()->for($customer)->create(['order_no' => 'KOT-EXPRT1']);

        Livewire::test(ListOrders::class)
            ->assertActionVisible('export')
            ->callAction('export')
            ->assertFileDownloaded('kotiva-orders-'.now()->format('Y-m-d').'.csv');

        $csv = $this->body(CsvExports::orders());

        $this->assertStringContainsString('KOT-EXPRT1', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'a customer-typed formula must be neutralised');
    }

    /* ── the product form keeps tags it has no pill for ──────── */

    #[Test]
    public function saving_a_product_keeps_a_tag_that_has_no_shop_pill(): void
    {
        $this->actingAsAdmin();
        $product = Product::factory()->create(['filter_tags' => ['face', 'cleanser']]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => 'Renamed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['face', 'cleanser'], $product->refresh()->filter_tags);
    }
}
