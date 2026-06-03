<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateXpLevelRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'min_xp' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_xp' => ['nullable', 'integer', 'gte:min_xp', 'max:100000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'Color must be a 6-digit hex code prefixed with #.',
            'max_xp.gte' => 'max_xp must be greater than or equal to min_xp, or null for the open-ended top level.',
        ];
    }
}
