<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\ShippingCity;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShippingDetailsRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],

            // §6.6 specifies ^(\+966|0)?5\d{8}$. Validating by attempting the
            // normalisation instead accepts that shape plus the ones people
            // genuinely type (00966…, 966…, spaced, dashed) while still
            // rejecting everything that is not a KSA mobile — and it
            // guarantees the value stored is the canonical +9665XXXXXXXX,
            // because the check and the transform are the same code.
            'phone' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || Customer::normalisePhone($value) === null) {
                        $fail('Enter a Saudi mobile number, for example 05XXXXXXXX.');
                    }
                },
            ],

            'zone_id' => ['required', 'integer', Rule::exists('shipping_zones', 'id')->where('is_active', true)],

            // The city must belong to the chosen zone. Without this pairing a
            // crafted request could ship a Riyadh-rate order to Jizan.
            'city_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $belongs = ShippingCity::query()
                        ->whereKey($value)
                        ->where('zone_id', $this->integer('zone_id'))
                        ->where('is_active', true)
                        ->exists();

                    if (! $belongs) {
                        $fail('Choose a city in the selected delivery area.');
                    }
                },
            ],

            'address_line1' => ['required', 'string', 'max:190'],
            'address_line2' => ['nullable', 'string', 'max:190'],
            'district' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'address_line1' => 'address',
            'address_line2' => 'address line 2',
            'zone_id' => 'delivery area',
            'city_id' => 'city',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zone_id.required' => 'Choose a delivery area.',
            'city_id.required' => 'Choose a city.',
        ];
    }

    /**
     * The canonical phone, computed once validation has passed.
     */
    public function normalisedPhone(): string
    {
        return (string) Customer::normalisePhone((string) $this->input('phone'));
    }
}
