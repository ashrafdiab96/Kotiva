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
 * bundle from a script tag and now reads the same shape over HTTP. Since the
 * Salla handoff each row carries its SKU and Salla product URL, and no price
 * or stock — Salla owns both.
 */
final class ProductApiController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->active()
            ->orderBy('id')
            ->get([
                'id', 'sku', 'slug', 'name', 'image', 'salla_url',
                'filter_tags', 'category_id',
                'skin_type', 'concern',
            ]);

        return ProductResource::collection($products);
    }
}
