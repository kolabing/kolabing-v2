<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ValidatesReturnUrl;
use Illuminate\Foundation\Http\FormRequest;

class BillingPortalRequest extends FormRequest
{
    use ValidatesReturnUrl;

    /**
     * The route is behind auth:sanctum; the business-only check is in the controller.
     */
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
            'return_url' => ['required', 'string', 'max:2048', $this->returnUrlRule()],
        ];
    }
}
