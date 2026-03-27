<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RestoreApplePurchasesRequest extends FormRequest
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
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.transaction_id' => ['required', 'string'],
            'transactions.*.original_transaction_id' => ['required', 'string'],
            'transactions.*.product_id' => ['required', 'string'],
        ];
    }
}
