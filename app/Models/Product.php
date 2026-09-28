<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use App\Support\Vat;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A catalog product.
 *
 * DUAL PRICING. A product carries BOTH brochure prices. `price_excl_vat` is
 * what the storefront displays and sorts on; `price_incl_vat` is what the
 * customer is charged at checkout. Both come from the client's A2 brochures and
 * both are authoritative — nothing here derives one from the other, because a
 * derivation would silently overrule a figure the client signed off. Where the
 * two disagree at the configured rate, vatDiscrepancy() reports it so an admin
 * can decide. See App\Support\Vat.
 *
 * `price` is a LEGACY MIRROR of `price_incl_vat`, kept in step by the saving
 * hook in booted() and read by nothing in this application. It stays because an
 * existing production dump carries it, the retired `legacy/js/data-lite.js`
 * bundle and outside reports still read it, and quietly dropping a money column
 * is worse than maintaining one derived value in one place. Write to
 * `price_incl_vat`; never to `price`.
 *
 * The @property block below is for static analysis. The json columns are cast
 * to arrays in casts(), but that is a runtime contract PHPStan cannot see —
 * without these it infers `null` for every one of them and reports iterating
 * them as iterating an empty array.
 *
 * @property int $id
 * @property string $sku
 * @property string $slug
 * @property string $name
 * @property int $category_id
 * @property int $sort_order
 * @property string|null $skin_type
 * @property string|null $concern
 * @property string|null $action
 * @property string|null $volume
 * @property string $price Legacy mirror of price_incl_vat; see the class docblock.
 * @property string $price_excl_vat
 * @property string $price_incl_vat
 * @property string|null $compare_at_price
 * @property string|null $description
 * @property list<string>|null $benefits
 * @property string|null $how_to_use
 * @property string|null $science
 * @property list<string>|null $ingredients
 * @property list<string>|null $free_from
 * @property list<string>|null $filter_tags
 * @property string|null $image
 * @property list<string>|null $gallery
 * @property bool $is_featured
 * @property bool $is_best_seller
 * @property bool $is_active
 * @property int $stock_qty
 * @property int $low_stock_threshold
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property int|null $weight_grams
 */
final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'sku', 'slug', 'name', 'category_id', 'sort_order',
        'skin_type', 'concern', 'action', 'volume',
        'price_excl_vat', 'price_incl_vat', 'compare_at_price',
        'description', 'benefits', 'how_to_use', 'science',
        'ingredients', 'free_from', 'filter_tags',
        'image', 'gallery',
        'is_featured', 'is_best_seller', 'is_active',
        'stock_qty', 'low_stock_threshold',
        'meta_title', 'meta_description', 'weight_grams',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_excl_vat' => 'decimal:2',
            'price_incl_vat' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'benefits' => 'array',
            'ingredients' => 'array',
            'free_from' => 'array',
            'filter_tags' => 'array',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'stock_qty' => 'integer',
            'low_stock_threshold' => 'integer',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * Keep the legacy `price` column in step with `price_incl_vat`.
     *
     * One hook, one direction, one place. The alternative — leaving every
     * writer (the form, the importer, the seeder, a tinker session) to
     * remember a third column — is how a mirror stops mirroring, and a stale
     * VAT-inclusive price is the kind of bug that surfaces in somebody's
     * accounting rather than in a test.
     */
    protected static function booted(): void
    {
        self::saving(function (self $product): void {
            // Read from the raw attributes rather than the accessor, so a
            // partially-hydrated model — one selected without its price
            // columns — is left alone instead of having `price` stamped to
            // zero from a field that was never loaded.
            $inclusive = $product->getAttributes()['price_incl_vat'] ?? null;

            if ($inclusive !== null) {
                $product->attributes['price'] = Money::of($inclusive);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /* ── pricing ────────────────────────────────────────────── */

    /**
     * The price the storefront shows: VAT-exclusive, as a 2dp string.
     */
    public function priceExclVat(): string
    {
        return Money::of($this->price_excl_vat);
    }

    /**
     * The price the customer is charged: VAT-inclusive, as a 2dp string.
     */
    public function priceInclVat(): string
    {
        return Money::of($this->price_incl_vat);
    }

    /**
     * The VAT a single unit carries — the difference between the two
     * authoritative prices, not a recomputation from the rate.
     */
    public function unitVat(): string
    {
        return Money::sub($this->priceInclVat(), $this->priceExclVat());
    }

    /**
     * By how much the two stored prices disagree at the configured VAT rate.
     *
     * Zero when they are consistent. Positive when the inclusive price is
     * higher than the rate predicts. Surfaced by the admin form, the product
     * table and the importer — and acted on by none of them, because both
     * figures came off a signed-off brochure and a program guessing which one
     * is "wrong" is worse than a person being told they differ.
     */
    public function vatDiscrepancy(?string $rate = null): string
    {
        return Vat::discrepancy($this->priceExclVat(), $this->priceInclVat(), $rate);
    }

    /**
     * Whether the two stored prices are consistent at the configured rate,
     * within the configured tolerance.
     */
    public function pricesAgreeWithVatRate(?string $rate = null): bool
    {
        return Vat::pricesAgree($this->priceExclVat(), $this->priceInclVat(), $rate);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductStockMovement, $this> */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    /* ── scopes ─────────────────────────────────────────────── */

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Product> $query */
    public function scopeInStock(Builder $query): void
    {
        $query->where('stock_qty', '>', 0);
    }

    /** @param Builder<Product> $query */
    public function scopeBestSellers(Builder $query): void
    {
        $query->where('is_best_seller', true);
    }

    /**
     * Products whose two prices no longer reconcile at the configured VAT rate.
     *
     * The list to work through after a rate change, or after an import whose
     * sheet carried only one of the two columns.
     *
     * The rate and tolerance are formatted into the SQL as numeric literals
     * rather than bound as parameters, and that is deliberate: PDO's SQLite
     * driver binds a PHP float as TEXT, and SQLite sorts every number below
     * every string — so `ABS(...) > ?` with a bound 0.01 is always FALSE and
     * the filter silently matches nothing while looking perfectly correct. It
     * works on MySQL, which coerces, so the bug would only ever have appeared
     * in the one place nobody would look for it.
     *
     * sprintf('%.8F') on a value that has already been cast to float emits
     * digits, a dot and at most a minus sign, so there is nothing here to
     * inject even though these come from config rather than from a request.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeVatMismatched(Builder $query, ?string $rate = null): void
    {
        $query->whereRaw(self::vatMismatchExpression($rate));
    }

    /**
     * The SQL condition behind scopeVatMismatched(), for callers that hold a
     * bare Builder — a Filament table filter, for instance — rather than a
     * Product query.
     */
    public static function vatMismatchExpression(?string $rate = null): string
    {
        $multiplier = sprintf('%.8F', 1 + (float) ($rate ?? Vat::rate()));
        $toleranceHalalas = (int) round(((float) Vat::tolerance()) * 100);

        /*
         | Compared in whole HALALAS, as integers.
         |
         | Every part of this is load-bearing. Rounding to money scale is what
         | makes the database agree with Vat::pricesAgree(), which rounds the
         | product before measuring the gap. Doing the final comparison on
         | integers is what stops floating point deciding the boundary: at the
         | scale of a 115.00 price, the difference of two doubles carries about
         | 1e-14 of error, which is more than enough to put a gap of exactly
         | 0.01 on either side of a 0.01 tolerance depending on the values.
         | A row the table calls consistent and the filter calls broken is worse
         | than either answer on its own.
         */
        return 'ABS(ROUND(price_incl_vat * 100) - ROUND(price_excl_vat * '.$multiplier.' * 100)) > '.$toleranceHalalas;
    }

    /* ── stock ──────────────────────────────────────────────── */

    public function isInStock(): bool
    {
        return $this->stock_qty > 0;
    }

    public function isSoldOut(): bool
    {
        return $this->stock_qty <= 0;
    }

    /**
     * In stock, but at or below the threshold that should be shown to the
     * shopper as scarcity ("Only N left") and to the admin as a low-stock row.
     */
    public function isLowStock(): bool
    {
        return $this->stock_qty > 0 && $this->stock_qty <= $this->low_stock_threshold;
    }

    /**
     * The most a shopper may put in one cart line: the configured ceiling,
     * capped by what actually exists.
     */
    public function maxOrderableQty(): int
    {
        return max(0, min((int) config('kotiva.cart.max_qty_per_line'), $this->stock_qty));
    }

    /* ── images ─────────────────────────────────────────────── */

    /**
     * A stored image path resolved to one document-root-relative convention.
     *
     * Two conventions exist and both are legitimate: the 25 launch products
     * carry committed brand assets under `public/assets/products/...`, while
     * anything uploaded from the dashboard lands on the `public` disk and is
     * served from `/storage/...`. Every render goes through here, because the
     * failure mode of letting call sites choose is that half the storefront
     * keeps working and the other half 404s the moment an image is replaced.
     */
    public static function resolveImagePath(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $path = ltrim($stored, '/');

        return str_starts_with($path, 'assets/') ? $path : 'storage/'.$path;
    }

    public function imagePath(): ?string
    {
        return self::resolveImagePath($this->image);
    }

    public function imageUrl(): ?string
    {
        $path = $this->imagePath();

        return $path === null ? null : asset($path);
    }

    /**
     * The hero render's URL, falling back to the catalog image — see
     * heroImage() for why the fallback exists.
     */
    public function heroImageUrl(): ?string
    {
        $path = self::resolveImagePath($this->heroImage());

        return $path === null ? null : asset($path);
    }

    /**
     * @return list<string>
     */
    public function galleryUrls(): array
    {
        $urls = [];

        foreach ($this->gallery ?? [] as $path) {
            $resolved = self::resolveImagePath($path);

            if ($resolved !== null) {
                $urls[] = asset($resolved);
            }
        }

        return $urls;
    }

    /**
     * schema.org availability, reflecting real stock rather than the static
     * site's hardcoded PreOrder.
     */
    public function schemaAvailability(): string
    {
        return $this->isInStock()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';
    }

    /* ── presentation ───────────────────────────────────────── */

    /**
     * The listing and PDP drop the "Kotiva " prefix from the display name —
     * the legacy cards did this inline and the two must not disagree.
     */
    public function displayName(): string
    {
        return preg_replace('/^Kotiva\s+/i', '', $this->name) ?? $this->name;
    }

    public function filterTagsAttribute(): string
    {
        return implode(',', $this->filter_tags ?? []);
    }

    /**
     * The home page's bestseller rail uses a distinct square "hero" render per
     * SKU (assets/products/hero/kot001.webp), not the catalog image.
     *
     * Only the seven launch bestsellers were ever given one. Since the
     * dashboard lets an admin flag any product as a best seller, an eighth
     * would otherwise render a 404 image on the home page — so this falls back
     * to the catalog image rather than trusting the convention blindly.
     */
    public function heroImage(): ?string
    {
        $hero = 'assets/products/hero/'.strtolower($this->sku).'.webp';

        return is_file(public_path($hero)) ? $hero : $this->image;
    }
}
