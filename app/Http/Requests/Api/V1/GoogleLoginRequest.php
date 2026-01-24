<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class GoogleLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'user_type' => ['required', 'string', Rule::in(UserType::values())],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id_token.required' => __('validation.required', ['attribute' => 'id token']),
            'user_type.required' => __('validation.required', ['attribute' => 'user type']),
            'user_type.in' => __('validation.in', ['attribute' => 'user type']),
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
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

    /**
     * Get the user type as enum.
     */
    public function getUserType(): UserType
    {
        return UserType::from($this->validated('user_type'));
    }
}
