# BACKLOG (kolabing-v2 / backend)

> **Single source of truth for outstanding BACKEND work.** `CLAUDE.md` requires this
> file to be read at the start of every session and kept in sync (see "Maintenance
> rules" at the bottom).
>
> **Scope:** this file tracks **backend (Laravel / API / DB / ops)** work only. App
> (Flutter) work is tracked in the **kolabing-app** repo's `BACKLOG.md`, which stays
> the authoritative app backlog. The live cross-repo board is **Kolabing Engineering**
> (GitHub Project 4, owner `kolabing`).
>
> Last updated: 2026-08-15 (BE-NF-16 blog engine + SEO/GEO built, branch feat/blog-engine-seo-geo: public /blog + admin /admin/blog CRUD + Article/Organization/WebSite/FAQPage JSON-LD + homepage meta/OG + sitemap/llms now include posts. Prior 2026-08-02: BE-FX-11 F1 host_profile_id on EventResource + BE-FX-8 chat
> last_message preview + BE-FX-9 factory-avatar distinctness — fixed in code on branch
> fix/qa-batch-host-profile-chat-preview-avatars; app half = kolabing-app PR 108. Prior:
> added BE-FX-10 — Explore browse feed now hides date-exhausted
> Kolabs, fixed + tested. Prior: bootstrapped — the file `CLAUDE.md` mandates did not exist;
> seeded with the known backend-outstanding items from the mobile-audit analysis and
> referenced tickets. Future sessions must keep it in sync.)

---

## 🆕 New Features
_Planned backend work that does not exist yet._

| # | Feature | Notes | Status |
|---|---------|-------|--------|
| BE-NF-5 | **Admin-managed gamification economy** | Server-owned reward economy so the app stops hardcoding XP/badges. `GET /gamification/config` + `xp_earn_rules` (drives `point_ledger` + display), badge requirements, referral/withdrawal economics. Prompts: `docs/tickets/2026-06-01-admin-challenges-prompt.md`, `docs/tickets/2026-06-01-admin-xp-economy-prompt.md`. | Not started |
| BE-NF-10 | **SMS notification channel** | Transactional SMS (Twilio/Vonage) alongside push: application accepted, kolab scheduled/reminder, check-in. Needs provider integration + per-user phone capture/verify + channel preference. **[VERIFY with Daniel]:** provider, trigger events, opt-in, cost ceiling. | Not started |
| BE-NF-15 | **Scale audit & query optimization** | List endpoints issue O(N) queries/page (`EventResource` ~3 counts/event; `ChatService::visibleThreads` per-thread unread count; unpaginated `GET /chats`; non-index-friendly `COALESCE` time filter). Instrument → seed at scale → k6 load-test → fix via `withCount`/eager/grouped counts, cursor pagination + caps, covering indexes, chunked fan-out. Ticket: `docs/tickets/2026-06-05-backend-scale-audit-optimization.md`. | Spec ready — not started |
| BE-NF-22 | **Web App — profile edit + applications** (epic #128, stacked on BE-NF-21) | ✅ BUILT (branch `feat/web-app-profile-apply`): **/account** (edit profile via `PUT /me/profile` — business: name/about/business_type/categories/city/instagram/website; community: +type/size/tiktok; photo stays in-app), **/applications** (role-aware: community sees sent apps + withdraw via `GET /me/applications`; business sees received + accept(`scheduled_date`)/decline via `GET /me/received-applications`), and the **apply flow** on the Kolab detail page (community → `POST /kolabs/{id}/applications` {message, availability≥20}; owner → View applications; business viewer → open-in-app). Nav gains Applications + Account. `WebAppRoutesTest` extended. **PENDING:** merge (stacked chain #132→#133→this). | Built (PR, stacked) — pending merge |
| BE-NF-21 | **Web App — Phase 1b (feed + Kolab CRUD)** (epic #128, stacked on BE-NF-20) | ✅ BUILT (branch `feat/web-app-feed-kolabs`): the `app.kolabing.com` app gains real pages (replacing the coming-soon shells): **feed** (`GET /kolabs` with search/city/intent filters + save/unsave), **my Kolabs** (`GET /kolabs/me` + status tabs + publish/close/delete; publish 402 → routes to /subscription), **detail** (`GET /kolabs/{id}` + owner actions + save + apply-in-app), **create/edit** (`POST`/`PUT /kolabs`, driven by the real `CreateKolabRequest`: community users → `community_seeking` (needs/typical_attendance/offers_in_return via `/lookup/*`); business → `product_promotion` (product_name/type/offering); venue promotions need photos + a venue profile → gated to the app). Blade + Alpine, all against the existing API. `WebAppRoutesTest` extended. **PENDING:** merge (stacked on #132); can't e2e-verify the create→publish round-trip headless (needs the live app.kolabing.com). | Built (PR, stacked on #132) — pending merge |
| BE-NF-20 | **Web App — Phase 1 frontend (foundation + auth + sales)** (epic #128) | ✅ BUILT (branch `feat/web-app-foundation-auth-subscription`): host-scoped web app at `app.kolabing.com` (`config/webapp.php` + `Route::domain` group in `routes/web.php`, registered before marketing so the app host wins at `/`; other hosts get the marketing site). Blade shells + CDN Alpine (matches the marketing site's CDN convention — no npm/Vite change) + an inline same-origin API client (bearer token in localStorage, one transparent refresh on 401 — same flow as mobile). Pages: index (auth-route), login (email/password + Google w/ business/community), register (Google sign-up), dashboard, **subscription (buy €49/€129 via checkout + manage via portal — the sales surface)**, welcome (post-purchase app-handoff nudge), kolabs/feed coming-soon shells. `WebAppRoutesTest`. Degrades gracefully before `GOOGLE_CLIENT_ID_WEB` is set (Google hidden, email/password works). **PENDING:** merge; Phase 1b = Kolab CRUD + live feed pages (need the Kolab/Discovery resource shapes); depends on the #127/#129/#130/#103 endpoints being live to function. | Built (PR) — pending merge + Phase 1b |

---

## 🚧 Incomplete Features
_Started or partially shipped; not yet fully working end-to-end._

| # | Feature | What's done / what's missing | Status |
|---|---------|------------------------------|--------|
| BE-IF-18 | **Real-time chat — Reverb Part A (ops)** | Code exists (event + channel auth + broadcasting route); the Flutter client (app IF-18) is dormant until the backend returns `REVERB_APP_KEY`. **MISSING:** env config + self-hosted Reverb daemon + queue worker + nginx/TLS. No code change on handoff — app flips live once the key is served. Ticket: `docs/tickets/2026-06-09-reverb-realtime-chat-PART-A-ops.md`. | Code ready — ops/deploy pending |
| BE-IF-47 | **Legacy `collab_opportunities` → Kolab consolidation** | PR #32 removed table-level legacy code (archive table), but the app still routes System-A edits to legacy rows and the split persists conceptually. Full data migration + route/model consolidation remains open-ended (needs a migration plan). App audit item #47. | Partial — migration plan needed |
| BE-IF-48 | **Landing-page newsletter + book-a-call pop-up** | ✅ BUILT: `newsletter_subscribers` table + `NewsletterSubscriber` model + `NewsletterAudience` enum + `NewsletterSubscribeRequest` (email + optional community/business audience + honeypot) + `NewsletterController@store` (idempotent per email) + `POST /newsletter` (throttle 10/min) + pop-up UI in `welcome.blade.php` (exit-intent/18s/45%-scroll, once per session, segmented audience, book-a-call CTA). 7 feature tests green, pint clean. **PENDING:** run migration on prod; set `KOLABING_BOOK_A_CALL_URL` env to the real Cal.com link (default is a placeholder). | Built — pending deploy + real Cal.com link |
| BE-NF-16 | **Blog engine + SEO/GEO** | ✅ BUILT (branch `feat/blog-engine-seo-geo`): `blog_posts` table + `BlogPost` model/factory + public `/blog` + `/blog/{slug}` (Blade on the `marketing-page` layout, per-post canonical/OG) + admin `/admin/blog/*` CRUD (maintainer-gated, sidebar entry) + **Article/Organization/WebSite/FAQPage JSON-LD** + homepage `welcome.blade.php` meta/canonical/OG fix + `/sitemap.xml` & `/llms.txt` now include published posts. 2 feature test files (public + admin). Owned by Clark going forward (blog + SEO/GEO workstream). **PENDING:** merge (Volkan) + prod migrate (the `master` deploy runs `migrate --force`); then Phase 2 = first Community-Commerce articles + a Barcelona city page. | Built (PR) - pending merge/deploy + content |

---

## 🐛 Fixes
_Backend bugs / gaps. Add when detected; strike through with a date once confirmed, then remove._

| # | Bug / gap | Status |
|---|-----------|--------|
| BE-FX-11 | **Event host profile 404 (public-profile F1)** — the app opened an event's host-community public profile by pushing `events.community_id` (a `communities.id`) into `/profiles/{id}`, which binds `profiles.id` → 404 on profile/collaborations/gallery. `EventResource` now emits `host_profile_id` (the eager-loaded community owner's `profiles.id`, falling back to `events.profile_id` — always a valid `profiles.id`). Branch `fix/qa-batch-host-profile-chat-preview-avatars`; app half = kolabing-app PR #108. | Fixed in code 2026-08-02 — pending review + e2e |
| BE-FX-8 | **Chat inbox has no message preview** (app audit #8) — `GET /chats` / `ChatThread` exposed only `last_message_at`, never body text, so the app showed a "Tap to open" placeholder. Added `ChatThread::latestMessage()` (`latestOfMany`), eager-loaded in all 3 `ChatService` list loaders (no N+1), and emit `last_message: {content, created_at}` on `ChatThreadResource`. +1 feature test (`ChatActiveListTest`). App reads `last_message.content` (kolabing-app PR #108). | Fixed in code 2026-08-02 — pending review + e2e |
| BE-FX-9 | **Same stock avatar for multiple profiles** (app audit #9) — the QA read was "same waterfall stock photo everywhere". **Ground-truth:** `RealisticDataSeeder` already assigns DISTINCT per-name picsum photos (`.../seed/profile-<slug>/...`) — not the culprit. The real identical-image source was the **factories** (`Profile/Business/CommunityProfileFactory`), whose `fake()->imageUrl()` returns one shared `via.placeholder` image for every row → fixed to a distinct per-row picsum seed (or null → the app's initials placeholder). **Still open:** confirm prod isn't holding a shared stock URL (`SELECT avatar_url FROM profiles WHERE avatar_url LIKE 'https://picsum.photos/%'` + the two `profile_photo` columns) and the app-side avatar-source inconsistency Jace saw (stock in offer-detail vs initials in Explore card) needs a device re-check. | Factory source fixed in code 2026-08-02 — prod-data + app-consistency check still open |
| BE-FX-10 | ~~**Explore feed returns date-exhausted Kolabs** — the browse feed (`GET /kolabs` + `/opportunities` shim → `KolabService::browse()`) surfaced Kolabs whose application dates had all passed, so applicants hit an empty date picker ("No available dates for this kolab"). Added `Kolab::scopeWithSelectableDates()` on the discovery path, mirroring the apply-time guard; saved list unaffected.~~ | Fixed 2026-07-28 (tested) |

---

## Maintenance rules (mandatory — per CLAUDE.md)

- A **New Feature** you begin → move to **Incomplete Features**.
- An **Incomplete Feature** verified working end-to-end → remove it.
- A **bug** you detect → add to **Fixes** immediately; once the fix is **confirmed**
  (tested, not just written), strike it through with the date, then remove later.
- Update the **`Last updated:`** date whenever you edit this file.
- Keep **backend** work here; app work stays in `kolabing-app/BACKLOG.md`.
