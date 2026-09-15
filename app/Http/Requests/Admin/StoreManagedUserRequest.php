<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Http\Requests\Admin\Concerns\DecodesPrimaryVenue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManagedUserRequest extends FormRequest
{
    use DecodesPrimaryVenue;

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
            // whereNull('deleted_at'): see QuickAddProfileRequest -- a soft-deleted profile's
            // email must not stay permanently blocked, and Rule::unique doesn't apply the
            // SoftDeletes scope on its own.
            'email' => ['required', 'email', 'max:255', Rule::unique('profiles', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'user_type' => ['required', Rule::in(UserType::values())],
            'email_verified' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')],
            'profile_photo' => ['nullable', 'url', 'max:2048'],
            'offer_photos' => ['nullable', 'array', 'max:10'],
            'offer_photos.*' => ['url', 'max:2048'],
            'primary_venue' => ['nullable', 'array'],
            'business_type' => ['nullable', 'string', Rule::exists('business_types', 'slug')],
            'opening_hours' => ['nullable', 'array', 'max:7'],
            'opening_hours.*' => ['string', 'max:255'],
        ];
    }
}
