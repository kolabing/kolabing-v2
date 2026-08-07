<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\NewsletterAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsletterSubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'audience' => ['nullable', Rule::in(NewsletterAudience::values())],
            // Honeypot: real users never fill this hidden field. A non-empty
            // value marks the submission as a bot and is rejected.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
