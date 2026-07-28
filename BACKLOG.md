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
> Last updated: 2026-07-28 (added BE-FX-10 — Explore browse feed now hides date-exhausted
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

---

## 🚧 Incomplete Features
_Started or partially shipped; not yet fully working end-to-end._

| # | Feature | What's done / what's missing | Status |
|---|---------|------------------------------|--------|
| BE-IF-18 | **Real-time chat — Reverb Part A (ops)** | Code exists (event + channel auth + broadcasting route); the Flutter client (app IF-18) is dormant until the backend returns `REVERB_APP_KEY`. **MISSING:** env config + self-hosted Reverb daemon + queue worker + nginx/TLS. No code change on handoff — app flips live once the key is served. Ticket: `docs/tickets/2026-06-09-reverb-realtime-chat-PART-A-ops.md`. | Code ready — ops/deploy pending |
| BE-IF-47 | **Legacy `collab_opportunities` → Kolab consolidation** | PR #32 removed table-level legacy code (archive table), but the app still routes System-A edits to legacy rows and the split persists conceptually. Full data migration + route/model consolidation remains open-ended (needs a migration plan). App audit item #47. | Partial — migration plan needed |

---

## 🐛 Fixes
_Backend bugs / gaps. Add when detected; strike through with a date once confirmed, then remove._

| # | Bug / gap | Status |
|---|-----------|--------|
| BE-FX-8 | **Chat inbox has no message preview** (app audit #8) — `GET /chats` / `ChatThread` expose only `last_message_at`, never message body text, so the app shows a "Tap to open" placeholder. Add a `last_message_preview` (truncated, permission-safe) field to the chat-thread list resource. | Open — app blocked on this |
| BE-FX-9 | **Same stock avatar for multiple profiles** (app audit #9) — the backend returns the same waterfall stock photo URL for profiles that never uploaded one, so the app's initials fallback never triggers. Either stop seeding/defaulting real profiles to a shared stock image, or expose a `has_custom_avatar` flag so the app can force the initials fallback. | Open — app blocked on this |
| BE-FX-10 | ~~**Explore feed returns date-exhausted Kolabs** — the browse feed (`GET /kolabs` + `/opportunities` shim → `KolabService::browse()`) surfaced Kolabs whose application dates had all passed, so applicants hit an empty date picker ("No available dates for this kolab"). Added `Kolab::scopeWithSelectableDates()` on the discovery path, mirroring the apply-time guard; saved list unaffected.~~ | Fixed 2026-07-28 (tested) |

---

## Maintenance rules (mandatory — per CLAUDE.md)

- A **New Feature** you begin → move to **Incomplete Features**.
- An **Incomplete Feature** verified working end-to-end → remove it.
- A **bug** you detect → add to **Fixes** immediately; once the fix is **confirmed**
  (tested, not just written), strike it through with the date, then remove later.
- Update the **`Last updated:`** date whenever you edit this file.
- Keep **backend** work here; app work stays in `kolabing-app/BACKLOG.md`.
