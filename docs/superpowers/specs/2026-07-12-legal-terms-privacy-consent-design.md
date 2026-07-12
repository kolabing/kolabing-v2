# Legal Pages (Terms & Privacy) + Mobile Consent Tracking — Design

**Date:** 2026-07-12
**Status:** Approved (design)
**Owner:** Volkan

## Problem

The marketing site needs real **Terms of Service** and **Privacy Policy** pages
(currently thin placeholders, footer links are dead `href="#"`). The Privacy
Policy must accurately reflect the permissions/data the mobile app actually uses.
The mobile app must obtain and record user consent in a provable way (App Store /
Google Play requirement + GDPR/LOPDGDD, Spain).

## What the app actually collects (basis for the Privacy Policy)

Derived from the models/services, not invented:

- **Identity / account:** Google & Apple sign-in — name, email, `email_verified`,
  avatar; optional password (reset flow). Handle, city, interests, `preferred_locale`.
- **Photos (camera + photo library):** profile photos, business `offer_photos`,
  `ProfileGalleryPhoto`, `EventPhoto`; QR scanning for check-in / reward redeem.
- **Location:** `Event.location_lat/location_lng` — event discovery (Haversine) &
  check-in.
- **Push notifications:** FCM `device_token` (`me/device-token`), `NotificationPreference`.
- **Messaging:** `ChatThread` / `ChatMessage`.
- **Payments:** Stripe business subscriptions (`BusinessSubscription`) — card data is
  handled by Stripe, not stored by us.
- **Gamification / activity:** points, wallet, referrals, badges, check-ins.
- **Analytics:** already have `analytics_opt_out` on `profiles` (PostHog).
- **Account deletion:** `profiles.deleted_at` (soft delete) — right to erasure.

Third-party processors disclosed: Google, Apple, Stripe, FCM/APNs, hosting/analytics.

## Decisions (from user)

- Legal entity details → **clearly-marked placeholders** (`[COMPANY NAME]`,
  `[ADDRESS]`, `[privacy@kolabing.com]`) to be filled in one pass later.
- Language → **English + Spanish** (both).
- Mobile consent → **document the flow + implement backend consent tracking**.

## Design

### 1. Web pages (EN + ES)

- Keep the existing `Route::view` + `<x-layouts.marketing-page>` convention and
  Tailwind/`prose` styling. Replace placeholder content with full, plain-language,
  GDPR-aware copy authored via the `legal-advisor` agent.
- Routes/blades:
  - `/terms` → `pages.terms` (EN), `/privacy` → `pages.privacy` (EN)
  - `/es/terms` → `pages.es.terms` (ES), `/es/privacy` → `pages.es.privacy` (ES)
- Each page: small **EN | ES** language toggle at the top + `hreflang` alternate
  links for SEO. Add ES pages to `sitemap.xml`.
- Wire `welcome.blade.php` footer: `href="#"` → `route('terms')` / `route('privacy')`.

### 2. Backend consent tracking

- `config/legal.php`: `terms_version` (e.g. `'2026-07-12'`), page URLs, contact email.
- **Migration** — add to `profiles`: `terms_accepted_at TIMESTAMPTZ NULL`,
  `terms_version VARCHAR NULL`.
- **Registration** (`RegisterBusinessRequest` / `RegisterCommunityRequest` /
  `RegisterAttendeeRequest`): add `accepted_terms` — required, must be `accepted`
  (missing/false → 422). On create, set `terms_accepted_at = now()`,
  `terms_version = config('legal.terms_version')`.
- **Re-gate** (mirrors the subscription-lapse re-gate pattern):
  - `GET auth/me` response gains
    `terms: { current_version, accepted_version, needs_acceptance }`.
  - `POST me/consent` (auth:sanctum) — records acceptance of the current version.
- Assumption: consent is captured at the `register/{type}` endpoints; `auth/google`
  and `auth/apple` only authenticate (new accounts are created via register). If a
  new account can be created directly in the OAuth flow, capture consent there too.

### 3. Mobile consent flow (doc)

`docs/mobile-consent-flow.md`: mandatory checkbox on the sign-up screen linking to
the web `/terms` and `/privacy`; send `accepted_terms=true` in the register call; on
app launch, if `me.terms.needs_acceptance` is true, show a re-consent modal that
calls `POST me/consent`.

## Testing

- Register requires `accepted_terms` → 422 when missing/false (all three types).
- Successful register stores `terms_version` + `terms_accepted_at`.
- `auth/me` reports `needs_acceptance=false` after fresh accept, `true` after a
  version bump.
- `POST me/consent` updates the stored version/timestamp.
- Web: `/terms`, `/privacy`, `/es/terms`, `/es/privacy` return 200; footer links
  resolve to named routes.

## Docs to update (same PR)

- `docs/BACKEND-SCHEMA.md` — new `profiles` columns + `me/consent` endpoint + `me`
  contract change.
- `docs/ROLES-AND-PERMISSIONS.md` + `docs/ROLES-BACKEND-DB-MAP.md` — onboarding
  consent gate; bump *Last updated*.
- `BACKLOG.md` — track then complete.
- PR template *Mobile impact* — API contract change; link `kolabing-app` ticket.

## Out of scope (YAGNI)

- Full per-event consent audit table (columns on `profiles` are enough for MVP).
- Cookie banner / consent management platform for the marketing site.
- In-app rendering of the legal text (app links out to the web pages).
