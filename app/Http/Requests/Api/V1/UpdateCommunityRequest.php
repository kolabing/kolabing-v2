<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\JoinPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunityRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:2', 'max:100'],
            // type uses the REAL 17-slug vocabulary (CommunityOnboardingRequest::
            // COMMUNITY_TYPES), NOT the 5-value App\Enums\CommunityType placeholder.
            'type' => ['sometimes', 'string', 'exists:community_types,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'join_policy' => ['sometimes', 'string', Rule::in(JoinPolicy::values())],
            'community_profile_id' => ['nullable', 'uuid', 'exists:community_profiles,id'],
        ];
    }
}
