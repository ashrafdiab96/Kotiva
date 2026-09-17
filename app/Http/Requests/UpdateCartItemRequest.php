<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCartItemRequest extends FormRequest
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
            // Zero is allowed and means "remove this line" — the quantity
            // stepper reaching 0 is the same intent as pressing remove.
            'qty' => ['required', 'integer', 'min:0', 'max:'.(int) config('kotiva.cart.max_qty_per_line')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qty.max' => 'You can order at most :max of one product.',
        ];
    }
}
