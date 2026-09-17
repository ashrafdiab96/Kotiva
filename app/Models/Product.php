<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Column types for static analysis. The json columns are cast to arrays in
 * casts(), but that is a runtime contract PHPStan cannot see — without these
 * it infers `null` for every one of them and reports iterating them as
 * iterating an empty array.
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
 * @property string $price
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
        'price', 'compare_at_price',
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

    public function getRouteKeyName(): string
    {
        return 'slug';
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
