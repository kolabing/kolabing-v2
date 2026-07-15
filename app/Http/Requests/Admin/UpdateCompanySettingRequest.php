<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingRequest extends FormRequest
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
            'legal_name' => ['required', 'string', 'max:255'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'refund_policy' => ['nullable', 'string', 'max:2000'],
            'privacy_email' => ['required', 'email', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],
            'terms_version' => ['required', 'string', 'max:50'],
            'terms_effective_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legal_name.required' => 'The registered company name is required — it fills [COMPANY NAME] on the legal pages.',
            'terms_version.required' => 'A version is required. Change it (e.g. bump the date) to re-prompt app users for consent after a material change.',
        ];
    }
}
