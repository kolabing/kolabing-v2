<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\KolabingTeamOfferKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKolabingTeamOfferRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(KolabingTeamOfferKind::class)],
            // A profile id or an email.
            'recipient' => ['required', 'string', 'max:255'],
            'kolab' => ['nullable', 'uuid', 'exists:kolabs,id', 'required_if:kind,community_apply'],
            // A community profile id or email (business_propose only).
            'community' => ['nullable', 'string', 'max:255', 'required_if:kind,business_propose'],
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'what_business_gets' => ['nullable', 'string', 'max:500'],
            'what_community_gets' => ['nullable', 'string', 'max:500'],
            'proposed_date' => ['nullable', 'date_format:Y-m-d'],
            'test_send' => ['nullable', 'boolean'],
        ];
    }
}
