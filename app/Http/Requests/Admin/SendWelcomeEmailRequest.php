<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\AdminWelcomeEmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendWelcomeEmailRequest extends FormRequest
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
            'locale' => [
                'required',
                'string',
                Rule::exists(AdminWelcomeEmailTemplate::class, 'locale')->where('is_active', true),
            ],
        ];
    }
}
