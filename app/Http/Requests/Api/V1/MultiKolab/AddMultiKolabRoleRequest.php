<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MultiKolab;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddMultiKolabRoleRequest extends FormRequest
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
            'eligible_account_type' => ['required', 'string', 'in:business,community,either'],
            'positions_needed' => ['sometimes', 'integer', 'min:1'],
            'required' => ['sometimes', 'boolean'],
            'need' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'receive' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'compensation_type' => ['sometimes', 'nullable', 'string', 'in:paid,sponsored_in_kind,value_exchange,negotiable'],
            'requirements' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'details' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
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
