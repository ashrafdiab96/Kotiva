<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Feeds the Routine Finder.
 *
 * The quiz engine itself is untouched; it previously read the KOTIVA_PRODUCTS
 * bundle from a script tag and now reads the same shape over HTTP, with live
 * stock added so a routine can say when something is sold out.
 */
final class ProductApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->active()
            ->orderBy('id')
            ->get([
                'id', 'slug', 'name', 'price', 'image',
                'filter_tags', 'stock_qty', 'category_id',
                'skin_type', 'concern',
            ]);

        return ProductResource::collection($products);
    }
}
