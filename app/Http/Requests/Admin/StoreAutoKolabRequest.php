<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoKolabRequest extends FormRequest
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
            'business' => ['required', 'string', 'max:255'],
            'community' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'title' => ['nullable', 'string', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business.required' => 'Pick a business.',
            'community.required' => 'Pick a community.',
            'date.after_or_equal' => 'The date must be today or later.',
        ];
    }
}
