<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ContactMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            // The static form's select allowed an empty value, and the three
            // Where-to-Buy CTAs pre-fill it. Nullable keeps both working.
            'enquiry_type' => ['nullable', Rule::in(array_keys(ContactMessage::enquiryTypes()))],
            'message' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please tell us your name.',
            'email.required' => 'Please give us an email address to reply to.',
            'email.email' => 'That email address does not look right.',
        ];
    }
}
