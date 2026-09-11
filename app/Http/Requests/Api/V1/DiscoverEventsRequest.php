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
     * Drop a falsy `following` before validation.
     *
     * `following` relaxes the lat/lng requirement (see rules()), and
     * `required_without_all` tests PRESENCE, not truthiness — so leaving a
     * `following=0` in the input would let a caller skip both the city and the
     * coordinates and get every public event on the platform, which no caller
     * is asking for and no caller could get before. Removing the key makes
     * `following=0` behave exactly like omitting it.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('following')) {
            return;
        }

        $following = filter_var($this->input('following'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($following !== true) {
            // Both bags on purpose. This arrives as a query string, and
            // `Request::input()` unions the input source with the query bag —
            // `replace()` alone writes to the request bag and leaves the query
            // untouched, so on a GET the key survives and the rule still sees
            // it.
            $this->query->remove('following');
            $this->request->remove('following');
        }
    }

    /**
     * Geo params (lat/lng) are only required when the caller gives us no other
     * way to scope the query — a city_id, or `following` (the communities the
     * viewer follows). The endpoint supports all three.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // lat/lng remain required for the geo path, but become optional when a
            // city_id or `following` is given so either can drive discovery on
            // its own.
            'lat' => ['required_without_all:city_id,following', 'numeric', 'between:-90,90'],
            'lng' => ['required_without_all:city_id,following', 'numeric', 'between:-180,180'],
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
            // Only events hosted by a community the VIEWER follows
            // (kolabing-app#142). Needs no city: following is an explicit
            // relationship, and a city is only ever a guess at relevance.
            'following' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lat.required_without_all' => 'Latitude is required unless a city_id or following is provided.',
            'lat.between' => 'Latitude must be between -90 and 90.',
            'lng.required_without_all' => 'Longitude is required unless a city_id or following is provided.',
            'lng.between' => 'Longitude must be between -180 and 180.',
            'radius_km.min' => 'Radius must be at least 1 km.',
            'radius_km.max' => 'Radius cannot exceed 200 km.',
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit cannot exceed 50.',
            'city_id.exists' => 'The selected city does not exist.',
            'date.in' => 'The date filter must be one of: today, week, weekend, month, upcoming.',
            'type.in' => 'The community type is not valid.',
            'following.boolean' => 'The following filter must be true or false.',
        ];
    }
}
