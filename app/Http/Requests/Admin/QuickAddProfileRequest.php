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
 * server-side, never shown). Attendee is excluded: quick-add is for the two listing-first
 * profile types, not the mobile-only attendee role.
 *
 * `about`/`website`/`phone_number`/`profile_photo`/`offer_photos`/`primary_venue` are optional
 * and, on the admin form, populated by the Google Places import the maintainer can pull up
 * for a business (reuses the same `importablePlaceDetails()` shape the mobile onboarding
 * flow already sends via `GET /api/v1/places/details` — see GooglePlacesService) — still
 * fine to leave blank and let the owner fill in the rest themselves later.
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
            'about' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'url', 'max:2048'],
            'offer_photos' => ['nullable', 'array', 'max:10'],
            'offer_photos.*' => ['url', 'max:2048'],
            'primary_venue' => ['nullable', 'array'],
        ];
    }
}
