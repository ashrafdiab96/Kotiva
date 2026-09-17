<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlaceOrderRequest extends FormRequest
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
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],

            // Explicit consent, and it must be ticked — 'accepted' rejects
            // "0"/"false"/absent rather than quietly treating them as agreement.
            'terms' => ['accepted'],

            // Guards against a double submit placing two orders: the token is
            // issued with the payment form and invalidated by the first
            // successful placement.
            'checkout_token' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please accept the terms to place your order.',
            'payment_method.required' => 'Choose a payment method.',
        ];
    }
}
