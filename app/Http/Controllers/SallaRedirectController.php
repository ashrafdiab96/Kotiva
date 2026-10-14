<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Salla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The storefront's old addresses, handed off to Salla.
 *
 * Every redirect goes to its final Salla URL in one hop with the configured
 * status (302 while the store is verified, 301 after), so search engines
 * transfer each indexed page's signal straight to its new home rather than
 * through a chain of Laravel paths.
 */
final class SallaRedirectController extends Controller
{
    /**
     * /shop, /shop?filter=…, /shop?routine=SKU,SKU — and /shop.html.
     */
    public function shop(Request $request): RedirectResponse
    {
        $filter = $request->query('filter');
        $routine = $request->query('routine');

        return $this->away(Salla::listingUrl(
            is_string($filter) ? $filter : null,
            is_string($routine) ? explode(',', $routine) : [],
        ));
    }

    /**
     * /product/{slug} and the legacy /product/{slug}.html.
     */
    public function product(string $slug): RedirectResponse
    {
        $product = Product::query()->active()->where('slug', $slug)->first();

        if (! $product instanceof Product) {
            throw new NotFoundHttpException("No active product for slug [{$slug}].");
        }

        return $this->away(Salla::productUrl($product));
    }

    /**
     * The legacy JS product page, /product.html?id=N. An id that resolves to
     * nothing still lands somewhere useful — the store — as it always has.
     */
    public function legacyProduct(Request $request): RedirectResponse
    {
        $id = $request->query('id');

        $product = is_numeric($id) ? Product::query()->active()->find((int) $id) : null;

        return $this->away($product instanceof Product ? Salla::productUrl($product) : Salla::storeUrl());
    }

    /**
     * /cart, /checkout and every checkout step: the basket lives on Salla.
     */
    public function cart(): RedirectResponse
    {
        return $this->away(Salla::cartUrl());
    }

    /**
     * The retired cart and checkout mutations.
     */
    public function gone(): never
    {
        abort(410);
    }

    private function away(string $url): RedirectResponse
    {
        return redirect()->away($url, Salla::redirectStatus());
    }
}
