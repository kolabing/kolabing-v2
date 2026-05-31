<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\KolabStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKolabRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:5000'],
            'preferred_city' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(KolabStatus::values())],
            'offer_headline' => ['nullable', 'string', 'max:255'],
            'base_offer' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
