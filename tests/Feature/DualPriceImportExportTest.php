<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Export\CsvExports;
use App\Support\Import\ImportReport;
use App\Support\Import\ProductImporter;
use App\Support\Import\SpreadsheetData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * Importing and exporting two prices.
 *
 * The rules being protected here, in order of how expensive they are to get
 * wrong:
 *
 *   1. A sheet carrying both prices has neither of them recalculated, even when
 *      they disagree with the configured rate.
 *   2. A sheet carrying one has the other calculated — and SAYS SO, because on
 *      an update that replaces a stored figure that itself came off a brochure.
 *   3. An old single-column "price" sheet keeps working, read as VAT-inclusive,
 *      which is what it has always meant.
 */
final class DualPriceImportExportTest extends TestCase
{
    use RefreshDatabase;

    private function report(string $csv): ImportReport
    {
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));
        $headers = array_shift($rows);

        $importer = app(ProductImporter::class);
        $data = new SpreadsheetData($headers, $this->numbered($rows));

        return $importer->validate($data, $importer->autoMap($headers));
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<int, list<string>>
     */
    private function numbered(array $rows): array
    {
        $numbered = [];
        foreach ($rows as $i => $row) {
            $numbered[$i + 2] = $row;
        }

        return $numbered;
    }

    private function import(string $csv): ImportReport
    {
        $report = $this->report($csv);
        app(ProductImporter::class)->import($report);

        return $report;
    }

    #[Test]
    public function both_columns_are_imported_verbatim(): void
    {
        $this->import("sku,name,price_excl_vat,price_incl_vat,category\nKOT-IMP-1,Kotiva One,135.00,155.25,Serums\n");

        $product = Product::query()->where('sku', 'KOT-IMP-1')->firstOrFail();

        $this->assertSame('135.00', $product->priceExclVat());
        $this->assertSame('155.25', $product->priceInclVat());
    }

    #[Test]
    public function two_supplied_prices_that_disagree_are_imported_unchanged_and_reported(): void
    {
        // The requirement in one test: an import may not overwrite a price the
        // client stated, and may not stay quiet about the disagreement either.
        $report = $this->import("sku,name,price_excl_vat,price_incl_vat,category\nKOT-IMP-2,Kotiva Two,100.00,120.00,Serums\n");

        $product = Product::query()->where('sku', 'KOT-IMP-2')->firstOrFail();

        $this->assertSame('100.00', $product->priceExclVat());
        $this->assertSame('120.00', $product->priceInclVat());
        $this->assertFalse($report->hasErrors(), 'a disagreement is not a reason to refuse the row');
        $this->assertTrue($report->hasWarnings());
        $this->assertStringContainsString('KOT-IMP-2', $report->warningLines()[0]);
        $this->assertStringContainsString('implies 115.00', $report->warningLines()[0]);
        $this->assertStringContainsString('unchanged', $report->warningLines()[0]);
    }

    #[Test]
    public function a_missing_price_column_is_calculated_and_reported(): void
    {
        $report = $this->import("sku,name,price_excl_vat,category\nKOT-IMP-3,Kotiva Three,200.00,Serums\n");

        $product = Product::query()->where('sku', 'KOT-IMP-3')->firstOrFail();

        $this->assertSame('200.00', $product->priceExclVat());
        $this->assertSame('230.00', $product->priceInclVat());
        $this->assertTrue($report->hasWarnings(), 'a calculated money figure is never silent');
        $this->assertStringContainsString('calculated as 230.00', $report->warningLines()[0]);
    }

    #[Test]
    public function a_legacy_single_price_column_is_read_as_vat_inclusive(): void
    {
        /*
         | Every sheet the client already has carries one "price" column, and it
         | has always meant the VAT-inclusive figure — it is what the old export
         | produced and what the import screen documented. Reading it as net
         | would quietly cut the whole catalog by the VAT.
         */
        $this->import("sku,name,price,category\nKOT-IMP-4,Kotiva Four,155.25,Serums\n");

        $product = Product::query()->where('sku', 'KOT-IMP-4')->firstOrFail();

        $this->assertSame('155.25', $product->priceInclVat());
        $this->assertSame('135.00', $product->priceExclVat());
    }

    #[Test]
    public function common_header_spellings_map_to_the_right_column(): void
    {
        $importer = app(ProductImporter::class);

        foreach (['price', 'price_sar', 'Price incl. VAT', 'price with VAT', 'Gross Price'] as $header) {
            $mapping = $importer->autoMap(['sku', 'name', $header, 'category']);
            $this->assertSame(2, $mapping['price_incl_vat'], $header.' should be the inclusive column');
        }

        foreach (['price_excl_vat', 'Price excl. VAT', 'price without VAT', 'Net Price', 'price before VAT'] as $header) {
            $mapping = $importer->autoMap(['sku', 'name', $header, 'category']);
            $this->assertSame(2, $mapping['price_excl_vat'], $header.' should be the exclusive column');
        }
    }

    #[Test]
    public function a_row_with_no_price_at_all_is_refused(): void
    {
        // A sheet that cannot price a product must not create one at zero.
        $report = $this->report("sku,name,price_excl_vat,category\nKOT-IMP-5,Kotiva Five,,Serums\n");

        $this->assertTrue($report->hasErrors());
        $this->assertStringContainsString('A price is required', $report->errorLines()[0]);
    }

    #[Test]
    public function a_file_with_neither_price_column_names_what_is_missing(): void
    {
        $importer = app(ProductImporter::class);
        $mapping = $importer->autoMap(['sku', 'name', 'category']);

        $this->assertTrue($importer->priceColumnsMissing($mapping));
        $this->assertContains('Price excl. VAT or Price incl. VAT', $importer->missingRequired($mapping));
    }

    #[Test]
    public function swapped_price_columns_are_refused_rather_than_silently_accepted(): void
    {
        $report = $this->report("sku,name,price_excl_vat,price_incl_vat,category\nKOT-IMP-6,Kotiva Six,155.25,135.00,Serums\n");

        $this->assertTrue($report->hasErrors());
        $this->assertStringContainsString('swapped', $report->errorLines()[0]);
    }

    #[Test]
    public function an_unedited_export_reimports_with_nothing_recalculated(): void
    {
        /*
         | The round trip that matters: export, open in Excel, change nothing,
         | import. Because the export carries BOTH columns, a product whose two
         | prices deliberately disagree with the rate survives it untouched and
         | without even a warning.
         */
        $category = Category::factory()->create(['name' => 'Serums', 'slug' => 'serums']);
        Product::factory()->create([
            'sku' => 'KOT-IMP-7',
            'name' => 'Kotiva Seven',
            'category_id' => $category->getKey(),
            'price_excl_vat' => '100.00',
            'price_incl_vat' => '120.00',
        ]);

        $report = $this->import("sku,name,price_excl_vat,price_incl_vat,category\nKOT-IMP-7,Kotiva Seven,100.00,120.00,Serums\n");

        $product = Product::query()->where('sku', 'KOT-IMP-7')->firstOrFail();

        $this->assertSame('100.00', $product->priceExclVat());
        $this->assertSame('120.00', $product->priceInclVat());
        $this->assertFalse($report->hasErrors());
        // It does warn that the two disagree — which is right, and different
        // from warning that something was recalculated.
        $this->assertStringNotContainsString('calculated as', implode(' ', $report->warningLines()));
    }

    #[Test]
    public function the_export_carries_both_prices_in_the_importers_own_column_order(): void
    {
        $category = Category::factory()->create(['name' => 'Serums', 'slug' => 'serums']);
        Product::factory()->create([
            'sku' => 'KOT-IMP-8',
            'category_id' => $category->getKey(),
            'price_excl_vat' => '135.00',
            'price_incl_vat' => '155.25',
        ]);

        $fields = array_keys(ProductImporter::FIELDS);

        $this->assertSame(['sku', 'name', 'price_excl_vat', 'price_incl_vat', 'category'], array_slice($fields, 0, 5));

        $csv = $this->streamed(CsvExports::products());
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));

        $this->assertSame($fields, array_map(fn (string $h): string => ltrim($h, "\u{FEFF}"), $rows[0]));

        $row = array_combine($fields, $rows[1]);
        $this->assertSame('135.00', $row['price_excl_vat']);
        $this->assertSame('155.25', $row['price_incl_vat']);
    }

    private function streamed(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
