# Google Places Onboarding Import Design

**Goal:** Let business onboarding import a selected venue from Google Places, prefill the onboarding form without saving anything yet, and rehost only the chosen Google photos when the business taps the final Save button.

## Scope

- Add a Google Places details import endpoint that accepts a `place_id`.
- Return an app-shaped prefill payload for the business onboarding form.
- Keep Google Places API usage server-side only.
- Add a backend photo proxy so the mobile app can preview imported Google photos without holding the API key.
- Extend final onboarding save so selected Google photo resource names are rehosted to Kolabing storage.

## Flow

1. Mobile uses the existing Google venue picker and gets a `place_id`.
2. Mobile calls the backend import endpoint with that `place_id`.
3. Backend fetches Place Details from Google Places v1 using an explicit field mask.
4. Backend returns form-prefill data plus previewable imported photo entries.
5. Mobile lets the business edit fields, remove photos, reorder photos, and add its own uploads.
6. Mobile sends the normal onboarding save request, with the kept Google photo resource names included in `primary_venue.photos` in the chosen order.
7. Backend rehosts only those kept Google photos during the final save.

## Data Contract

- Import response fills business-level fields:
  - `name`
  - `about`
  - `business_type`
  - `categories`
  - `city_id`
  - `city_name`
  - `phone_number`
  - `website`
- Import response fills venue-level fields inside `primary_venue`:
  - `name`
  - `venue_type`
  - `place_id`
  - `formatted_address`
  - `city`
  - `country`
  - `latitude`
  - `longitude`
  - `phone_number`
  - `website`
  - `opening_hours`
  - `description`
  - `price_level`
  - `rating`
  - `user_ratings_total`
  - `google_place_types`
  - `photos`
- `primary_venue.capacity` remains empty because Google does not provide it. The mobile form still collects it before the final save.
- Imported `photos` are returned as objects for preview. Each entry includes:
  - `resource_name`
  - `preview_url`
  - `author_attributions`
  - `width`
  - `height`

## Mapping Rules

- Google `displayName.text` maps to business `name` and venue `primary_venue.name`.
- Google `editorialSummary.text` maps to business `about` and venue `primary_venue.description`.
- Google `websiteUri` maps to business `website` and venue `primary_venue.website`.
- Google `internationalPhoneNumber` falls back to `nationalPhoneNumber` and maps to top-level `phone_number` plus venue `primary_venue.phone_number`.
- Google `types[]` map to Kolabing business categories and venue type using a best-effort server-side mapper.
- Google `regularOpeningHours.weekdayDescriptions` falls back to `currentOpeningHours.weekdayDescriptions`.
- Google `photos[].name` is the canonical Google photo resource name that mobile sends back on final save if the photo is kept.

## Error Handling

- If Place Details fails, times out, or the API key is unavailable, the import endpoint returns `503` with a user-safe message that mobile can surface in a toast:
  - `We couldn't import from Google, please fill in manually.`
- Final onboarding save still behaves like today for manually entered fields and manual uploads.
- If one imported photo fails to rehost during final save, the save should continue with the remaining valid photos.

## Persistence

- Keep storing the normalized venue snapshot in `business_profiles.primary_venue`.
- Extend that snapshot with imported metadata fields:
  - `phone_number`
  - `website`
  - `opening_hours`
  - `description`
  - `price_level`
  - `rating`
  - `user_ratings_total`
  - `google_place_types`
- Persist rehosted photo URLs in `primary_venue.photos`.
- Do not persist Google preview URLs or temporary Google photo URIs.

## Testing

- Feature tests cover the new import endpoint success and failure cases.
- Feature tests cover the Google photo proxy response.
- Feature tests cover final onboarding save with mixed photo inputs, including Google photo resource names and user-uploaded base64 images.
