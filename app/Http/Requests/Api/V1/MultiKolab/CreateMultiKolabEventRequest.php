<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MultiKolab;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Draft creation/update — deliberately lenient (per the plan's Task 4: draft
 * creation must never be gated). Only `title` is required; everything else
 * can be filled in incrementally before publish, where
 * {@see \App\Services\MultiKolabEventService::publish()} applies the strict
 * validation from the frozen API contract §3/§5.
 */
class CreateMultiKolabEventRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'value_summary' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'venue_needed' => ['sometimes', 'boolean'],
            'date_mode' => ['sometimes', 'nullable', 'string', 'in:exact,range'],
            'event_date' => ['sometimes', 'nullable', 'date'],
            'date_range_start' => ['sometimes', 'nullable', 'date'],
            'date_range_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_range_start'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rsvp_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'url'],
            'eligible_account_type' => ['sometimes', 'nullable', 'string', 'in:business,community,either'],
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
