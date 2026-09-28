<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class NewsletterRequest extends FormRequest
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
            /*
             | Deliberately NOT unique:newsletter_subscribers. Someone who is
             | already on the list should be told "you're subscribed", not
             | shown a validation error telling them their own email is taken.
             | The controller handles the already-subscribed case.
             */
            'email' => ['required', 'email:rfc', 'max:190'],
            'source' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'That email address does not look right.',
        ];
    }
}
