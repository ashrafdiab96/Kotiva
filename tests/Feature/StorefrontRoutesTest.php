<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Salla;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every content URL resolves, and every URL the static site ever exposed
 * still resolves. The old site was indexed with .html extensions and both
 * sitemap.xml and llms.txt pointed crawlers at that exact set, so a 404 here
 * is a lost page, not a cosmetic problem.
 *
 * Since the Salla handoff the shop and product URLs resolve by redirecting to
 * the Salla store. These run against the real seeded catalog linked from the
 * committed storage/app/salla-links.json, so a SKU that drifts between the two
 * fails here rather than in production.
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

        config([
            'kotiva.salla.redirect_status' => 302,
        ]);
    }

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    private function linkCatalogToSalla(): void
    {
        $this->artisan('kotiva:salla-links', ['--from' => 'storage/app/salla-links.json'])
            ->assertSuccessful();
    }

    /**
     * @return list<array{string}>
     */
    public static function storefrontPaths(): array
    {
        return [
            ['/'],
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
    public function the_legacy_shop_page_goes_straight_to_salla(): void
    {
        $this->get('/shop.html')
            ->assertStatus(302)
            ->assertRedirect($this->store());
    }

    #[Test]
    public function the_committed_link_file_links_every_active_product(): void
    {
        $this->assertCount(25, Product::query()->active()->get());

        $this->linkCatalogToSalla();

        $this->artisan('kotiva:salla-links', ['--check' => true])->assertExitCode(0);

        foreach (Product::query()->active()->get() as $product) {
            $this->assertTrue(Salla::isStorePath($product->salla_path), $product->sku);
            $this->assertStringStartsWith($this->store().'/', Salla::productUrl($product), $product->sku);
            $this->assertNotNull($product->salla_product_id, $product->sku);
        }
    }

    #[Test]
    public function every_product_url_redirects_to_its_salla_page(): void
    {
        $this->linkCatalogToSalla();

        foreach (Product::query()->active()->get() as $product) {
            $this->get("/product/{$product->slug}")
                ->assertStatus(302)
                ->assertRedirect(Salla::productUrl($product));
        }
    }

    #[Test]
    public function every_legacy_product_url_goes_to_salla_in_one_hop(): void
    {
        $this->linkCatalogToSalla();

        foreach (Product::query()->active()->get() as $product) {
            // The Location is the Salla page itself, not a Laravel path that
            // would redirect again.
            $this->get("/product/{$product->slug}.html")
                ->assertStatus(302)
                ->assertRedirect(Salla::productUrl($product));

            // The JS page addressed products by numeric id.
            $this->get("/product.html?id={$product->id}")
                ->assertStatus(302)
                ->assertRedirect(Salla::productUrl($product));
        }
    }

    #[Test]
    public function an_unknown_legacy_product_id_falls_back_to_the_store(): void
    {
        $this->get('/product.html?id=99999')
            ->assertStatus(302)
            ->assertRedirect($this->store());

        $this->get('/product.html')
            ->assertStatus(302)
            ->assertRedirect($this->store());
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
    public function the_sunscreen_filter_alias_lands_on_the_salla_spf_filter(): void
    {
        // The static cards were hand-tagged "sunscreen" while the catalog tags
        // those products "spf". Old links must keep working.
        $this->get('/shop?filter=sunscreen')
            ->assertStatus(302)
            ->assertRedirect($this->store().'/?filter=spf');
    }

    #[Test]
    public function the_sitemap_lists_the_content_pages_only(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $body = $response->getContent() ?: '';

        $this->assertSame(9, substr_count($body, '<url>'), '9 content pages; the shop and products are on Salla');
        $this->assertStringNotContainsString('/product/', $body);
        $this->assertStringNotContainsString('/shop', $body);
        $this->assertStringContainsString('/routine-finder</loc>', $body);
    }

    #[Test]
    public function the_products_api_returns_what_the_routine_finder_needs(): void
    {
        $this->linkCatalogToSalla();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(25, 'data');
        $response->assertJsonStructure([
            'data' => [
                ['id', 'sku', 'slug', 'name', 'image', 'url', 'filter_tags'],
            ],
        ]);
    }
}
