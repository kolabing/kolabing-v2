<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class AppleLoginRequest extends FormRequest
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
            'identity_token' => ['required', 'string'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_type' => ['sometimes', 'nullable', 'string', Rule::in(UserType::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identity_token.required' => __('validation.required', ['attribute' => 'identity token']),
            'user_type.in' => __('validation.in', ['attribute' => 'user type']),
        ];
    }

    public function getUserType(): ?UserType
    {
        $validated = $this->validated();

        if (! array_key_exists('user_type', $validated) || $validated['user_type'] === null || $validated['user_type'] === '') {
            return null;
        }

        return UserType::from($validated['user_type']);
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
