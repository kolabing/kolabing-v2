<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use Illuminate\Contracts\Validation\Validator;
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
        ];
    }

    /**
     * Reassemble the app's payload shape out of what an HTML form can post.
     *
     * Three transforms, each closing a real gap between the two clients:
     *
     * 1. `primary_venue` arrives as a JSON string, because the shared Places import
     *    partial writes the whole Google Places payload into one hidden input.
     * 2. `venue[...]` holds the fields a human must confirm — `venue_type` and
     *    `capacity` are `required_with:primary_venue` and Google supplies neither.
     *    They are overlaid on top of the imported venue, not merged under it, so a
     *    maintainer's correction always wins over the import.
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

        parent::prepareForValidation();
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
