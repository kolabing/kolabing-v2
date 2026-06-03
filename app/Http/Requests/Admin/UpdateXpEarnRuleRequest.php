<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateXpEarnRuleRequest extends FormRequest
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
            'points' => ['required', 'integer', 'between:0,10000'],
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Checkboxes don't post when unchecked; treat missing as false.
        $this->merge([
            'is_active' => filter_var($this->input('is_active', false), FILTER_VALIDATE_BOOL),
        ]);
    }
}
