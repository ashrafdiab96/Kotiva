<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
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
            'slug' => $this->slug,
            'name' => $this->name,
            'price' => (float) $this->price,
            'currency' => config('kotiva.currency.code'),
            'image' => $this->imageUrl(),
            'url' => route('product.show', ['slug' => $this->slug]),
            'filter_tags' => $this->filter_tags ?? [],
            'stock' => $this->stock_qty,
            'in_stock' => $this->isInStock(),
        ];
    }
}
