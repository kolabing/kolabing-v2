<!--
  ⚠️ ALL sections below are MANDATORY. Do not delete headings.
  If a section does not apply, write "N/A" with a one-line reason — never leave it blank.
  PRs with empty mandatory sections will not be merged.
-->

## Summary
<!-- REQUIRED. What does this PR do and why? 1–3 sentences. -->


## Linked tracking item
<!-- REQUIRED. Link the GitHub Projects item / issue this PR delivers.
     Use a closing keyword so it auto-closes on merge. -->
Closes #

## Type of change
<!-- REQUIRED. Check at least one. -->
- [ ] ✨ Feature
- [ ] 🐛 Bug fix
- [ ] ♻️ Refactor
- [ ] 🧪 Tests
- [ ] 📝 Docs
- [ ] 🔧 Chore / tooling
- [ ] ⚠️ Breaking change

## Changes
<!-- REQUIRED. Bullet list of the key changes a reviewer should look at. -->
-

## Mobile impact (kolabing-app)
<!-- REQUIRED. Does this change affect the mobile app — API contract, JSON payload
     shape, new/renamed endpoints, enum values, auth, or error codes?
     If YES, you MUST describe the contract change and link the kolabing-app ticket/PR. -->
- [ ] No mobile changes required
- [ ] Mobile changes required (described below + tracked in `kolabing-app`)

**Mobile details / kolabing-app link:** <!-- required if box 2 is checked, else N/A -->

## Database / migrations
<!-- REQUIRED. -->
- [ ] No schema changes
- [ ] Includes migrations — additive & reversible
- [ ] Includes data backfill / destructive change (explain rollback below)

**Migration notes:** <!-- N/A if none -->

## Testing
<!-- REQUIRED. How was this verified? -->
- [ ] `php artisan test` passes — paste counts:
- [ ] `vendor/bin/pint` clean
- [ ] Manually verified the happy path

## Docs & rules updated
<!-- REQUIRED. Tick what applies; the repo rules require these to stay in sync. -->
- [ ] `BACKLOG.md` updated (work started → moved / completed → removed)
- [ ] `docs/ROLES-AND-PERMISSIONS.md` + `docs/ROLES-BACKEND-DB-MAP.md` (role/paywall/feed/onboarding surfaces)
- [ ] `docs/BACKEND-SCHEMA.md` (schema / payload / JSON keys)
- [ ] No docs impact

## Screenshots / notes
<!-- Optional. Add UI screenshots, API examples, or reviewer guidance. -->
