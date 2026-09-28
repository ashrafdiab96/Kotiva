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
            /*
             | `price` is the VAT-EXCLUSIVE figure, because that is what the
             | Routine Finder renders next to a product and what the shop shows
             | beside it — the quiz result and the listing must not disagree.
             |
             | Both prices are sent so a consumer never has to apply a rate
             | itself, and `price_vat` names which one `price` is rather than
             | leaving it to be inferred.
             */
            'price' => (float) $this->price_excl_vat,
            'price_vat' => 'excluded',
            'price_excl_vat' => (float) $this->price_excl_vat,
            'price_incl_vat' => (float) $this->price_incl_vat,
            'currency' => config('kotiva.currency.code'),
            'image' => $this->imageUrl(),
            'url' => route('product.show', ['slug' => $this->slug]),
            'filter_tags' => $this->filter_tags ?? [],
            'stock' => $this->stock_qty,
            'in_stock' => $this->isInStock(),
        ];
    }
}
