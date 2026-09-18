<?php

declare(strict_types=1);

namespace App\Support\Import;

use App\Enums\StockMovementReason;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Product import from CSV/XLSX (§7.2).
 *
 * Two passes, deliberately. validate() reads every row and writes nothing, so
 * the caller can refuse the whole file when any row is wrong — the brief's
 * all-or-nothing rule — or knowingly go ahead with only the valid rows.
 * import() then writes those rows inside one transaction.
 *
 * Rules that are not obvious:
 *
 * - Upserts by SKU, including soft-deleted products, which are restored. The
 *   SKU column is unique across trashed rows too, so "create" would otherwise
 *   fail with an integrity error the admin cannot act on.
 * - On an update, an empty cell leaves the field alone. A sheet that only
 *   carries sku + price must reprice, not wipe every description.
 * - The slug of an existing product is never changed unless the file says so:
 *   it is a live, indexed URL.
 * - stock_qty is the target quantity, applied as the difference through
 *   StockService with reason `import` — the column is never written directly,
 *   so the ledger still explains every unit.
 * - Categories are matched by slug, exactly as ProductSeeder creates them, so
 *   "Face Care" in a sheet finds the seeded category instead of cloning it.
 */
final class ProductImporter
{
    /**
     * field => [label, type, required]. Order is the template's column order.
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    public const FIELDS = [
        'sku' => ['SKU', 'string', true],
        'name' => ['Name', 'string', true],
        'price' => ['Price', 'money', true],
        'category' => ['Category', 'string', true],
        'slug' => ['Slug', 'slug', false],
        'compare_at_price' => ['Compare-at price', 'money', false],
        'stock_qty' => ['Stock', 'int', false],
        'low_stock_threshold' => ['Low-stock threshold', 'int', false],
        'skin_type' => ['Skin type', 'string', false],
        'concern' => ['Concern', 'string', false],
        'action' => ['Action', 'string', false],
        'volume' => ['Volume', 'string', false],
        'description' => ['Description', 'text', false],
        'benefits' => ['Benefits', 'list', false],
        'how_to_use' => ['How to use', 'text', false],
        'science' => ['Science', 'text', false],
        'ingredients' => ['Ingredients', 'list', false],
        'free_from' => ['Free from', 'list', false],
        'filter_tags' => ['Shop filters', 'tags', false],
        'image' => ['Image path', 'image', false],
        'is_active' => ['Active', 'bool', false],
        'is_featured' => ['Featured', 'bool', false],
        'is_best_seller' => ['Best seller', 'bool', false],
        'sort_order' => ['Sort order', 'int', false],
        'weight_grams' => ['Weight (g)', 'int', false],
        'meta_title' => ['Meta title', 'string', false],
        'meta_description' => ['Meta description', 'text', false],
    ];

    /**
     * Header spellings people actually use, normalised, mapped to a field.
     */
    private const HEADER_ALIASES = [
        'product' => 'name',
        'product_name' => 'name',
        'title' => 'name',
        'price_sar' => 'price',
        'category_name' => 'category',
        'stock' => 'stock_qty',
        'qty' => 'stock_qty',
        'quantity' => 'stock_qty',
        'tags' => 'filter_tags',
        'filters' => 'filter_tags',
        'active' => 'is_active',
        'featured' => 'is_featured',
        'best_seller' => 'is_best_seller',
        'bestseller' => 'is_best_seller',
        'weight' => 'weight_grams',
        'freefrom' => 'free_from',
        'howtouse' => 'how_to_use',
    ];

    public function __construct(private readonly StockService $stock) {}

    /* ── mapping ─────────────────────────────────────────────── */

    /**
     * Best-guess column for each field, by header name.
     *
     * @param  list<string>  $headers
     * @return array<string, int|null> field => column index
     */
    public function autoMap(array $headers): array
    {
        $byNormalised = [];

        foreach ($headers as $index => $header) {
            $normalised = self::normalise($header);
            $field = array_key_exists($normalised, self::FIELDS)
                ? $normalised
                : (self::HEADER_ALIASES[$normalised] ?? null);

            // First column wins if two headers map to the same field.
            if ($field !== null && ! array_key_exists($field, $byNormalised)) {
                $byNormalised[$field] = $index;
            }
        }

        $mapping = [];

        foreach (array_keys(self::FIELDS) as $field) {
            $mapping[$field] = $byNormalised[$field] ?? null;
        }

        return $mapping;
    }

    /**
     * Fields the mapping has left unassigned but must have.
     *
     * @param  array<string, int|null>  $mapping
     * @return list<string> field labels
     */
    public function missingRequired(array $mapping): array
    {
        $missing = [];

        foreach (self::FIELDS as $field => [$label, , $required]) {
            if ($required && ($mapping[$field] ?? null) === null) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /* ── pass 1: validate ────────────────────────────────────── */

    /**
     * @param  array<string, int|null>  $mapping
     */
    public function validate(SpreadsheetData $data, array $mapping): ImportReport
    {
        // Catalog-sized, so preloading beats a query per row.
        $slugOwners = Product::withTrashed()->pluck('sku', 'slug')->all();

        $valid = [];
        $errors = [];
        $seenSkus = [];
        $seenSlugs = [];

        foreach ($data->rows as $line => $cells) {
            [$row, $messages] = $this->parseRow($cells, $mapping);

            $sku = $row['sku'] ?? null;

            if (is_string($sku)) {
                if (isset($seenSkus[$sku])) {
                    $messages[] = 'SKU '.$sku.' also appears on line '.$seenSkus[$sku].'.';
                } else {
                    $seenSkus[$sku] = $line;
                }
            }

            $slug = $row['slug'] ?? null;

            if (is_string($slug)) {
                $owner = $slugOwners[$slug] ?? null;

                if ($owner !== null && $owner !== $sku) {
                    $messages[] = 'Slug "'.$slug.'" already belongs to '.$owner.'.';
                } elseif (isset($seenSlugs[$slug])) {
                    $messages[] = 'Slug "'.$slug.'" also appears on line '.$seenSlugs[$slug].'.';
                } else {
                    $seenSlugs[$slug] = $line;
                }
            }

            if ($messages === []) {
                $valid[$line] = $row;
            } else {
                $errors[$line] = $messages;
            }
        }

        return new ImportReport($valid, $errors);
    }

    /**
     * @param  list<string>  $cells
     * @param  array<string, int|null>  $mapping
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function parseRow(array $cells, array $mapping): array
    {
        $row = [];
        $messages = [];

        foreach (self::FIELDS as $field => [$label, $type, $required]) {
            $index = $mapping[$field] ?? null;
            $cell = $index === null ? '' : self::unguard($cells[$index] ?? '');

            // Long text keeps its exact whitespace: `science` is rendered by
            // ScienceRenderer, whose output is checked byte for byte, so an
            // export/import round trip must not rewrite it. Everything else
            // is trimmed as a person would expect.
            $raw = $type === 'text' ? $cell : trim($cell);

            if (trim($raw) === '') {
                if ($required) {
                    $messages[] = $label.' is required.';
                }

                continue;
            }

            [$value, $error] = $this->cast($raw, $type, $label);

            if ($error !== null) {
                $messages[] = $error;

                continue;
            }

            $row[$field] = $value;
        }

        return [$row, $messages];
    }

    /**
     * @return array{0: mixed, 1: string|null}
     */
    private function cast(string $raw, string $type, string $label): array
    {
        switch ($type) {
            case 'money':
                $clean = str_replace([',', ' '], '', preg_replace('/^SAR/i', '', $raw) ?? $raw);

                if (! is_numeric($clean) || (float) $clean < 0) {
                    return [null, $label.' "'.$raw.'" is not a price.'];
                }

                return [bcadd($clean, '0', 2), null];

            case 'int':
                if (preg_match('/^\d+$/', $raw) !== 1) {
                    return [null, $label.' "'.$raw.'" must be a whole number of 0 or more.'];
                }

                return [(int) $raw, null];

            case 'bool':
                $normalised = strtolower($raw);

                if (in_array($normalised, ['1', 'yes', 'y', 'true'], true)) {
                    return [true, null];
                }

                if (in_array($normalised, ['0', 'no', 'n', 'false'], true)) {
                    return [false, null];
                }

                return [null, $label.' "'.$raw.'" must be yes or no.'];

            case 'list':
                return [self::splitList($raw), null];

            case 'tags':
                return $this->castTags($raw, $label);

            case 'slug':
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $raw) !== 1) {
                    return [null, $label.' "'.$raw.'" may only contain lowercase letters, numbers and hyphens.'];
                }

                return [$raw, null];

            case 'image':
                // Product::resolveImagePath() treats anything outside assets/
                // as a storage path, so a pasted URL would render as a broken
                // /storage/https://… image rather than fail visibly.
                if (str_contains($raw, '://')) {
                    return [null, $label.' must be a path such as assets/products/kot001.webp, not a web address.'];
                }

                return [ltrim($raw, '/'), null];

            case 'string':
                if (mb_strlen($raw) > 255) {
                    return [null, $label.' is longer than 255 characters.'];
                }

                return [$raw, null];

            default:
                return [$raw, null];
        }
    }

    /**
     * Tags are free-form slugs, not restricted to the shop's pills.
     *
     * The pills are a curated list in config, so an extra tag cannot invent
     * one — it simply is not filterable. And the launch data already relies on
     * that: seven products carry `cleanser`, which has no pill. Restricting
     * tags to the pills would make an unedited export fail to re-import.
     * Aliases still apply, so "sunscreen" becomes the canonical "spf".
     *
     * @return array{0: mixed, 1: string|null}
     */
    private function castTags(string $raw, string $label): array
    {
        /** @var array<string, string> $aliases */
        $aliases = config('kotiva.shop.filter_aliases', []);

        $tags = [];

        foreach (self::splitList($raw) as $tag) {
            $tag = strtolower($tag);
            $tag = $aliases[$tag] ?? $tag;

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tag) !== 1) {
                return [null, $label.' "'.$tag.'" may only contain lowercase letters, numbers and hyphens.'];
            }

            if (! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return [$tags, null];
    }

    /* ── pass 2: write ───────────────────────────────────────── */

    /**
     * Writes the report's valid rows. The caller decides whether an invalid
     * row should have stopped the whole file before this is reached.
     */
    public function import(ImportReport $report, ?int $adminId = null, string $source = 'import'): ImportResult
    {
        return DB::transaction(function () use ($report, $adminId, $source): ImportResult {
            $created = 0;
            $updated = 0;
            $unchanged = 0;
            $stockAdjusted = 0;

            $categories = [];
            $usedSlugs = array_fill_keys(Product::withTrashed()->pluck('slug')->all(), true);
            $nextSort = (int) Product::withTrashed()->max('sort_order') + 1;
            $defaultThreshold = (int) Setting::get('low_stock_threshold', config('kotiva.stock.low_stock_threshold'));

            foreach ($report->valid as $row) {
                $categoryId = $categories[$row['category']] ??= $this->categoryId((string) $row['category']);

                $attributes = array_diff_key($row, array_flip(['sku', 'category', 'stock_qty']));
                $attributes['category_id'] = $categoryId;

                $product = Product::withTrashed()->where('sku', $row['sku'])->first();

                if ($product instanceof Product) {
                    $wasTrashed = $product->trashed();

                    if ($wasTrashed) {
                        $product->restore();
                    }

                    $product->fill($attributes);

                    // A re-imported, unedited export should say "unchanged",
                    // not claim to have updated the whole catalog.
                    if ($product->isDirty() || $wasTrashed) {
                        $product->save();
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                } else {
                    $slug = $row['slug'] ?? $this->uniqueSlug((string) $row['name'], (string) $row['sku'], $usedSlugs);
                    $usedSlugs[$slug] = true;

                    $product = Product::create($attributes + [
                        'sku' => $row['sku'],
                        'slug' => $slug,
                        'sort_order' => $nextSort++,
                        'low_stock_threshold' => $defaultThreshold,
                        'is_active' => true,
                        // Always created empty: stock arrives as a ledger
                        // movement below, never as a column value.
                        'stock_qty' => 0,
                    ]);
                    $created++;
                }

                if (array_key_exists('stock_qty', $row)) {
                    $delta = (int) $row['stock_qty'] - $product->stock_qty;

                    // adjust() refuses a zero delta; an unchanged level is not
                    // a movement and must not add a row to the ledger.
                    if ($delta !== 0) {
                        $this->stock->adjust($product, $delta, StockMovementReason::Import, 'Set to '.$row['stock_qty'].' by '.$source, $adminId);
                        $stockAdjusted++;
                    }
                }
            }

            return new ImportResult(
                created: $created,
                updated: $updated,
                unchanged: $unchanged,
                stockAdjusted: $stockAdjusted,
                skipped: count($report->errors),
            );
        });
    }

    private function categoryId(string $name): int
    {
        $category = Category::query()->where('slug', Str::slug($name))->first()
            ?? Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => (int) Category::query()->max('sort_order') + 1,
                'is_active' => true,
            ]);

        return (int) $category->getKey();
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueSlug(string $name, string $sku, array $used): string
    {
        // An Arabic-only name slugs to '', so fall back to the SKU.
        $base = Str::slug($name);
        $base = $base !== '' ? $base : Str::slug('product-'.$sku);

        $slug = $base;
        $n = 2;

        while (isset($used[$slug])) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /* ── helpers ─────────────────────────────────────────────── */

    /**
     * @return list<string>
     */
    public static function splitList(string $raw): array
    {
        return array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            fn (string $item): bool => $item !== ''
        ));
    }

    public static function normalise(string $header): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($header)), '_');
    }

    /**
     * Reverses the export's spreadsheet-formula guard (see CsvExports::safe),
     * so an exported file re-imports without a stray leading apostrophe.
     */
    public static function unguard(string $value): string
    {
        return preg_match("/^'[=+\\-@\t\r]/", $value) === 1 ? substr($value, 1) : $value;
    }
}
