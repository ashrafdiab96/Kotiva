<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\StockLow;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\LowStockProducts;
use App\Filament\Widgets\OrdersByStatusOverview;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\RevenueOverview;
use App\Filament\Widgets\SallaNotice;
use App\Filament\Widgets\TopProductsChart;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\StockService;
use App\Support\Salla;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * The Salla handoff: this site is content-only, and every commercial path
 * lands on the Salla store.
 *
 * Pinned from both sides — what now redirects or answers 410, and what can no
 * longer happen here (stock moving, commerce mail, prices on a page).
 */
final class SallaHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function store(): string
    {
        return (string) config('kotiva.salla.store_url');
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kotiva.salla.redirect_status' => 302,
            'kotiva.commerce_enabled' => false,
        ]);
    }

    private function micellar(bool $linked = true): Product
    {
        $factory = Product::factory()->state([
            'sku' => 'KOT001',
            'slug' => 'micellar-water',
            'name' => 'Kotiva Micellar Water',
        ]);

        return ($linked ? $factory->state([
            'salla_product_id' => 1713901761,
            'salla_path' => '/micellar-water/p1713901761',
            'salla_synced_at' => now(),
        ]) : $factory)->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Shop listing
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, array{string, string}>
     */
    public static function shopUrls(): array
    {
        return [
            'bare' => ['/shop', ''],
            'salla filter' => ['/shop?filter=spf', '/?filter=spf'],
            'legacy face' => ['/shop?filter=face', '/?filter=cleanser'],
            'legacy treatments' => ['/shop?filter=treatments', '/?filter=treatment'],
            'legacy sunscreen' => ['/shop?filter=sunscreen', '/?filter=spf'],
            'unknown filter dropped' => ['/shop?filter=nonsense', ''],
            'routine upper-cased' => ['/shop?routine=kot001,KOT005', '/?routine=KOT001,KOT005'],
            'routine de-duplicated' => ['/shop?routine=KOT001,kot001,,KOT002', '/?routine=KOT001,KOT002'],
            'routine junk dropped' => ['/shop?routine=KOT001,<script>,KOT 2', '/?routine=KOT001'],
            'filter and routine' => ['/shop?filter=hair&routine=KOT023', '/?filter=hair&routine=KOT023'],
            'legacy shop.html' => ['/shop.html', ''],
        ];
    }

    #[Test]
    #[DataProvider('shopUrls')]
    public function the_shop_redirects_to_the_salla_listing(string $from, string $suffix): void
    {
        $this->get($from)->assertStatus(302)->assertRedirect($this->store().$suffix);
    }

    #[Test]
    public function the_redirect_status_is_read_from_config(): void
    {
        config(['kotiva.salla.redirect_status' => 301]);
        $this->micellar();

        $this->get('/shop')->assertStatus(301);
        $this->get('/product/micellar-water')->assertStatus(301);
        $this->get('/cart')->assertStatus(301);
    }

    #[Test]
    public function an_array_query_cannot_break_the_shop_redirect(): void
    {
        $this->get('/shop?filter[]=spf&routine[]=KOT001')->assertStatus(302)->assertRedirect($this->store());
    }

    /*
    |--------------------------------------------------------------------------
    | Product pages
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function a_product_redirects_to_its_salla_page(): void
    {
        $this->micellar();

        $this->get('/product/micellar-water')
            ->assertStatus(302)
            ->assertRedirect($this->store().'/micellar-water/p1713901761');
    }

    #[Test]
    public function an_unlinked_product_redirects_to_the_store_root(): void
    {
        $this->micellar(linked: false);

        $this->get('/product/micellar-water')->assertStatus(302)->assertRedirect($this->store());
    }

    #[Test]
    public function an_unknown_product_is_a_404(): void
    {
        $this->get('/product/not-a-product')->assertNotFound();
        $this->get('/product/not-a-product.html')->assertNotFound();
    }

    #[Test]
    public function legacy_product_urls_reach_salla_in_a_single_hop(): void
    {
        $product = $this->micellar();

        foreach (['/product/micellar-water.html', '/product.html?id='.$product->id] as $legacy) {
            $response = $this->get($legacy);

            $response->assertStatus(302);
            // The Location header is the Salla page itself — not a Laravel
            // path that would redirect a second time.
            $this->assertSame($this->store().'/micellar-water/p1713901761', $response->headers->get('Location'), $legacy);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cart and checkout
    |--------------------------------------------------------------------------
    */

    /**
     * @return list<array{string}>
     */
    public static function basketPages(): array
    {
        return [['/cart'], ['/checkout'], ['/checkout/review'], ['/checkout/shipping'], ['/checkout/payment'], ['/checkout/confirmation/KOT-ABC234']];
    }

    #[Test]
    #[DataProvider('basketPages')]
    public function cart_and_checkout_pages_redirect_to_the_salla_cart(string $path): void
    {
        $this->get($path)->assertStatus(302)->assertRedirect((string) config('kotiva.salla.cart_url'));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function retiredEndpoints(): array
    {
        return [
            ['GET', '/cart/summary'],
            ['POST', '/cart/items'],
            ['PATCH', '/cart/items/1'],
            ['DELETE', '/cart/items/1'],
            ['POST', '/checkout/shipping'],
            ['POST', '/checkout/payment'],
            ['GET', '/api/shipping/cities'],
        ];
    }

    #[Test]
    #[DataProvider('retiredEndpoints')]
    public function retired_commerce_endpoints_are_gone(string $method, string $path): void
    {
        $this->json($method, $path, ['product_id' => 1, 'qty' => 1])->assertStatus(410);
    }

    #[Test]
    public function a_retired_endpoint_writes_nothing(): void
    {
        $product = $this->micellar();

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertStatus(410);

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(50, $product->fresh()->stock_qty);
    }

    /*
    |--------------------------------------------------------------------------
    | Stock, mail and schedule side-effects
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function stock_cannot_be_reserved_released_or_fulfilled_here(): void
    {
        $product = $this->micellar();
        $stock = app(StockService::class);

        foreach ([
            'reserve' => fn () => $stock->reserve($product, 1),
            'release' => fn () => $stock->release($product, 1),
            'fulfil' => fn () => $stock->fulfil($product, 1, $product),
        ] as $name => $call) {
            try {
                $call();
                $this->fail("{$name}() should refuse while Salla owns stock");
            } catch (LogicException $e) {
                $this->assertSame('Laravel commerce is disabled; stock is owned by Salla.', $e->getMessage());
            }
        }

        $this->assertSame(50, $product->fresh()->stock_qty);
        $this->assertDatabaseCount('product_stock_movements', 0);
    }

    #[Test]
    public function the_commerce_listeners_are_not_registered(): void
    {
        $this->assertFalse(Event::hasListeners(OrderPlaced::class));
        $this->assertFalse(Event::hasListeners(OrderStatusChanged::class));
        $this->assertFalse(Event::hasListeners(StockLow::class));
    }

    #[Test]
    public function the_cart_purge_is_no_longer_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => (string) $event->command);

        $this->assertFalse($commands->contains(fn (string $command): bool => str_contains($command, 'carts:purge')));
    }

    /*
    |--------------------------------------------------------------------------
    | Product API (Routine Finder)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_products_api_carries_sku_and_salla_url_and_no_price(): void
    {
        $linked = $this->micellar();
        $unlinked = Product::factory()->create(['sku' => 'KOT002']);

        $rows = collect($this->getJson('/api/products')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('KOT001', $rows[$linked->id]['sku']);
        $this->assertSame(Salla::productUrl($linked), $rows[$linked->id]['url']);
        $this->assertSame($this->store(), $rows[$unlinked->id]['url']);

        foreach ($rows as $row) {
            foreach (['price', 'price_excl_vat', 'price_incl_vat', 'price_vat', 'in_stock', 'stock'] as $key) {
                $this->assertArrayNotHasKey($key, $row);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_layout_links_the_shop_and_cart_to_salla_and_holds_no_cart(): void
    {
        $html = (string) $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('class="nav-cart" href="'.$this->store().'/cart"', $html);
        $this->assertStringContainsString('href="'.$this->store().'/?filter=cleanser">Face Care', $html);
        $this->assertStringContainsString('href="'.$this->store().'/?filter=spf">SPF', $html);

        foreach (['data-cart-badge', 'data-mini-cart', 'mini-cart', 'js/shop.js', 'cart/summary', 'href="/shop', 'href="'.url('/shop')] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "{$gone} should be gone from the layout");
        }
    }

    #[Test]
    public function the_home_rail_links_products_to_salla_and_shows_no_price(): void
    {
        $product = $this->micellar();
        $product->update(['is_best_seller' => true, 'price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="bs-card" href="'.Salla::productUrl($product).'"', $html);
        $this->assertStringNotContainsString('bs-card-price', $html);
        $this->assertStringNotContainsString('135.00', $html);
        $this->assertStringNotContainsString('excluding VAT', $html);
        $this->assertStringContainsString('href="'.$this->store().'/?filter=treatment"', $html);
        $this->assertStringNotContainsString(url('/product/'), $html);
        $this->assertStringNotContainsString(url('/shop'), $html);
    }

    #[Test]
    public function the_ingredient_glossary_links_products_to_salla(): void
    {
        $this->micellar();
        Product::factory()->create(['sku' => 'KOT002', 'ingredients' => ['Niacinamide']]);

        $html = (string) $this->get('/ingredients')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.$this->store().'/micellar-water/p1713901761"', $html);
        // Unlinked: the store root, never a dead Laravel product page.
        $this->assertStringContainsString('class="ig-prod" href="'.$this->store().'"', $html);
        $this->assertStringNotContainsString(url('/product/'), $html);
    }

    #[Test]
    public function the_routine_finder_hands_off_to_salla(): void
    {
        $html = (string) $this->get('/routine-finder')->assertOk()->getContent();

        $this->assertStringContainsString('window.SALLA_STORE_URL = '.json_encode($this->store()), $html);
        $this->assertStringContainsString("SALLA_STORE_URL + '/?routine='", $html);
        $this->assertStringContainsString('id="shop-routine-cta"', $html);
        $this->assertStringNotContainsString("'/product/' + slug", $html);
        $this->assertStringNotContainsString("'/shop?routine='", $html);
    }

    #[Test]
    public function about_and_science_link_the_range_to_salla(): void
    {
        foreach (['/about', '/science'] as $path) {
            $html = (string) $this->get($path)->assertOk()->getContent();

            $this->assertStringNotContainsString('href="'.url('/shop').'"', $html, $path);
            $this->assertStringContainsString('href="'.$this->store().'" class="btn', $html, $path);
        }
    }

    #[Test]
    public function no_view_hard_codes_the_store_host(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
            if (str_contains($file->getContents(), (string) parse_url($this->store(), PHP_URL_HOST))) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Store links must go through App\Support\Salla');
    }

    #[Test]
    public function the_store_host_follows_config(): void
    {
        config(['kotiva.salla.store_url' => 'https://staging-store.example.test', 'kotiva.salla.cart_url' => 'https://staging-store.example.test/cart']);

        $this->get('/shop?filter=spf')->assertRedirect('https://staging-store.example.test/?filter=spf');
        $this->assertStringContainsString('href="https://staging-store.example.test/cart"', (string) $this->get('/')->getContent());
    }

    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_helper_only_accepts_urls_on_the_store(): void
    {
        $host = (string) parse_url($this->store(), PHP_URL_HOST);
        $siteHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        $this->assertTrue(Salla::isStoreUrl($this->store().'/micellar-water/p1'));
        $this->assertTrue(Salla::isStoreUrl('https://'.strtoupper($host).'/x'));
        $this->assertFalse(Salla::isStoreUrl('https://'.$host.'.evil.test/x'));
        $this->assertFalse(Salla::isStoreUrl('http://'.$host.'/x'));
        $this->assertFalse(Salla::isStoreUrl('https://'.$siteHost.'/product/x'));
        $this->assertFalse(Salla::isStoreUrl('/micellar-water/p1'));
        $this->assertFalse(Salla::isStoreUrl('javascript:alert(1)'));
    }

    #[Test]
    public function product_urls_join_the_configured_origin_to_the_stored_path(): void
    {
        $product = $this->micellar();

        $this->assertSame('/micellar-water/p1713901761', $product->salla_path);
        $this->assertSame($this->store().'/micellar-water/p1713901761', Salla::productUrl($product));

        // Moving the store is an .env change: the stored path follows it.
        config(['kotiva.salla.store_url' => 'https://moved-store.example.test']);
        $this->assertSame('https://moved-store.example.test/micellar-water/p1713901761', Salla::productUrl($product));

        // A value that is not an absolute path never becomes a link elsewhere.
        foreach (['//evil.test/x', 'https://evil.test/x', 'micellar-water/p1', " /x\n", ''] as $bad) {
            $product->salla_path = $bad;
            $this->assertSame('https://moved-store.example.test', Salla::productUrl($product), var_export($bad, true));
        }
    }

    #[Test]
    public function every_salla_filter_key_passes_through(): void
    {
        foreach (Salla::FILTERS as $key) {
            $this->assertSame($this->store().'/?filter='.$key, Salla::listingUrl($key));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sitemap and crawl files
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_sitemap_lists_no_shop_or_product_urls(): void
    {
        $this->micellar();

        $body = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('/product/', $body);
        $this->assertStringNotContainsString('/shop', $body);
        $this->assertStringNotContainsString('/cart', $body);
        $this->assertMatchesRegularExpression('#<loc>[^<]+/about</loc><lastmod>\d{4}-\d{2}-\d{2}</lastmod>#', $body);
    }

    #[Test]
    public function robots_keeps_the_redirecting_paths_crawlable(): void
    {
        $robots = (string) $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['Disallow: /cart', 'Disallow: /checkout', 'Disallow: /api/', 'Sitemap: '.config('app.url').'/sitemap.xml'] as $line) {
            $this->assertStringContainsString($line, $robots);
        }

        // A crawler cannot follow a redirect it is forbidden to fetch.
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /(shop|product)#m', $robots);
        $this->assertDoesNotMatchRegularExpression('#^Disallow: /\s*$#m', $robots);
    }

    #[Test]
    public function llms_txt_is_generated_from_the_catalog(): void
    {
        $product = $this->micellar();
        $out = storage_path('framework/testing/llms-test.txt');
        @mkdir(dirname($out), 0777, true);

        $this->artisan('kotiva:llms-txt', ['--origin' => config('app.url'), '--output' => 'storage/framework/testing/llms-test.txt'])
            ->assertSuccessful();

        $text = (string) file_get_contents($out);
        @unlink($out);

        $this->assertStringContainsString('Main website: '.config('app.url'), $text);
        $this->assertStringContainsString('Online store: '.$this->store(), $text);
        $this->assertStringContainsString('[Kotiva Micellar Water]('.Salla::productUrl($product).')', $text);
        $this->assertStringNotContainsString('/product/', $text);
        $this->assertStringNotContainsString('.html', $text);
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

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

    #[Test]
    public function the_product_edit_page_shows_the_salla_link_read_only(): void
    {
        $this->actingAsAdmin();
        $product = $this->micellar();

        $this->get(ProductResource::getUrl('edit', ['record' => $product]))
            ->assertOk()
            ->assertSee('Salla product ID')
            ->assertSee('1713901761')
            ->assertSee(Salla::productUrl($product))
            ->assertSee('Managed in Salla');
    }

    #[Test]
    public function prices_stock_and_salla_link_cannot_be_changed_from_the_form(): void
    {
        $this->actingAsAdmin();
        $product = $this->micellar();
        $product->update(['price_excl_vat' => '135.00', 'price_incl_vat' => '155.25']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldIsDisabled('price_excl_vat')
            ->assertFormFieldIsDisabled('price_incl_vat')
            ->assertFormFieldIsDisabled('low_stock_threshold')
            ->assertActionHidden('adjustStock')
            ->fillForm([
                'name' => 'Kotiva Micellar Water Renamed',
                'price_excl_vat' => '1.00',
                'price_incl_vat' => '1.15',
                'low_stock_threshold' => 99,
                'salla_path' => '//evil.test/x',
                'salla_product_id' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $product->fresh();
        $this->assertSame('Kotiva Micellar Water Renamed', $fresh->name, 'editorial fields still save');
        $this->assertSame('135.00', (string) $fresh->price_excl_vat);
        $this->assertSame('155.25', (string) $fresh->price_incl_vat);
        $this->assertSame(5, $fresh->low_stock_threshold);
        $this->assertSame('/micellar-water/p1713901761', $fresh->salla_path);
        $this->assertSame(1713901761, $fresh->salla_product_id);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function everyRole(): array
    {
        return array_combine(
            array_map(fn (AdminRole $r): string => $r->value, AdminRole::cases()),
            array_map(fn (AdminRole $r): array => [$r->value], AdminRole::cases()),
        );
    }

    #[Test]
    #[DataProvider('everyRole')]
    public function the_dashboard_points_at_salla_and_drops_the_commerce_widgets(string $role): void
    {
        // One login per test: Filament's AuthenticateSession logs a request
        // out when the user changes mid-test (see DashboardWidgetsTest).
        $this->actingAsAdmin(AdminRole::from($role));

        $this->assertTrue(SallaNotice::canView());

        foreach ([LatestOrders::class, LowStockProducts::class, OrdersByStatusOverview::class, RevenueChart::class, RevenueOverview::class, TopProductsChart::class] as $widget) {
            $this->assertFalse($widget::canView(), "{$widget} must be hidden for {$role}");
        }

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Store, orders and stock are managed in Salla')
            ->assertSee((string) parse_url($this->store(), PHP_URL_HOST));
    }

    #[Test]
    public function the_settings_page_hides_cod_and_cart_lifetime_and_leaves_them_alone(): void
    {
        $this->actingAsAdmin();
        Setting::put('cod_enabled', true);
        Setting::put('cart_ttl_hours', 72);

        Livewire::test(ManageSettings::class)
            ->assertFormFieldIsHidden('cod_enabled')
            ->assertFormFieldIsHidden('cart_ttl_hours')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) Setting::get('cod_enabled'));
        $this->assertSame(72, (int) Setting::get('cart_ttl_hours'));
    }
}
