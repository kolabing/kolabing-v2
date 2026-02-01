<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Profile $profile */
        $profile = $this->user();

        $baseRules = [
            'phone_number' => ['nullable', 'string', 'max:20'],
        ];

        if ($profile->user_type === UserType::Business) {
            return array_merge($baseRules, $this->businessProfileRules());
        }

        return array_merge($baseRules, $this->communityProfileRules());
    }

    /**
     * Get business profile specific validation rules.
     *
     * @return array<string, mixed>
     */
    private function businessProfileRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'business_type' => ['nullable', 'string', 'max:100', Rule::exists('business_types', 'name')],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')],
            'instagram' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * Get community profile specific validation rules.
     *
     * @return array<string, mixed>
     */
    private function communityProfileRules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:2000'],
            'community_type' => ['nullable', 'string', 'max:100', Rule::exists('community_types', 'name')],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')],
            'instagram' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * Get base profile data for update.
     *
     * @return array{phone_number?: string|null}
     */
    public function getProfileData(): array
    {
        return $this->only(['phone_number']);
    }

    /**
     * Get business profile data for update.
     *
     * @return array<string, mixed>
     */
    public function getBusinessProfileData(): array
    {
        return $this->only([
            'name',
            'about',
            'business_type',
            'city_id',
            'instagram',
            'website',
        ]);
    }

    /**
     * Get community profile data for update.
     *
     * @return array<string, mixed>
     */
    public function getCommunityProfileData(): array
    {
        return $this->only([
            'name',
            'about',
            'community_type',
            'city_id',
            'instagram',
            'tiktok',
            'website',
        ]);
    }
}
