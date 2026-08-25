# Invitation landing page — make it work, and put a join form on it (BE-NF-38)

> Design spec. Date: 2026-08-20. Fixes a live defect shipped in BE-NF-34 and adds
> attendee sign-up on the invite page. No schema change.

---

## 1. The live defect: the invite page does nothing

`/c/{slug}` renders the community, the tier ladder and upcoming events, and then shows
**nothing at all** where the join button should be. Two causes, stacked — either alone
would break it:

1. **No Alpine on the marketing layout.** `components/layouts/marketing-page.blade.php`
   loads no Alpine and defines no `[x-cloak]` rule. The CTA block is
   `x-data="communityJoinCta()"` wrapping `<template x-if>` blocks; with no Alpine those
   templates never render, so the button is simply absent. Verified on production: the
   page returns 200, the strings sit in the HTML inside `<template>` tags, and no
   `accounts.google.com`, no Alpine `<script>`, no `[x-cloak]` rule is present.
2. **The CSP would block it anyway.** `AddSecurityHeaders` grants `'unsafe-eval'` and
   `accounts.google.com` **only** to `config('webapp.host')`. Alpine compiles every
   `x-*` expression with `new Function`, so it cannot run under the marketing policy —
   the same class of failure BE-NF-21 hit and documented. Google Identity Services is
   blocked there too.

I shipped this in BE-NF-34 without loading the page in a browser. The route test
asserted the markup was present, which it is — inside inert `<template>` tags.

## 2. Decision: move the page to the app host

`/c/{slug}` moves from the marketing host to `app.kolabing.com`, where the CSP already
permits exactly what this page needs — Alpine and Google Sign-In — and where the person
ends up anyway. **No security header is weakened**; the marketing site keeps its strict
baseline.

- `config('communities.invite_base_url')` default becomes `https://app.kolabing.com/c`.
- `kolabing.com/c/{slug}` stays as a **301 redirect** preserving the query string, so any
  link already shared still resolves. Cheap, and it costs nothing to keep.

Rejected: relaxing the CSP for one marketing route (keeps the shorter URL but weakens a
header the code explicitly guards), and opening the marketing host entirely (weakens
blog, homepage and every marketing page for one page's benefit).

## 3. The join form

Today a signed-out visitor is told to sign in. Now they can join in place.

**Sign-up is Google-only, matching the platform's primary auth.**
`POST /auth/register/attendee` requires a password (min 8, confirmed), which the
requested field list does not include — so the form does not use it. Instead:

```
[ Continue with Google ]        →  POST /auth/google { id_token, user_type: 'attendee' }
        ↓  (now authenticated)
Full name        (required)     →  PUT /me/profile  (multipart)
Phone number     (optional)          name, phone_number, profile_photo
Photo            (optional)
        ↓
Join / Accept invitation        →  the existing endpoint for the case
        ↓
"You're in" state               →  community name + tier + get-the-app CTA
```

Every field maps to something `UpdateProfileRequest` already accepts on the attendee
path (`name`, `phone_number`, `profile_photo` as an image ≤5 MB) — **no backend change**.
The email comes from Google, so it is shown read-only rather than asked for.

`accepted_terms` is stamped server-side for OAuth sign-ups by
`AuthService::consentStamp()`, so the form carries the terms notice as text rather than a
checkbox that would not be sent anywhere.

**Already signed in?** The form is skipped entirely — the CTA is the join action, as
today. **Already a member?** The success state is shown directly rather than an error.

## 4. Where they land afterwards

Nowhere else — deliberately. The web app has **no attendee surface**: Explore, My Kolabs
and the Community Hub are all business/community. Sending a new member to `/dashboard`
would drop them on a screen written for someone else.

So the page ends on its own success state: "You've joined {community}", their tier if the
community assigned one, and a get-the-app CTA. Honest about what the web can do for them
today.

## 5. Architecture

- **`resources/views/webapp/community-join.blade.php`** — the page, on the webapp layout
  (so Alpine, `window.kb` and the theme come for free) with no sidebar, like
  `login.blade.php`. Replaces `resources/views/pages/community-join.blade.php`.
- **`CommunityJoinPageController`** stays as the data source; its view name changes and
  it gains nothing else.
- **Routes:** the join route joins `$webappRoutes` (so it exists at `/`, `/es`, `/ca`);
  the marketing host keeps a redirect.
- **State:** one Alpine component with three phases — `intro` (community + CTA),
  `profile` (the form, only after Google returns), `done` (success). A single `phase`
  string, not three booleans, so two panels can never show at once.
- **Google button:** `window.kbGoogle.render()` from the webapp layout, already used by
  login and register. If GSI is blocked, the component falls back to a "sign in" link
  rather than a dead button.

## 6. Testing

- `CommunityJoinPageTest` moves to the app host and keeps its existing assertions
  (unknown slug 404s, tier ladder ordering, members-only events do not leak,
  invite-only is `noindex`, the token reaches the page).
- **New:** the page ships an Alpine `<script>` and the `[x-cloak]` rule — the assertion
  that would have caught this defect. Asserting the markup exists was not enough; the
  test must assert the page can *run* it.
- **New:** `kolabing.com/c/{slug}` 301-redirects to the app host preserving `?i=`.
- **New:** the form's fields and the Google button render for a signed-out visitor.
- `Community::inviteUrl()` now points at the app host — assert it, since that is the
  string people actually receive.
- es/ca at 100% key parity.

## 7. Docs

- `docs/ROLES-BACKEND-DB-MAP.md` §12.6 — the invite URL host change, the CSP reasoning,
  and that sign-up here creates an **attendee** (§8.1 D4: community members are
  attendees).
- `BACKLOG.md` — BE-NF-38, and the BE-NF-34 row corrected: its `/c/{slug}` claim was only
  half true.
- No `ROLES-AND-PERMISSIONS.md` change: no role, gate or permission changes. Joining a
  community is free, as §8.4 requires.
