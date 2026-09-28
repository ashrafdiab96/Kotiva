<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Money;
use App\Support\Vat;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The catalog's prices against the client's A2 brochures.
 *
 * The brochures are a single A2 page of six vertical product columns, so
 * `pdftotext` flattens them into a grid where a price sits dozens of lines from
 * its heading with other products' text in between. Reading the prices in text
 * order assigns them to the wrong products — silently, and in a way that looks
 * entirely plausible until a customer is charged the wrong amount.
 *
 * docs/pricing/extract-brochure-prices.mjs binds each price to the nearest
 * preceding heading in its own column band and writes brochure-prices.json.
 * This file is what stops that mapping rotting: it pins the 25 prices, checks
 * the seeder writes them verbatim, and checks nothing derives one from the
 * other.
 */
final class BrochurePricingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The mapping, as read off the two brochures. SKU => [excl, incl].
     *
     * Written out here rather than read from the JSON on purpose: a test that
     * loads the same file the seeder loads would pass even if the extractor
     * produced nonsense. This is the independent copy.
     *
     * @var array<string, array{string, string}>
     */
    private const BROCHURE = [
        'KOT001' => ['135.00', '155.25'],  // Micellar Water
        'KOT002' => ['145.00', '166.75'],  // Acne Cleansing Gel
        'KOT003' => ['135.00', '155.25'],  // Hyaluronic Facial Cleanser
        'KOT004' => ['135.00', '155.25'],  // Facial Foam for Sensitive Skin
        'KOT005' => ['150.00', '172.50'],  // Glow Up Water Essence Toner
        'KOT006' => ['145.00', '166.75'],  // Oil Control Foam
        'KOT007' => ['135.00', '155.25'],  // Face & Body Cleansing Foam
        'KOT008' => ['120.00', '138.00'],  // Post Fillers Lip Balm
        'KOT009' => ['160.00', '184.00'],  // Sun Protection SPF 50+
        'KOT010' => ['120.00', '138.00'],  // After Sun Cream
        'KOT011' => ['125.00', '143.75'],  // Younger Hand Cream
        'KOT012' => ['235.00', '270.25'],  // Whitening Hand Cream
        'KOT013' => ['80.00', '92.00'],    // Anti-Perspirant Roll On
        'KOT014' => ['150.00', '172.50'],  // Whitening Anti-Perspirant
        'KOT015' => ['170.00', '195.50'],  // Hydroshield Post-Laser Cream
        'KOT016' => ['350.00', '402.50'],  // Triple Action Whitening Day Cream
        'KOT017' => ['350.00', '402.50'],  // Triple Action Whitening Night Cream
        'KOT018' => ['350.00', '402.50'],  // Triple Action Bikini Area Whitening Cream
        'KOT019' => ['145.00', '166.75'],  // Triple Action Whitening Wash Gel
        'KOT020' => ['150.00', '172.50'],  // Acne Control
        'KOT021' => ['200.00', '230.00'],  // Pores Off Serum
        'KOT022' => ['160.00', '184.00'],  // UV Balance SPF 50+
        'KOT023' => ['300.00', '345.00'],  // Anti-Hair Loss Ampoules
        'KOT024' => ['150.00', '172.50'],  // Anti-Hair Loss Shampoo
        'KOT025' => ['300.00', '345.00'],  // Siliscar Gel
    ];

    #[Test]
    public function the_extracted_mapping_matches_the_brochures(): void
    {
        $report = $this->report();

        $this->assertSame([], $report['problems'], 'the extractor must resolve every price unambiguously');
        $this->assertCount(25, $report['products']);

        $extracted = [];
        foreach ($report['products'] as $row) {
            $extracted[$row['sku']] = [$row['price_excl_vat'], $row['price_incl_vat']];
        }

        $this->assertSame(self::BROCHURE, $extracted);
    }

    #[Test]
    public function every_price_token_in_both_brochures_was_consumed(): void
    {
        /*
         | 25 products, 25 price tokens per brochure, every one assigned.
         |
         | This is the assertion that would catch a silent mis-mapping: if the
         | column-band rule bound two prices to one product, some other product
         | would be left without one and the counts would not match.
         */
        $counts = $this->report()['counts'];

        $this->assertSame(25, $counts['products']);
        $this->assertSame(25, $counts['exclusive_price_tokens']);
        $this->assertSame(25, $counts['inclusive_price_tokens']);
        $this->assertSame(25, $counts['exclusive_prices_found']);
        $this->assertSame(25, $counts['inclusive_prices_found']);
    }

    #[Test]
    public function the_two_brochures_agree_at_fifteen_percent(): void
    {
        // They do today, on all 25 — which is why the catalog can be seeded
        // from both without anyone having to choose between them. If a revised
        // brochure ever disagrees, this fails and somebody decides.
        foreach (self::BROCHURE as $sku => [$excl, $incl]) {
            $this->assertSame($incl, Vat::inclusiveOf($excl, '0.15'), $sku.' disagrees between the two brochures');
        }
    }

    #[Test]
    public function the_seeder_writes_both_brochure_prices_verbatim(): void
    {
        $this->seed(ProductSeeder::class);

        $this->assertSame(25, Product::query()->count());

        foreach (self::BROCHURE as $sku => [$excl, $incl]) {
            $product = Product::query()->where('sku', $sku)->firstOrFail();

            $this->assertSame($excl, $product->priceExclVat(), $sku.' exclusive price');
            $this->assertSame($incl, $product->priceInclVat(), $sku.' inclusive price');
        }
    }

    #[Test]
    public function the_seeded_catalog_has_no_vat_discrepancies(): void
    {
        $this->seed(ProductSeeder::class);

        $mismatched = Product::query()->get()
            ->reject(fn (Product $p): bool => $p->pricesAgreeWithVatRate())
            ->map(fn (Product $p): string => $p->sku)
            ->all();

        $this->assertSame([], $mismatched, 'every launch product reconciles at the configured rate');
    }

    #[Test]
    public function the_legacy_price_column_mirrors_the_inclusive_price(): void
    {
        /*
         | `price` is kept for the existing production dump and outside reports
         | and is read by nothing in the application. It has to keep tracking
         | the inclusive price, or a report run off the raw table would quote
         | figures the shop no longer charges.
         */
        $this->seed(ProductSeeder::class);

        foreach (Product::query()->get() as $product) {
            $this->assertSame(
                Money::of($product->price_incl_vat),
                Money::of($product->price),
                $product->sku.': the legacy price column drifted from price_incl_vat'
            );
        }

        // And it keeps tracking through an ordinary update.
        $product = Product::query()->firstOrFail();
        $product->update(['price_excl_vat' => '999.00', 'price_incl_vat' => '1148.85']);

        $this->assertSame('1148.85', Money::of($product->fresh()->price));
    }

    #[Test]
    public function reseeding_does_not_disturb_the_prices(): void
    {
        // The seeder is idempotent everywhere else; prices are no exception.
        $this->seed(ProductSeeder::class);
        $this->seed(ProductSeeder::class);

        $product = Product::query()->where('sku', 'KOT001')->firstOrFail();

        $this->assertSame('135.00', $product->priceExclVat());
        $this->assertSame('155.25', $product->priceInclVat());
        $this->assertSame(25, Product::query()->count());
    }

    /**
     * @return array{problems: list<string>, counts: array<string, int>, products: list<array<string, mixed>>}
     */
    private function report(): array
    {
        $path = base_path('docs/pricing/brochure-prices.json');

        $this->assertFileExists($path, 'run `node docs/pricing/extract-brochure-prices.mjs` to regenerate it');

        /** @var array{problems: list<string>, counts: array<string, int>, products: list<array<string, mixed>>} $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
