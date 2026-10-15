<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ProductApiController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CrawlFilesController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SallaRedirectController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Content pages
|--------------------------------------------------------------------------
|
| This is the brand/content website. The storefront — listing, product
| pages, cart, checkout, orders, stock — lives on Salla, and every commercial
| path below hands off there through App\Support\Salla.
|
*/

Route::get('/', [PageController::class, 'home'])->name('home');

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
| Storefront → Salla
|--------------------------------------------------------------------------
|
| Every page a shopper could have bookmarked or a search engine indexed
| redirects to its Salla equivalent with the configured status — 302 while
| the store is verified, 301 once the operator flips
| KOTIVA_SALLA_REDIRECT_STATUS. The route names are kept so any route() call
| that survives still resolves to a working link.
|
| /product/{slug} is constrained so it cannot swallow "/product/<slug>.html",
| which has its own direct redirect further down.
|
*/

Route::get('/shop', [SallaRedirectController::class, 'shop'])->name('shop.index');

Route::get('/product/{slug}', [SallaRedirectController::class, 'product'])
    ->where('slug', '[a-z0-9-]+')
    ->name('product.show');

Route::get('/cart', [SallaRedirectController::class, 'cart'])->name('cart.index');

Route::get('/checkout', [SallaRedirectController::class, 'cart'])->name('checkout.index');
Route::get('/checkout/{step}', [SallaRedirectController::class, 'cart'])->where('step', '.*');

/*
|--------------------------------------------------------------------------
| Retired commerce endpoints → 410 Gone
|--------------------------------------------------------------------------
|
| 2026-10-13 (Salla handoff): the cart and checkout mutations are removed
| from the router. They used to be:
|
|   GET    /cart/summary              CartController@summary
|   POST   /cart/items                CartController@store
|   PATCH  /cart/items/{item}         CartController@update
|   DELETE /cart/items/{item}         CartController@destroy
|   POST   /checkout/shipping         CheckoutController@storeShipping
|   POST   /checkout/payment          CheckoutController@placeOrder
|   GET    /api/shipping/cities       ShippingCityApiController@index
|
| The controllers stay on disk until the commerce code is deleted in its own
| PR. A stale tab, cached JS or a crawler gets a definitive 410 rather than a
| redirect it would try to POST through.
|
*/

Route::get('/cart/summary', [SallaRedirectController::class, 'gone']);
Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/cart/items/{item?}', [SallaRedirectController::class, 'gone'])
    ->where('item', '.*');
Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '/checkout/{step?}', [SallaRedirectController::class, 'gone'])
    ->where('step', '.*');
Route::get('/api/shipping/cities', [SallaRedirectController::class, 'gone']);

/*
|--------------------------------------------------------------------------
| Doctor referral links (D-38)
|--------------------------------------------------------------------------
|
| /dr/<code> records a visit, sets the kotiva_ref cookie and redirects; every
| Salla URL the visitor then sees carries the doctor's affiliate query. The
| "referral" limiter (AppServiceProvider) caps it per IP and per code.
|
*/

// No pattern on {code}: a malformed code must reach the controller and go
// home quietly rather than 404.
Route::get('/dr/{code}', [ReferralController::class, 'enter'])
    ->middleware('throttle:referral')
    ->name('referral.enter');

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

// Kept: the Routine Finder reads the catalog (now with SKUs and Salla URLs).
Route::get('/api/products', [ProductApiController::class, 'index'])->name('api.products');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Rendered from config so no origin is written into a static file. Do not
// recreate public/robots.txt or public/llms.txt — they would shadow these.
Route::get('/robots.txt', [CrawlFilesController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [CrawlFilesController::class, 'llms'])->name('llms');

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

// /shop.html goes straight to the Salla listing rather than hopping via /shop.
Route::get('/shop.html', [SallaRedirectController::class, 'shop']);

// The 25 pre-rendered pages: /product/<slug>.html — straight to the product on
// Salla in one hop, never chained through /product/{slug}.
Route::get('/product/{slug}.html', [SallaRedirectController::class, 'product'])
    ->where('slug', '[a-z0-9-]+');

// The JS page that addressed products by numeric id: /product.html?id=N.
Route::get('/product.html', [SallaRedirectController::class, 'legacyProduct']);
