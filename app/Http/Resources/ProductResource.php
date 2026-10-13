<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Support\Salla;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'name' => $this->name,
            /*
             | No price and no stock. Salla is the price and stock authority
             | now, and this content site shows neither: a figure here could
             | only ever disagree with the one the shopper is charged.
             */
            'image' => $this->imageUrl(),
            // The product's own Salla page, or the store root until linked.
            'url' => Salla::productUrl($this->resource),
            'filter_tags' => $this->filter_tags ?? [],
        ];
    }
}
