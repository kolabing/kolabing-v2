# Backend: `POST /auth/apple` must accept & honor `user_type` (attendee social signup)

> Date: 2026-06-11 · Requested by: mobile app (kolabing-app `feat/attendee-social-login`)
> Priority: medium · Blocks: Apple-based attendee signup in the app (currently flag-gated off)

## Context

The mobile app is adding **Google + Apple social login for the `attendee` user type**.

- **Google already works.** `POST /auth/google` accepts an optional `user_type`
  (`business|community|attendee`) and, for a brand-new user, creates the account with that
  role (auto-creating the matching detail row, incl. `attendee_profiles`). The app now passes
  `user_type=attendee` from the attendee register screen — no backend change needed for Google.
- **Apple does not.** `POST /auth/apple` currently accepts only `identity_token` + optional
  `name`. It has **no `user_type`**, so a brand-new Apple user cannot be created as an
  attendee — the backend would fall back to its default role.

Because of this gap, the app ships the **Apple button on the attendee screen behind an
off-by-default feature flag** (`FeatureFlags.attendeeAppleSignupEnabled`). Flipping it on is a
one-line app change once this ticket is deployed.

## Required change

Make `POST /auth/apple` mirror `POST /auth/google` with respect to role selection:

1. **Accept** an optional `user_type` in the request body, validated against the same enum as
   Google (`business|community|attendee`).
2. **Honor it on new-user creation only.** When the Apple identity resolves to a *new* user
   (`is_new_user = true`), create the `profiles` row with the requested `user_type` and
   auto-create the matching detail row — for `attendee`, the 1:1 `attendee_profiles` row
   (`total_points=0`, `total_challenges_completed=0`, `total_events_attended=0`,
   `global_rank=null`), exactly as the Google path does.
3. **Ignore it for existing users.** If the Apple identity matches an existing account, return
   that account as-is. If `user_type` is sent and **mismatches** the existing account's role,
   return the same conflict response Google returns (e.g. `409`) — do not switch roles.
4. **Response parity.** Return the same shape as Google: `{ token, token_type, refresh_token?,
   is_new_user, user: { id, email, user_type, onboarding_completed, ... } }`.

## Attendee onboarding semantics (please confirm)

- For a fresh social attendee, return **`onboarding_completed = false`** (or otherwise signal
  that onboarding is pending). The app routes new attendees into its 4-step onboarding
  (`PUT /onboarding/attendee` — name/handle/interests/communities) and relies on `is_new_user`
  to do so. The Apple `name` (first authorization only) may be stored on `profiles.name` as a
  best-effort prefill; `handle` stays null until onboarding runs.

## Implementation note

Use the existing **Google controller/service as the template** — the validation, new-vs-existing
branching, detail-row creation, and conflict handling should be identical; only the token
verification (Apple public keys / identity_token) differs, which already exists.

## Acceptance

- `POST /auth/apple` with `{ identity_token, user_type: "attendee" }` for a new Apple identity
  → creates an attendee (`profiles.user_type='attendee'` + `attendee_profiles` row),
  `is_new_user=true`, `onboarding_completed=false`.
- Same call for an existing attendee → returns that user, `is_new_user=false`.
- `user_type` mismatch vs an existing account → `409` (parity with Google).
- Omitting `user_type` keeps today's behavior (back-compat).

## App-side reference

- Client already sends the field: `AuthService.authenticateWithApple(... , userType:)` →
  body `{ identity_token, name?, user_type? }` (kolabing-app
  `lib/features/auth/services/auth_service.dart`).
- App spec: `docs/superpowers/specs/2026-06-11-attendee-social-login-design.md`.
