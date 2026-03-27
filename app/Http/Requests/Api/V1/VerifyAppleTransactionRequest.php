<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyAppleTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['required', 'string'],
            'original_transaction_id' => ['required', 'string'],
            'product_id' => ['required', 'string'],
        ];
    }
}
