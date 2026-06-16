<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\CommunityTypeVocabulary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscoverEventsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Geo params (lat/lng) are only required when no city_id is supplied — the
     * endpoint supports both a city-scoped query and the legacy radius query.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // lat/lng remain required for the geo path, but become optional when a
            // city_id is given so the city filter can drive discovery on its own.
            'lat' => ['required_without:city_id', 'numeric', 'between:-90,90'],
            'lng' => ['required_without:city_id', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'min:1', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            // Filter to events whose host community sits in this city.
            'city_id' => ['sometimes', 'uuid', 'exists:cities,id'],
            // today | week (Mon-Sun) | weekend (Sat-Sun) | month (calendar month)
            // | upcoming (default, all future).
            'date' => ['sometimes', 'string', Rule::in(['today', 'week', 'weekend', 'month', 'upcoming'])],
            // A host community_type slug from the canonical 17-slug vocabulary
            // (community_types table, falling back to the COMMUNITY_TYPES constant);
            // NEVER the 5-value App\Enums\CommunityType placeholder.
            'type' => ['sometimes', 'string', Rule::in(CommunityTypeVocabulary::slugs())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lat.required_without' => 'Latitude is required unless a city_id is provided.',
            'lat.between' => 'Latitude must be between -90 and 90.',
            'lng.required_without' => 'Longitude is required unless a city_id is provided.',
            'lng.between' => 'Longitude must be between -180 and 180.',
            'radius_km.min' => 'Radius must be at least 1 km.',
            'radius_km.max' => 'Radius cannot exceed 200 km.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 50.',
            'city_id.exists' => 'The selected city does not exist.',
            'date.in' => 'The date filter must be one of: today, week, weekend, month, upcoming.',
            'type.in' => 'The community type is not valid.',
        ];
    }
}
