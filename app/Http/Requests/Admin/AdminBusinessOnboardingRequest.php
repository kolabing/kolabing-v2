<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Models\OfferOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The admin panel's business onboarding, validated by the app's own rules.
 *
 * Extends {@see BusinessOnboardingRequest} instead of restating it. The whole point
 * of this surface is that a maintainer-onboarded business is indistinguishable from
 * a self-onboarded one, and two copies of a rule set is precisely how the two drift:
 * quick-add (BE-NF-54) validated its own thinner shape, so it produced profiles the
 * app's own onboarding would have rejected — no `categories`, no `has_venue`, no
 * `venue_type`/`capacity` on the venue it nonetheless stored.
 *
 * Only what the app cannot supply is added here: the account's email address. The
 * app already has an authenticated profile by the time it reaches onboarding.
 */
final class AdminBusinessOnboardingRequest extends BusinessOnboardingRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('profiles', 'email')->whereNull('deleted_at'),
            ],
            /*
             * `opening_hours` is a top-level rule even though the app models hours
             * under the venue, because `business_profiles.opening_hours` is a real
             * column (BE-NF-62) and the public listing page reads *that*, not the
             * nested copy — `PublicProfilePageController::openingHours()`. Without
             * this rule `validated()` drops the field, `upsertDetailProfile()`
             * writes null, and a fully-onboarded business shows no hours on its
             * public page while a merely quick-added one does. The nesting in
             * prepareForValidation() stays: both destinations are wanted.
             */
            'opening_hours' => ['nullable', 'array', 'max:7'],
            'opening_hours.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Reassemble the app's payload shape out of what an HTML form can post.
     *
     * Three transforms, each closing a real gap between the two clients:
     *
     * 1. `primary_venue` arrives as a JSON string, because the shared Places import
     *    partial writes the whole Google Places payload into one hidden input.
     * 2. `venue[...]` holds the fields a human confirms. Both are
     *    `required_with:primary_venue`; Google supplies one of them.
     *    `GooglePlacesService::mapVenueType()` derives `venue_type` from the place's
     *    `types`, but `capacity` is returned hardcoded null (`LookupController`), so
     *    only a human can ever fill it. The overlay skips empty values, which is what
     *    lets an imported `venue_type` survive while a maintainer's correction still
     *    wins over the import.
     * 3. Places returns venue photos as objects (`{resource_name: …}`) while the
     *    request validates `primary_venue.photos.*` as strings. Flattening here is
     *    what keeps rule 1 of this class true: the API's rules stay untouched.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeDecodedJson('primary_venue');
        $this->mergeDecodedJson('opening_hours');

        $venue = $this->input('primary_venue');
        $venue = is_array($venue) ? $venue : [];

        // A venue imported from Places carries photo objects; the rules want strings.
        if (isset($venue['photos']) && is_array($venue['photos'])) {
            $venue['photos'] = array_values(array_filter(array_map(
                static fn ($photo) => is_array($photo)
                    ? ($photo['resource_name'] ?? $photo['url'] ?? null)
                    : (is_string($photo) ? $photo : null),
                $venue['photos'],
            )));
        }

        // Hours are posted separately by the shared partial; the app models them
        // under the venue, which is where they have to end up.
        $hours = $this->input('opening_hours');
        if (is_array($hours) && $hours !== [] && empty($venue['opening_hours'])) {
            $venue['opening_hours'] = $hours;
        }

        foreach ($this->arrayInput('venue') as $key => $value) {
            if ($value !== null && $value !== '') {
                $venue[$key] = $value;
            }
        }

        // No venue means the product path, and an empty array would still trip
        // `required_with:primary_venue` on venue_type/capacity.
        $hasVenue = $this->has('has_venue') ? $this->boolean('has_venue') : true;

        $this->merge([
            'primary_venue' => ($hasVenue && $venue !== []) ? $venue : null,
        ]);

        /*
         * Fall back to the imported venue's city when neither city field is filled.
         *
         * `city_id`/`city_name` are a `required_without` pair, and a place in a
         * locality with no `cities` row — the exact case the "City Name (unlisted)"
         * input exists for — imports with `city_id = null`. Without this the form
         * rejects a submission whose venue plainly says which city it is in, and the
         * maintainer is told "the city field is required" twice with a filled-in
         * address on screen.
         */
        if (blank($this->input('city_id')) && blank($this->input('city_name')) && filled($venue['city'] ?? null)) {
            $this->merge(['city_name' => $venue['city']]);
        }

        $this->composeOffering();

        parent::prepareForValidation();
    }

    /**
     * Turn the picked offering options into the free-text `offering` the API stores.
     *
     * The form asks with the same multi-select chips the app uses, sourced from the
     * same `/lookup/offerings` taxonomy — but `offering` is a free-text column, and
     * `OnboardingService::provisionBusinessAutoOffer()` uses it as the auto-offer's
     * description when About is empty. Writing raw slugs there would publish
     * "venue,food_drink,discount" to communities as the pitch.
     *
     * So the slugs become their human labels, straight from `offer_options` — the
     * same words the chips showed — and the maintainer's own free text is appended
     * after them. Composed here rather than in JS: the label lookup belongs on the
     * side that owns the taxonomy, and a hidden field assembled by the browser is
     * one view edit away from silently shipping slugs.
     */
    private function composeOffering(): void
    {
        $slugs = array_values(array_filter(
            (array) $this->input('offering_options', []),
            static fn (mixed $slug): bool => is_string($slug) && $slug !== '',
        ));

        $detail = trim((string) $this->input('offering_detail', ''));

        if ($slugs === [] && $detail === '') {
            return;
        }

        $labels = OfferOption::query()
            ->where('kind', OfferOption::KIND_OFFERING)
            ->whereIn('slug', $slugs)
            ->pluck('name', 'slug');

        // Keep the maintainer's click order, and fall back to a readable form of
        // the slug if the option was deactivated between render and submit.
        $chosen = array_map(
            static fn (string $slug): string => (string) ($labels[$slug] ?? Str::headline($slug)),
            $slugs,
        );

        $offering = implode(', ', $chosen);

        if ($detail !== '') {
            $offering = $offering === '' ? $detail : $offering.' — '.$detail;
        }

        $this->merge(['offering' => $offering]);
    }

    /**
     * Decode a field the form posts as a JSON string into the array the rules expect.
     */
    private function mergeDecodedJson(string $key): void
    {
        $value = $this->input($key);

        if (! is_string($value) || $value === '') {
            return;
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            $this->merge([$key => $decoded]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayInput(string $key): array
    {
        $value = $this->input($key);

        return is_array($value) ? $value : [];
    }

    /**
     * The parent answers a 422 JSON body, which on an HTML form renders as a blank
     * page with the maintainer's typing gone. Back to Laravel's default — but still
     * `never`, because the parent's signature cannot be widened.
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw (new ValidationException($validator))
            ->errorBag($this->errorBag)
            ->redirectTo($this->getRedirectUrl());
    }
}
