<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityMemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateCommunityMembersRequest extends FormRequest
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
            'member_ids' => ['required', 'array', 'min:1', 'max:100'],
            'member_ids.*' => ['required', 'uuid'],
            'tier_id' => ['nullable', 'uuid', 'exists:community_tiers,id'],
            'can_manage' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(CommunityMemberStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_ids.max' => 'You can update at most 100 members at a time.',
        ];
    }
}
