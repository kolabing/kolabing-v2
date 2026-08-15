# Kolabing Web App — design & implementation plan

**Date:** 2026-08-15 · **Owner:** Clark (build) · **Requested by:** Volkan · **Epic:** #128

Bring the core Kolabing flows to the web, consuming the existing `/api/v1` (same flow as
the mobile app). **Goal: acquire users and take sales on the web, then nudge them into the
mobile app** (a slight nudge **after purchase**; the web stays usable).

## Scope (Volkan)
- Community + business **login / register** on web
- **Buy + manage** subscription
- **Create & manage Kolabs** (CRUD)
- **Feed for paid users**
- Reuse the existing API; continue the same flow as the app; **redirect web users to the mobile app**

## Architecture (decided)
Blade + **Alpine.js** + `axios` ("SPA-lite") inside **kolabing-v2** — same repo/deploy as the
marketing site + admin panel — calling `/api/v1` exactly like the mobile client. Domain:
`kolabing.com/app`.

Rationale: the repo has no SPA framework today (just Vite + Tailwind v4 + axios). Adding
Vue/React/Inertia means a new dependency + a second build/deploy target; Blade+Alpine reuses
the existing stack and the marketing→signup funnel on one domain and ships far faster. Since
the endgame is to push users into the mobile app, the web is a **conversion surface**, not a
full-fidelity app. A Vue/React SPA remains the alternative if we later want a richer web product.

### Auth model (grounded)
The API is **token-based Sanctum** and already supports **both** Google (`auth/google`) **and
email/password** (`auth/register/{business,community}`, `auth/login`, forgot/reset) — the
"Google-only" line in `CLAUDE.md` is stale. Every endpoint returns a bearer token + refresh token.

Because the web app is **same-origin** (`kolabing.com/app` → `kolabing.com/api/v1`), it can call
the existing API directly with the bearer token — **no CORS and no Sanctum stateful/cookie rewrite
required**. This is the same flow as mobile, per Volkan. Token hygiene: keep the access token in
memory and (optionally, later) hold the refresh token in an httpOnly cookie to reduce XSS exposure.
A cookie-session (Sanctum stateful) rewrite is only worth doing if we choose a **separate** web
origin (e.g. `app.kolabing.com`) — deferred unless that decision changes.

## Backend enablers (small, independent PRs)
1. **Web Google login** — accept a web Google client id (`GOOGLE_CLIENT_ID_WEB`) as a valid token
   audience in `GoogleAuthService` (previously only iOS/Android/primary). Web uses Google Identity
   Services JS → posts the id_token to the existing `auth/google`. **← this PR.**
2. **Subscription buy** — Stripe Checkout: **PR #127** (in review).
3. **Subscription manage/cancel** — `POST /me/subscription/portal` → Stripe Billing Portal URL.
   *(Depends on PR #127's `StripeService`; lands after #127 merges.)*
4. **Paid-feed gating** — implement the identity-blur flag on the discovery resource (closes the
   open `ROLES-BACKEND-DB-MAP.md` §4 gap): unpaid business → blurred feed + upgrade CTA; paid → full.
   Benefits web **and** mobile.
5. *(Optional)* email/password polish for web if we lean on it over Google.

## Web pages (Blade + Alpine, on kolabing.com)
- `/login` + `/register` — business/community toggle, "Sign in with Google" + email/password, the
  extended-profile form, `accepted_terms`.
- `/app` — authenticated dashboard (my Kolabs, applications, collaborations, profile).
- `/app/kolabs` — list + **create/edit/publish/close** (business + community-seeking).
- `/app/feed` — Explore/discovery; **paid → full, unpaid → blurred + upgrade CTA**.
- `/app/subscription` — status + **Buy** (checkout) + **Manage** (portal).
- **App-handoff surface** — store badges + `kolabing://` deep link + smart app banner, shown as a
  **slight nudge after purchase**; the web-created account + web-purchased subscription are already
  active in the app on first login.

## Sequence
- **Phase 0 (backend enablers):** #127 (buy) → web Google login (#1) → portal (#3, after #127) →
  feed blur (#4).
- **Phase 1 (web client):** auth pages → **subscription page first** (this is "do sales from web")
  → dashboard + Kolab CRUD → feed → app-handoff.
- **Phase 2:** es/i18n, funnel analytics events, polish, SEO on the logged-out → signup path.

## Open items (non-blocking; defaults noted)
1. **Domain:** `kolabing.com/app` (default — one deploy, no CORS) vs `app.kolabing.com` (would need
   the cookie-session/CORS enabler).
2. **Google web vs email/password** as the primary web sign-in (both are supported).

## Verification (per PR)
`vendor/bin/pint` clean; `php artisan test` (box lacks `pdo_sqlite` → CI-validated, per #122/#125);
role/paywall-touching PRs (feed blur) update **both** ROLES docs; payments/auth PRs are red-teamed.
