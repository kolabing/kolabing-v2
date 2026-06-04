<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityType;
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
            'type' => ['sometimes', 'string', Rule::in(CommunityType::values())],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'join_policy' => ['sometimes', 'string', Rule::in(JoinPolicy::values())],
            'community_profile_id' => ['nullable', 'uuid', 'exists:community_profiles,id'],
        ];
    }
}
