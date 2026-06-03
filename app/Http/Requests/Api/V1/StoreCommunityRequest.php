<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['required', 'string', Rule::in(CommunityType::values())],
            'description' => ['nullable', 'string', 'max:2000'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'join_policy' => ['nullable', 'string', Rule::in(JoinPolicy::values())],
            'community_profile_id' => ['nullable', 'uuid', 'exists:community_profiles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A community name is required.',
            'name.min' => 'The community name must be at least 2 characters.',
            'type.in' => 'The community type is not valid.',
            'join_policy.in' => 'The join policy must be open or invite_only.',
        ];
    }
}
