<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmailTemplateRequest extends FormRequest
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
            // Free-text locale code, not a fixed enum — Daniel 2026-09-14: maintainers add
            // their own languages. Kept short (BCP-47-ish, e.g. "en", "es", "pt-BR") but not
            // validated against a hardcoded list.
            'locale' => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z-]+$/', Rule::unique('admin_welcome_email_templates', 'locale')],
            'label' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'intro_markdown' => ['required', 'string', 'max:5000'],
            'next_steps_markdown' => ['required', 'string', 'max:5000'],
            'footer_markdown' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
