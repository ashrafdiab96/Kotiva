<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two public origins come only from config (and so from .env): APP_URL
 * for this site, KOTIVA_SALLA_STORE_URL for the store.
 *
 * Both are pointed at hosts that appear nowhere in the codebase, so any
 * production or legacy domain still written into a view, the sitemap or
 * robots.txt shows up here as a literal that config did not produce.
 */
final class PublicOriginsTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://site.origin-test.example';

    private const STORE = 'https://store.origin-test.example';

    /** Domains this project has used; none may appear once config points elsewhere. */
    private const KNOWN_HOSTS_PATTERN = '#(?:[a-z0-9-]+\.)*(?:klyvosystems\.com|kotiva\.co(?![a-z])|ashater\.com)#i';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);

        config([
            'app.url' => self::SITE,
            'kotiva.salla.store_url' => self::STORE,
            'kotiva.salla.cart_url' => self::STORE.'/cart',
            'kotiva.salla.redirect_status' => 302,
            'kotiva.commerce_enabled' => false,
        ]);

        // The committed link file holds paths only, so the real catalog links
        // must come out on the configured test store.
        $this->artisan('kotiva:salla-links', ['--from' => 'storage/app/salla-links.json'])->assertSuccessful();

        // Request-relative links (asset(), url(), route()) follow the request
        // host, which the test client fixed from APP_URL at boot. Serve the
        // requests from the configured site so those links are checked too.
        URL::forceRootUrl(self::SITE);
    }

    #[Test]
    public function the_home_page_uses_only_the_configured_origins(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertNoHardCodedHost($html, 'home page');
        $this->assertStringContainsString('<link rel="canonical" href="'.self::SITE.'/"', $html);
        $this->assertStringContainsString('href="'.self::STORE, $html);

        // A bestseller card: the stored path, on the configured store.
        $micellar = Product::query()->where('sku', 'KOT001')->firstOrFail();
        $this->assertSame('/micellar-water/p1713901761', $micellar->salla_path);
        $this->assertStringContainsString('href="'.self::STORE.'/micellar-water/p1713901761"', $html);
    }

    #[Test]
    public function the_sitemap_lists_only_the_configured_site_origin(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertNoHardCodedHost($xml, 'sitemap');

        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $locs);
        $this->assertNotEmpty($locs[1]);

        foreach ($locs[1] as $loc) {
            $this->assertStringStartsWith(self::SITE.'/', $loc);
        }
    }

    #[Test]
    public function robots_txt_is_rendered_from_config(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));

        $robots = (string) $response->getContent();

        $this->assertNoHardCodedHost($robots, 'robots.txt');
        $this->assertStringContainsString('Sitemap: '.self::SITE.'/sitemap.xml', $robots);
    }

    #[Test]
    public function llms_txt_is_rendered_from_config(): void
    {
        $text = (string) $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertNoHardCodedHost($text, 'llms.txt');
        $this->assertStringContainsString('Main website: '.self::SITE, $text);
        $this->assertStringContainsString('Online store: '.self::STORE, $text);
        $this->assertStringContainsString('[Home]('.self::SITE.'/)', $text);
    }

    #[Test]
    public function shop_redirects_to_the_configured_store(): void
    {
        $this->get('/shop')->assertRedirect(self::STORE);
    }

    #[Test]
    public function no_static_crawl_file_shadows_the_routes(): void
    {
        foreach (['robots.txt', 'llms.txt', 'sitemap.xml'] as $file) {
            $this->assertFileDoesNotExist(public_path($file), "public/{$file} would be served instead of the route");
        }
    }

    private function assertNoHardCodedHost(string $body, string $what): void
    {
        preg_match_all(self::KNOWN_HOSTS_PATTERN, $body, $hits);

        $this->assertSame([], array_values(array_unique($hits[0])), "{$what} contains a hard-coded origin");
    }
}
