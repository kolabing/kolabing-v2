# Fix: application-message-min-length

## Status
- Created: 2026-05-03 20:20
- Started: 2026-05-03 20:20
- Completed: 2026-05-03 20:35

## Issue Type
- [x] Backend Logic Bug (over-strict validation)

## Affected Area
- [x] Backend
- [x] API

## Problem Statement
Submitting an application with a short `message` (e.g. `"tesdfsdfsd"` — 10 chars) returns `HTTP 422 Validation failed` with `"The message field must be at least 20 characters."`. Product wants no minimum length on the application message.

## Root Cause
`app/Http/Requests/Api/V1/ApplyToOpportunityRequest.php:29` declares `'message' => ['required', 'string', 'min:20', 'max:2000']`. The `min:20` rule (and its custom error message at line 43) blocks shorter messages.

## Proposed Solution
Drop `'min:20'` from the `message` rule and remove the now-dead `message.min` custom error message. Keep `required`, `string`, and `max:2000` so empties / non-strings / oversized payloads still fail cleanly.

## Implementation Details
- `app/Http/Requests/Api/V1/ApplyToOpportunityRequest.php`:
  - rule: `['required', 'string', 'min:20', 'max:2000']` → `['required', 'string', 'max:2000']`
  - messages: delete `message.min` entry
- Add a test asserting a short (1–19 char) message is accepted by the apply endpoint.

## Files Affected
- `app/Http/Requests/Api/V1/ApplyToOpportunityRequest.php` — `min:20` removed from `message`; `message.min` custom error removed.
- `tests/Feature/Api/V1/ApplicationCreateTest.php` — new file, covers: short message accepted (11 chars), empty message rejected, oversized message rejected.

## Validation
- `php artisan test --compact tests/Feature/Api/V1/ApplicationCreateTest.php` → 3 passed (10 assertions).
- `php artisan test --compact` (full suite) → 618 passed (3119 assertions, +3 vs previous 615), no regressions.
- `vendor/bin/pint --dirty` → clean.

## Assigned Agents
- [x] @backend-developer

## Follow-up Recommendations
- The same `min:20` rule still applies to `availability`. The kolab card displays values like `"Apr 1 - May 31"` (14 chars) — if Flutter ever forwards that string verbatim it will fail. Consider dropping `min:20` from `availability` in a separate ticket.
