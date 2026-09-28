<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Product listing.
 *
 * Filtering stays client-side on `data-tags` — the pills are instant, and a
 * visitor with JS off still gets the full grid. Each pill is a product category
 * (keyed by its slug) and each card is tagged with its own category's slug, so
 * the Category set in the admin is what decides where a product is listed.
 * Sorting is server-side via ?sort= so a sorted listing is linkable and
 * crawlable, and each sort link carries the active filter through.
 */
final class ShopController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        /** @var array<string, string> $aliases */
        $aliases = config('kotiva.shop.category_aliases');

        // Old indexed URLs used tag names (?filter=sunscreen, ?filter=treatments)
        // that differ from the category slugs.
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

        // Counts come from the same collection that renders, so a pill can
        // never promise a number the grid does not contain. A category with no
        // active products gets no pill rather than an empty "(0)" one.
        $perCategory = $products->countBy('category_id');

        $filters = ['all' => 'All'];
        $counts = ['all' => $products->count()];

        foreach (Category::query()->active()->orderBy('sort_order')->orderBy('name')->get() as $category) {
            $count = (int) $perCategory->get($category->getKey(), 0);
            if ($count > 0) {
                $filters[$category->slug] = $category->name;
                $counts[$category->slug] = $count;
            }
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
            // Sorted on the price the listing DISPLAYS. Sorting on the
            // inclusive price would be the same order today, but only because
            // every product happens to share one VAT rate — the moment a
            // product's two prices disagree, "Price: Low to High" would put
            // the cards in an order the visible numbers contradict.
            'price_asc' => $query->orderBy('price_excl_vat')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price_excl_vat')->orderBy('name'),
            'name_asc' => $query->orderBy('name'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            // §6.1's default: featured first, then the curated sort_order.
            // id is the final tie-break so the order is always deterministic.
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
        };
    }
}
