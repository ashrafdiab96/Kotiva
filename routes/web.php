<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\Api\ShippingCityApiController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront routes
|--------------------------------------------------------------------------
|
| Paths are chosen so public/js/layout.js's markCurrentNav() keeps working
| unchanged: it strips a trailing "/index.html" and any ".html", compares
| pathnames, then falls back to a section match on /^\/product\// and
| /\/ingredients$/. Extensionless /shop, /product/{slug} and /ingredients all
| satisfy that, so neither regex in markCurrentNav() needed editing.
|
*/

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');

// Constrained so it cannot swallow "/product/<slug>.html" — without this the
// legacy redirect below is unreachable and the 25 indexed .html URLs 404.
Route::get('/product/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('product.show');

Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/science', [PageController::class, 'science'])->name('science');
Route::get('/journal', [PageController::class, 'journal'])->name('journal');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/ingredients', [PageController::class, 'ingredients'])->name('ingredients');
Route::get('/routine-finder', [PageController::class, 'routineFinder'])->name('routine-finder');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('/terms', [PageController::class, 'terms'])->name('terms');

/*
|--------------------------------------------------------------------------
| Cart
|--------------------------------------------------------------------------
|
| Mutations are rate-limited (§8): adding to a cart reserves real stock, so an
| unthrottled endpoint is a way to hold the whole catalog hostage from a script.
|
*/

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::get('/cart/summary', [CartController::class, 'summary'])->name('cart.summary');

Route::middleware('throttle:60,1')->group(function (): void {
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
    Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
});

/*
|--------------------------------------------------------------------------
| Checkout
|--------------------------------------------------------------------------
|
| Placement is throttled harder than the rest: it writes an order, fulfils
| stock and sends mail, so it is the most expensive thing an unattended script
| could repeat.
|
*/

Route::prefix('checkout')->name('checkout.')->group(function (): void {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::get('/review', [CheckoutController::class, 'review'])->name('review');

    Route::get('/shipping', [CheckoutController::class, 'shipping'])->name('shipping');
    Route::post('/shipping', [CheckoutController::class, 'storeShipping'])
        ->middleware('throttle:30,1')
        ->name('shipping.store');

    Route::get('/payment', [CheckoutController::class, 'payment'])->name('payment');
    Route::post('/payment', [CheckoutController::class, 'placeOrder'])
        ->middleware('throttle:10,1')
        ->name('payment.store');

    Route::get('/confirmation/{order_no}', [CheckoutController::class, 'confirmation'])
        ->where('order_no', '[A-Z0-9-]+')
        ->name('confirmation');
});

/*
|--------------------------------------------------------------------------
| Contact + newsletter
|--------------------------------------------------------------------------
|
| These replace the n8n webhook the static site posted to (§3). Both are rate
| limited: they are unauthenticated, they write rows and they send mail, which
| is precisely the shape of an endpoint that gets abused.
|
*/

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
    Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter.store');
});

Route::get('/api/products', [ProductApiController::class, 'index'])->name('api.products');
Route::get('/api/shipping/cities', [ShippingCityApiController::class, 'index'])->name('api.shipping.cities');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

/*
|--------------------------------------------------------------------------
| Legacy URL redirects (301)
|--------------------------------------------------------------------------
|
| The static site was indexed with .html extensions — sitemap.xml listed 35
| such URLs, and llms.txt pointed AI crawlers at the same set. Every one of
| them keeps resolving.
|
*/

$legacyPages = [
    '/index.html' => '/',
    '/shop.html' => '/shop',
    '/about.html' => '/about',
    '/science.html' => '/science',
    '/journal.html' => '/journal',
    '/contact.html' => '/contact',
    '/ingredients.html' => '/ingredients',
    '/routine-finder.html' => '/routine-finder',
    '/privacy-policy.html' => '/privacy-policy',
    '/terms.html' => '/terms',
];

foreach ($legacyPages as $from => $to) {
    Route::permanentRedirect($from, $to);
}

// The 25 pre-rendered pages: /product/<slug>.html -> /product/<slug>.
Route::get('/product/{slug}.html', fn (string $slug) => redirect()->route('product.show', ['slug' => $slug], 301))
    ->where('slug', '[a-z0-9-]+');

// The JS page that addressed products by numeric id: /product.html?id=N.
Route::get('/product.html', [ProductController::class, 'legacyRedirect']);
