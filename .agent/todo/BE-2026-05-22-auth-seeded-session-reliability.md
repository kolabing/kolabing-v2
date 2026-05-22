# BE-2026-05-22 · Auth + Seeded Session Reliability

**From**: `B4`, `B5`, `B8` in the mobile QA list

The Flutter app now restores session on splash, routes login correctly, and retries protected calls after refresh. If these bugs still reproduce, the remaining fault is on the backend/session/seed side, not in the basic mobile navigation flow.

This ticket is the backend closure pass for three related problems:

- seeded QA account upload throws `Session expired`
- seeded business onboarding venue-type selection fails inconsistently
- freshly signed-in accounts hit protected screens and still receive auth-expired responses

---

## Required outcome

A freshly registered business account and a seeded QA business account must both be able to:

1. sign in
2. hit a protected endpoint immediately
3. select venue type during onboarding without auth drift
4. upload media without a fake expiry
5. continue browsing protected screens without instant 401s

---

## Backend requirements

### 1. Seeded QA accounts must be real login accounts

Seeded business/community QA accounts need:

- deterministic email + password credentials
- valid refresh-token compatibility
- full profile rows expected by onboarding and protected mobile screens

If seeded accounts are only data fixtures without complete auth state, mobile QA will keep reporting fake product bugs.

### 2. Fresh login must be immediately usable

After `POST /api/v1/auth/login`, the returned token pair must be accepted by protected endpoints without a consistency gap.

Acceptance example:

1. login
2. immediately call one protected read endpoint
3. immediately call one protected write endpoint
4. both succeed without requiring a second login or manual refresh

### 3. Refresh path must be stable during upload-heavy flows

Upload entry points are the fastest way QA hits token drift. Backend must tolerate:

- access token just expired
- refresh succeeds
- retried upload/write succeeds with the new token

This includes onboarding media, gallery media, kolab media, and past-event uploads.

---

## Logging required during verification window

For the next QA cycle, add structured logs for:

- login issue timestamp
- first protected request timestamp
- refresh attempt timestamp
- refresh result
- any 401 returned within 60 seconds of login
- auth context on venue-type save and upload endpoints

The point is not long-term verbose logging. The point is to catch whether failures are:

- expired token
- refresh-token invalid
- missing bearer propagation
- profile/seed mismatch
- policy rejection misreported as auth failure

---

## Acceptance

- seeded QA business account can log in and upload venue media without `Session expired`
- seeded QA business account can complete venue-type selection without server-side auth failure
- a newly registered business can sign in and open multiple protected screens without immediate auth-expired errors
- first protected request after login succeeds consistently
- refresh-and-retry succeeds on protected writes when the access token has legitimately expired

---

## Mobile reference

Frontend hardening already exists in:

- `kolabing-app/lib/features/auth/screens/splash_screen.dart`
- `kolabing-app/lib/features/auth/providers/auth_state_provider.dart`
- `kolabing-app/lib/features/auth/screens/login_screen.dart`
- `kolabing-app/lib/features/auth/services/auth_service.dart`
- `kolabing-app/lib/features/onboarding/providers/onboarding_provider.dart`

If the bugs still reproduce after this ticket, inspect logs before changing the mobile flow again.
