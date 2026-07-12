# Mobile consent flow (Terms of Service + Privacy Policy)

How the `kolabing-app` mobile client collects and maintains user consent to the
legal agreements, and how the backend records it. Kolabing is Spain-based, so this
satisfies both the App Store / Google Play "link to a privacy policy + get consent"
requirement and GDPR/LOPDGDD provable-consent expectations.

## The web pages (canonical text)

The legal text lives on the marketing site (the app links out to it — it does not
re-render the text in-app):

| Page          | English            | Spanish               |
| ------------- | ------------------ | --------------------- |
| Terms         | `https://kolabing.com/terms`   | `https://kolabing.com/es/terms`   |
| Privacy       | `https://kolabing.com/privacy` | `https://kolabing.com/es/privacy` |

Link to the locale matching the user's device language; default to English.

## Versioning

The current agreement version is a backend constant: `config('legal.terms_version')`
(date-based, e.g. `2026-07-12`). Bump it whenever the published terms change
materially. The app never hardcodes the version — it always reads it from `/auth/me`.

## Sign-up (first consent)

1. The sign-up screen (for **all** methods — Google, Apple, and email/password) shows
   a **mandatory** "I agree to the Terms of Service and Privacy Policy" checkbox with
   tappable links to the two web pages above. The primary button stays disabled until
   it is checked.
2. On submit:
   - **Email/password** → `POST /api/v1/auth/register/{business|community|attendee}`
     with **`accepted_terms: true`**. The field is **required and must be accepted** —
     the API returns `422` with an `accepted_terms` error otherwise.
   - **Google / Apple** → `POST /api/v1/auth/{google|apple}` as today. These endpoints
     authenticate existing users and create new ones; the backend records consent
     automatically whenever it creates a new account, so no extra field is required.
     (The checkbox is still shown in the UI for a new sign-up.)
3. The backend stamps `terms_accepted_at` (now) and `terms_version` (the current
   version) on the profile at account creation.

## Re-consent (terms change after sign-up)

`GET /api/v1/auth/me` returns a `terms` block on the user object:

```json
"terms": {
  "current_version": "2026-07-12",
  "accepted_version": "2026-07-12",
  "accepted_at": "2026-07-12T16:00:00+00:00",
  "needs_acceptance": false
}
```

- On app launch / resume, after fetching `/auth/me`, if `terms.needs_acceptance` is
  `true` (the user accepted an older version, or never accepted), present a blocking
  re-consent sheet with links to the pages and an "Accept" button.
- On accept → `POST /api/v1/me/consent` (authenticated, no body). The backend records
  acceptance of `current_version` and returns the refreshed `terms` block with
  `needs_acceptance: false`.

## Backend surface (for reference)

- `profiles.terms_accepted_at` (timestamptz, nullable), `profiles.terms_version`
  (string, nullable).
- `config/legal.php` → `terms_version`, `contact_email`.
- `POST /api/v1/me/consent` → records acceptance of the current version.
- `GET /api/v1/auth/me` → adds the `terms` block shown above.
- `Profile::needsTermsAcceptance()` → `accepted version !== current version`.
