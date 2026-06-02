<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ChallengeBonusType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpsertCollaborationChallengeBonusRequest extends FormRequest
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
            'bonus_type' => ['required', Rule::in(ChallengeBonusType::values())],
            'bonus_value' => ['required', 'string', 'max:120'],
            'bonus_description' => ['nullable', 'string', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bonus_type.in' => 'Bonus type must be one of: '.implode(', ', ChallengeBonusType::values()).'.',
        ];
    }

    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
