<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRewardRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'total_quantity' => ['sometimes', 'integer', 'min:1'],
            'probability' => ['sometimes', 'numeric', 'min:0.0001', 'max:1'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Reward name must be at least 2 characters.',
            'name.max' => 'Reward name cannot exceed 150 characters.',
            'total_quantity.min' => 'Total quantity must be at least 1.',
            'probability.min' => 'Probability must be at least 0.0001.',
            'probability.max' => 'Probability cannot exceed 1.',
            'expires_at.after' => 'Expiry date must be in the future.',
        ];
    }
}
