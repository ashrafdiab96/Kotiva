<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every storefront URL resolves, and every URL the static site ever exposed
 * still resolves. The old site was indexed with .html extensions and both
 * sitemap.xml and llms.txt pointed crawlers at that exact set, so a 404 here
 * is a lost page, not a cosmetic problem.
 */
final class StorefrontRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeds from the real legacy bundles, so these tests exercise the same
        // parsing path production uses rather than a convenient fixture.
        $this->seed(ProductSeeder::class);
    }

    /**
     * @return list<array{string}>
     */
    public static function storefrontPaths(): array
    {
        return [
            ['/'],
            ['/shop'],
            ['/about'],
            ['/science'],
            ['/journal'],
            ['/contact'],
            ['/ingredients'],
            ['/routine-finder'],
            ['/privacy-policy'],
            ['/terms'],
        ];
    }

    #[Test]
    #[DataProvider('storefrontPaths')]
    public function every_storefront_page_returns_200(string $path): void
    {
        $this->get($path)->assertOk();
    }

    /**
     * @return list<array{string, string}>
     */
    public static function legacyUrls(): array
    {
        return [
            ['/index.html', '/'],
            ['/shop.html', '/shop'],
            ['/about.html', '/about'],
            ['/science.html', '/science'],
            ['/journal.html', '/journal'],
            ['/contact.html', '/contact'],
            ['/ingredients.html', '/ingredients'],
            ['/routine-finder.html', '/routine-finder'],
            ['/privacy-policy.html', '/privacy-policy'],
            ['/terms.html', '/terms'],
        ];
    }

    #[Test]
    #[DataProvider('legacyUrls')]
    public function every_legacy_page_url_redirects_permanently(string $from, string $to): void
    {
        $this->get($from)
            ->assertStatus(301)
            ->assertRedirect($to);
    }

    #[Test]
    public function every_product_page_returns_200(): void
    {
        $slugs = Product::query()->active()->pluck('slug');

        $this->assertCount(25, $slugs);

        foreach ($slugs as $slug) {
            $this->get("/product/{$slug}")->assertOk();
        }
    }

    #[Test]
    public function every_legacy_product_url_redirects_permanently(): void
    {
        foreach (Product::query()->active()->get(['id', 'slug']) as $product) {
            $this->get("/product/{$product->slug}.html")
                ->assertStatus(301)
                ->assertRedirect("/product/{$product->slug}");

            // The JS page addressed products by numeric id.
            $this->get("/product.html?id={$product->id}")
                ->assertStatus(301)
                ->assertRedirect("/product/{$product->slug}");
        }
    }

    #[Test]
    public function an_unknown_legacy_product_id_falls_back_to_the_shop(): void
    {
        $this->get('/product.html?id=99999')
            ->assertStatus(301)
            ->assertRedirect('/shop');

        $this->get('/product.html')
            ->assertStatus(301)
            ->assertRedirect('/shop');
    }

    #[Test]
    public function an_unknown_product_slug_returns_404(): void
    {
        $this->get('/product/not-a-real-product')->assertNotFound();
    }

    #[Test]
    public function an_inactive_product_is_not_reachable(): void
    {
        $product = Product::query()->active()->firstOrFail();
        $product->update(['is_active' => false]);

        $this->get("/product/{$product->slug}")->assertNotFound();
    }

    #[Test]
    public function the_sunscreen_filter_alias_redirects_to_the_canonical_tag(): void
    {
        // The static cards were hand-tagged "sunscreen" while the catalog tags
        // those products "spf". Old links must keep working.
        $this->get('/shop?filter=sunscreen')
            ->assertStatus(301)
            ->assertRedirect('/shop?filter=spf');

        $this->get('/shop?filter=spf')->assertOk();
    }

    #[Test]
    public function the_shop_accepts_every_sort_and_rejects_unknown_ones(): void
    {
        foreach (array_keys((array) config('kotiva.shop.sorts')) as $sort) {
            $this->get("/shop?sort={$sort}")->assertOk();
        }

        // An unknown sort falls back to the default rather than erroring.
        $this->get('/shop?sort=drop-table')->assertOk();
    }

    #[Test]
    public function the_sitemap_lists_every_page_and_active_product(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $response->getContent() ?: '';

        $this->assertSame(35, substr_count($body, '<url>'), '10 pages + 25 products');
        $this->assertStringContainsString('/product/micellar-water</loc>', $body);
    }

    #[Test]
    public function a_deactivated_product_leaves_the_sitemap(): void
    {
        $product = Product::query()->active()->firstOrFail();
        $product->update(['is_active' => false]);

        $body = $this->get('/sitemap.xml')->getContent() ?: '';

        $this->assertSame(34, substr_count($body, '<url>'));
        $this->assertStringNotContainsString("/product/{$product->slug}</loc>", $body);
    }

    #[Test]
    public function the_products_api_returns_what_the_routine_finder_needs(): void
    {
        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(25, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'slug', 'name', 'price', 'currency', 'image', 'url', 'filter_tags', 'stock', 'in_stock'],
            ],
        ]);
    }
}
