<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', Rule::unique('profiles', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'user_type' => ['required', Rule::in(UserType::values())],
            'email_verified' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
        ];
    }
}
