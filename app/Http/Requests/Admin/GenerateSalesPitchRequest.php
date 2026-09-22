<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Starting a pitch (BE-NF-65).
 *
 * The two numeric fields are optional: left empty they fall back to
 * {@see \App\Services\SalesOutreach\RevenueEstimator}'s suggestions. Their ceilings
 * are not decoration — these figures are quoted to a real business as money, and a
 * mistyped average spend is the difference between a credible pitch and an absurd one.
 */
class GenerateSalesPitchRequest extends FormRequest
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
            'business_profile_id' => ['required', 'uuid', Rule::exists('profiles', 'id')->whereNull('deleted_at')],
            'community_profile_id' => [
                'required',
                'uuid',
                'different:business_profile_id',
                Rule::exists('profiles', 'id')->whereNull('deleted_at'),
            ],
            'locale' => ['required', 'string', Rule::in((array) config('sales_outreach.locales'))],
            'expected_attendees' => ['nullable', 'integer', 'min:1', 'max:100000'],
            // 50_000 cents = 500 of the display currency per head. Anything beyond
            // that is a typo, not a venue.
            'avg_spend_cents' => ['nullable', 'integer', 'min:1', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'community_profile_id.different' => __('Pick a community that is not the same account as the business.'),
        ];
    }
}
