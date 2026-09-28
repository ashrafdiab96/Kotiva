<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StockMovementReason;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Support\Money;
use App\Support\Vat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the 25 SKUs from the legacy data bundles.
 *
 * legacy/js/data-lite.js holds the catalog fields and legacy/js/data-full.js the
 * long-form clinical text; both are `const NAME = [ … ];` whose right-hand side
 * is valid JSON, so they are extracted and decoded rather than executed. The two
 * sets are merged on `id`, full overriding lite — the same merge the retired
 * page generator did, so the seeded rows carry exactly what the static pages showed.
 */
final class ProductSeeder extends Seeder
{
    private const INITIAL_STOCK = 50;

    /**
     * The authoritative catalog prices, generated from the client's two A2
     * brochures. See pricesFor().
     */
    private const BROCHURE_PRICES = 'docs/pricing/brochure-prices.json';

    /** @var array<string, array{price_excl_vat: string, price_incl_vat: string}>|null */
    private ?array $brochurePrices = null;

    /**
     * The home page's bestseller rail was hand-ordered in index.html. That
     * editorial sequence exists in no data file, so reading bestsellers from
     * the database by id would quietly reshuffle the rail. Seeding sort_order
     * from it keeps the page identical and leaves the order editable.
     *
     * @var list<string>
     */
    private const HOME_RAIL_ORDER = [
        'micellar-water',
        'glow-up-water-essence-toner',
        'uv-balance-spf50',
        'acne-cleansing-gel',
        'anti-hair-loss-ampoules',
        'sun-protection-spf50',
        'acne-control',
    ];

    public function run(): void
    {
        $lite = $this->readJsArray('legacy/js/data-lite.js', 'KOTIVA_PRODUCTS');
        $full = $this->readJsArray('legacy/js/data-full.js', 'KOTIVA_PRODUCTS_FULL');

        /** @var array<int, array<string, mixed>> $fullById */
        $fullById = [];
        foreach ($full as $row) {
            $fullById[(int) $row['id']] = $row;
        }

        $products = array_map(
            fn (array $row): array => array_merge($row, $fullById[(int) $row['id']] ?? []),
            $lite
        );

        DB::transaction(function () use ($products): void {
            $categories = $this->seedCategories($products);

            foreach ($products as $index => $row) {
                $this->seedProduct($row, $categories, (int) $index);
            }
        });
    }

    /**
     * Categories are derived from the products' own `category` string, in first
     * appearance order — that order is the one the legacy shop grid used.
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<string, int> category name => id
     */
    private function seedCategories(array $products): array
    {
        $names = [];
        foreach ($products as $row) {
            $name = (string) $row['category'];
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        $map = [];
        foreach ($names as $i => $name) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                ]
            );
            $map[$name] = (int) $category->id;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $categories
     */
    private function seedProduct(array $row, array $categories, int $index): void
    {
        $name = (string) $row['name'];
        $slug = (string) $row['slug'];
        $description = (string) ($row['description'] ?? '');

        $product = Product::updateOrCreate(
            ['sku' => (string) $row['kot']],
            [
                'slug' => $slug,
                'name' => $name,
                'category_id' => $categories[(string) $row['category']],
                'sort_order' => $this->sortOrderFor($slug, $index),
                'skin_type' => $row['skinType'] ?? null,
                'concern' => $row['concern'] ?? null,
                'action' => $row['action'] ?? null,
                'volume' => $row['volume'] ?? null,
                ...$this->pricesFor((string) $row['kot'], $row['price']),
                'compare_at_price' => null,
                'description' => $description,
                'benefits' => $row['benefits'] ?? [],
                'how_to_use' => $row['howToUse'] ?? null,
                'science' => $row['science'] ?? null,
                'ingredients' => $row['ingredients'] ?? [],
                'free_from' => $row['freeFrom'] ?? [],
                'filter_tags' => $row['filterTags'] ?? [],
                'image' => $row['image'] ?? null,
                'gallery' => [],
                'is_featured' => (bool) ($row['featured'] ?? false),
                'is_best_seller' => (bool) ($row['bestSeller'] ?? false),
                'is_active' => true,
                'low_stock_threshold' => (int) config('kotiva.stock.low_stock_threshold'),
                'meta_title' => $name.' — kotiva™',
                'meta_description' => $this->shortDescription($description),
            ]
        );

        // Stock is never set directly: it is the sum of its movements. A fresh
        // seed opens the ledger with one restock; re-running the seeder must not
        // stack a second one on top.
        if ($product->stockMovements()->where('reason', StockMovementReason::Restock)->doesntExist()) {
            ProductStockMovement::create([
                'product_id' => $product->id,
                'delta' => self::INITIAL_STOCK,
                'reason' => StockMovementReason::Restock,
                'note' => 'Initial stock on seed',
            ]);

            $product->forceFill(['stock_qty' => self::INITIAL_STOCK])->save();
        }
    }

    /**
     * A product's two authoritative prices.
     *
     * Both come from the client's A2 brochures, extracted to
     * docs/pricing/brochure-prices.json by docs/pricing/extract-brochure-prices.mjs.
     * That file — not this seeder, and not the legacy JS bundle — is the source
     * of truth for what a product costs, because the brochures are what the
     * client signed off.
     *
     * The legacy bundle's `price` is used only as a FALLBACK, for a SKU the
     * brochures do not cover. Its value is VAT-inclusive (verified: all 25
     * launch prices equal the inclusive brochure exactly), so the exclusive
     * figure is derived from it at the configured rate in that case — and the
     * two are cross-checked below, so a brochure the client revises without
     * updating the bundle is reported rather than quietly ignored.
     *
     * @return array{price_excl_vat: string, price_incl_vat: string}
     */
    private function pricesFor(string $sku, mixed $legacyInclusive): array
    {
        $brochure = $this->brochurePrices()[$sku] ?? null;

        if ($brochure === null) {
            $inclusive = Money::of($legacyInclusive);

            $this->warn(
                "  {$sku} is not in the brochure data; pricing it from the legacy bundle at ".Vat::rateLabel().' VAT.'
            );

            return [
                'price_excl_vat' => Vat::exclusiveOf($inclusive),
                'price_incl_vat' => $inclusive,
            ];
        }

        // Both brochure values are written verbatim. If they disagree with the
        // configured rate, that is the client's decision and it is reported,
        // not corrected.
        if (! Vat::pricesAgree($brochure['price_excl_vat'], $brochure['price_incl_vat'])) {
            $this->warn(sprintf(
                '  %s: brochure prices %s excl. / %s incl. do not agree at %s VAT. Both seeded unchanged.',
                $sku, $brochure['price_excl_vat'], $brochure['price_incl_vat'], Vat::rateLabel()
            ));
        }

        return [
            'price_excl_vat' => $brochure['price_excl_vat'],
            'price_incl_vat' => $brochure['price_incl_vat'],
        ];
    }

    /**
     * A pricing note for whoever is running the seeder.
     *
     * Silent when there is no console attached — a seeder run from a test has
     * nowhere to write, and a pricing note is not worth a fatal error.
     */
    private function warn(string $message): void
    {
        $this->command?->warn($message);
    }

    /**
     * The brochure mapping, keyed by SKU. Read once per seeder run.
     *
     * @return array<string, array{price_excl_vat: string, price_incl_vat: string}>
     */
    private function brochurePrices(): array
    {
        if ($this->brochurePrices !== null) {
            return $this->brochurePrices;
        }

        $path = base_path(self::BROCHURE_PRICES);

        if (! is_file($path)) {
            throw new RuntimeException(
                'Brochure price data missing: '.self::BROCHURE_PRICES
                .'. Regenerate it with `node docs/pricing/extract-brochure-prices.mjs`.'
            );
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! isset($decoded['products']) || ! is_array($decoded['products'])) {
            throw new RuntimeException(self::BROCHURE_PRICES.' is not a brochure price report.');
        }

        // The extractor records anything it could not map unambiguously.
        // Seeding prices from a file that knows it is incomplete is exactly the
        // guessing the mapping step exists to avoid.
        if (($decoded['problems'] ?? []) !== []) {
            throw new RuntimeException(
                'Brochure price data reports unresolved problems; resolve them before seeding: '
                .implode(' ', (array) $decoded['problems'])
            );
        }

        $map = [];

        foreach ($decoded['products'] as $product) {
            $sku = (string) ($product['sku'] ?? '');
            $excl = $product['price_excl_vat'] ?? null;
            $incl = $product['price_incl_vat'] ?? null;

            if ($sku === '' || ! is_string($excl) || ! is_string($incl)) {
                continue;
            }

            $map[$sku] = [
                'price_excl_vat' => Money::of($excl),
                'price_incl_vat' => Money::of($incl),
            ];
        }

        return $this->brochurePrices = $map;
    }

    /**
     * Rail members keep the home page's hand-authored order (1-7). Everything
     * else follows the catalog's own order, offset so it can never interleave
     * with the rail.
     */
    private function sortOrderFor(string $slug, int $index): int
    {
        $railPosition = array_search($slug, self::HOME_RAIL_ORDER, true);

        return $railPosition === false
            ? 100 + $index
            : $railPosition + 1;
    }

    /**
     * The retired generator's rule, kept verbatim so meta descriptions do not
     * shift: longer than 160 characters is cut to 157 plus an ellipsis.
     */
    private function shortDescription(string $description): string
    {
        return mb_strlen($description) > 160
            ? mb_substr($description, 0, 157).'…'
            : $description;
    }

    /**
     * Extracts `const <name> = [ … ];` from a legacy JS bundle and decodes it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readJsArray(string $relativePath, string $constName): array
    {
        $path = base_path($relativePath);

        if (! is_file($path)) {
            throw new RuntimeException("Legacy data bundle missing: {$relativePath}");
        }

        $source = (string) file_get_contents($path);
        $pattern = '/const\s+'.preg_quote($constName, '/').'\s*=\s*(\[[\s\S]*?\]);/';

        if (preg_match($pattern, $source, $matches) !== 1) {
            throw new RuntimeException("Could not locate {$constName} in {$relativePath}");
        }

        $decoded = json_decode($matches[1], true);

        if (! is_array($decoded)) {
            throw new RuntimeException(
                "{$constName} in {$relativePath} is not valid JSON: ".json_last_error_msg()
            );
        }

        return $decoded;
    }
}
