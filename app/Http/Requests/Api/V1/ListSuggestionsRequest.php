<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Query parameters for GET /api/v1/suggestions. Authorization is not done here:
 * the list has no single row to authorize, so KolabSuggestion::forViewer() in
 * SuggestionReader is the guard, and KolabSuggestionPolicy guards the two
 * per-row routes.
 */
class ListSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'page' => (int) $this->input('page', 1),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'per_page.max' => __('The per page value may not be greater than 50.'),
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page');
    }
}
