<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Product listing.
 *
 * Filtering stays client-side on `data-tags`, exactly as the static site did —
 * the pills are instant, and a visitor with JS off still gets the full grid.
 * Sorting is server-side via ?sort= so a sorted listing is linkable and
 * crawlable, and each sort link carries the active filter through.
 */
final class ShopController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        /** @var array<string, string> $aliases */
        $aliases = config('kotiva.shop.filter_aliases');

        // Old indexed URLs used ?filter=sunscreen; the catalog tag is "spf".
        // Redirect rather than silently accept, so there is one canonical URL
        // and the client-side pill script finds a pill matching the param.
        $requested = (string) $request->query('filter', '');
        if ($requested !== '' && isset($aliases[$requested])) {
            return redirect()->route('shop.index', array_filter([
                'filter' => $aliases[$requested],
                'sort' => $request->query('sort'),
            ]), 301);
        }

        /** @var array<string, string> $sorts */
        $sorts = config('kotiva.shop.sorts');

        $sort = (string) $request->query('sort', (string) config('kotiva.shop.default_sort'));
        if (! array_key_exists($sort, $sorts)) {
            $sort = (string) config('kotiva.shop.default_sort');
        }

        $products = $this->applySort(
            Product::query()->active()->with('category'),
            $sort
        )->get();

        /** @var array<string, string> $filters */
        $filters = config('kotiva.shop.filters');

        // Counts come from the same collection that renders, so a pill can
        // never promise a number the grid does not contain.
        $counts = [];
        foreach ($filters as $tag => $label) {
            $counts[$tag] = $tag === 'all'
                ? $products->count()
                : $products->filter(
                    fn (Product $p): bool => in_array($tag, $p->filter_tags ?? [], true)
                )->count();
        }

        return view('shop.index', [
            'products' => $products,
            'filters' => $filters,
            'counts' => $counts,
            'sorts' => $sorts,
            'activeSort' => $sort,
            'activeFilter' => $requested === '' ? 'all' : $requested,
        ]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'name_asc' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            // §6.1's default: featured first, then the curated sort_order.
            // id is the final tie-break so the order is always deterministic.
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
        };
    }
}
