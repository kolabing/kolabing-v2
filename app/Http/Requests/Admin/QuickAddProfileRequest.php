<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Http\Requests\Admin\Concerns\DecodesPrimaryVenue;
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
            'user_type' => ['required', Rule::in([UserType::Business->value, UserType::Community->value])],
            'name' => ['required', 'string', 'max:255'],
            // whereNull('deleted_at'): profiles is soft-deleted (SoftDeletes), and Rule::unique
            // does not apply Eloquent's soft-delete scope automatically -- without this, a
            // deleted profile's email is permanently unusable forever, and the block is
            // invisible everywhere else (soft-deleted rows don't show in the admin list),
            // which reads as "the email is taken" for an email nothing shows as taken.
            // Caught live 2026-09-14/15 (Exploradores de Café: several delete attempts left
            // the profile soft-deleted, silently blocking every recreate attempt after).
            'email' => ['required', 'email', 'max:255', Rule::unique('profiles', 'email')->whereNull('deleted_at')],
            'city_id' => ['nullable', 'uuid', Rule::exists('cities', 'id')],
            'instagram' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'about' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'profile_photo' => ['nullable', 'url', 'max:2048'],
            'offer_photos' => ['nullable', 'array', 'max:10'],
            'offer_photos.*' => ['url', 'max:2048'],
            'primary_venue' => ['nullable', 'array'],
            'business_type' => ['nullable', 'string', Rule::exists('business_types', 'slug')],
        ];
    }
}
