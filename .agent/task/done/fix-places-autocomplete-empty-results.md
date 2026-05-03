# Fix: places-autocomplete-empty-results

## Status
- Created: 2026-05-03 14:00
- Started: 2026-05-03 14:00
- Completed: 2026-05-03 14:20

## Issue Type
- [x] Backend Logic Bug
- [x] Best Practice Issue

## Affected Area
- [x] Backend
- [x] API

## Problem Statement
`GET /api/v1/places/autocomplete?query=coffee` returns an empty `data` array instead of suggesting Barcelona coffee shops, coworking spaces, restaurants, etc. Real-world example: searching `Honest Greens` should list every Honest Greens location in Barcelona; instead it returns nothing.

## Root Cause
`App\Services\GooglePlacesService::autocomplete()` posts to `https://places.googleapis.com/v1/places:autocomplete` with `'includedPrimaryTypes' => ['establishment']`.

In the Places API (New), `includedPrimaryTypes` only accepts:
- Place types from **Table A** (e.g., `restaurant`, `cafe`, `bakery`, `gym`, `store`), or
- The collections `(regions)` / `(cities)`.

`establishment` is a **Table B** type and is rejected as an invalid filter, so Google returns a `400 INVALID_ARGUMENT`. The service catches this with `if (! $response->successful()) return []` and silently returns an empty list, which the controller passes through to the client.

There is also no `locationBias` toward Barcelona, so results are not focused on the target market.

## Proposed Solution
1. Remove the invalid `includedPrimaryTypes => ['establishment']` filter (let Google match name + all relevant categories).
2. Add a `locationBias` circle centered on Barcelona (lat `41.3874`, lng `2.1686`, radius `50_000` m) so coffee shops, Honest Greens locations, coworking, etc. inside the Barcelona metro area are surfaced first.
3. Keep `includedRegionCodes => ['es']` and `languageCode => 'es'`.
4. Update the existing tests in `tests/Feature/Api/V1/LookupControllerTest.php` so the asserted request payload matches the new shape.

## Implementation Details
- `app/Services/GooglePlacesService.php`: removed `includedPrimaryTypes => ['establishment']` and added a Barcelona-centered `locationBias.circle` (lat `41.3874`, lng `2.1686`, radius `50_000` m). Region (`es`) and language (`es`) preserved.
- `tests/Feature/Api/V1/LookupControllerTest.php`: added `test_places_autocomplete_request_payload_biases_to_barcelona_without_primary_type_filter`, which uses `Http::assertSent` to confirm `includedPrimaryTypes` is not present and the Barcelona `locationBias.circle` is sent verbatim.

## Validation
- `php artisan test --compact --filter=places_autocomplete tests/Feature/Api/V1/LookupControllerTest.php` → 3 passed (14 assertions).
- `php artisan test --compact tests/Feature/Api/V1/LookupControllerTest.php` → 15 passed (142 assertions), no regressions.
- `vendor/bin/pint --dirty` → pass.

## Files Affected
- `app/Services/GooglePlacesService.php`
- `tests/Feature/Api/V1/LookupControllerTest.php`

## Assigned Agents
- [x] @backend-developer (root cause + fix)

## Follow-up Recommendations
- Log Google Places API failures (currently silently swallowed) so future regressions surface quickly.
- Consider making the bias center configurable per `City` once the platform expands beyond Barcelona.
