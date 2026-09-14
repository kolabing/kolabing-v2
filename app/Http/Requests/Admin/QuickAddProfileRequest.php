<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The listing-first quick-add: a maintainer lists a business/community found via outreach
 * (e.g. an Instagram reply) before that owner has ever touched the app. Deliberately fewer
 * required fields than StoreManagedUserRequest (full admin CRUD) — no password (generated
 * server-side, never shown), no about/website/tiktok (filled in later by the owner or a
 * follow-up edit). Attendee is excluded: quick-add is for the two listing-first profile
 * types, not the mobile-only attendee role.
 */
class QuickAddProfileRequest extends FormRequest
{
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
            'user_type' => ['required', Rule::in([UserType::Business->value, UserType::Community->value])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('profiles', 'email')],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')],
            'instagram' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}
