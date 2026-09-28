<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            // The hard ceiling per line. Stock is a separate question and is
            // answered by StockService, which is the only thing that may refuse
            // on availability — validation must not duplicate that check or the
            // two can disagree.
            'qty' => ['required', 'integer', 'min:1', 'max:'.(int) config('kotiva.cart.max_qty_per_line')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'That product is no longer available.',
            'qty.max' => 'You can order at most :max of one product.',
        ];
    }
}
