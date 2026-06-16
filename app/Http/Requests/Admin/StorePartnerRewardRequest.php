<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRewardRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'partner' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'cost_xp' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
