<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MultiKolab;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * `reason` is optional at the HTTP-shape level — whether it's actually
 * required depends on the application's current status (a reason is only
 * mandatory when withdrawing an already-accepted application), which is a
 * business rule enforced in {@see \App\Services\MultiKolabRoleApplicationService::withdraw()},
 * not here.
 */
class WithdrawMultiKolabRoleApplicationRequest extends FormRequest
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
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
