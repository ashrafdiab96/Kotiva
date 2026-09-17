<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\ProductFacts;
use App\Support\ScienceRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductController extends Controller
{
    public function __construct(
        private readonly ScienceRenderer $science,
        private readonly ProductFacts $facts,
    ) {}

    public function show(string $slug): View
    {
        $product = Product::query()
            ->active()
            ->with('category')
            ->where('slug', $slug)
            ->first();

        if (! $product instanceof Product) {
            throw new NotFoundHttpException("No active product for slug [{$slug}].");
        }

        // Same category, in-stock first so the rail never opens with sold-out
        // items, then a stable order. Capped per config.
        $related = Product::query()
            ->active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->orderByRaw('CASE WHEN stock_qty > 0 THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->limit((int) config('kotiva.shop.related_limit'))
            ->get();

        // "01 / 25" in the gallery corner — position within the active catalog,
        // matching what the static pages printed.
        $activeIds = Product::query()->active()->orderBy('id')->pluck('id')->all();
        $position = (int) array_search($product->getKey(), $activeIds, true) + 1;

        $zone = ($product->filter_tags ?? [])[0] ?? null;
        /** @var array<string, array{name: string, filter: string}> $crumbs */
        $crumbs = config('kotiva.zone_crumbs');
        $zoneCrumb = $crumbs[$zone] ?? ['name' => 'All Products', 'filter' => 'all'];

        return view('product.show', [
            'product' => $product,
            'related' => $related,
            'position' => $position,
            'catalogTotal' => count($activeIds),
            'scienceHtml' => $this->science->render($product->science, $product->name),
            'facts' => $this->facts->for($product),
            'factIcons' => $this->facts,
            'zoneCrumb' => $zoneCrumb,
        ]);
    }

    /**
     * The legacy JS product page addressed products by numeric id
     * (/product.html?id=7). Those URLs were indexed, so they resolve rather
     * than 404 — by seeded id, which is the id the old bundles used.
     */
    public function legacyRedirect(Request $request): RedirectResponse
    {
        $id = $request->query('id');

        if (! is_numeric($id)) {
            return redirect()->route('shop.index', [], 301);
        }

        $product = Product::query()->active()->find((int) $id);

        return $product instanceof Product
            ? redirect()->route('product.show', ['slug' => $product->slug], 301)
            : redirect()->route('shop.index', [], 301);
    }
}
