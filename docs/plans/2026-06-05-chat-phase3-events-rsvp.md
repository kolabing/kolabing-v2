# Chat Phase 3 — Event RSVP + event chats + Reverb real-time (backend)

**Created:** 2026-06-05 · **Branch:** `feat/chat-phase3-events-rsvp` · **Repo:** `kolabing-v2`
**Ticket:** `kolabing-app/docs/tickets/2026-06-04-chat-phase3-events-rsvp-realtime-backend.md`
Builds on shipped Phase 1 (inbox) + Phase 2 (community chats, generic `/chats/{thread}/messages`).

## Status: backend BUILT — 842 tests green (11 new), 0 regressions

### Sign-ups (RSVP) — binary "I'm going" + capacity + waitlist
- Migration `event_signups` (`event_id`, `profile_id`, `status` going|waitlisted|cancelled,
  `waitlist_position`, unique `(event_id, profile_id)`). Enum `EventSignupStatus`. Model `EventSignup`.
- Migration adds to `events`: `starts_at`, `ends_at`, `location`, `capacity`, `tier_gate` (json),
  `collaboration_id`. (`community_id` already existed.) `Event` gains casts, `signups()`/`collaboration()`
  relations, `effectiveEnd()` + `isUpcoming()` (COALESCE(ends_at, starts_at, event_date)).
- `EventSignupService`: `signup` (capacity → going else waitlisted; idempotent; tier-gate + membership
  via `community_tiers`/owner), `cancel` (frees a seat → **auto-promote** head of waitlist + notify),
  `resequenceWaitlist`, counts/`isGoing`. Row-locked (`lockForUpdate`) so the last seat can't double-book.
- New `NotificationType::WaitlistPromoted`.
- Endpoints: `POST/DELETE /events/{event}/signup`, `GET /events/{event}/signups` (leader/can_manage).
  `EventResource` gains `my_signup`, `going_count`, `waitlist_count`, `capacity`, `starts_at`/`ends_at`,
  `location`, `community_id`, `collaboration_id`, `tier_gate`, `is_upcoming`. `EventSignupResource` for the roster.

### Event chat
- `POST /events/{event}/chat` (leader/can_manage) → idempotent `event` `ChatThread` via
  `ChatService::eventThreadFor` → `ChatThreadResource` (the app's `createEventChat` no longer 404s).
- `ChatService::canAccessThread` Event branch: `going` sign-up OR community leader/can_manage.
  Waitlisted/outsiders denied. Reuses the Phase-2 `/chats/{thread}/messages` + `/read` surface unchanged.
- `ChatService::visibleThreads` now also returns event threads (events the viewer is `going` to + the
  event chats of communities they manage), with per-thread unread.

### Real-time (Reverb)
- `routes/channels.php`: new private channel `chat.thread.{threadId}` authorized by
  `ChatService::canAccessThread` — SAME derived access as REST (the security boundary; never authorizes
  on community membership alone for tier/event-gated threads).
- `NewChatMessage` already broadcasts on `chat.thread.{id}` (Phase 2) for every thread type, so event
  messages broadcast with no further change. `laravel/reverb` + `config/reverb.php` + `BROADCAST_CONNECTION=reverb`
  + `REVERB_*` env already present.
- Ops to do on the server: run `php artisan reverb:start` (or supervisor) and point the app's Echo client at it.

### Events ↔ Kolabs + lifecycle (§6, §6.1)
- `events.collaboration_id` mirror of `collaborations.event_id` added.
- `GET /events` now filtered: `community_id`, `time=upcoming|past`, `attendee=me`, `profile_id` — one
  `events` lifecycle feeds the upcoming tab, community Details (past + gallery) and the profile showcase.
  No filter → the viewer's own events (back-compat). Retroactive past-event creation (showcase-only) is
  untouched; sign-up/waitlist/chat apply only to upcoming events (`isUpcoming`).

## Tests
`EventSignupTest` (7): signup, capacity→waitlist, cancel auto-promote+notify, tier-gate, non-member,
past-event blocked, leader roster (+ non-manager 403). `EventChatTest` (4): create chat, non-manager 403,
going/leader access vs waitlisted/outsider 403, event thread in `/chats` + send/read.

## Remaining (not backend code)
- App side: "I'm going" button + waitlist UI, event-chat surfacing, Reverb/Echo client (app contract §7).
- Ops: run the Reverb server; confirm prod env.
- Out of scope (separate tickets): member-to-member DMs, typing/presence, profile redesign.
