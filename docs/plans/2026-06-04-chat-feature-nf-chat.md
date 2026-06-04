# Chat feature (NF-CHAT) — Implementation plan

**Created:** 2026-06-04
**Repos:** `kolabing-v2` (backend, this plan's focus) + `kolabing-app` (Flutter, handoff §App).
**Source spec:** `kolabing-app/docs/tickets/2026-06-04-chat-feature-spec.md`.
**Read first:** `docs/ROLES-AND-PERMISSIONS.md` — chat must NEVER touch the business paywall;
a free business is never blocked from a collaboration chat it participates in.

---

## Current state (verified 2026-06-04)

Chat today is **application-scoped, two-party, and works**:
- `chat_messages` (`application_id`, `sender_profile_id`, `content`, `read_at`, timestamps).
  Indexes `[application_id, created_at]`, `[sender_profile_id]`. One application = one conversation.
- `ChatService`: `getMessages`, `sendMessage` (broadcast + push + reminder), `markMessagesAsRead`,
  `getUnreadCount`, `getUnreadCountByApplication`, `canParticipate` (applicant OR opportunity creator).
- Routes: `GET/POST /applications/{application}/messages`, `POST .../messages/read`,
  `GET /me/unread-messages-count`.
- `NewChatMessage` (ShouldBroadcast) → private `chat.application.{id}`, event `message.sent`.
- Unread = per-message `read_at` (no reads table). Push via `NotificationService::notifyNewMessage`.
- `ChatMessageResource` shape differs from the spec (uses `sender_profile`, `is_own`, `is_read`).

**Missing for the spec:** no `chat_threads`, no generic (non-application) chat, no multi-party chat,
no `last_message_at`, no community/event chat, **no `event_signups`/RSVP** (events have QR check-in +
a static `attendee_count` only). `community_tiers.permissions` JSON exists but `chat_channels` is unused.

---

## ⛏ Architecture decision (DECIDE BEFORE BUILDING)

The spec's data model (§2) is a **unified `chat_threads`** table with
`type ∈ {collaboration, community_main, community_custom, event}` and a generic
`chat_messages.thread_id`. Today's chat is `chat_messages.application_id`. Two ways:

**Option A — Unified threads (spec-faithful).** New `chat_threads` + new
`chat_messages.thread_id` + `chat_thread_reads`. Collaboration chat becomes a `collaboration`
thread (1 per accepted application). One set of endpoints (`/chats`, `/chats/{thread}/messages`)
serves all roles; the app gets one model.
- *Pros:* matches spec resource shapes; one code path; community/event chat are just new `type`s;
  multi-party read tracking via `chat_thread_reads` from day one.
- *Cons:* must migrate existing `chat_messages` (backfill a thread per application + repoint rows),
  rewrite `ChatService` + broadcast channel (`chat.thread.{id}`), update the app's existing chat
  screen + `ChatMessageResource`. Regression risk on a live feature.

**Option B — Parallel tables.** Keep application chat exactly as-is; add separate
`community_chats(/_messages/_reads)` and `event_chats` later.
- *Pros:* zero risk to existing collaboration chat; smaller Phase 1.
- *Cons:* diverges from the spec; two/three chat stacks to maintain; app needs adapters to present
  a unified inbox; `/chats` must union heterogeneous sources. Tech debt.

**Recommendation: Option A**, executed safely — *add* `chat_threads` + `thread_id` (nullable) and
**backfill lazily** (create/lookup a thread per application inside `sendMessage`/`getMessages`) so the
old `application_id` column stays during transition and nothing breaks. Migrate reads to
`chat_thread_reads`. This reaches the spec's model without a risky big-bang data migration.

> The rest of this plan assumes **Option A (lazy-backfill)**. If Daniel picks B, Phase 1 shrinks to
> "add `last_message_at` + an active-chats list endpoint over the existing application chat."

---

## Data model (Option A)

```
chat_threads
  id uuid PK
  type            enum  collaboration | community_main | community_custom | event
  community_id    FK communities      NULL
  collaboration_id FK collaborations  NULL          -- (or application_id; see note)
  event_id        FK events           NULL
  slug            string NULL                         -- custom chat key, matched vs tier chat_channels
  name            string NULL
  created_by      FK profiles
  last_message_at timestamp NULL                       -- business "active" filter + sort
  timestamps
  -- exactly one of community_id / collaboration_id set; event_id only with community_id

chat_messages  (add columns; keep application_id during transition)
  + thread_id   FK chat_threads (cascade), nullable until backfilled
  ...existing: sender_profile_id, content, read_at, created_at

chat_thread_reads
  thread_id FK chat_threads
  profile_id FK profiles
  last_read_at timestamp NULL
  UNIQUE (thread_id, profile_id)
```
> Note: today's chat keys off `application_id`; the spec says `collaboration_id`. A `collaboration`
> exists only after acceptance. Decide whether the collaboration thread keys on application (chat can
> start pre-collaboration, matches today) or collaboration. **Likely application** to preserve current
> behaviour — record this when building.

**Access is derived (no membership table):**
- `community_main` → any active `community_members` row for the community.
- `community_custom` → members whose `tier.permissions.chat_channels` contains the thread `slug`.
- `event` → profiles with an `event_signups` row (§Phase 3).
- `collaboration` → the two participants (as `canParticipate` today).

---

## Endpoints (target, `auth:sanctum`)
- `GET /chats` — viewer-visible threads, role-scoped, sorted `last_message_at desc`.
  **Business: only `last_message_at != null`.**
- `POST /communities/{community}/chats` — create `custom` (owner/`can_manage`); **≤5 cap** else
  `422 chat_limit_reached`.
- `POST /events/{event}/chat` — create the `event` thread (owner/`can_manage`).
- `GET /chats/{thread}/messages?page=` — paginated, access-checked.
- `POST /chats/{thread}/messages` — send; set `last_message_at=now()`; push to other members (+@mention).
- `POST /chats/{thread}/read` — set viewer `last_read_at` (in `chat_thread_reads`).
- `GET /chats/unread-count` — total unread across visible threads.

(Existing `/applications/{application}/messages` routes can stay as thin aliases during transition.)

**Resource shapes** (reconcile app + backend) per spec §4: `ChatThread` and `ChatMessage`
(`{id, thread_id, sender:{profile_id,name,avatar_url}, body, created_at, is_mine}`).
Today's `ChatMessageResource` uses `content`/`is_own`/`is_read` — align names with the app.

---

## Phasing (each shippable alone)

### Phase 1 — Business active-chats (smallest, NO RSVP dependency)

> ✅ **BACKEND BUILT 2026-06-04** (Option A, lazy-backfill). 276 tests green, no regressions.
> - Migrations: `chat_threads`, `chat_messages.thread_id` (+index), `chat_thread_reads`,
>   `backfill_chat_threads` (one collaboration thread per existing application convo, no-op on empty DB).
> - `ChatThreadType` enum; `ChatThread` + `ChatThreadRead` models; `ChatMessage` gains `thread_id`+`thread()`.
> - `ChatService::threadForApplication()` (lazy firstOrCreate) + `visibleThreads()` (business filters
>   `last_message_at != null`; transient `unread_count` per thread). `sendMessage()` now writes
>   `thread_id` and bumps `last_message_at`.
> - `ChatThreadResource` (matches spec §4 + `application_id` for Phase-1 app reuse).
> - Routes: `GET /chats`, `GET /chats/unread-count`. Existing `/applications/{id}/messages` untouched.
> - Tests: `tests/Feature/Api/V1/ChatActiveListTest.php` (5).
> **Remaining for Phase 1:** the app-side inbox (below). Optional later: thread-keyed
> `GET/POST /chats/{thread}/messages` + `/read` (app currently reuses `/applications/{id}/messages`).

Backend:
- Add `chat_threads` + `chat_messages.thread_id` + `chat_thread_reads` migrations.
- Lazy-backfill: `ChatService` resolves/creates a `collaboration` thread per application; writes
  go through `thread_id`; set `last_message_at` on send.
- `GET /chats` returning collaboration threads; **business filter `last_message_at != null`**.
- `GET /chats/unread-count` over threads.
- Keep broadcast working (channel `chat.thread.{id}` or keep `chat.application.{id}` alias).
App (`kolabing-app`):
- Inbox icon in `KolabingAppBar` (top-right) + unread badge from `chatsUnreadProvider` (NOT a 6th
  bottom-nav tab). `ChatsScreen` (business = flat active list). Reuse existing thread screen.

### Phase 2 — Community main + custom (≤5)
- Auto-create one `community_main` thread when a community is created (+ backfill existing communities).
- `POST /communities/{id}/chats` custom create with ≤5 cap (`chat_limit_reached`).
- Access: main → any active member; custom → tier `permissions.chat_channels` contains `slug`.
- App: community sections (Main · Custom · Events · Kolabs); attendee tier-filtered list using
  `CommunityTier.TierPermissions.chat_channels`.

### Phase 3 — Event chats (AFTER the RSVP model)
- **Net-new `event_signups`** (`event_id, profile_id, status[going|maybe|declined]`,
  `UNIQUE(event_id,profile_id)`) + `POST /events/{event}/signups`, `GET /events/{event}/signups`.
  Build once — the community-events→Kolab feature needs the same RSVP piece.
- `POST /events/{event}/chat`; access = has an `event_signups` row.

---

## Cross-cutting
- **Paywall:** never gate chat on the business subscription; free businesses keep collaboration chat.
- **Push/@mention:** reuse `NotificationService` + `SendPushNotification`; add @mention targeting.
- **Realtime v1:** poll-on-open + FCM push; websockets/Reverb already exist for collaboration and can
  extend to `chat.thread.{id}` later.
- **App refresh:** use the AsyncNotifier pattern from `2026-06-04-tier-instant-refresh-bug.md` for
  thread/message lists — NOT `FutureProvider + invalidate`.

## Acceptance (from spec §8)
1. Business inbox lists only collaboration chats with ≥1 message; empty otherwise.
2. Community auto-gets one `main`; leader creates ≤5 custom (6th → `chat_limit_reached`).
3. Attendee sees main + only tier-granted customs; not others.
4. Event chat joinable only by RSVP'd members.
5. Sending updates `last_message_at`, pushes to other members, bumps unread.
6. No chat path touches the paywall; collaboration chat works for free businesses.

## Open decisions for Daniel
1. **Option A (unified, recommended) vs B (parallel).**
2. Collaboration thread keys on **application** (preserve pre-collaboration chat, matches today) vs
   **collaboration**.
3. Start now with **Phase 1 backend** (migrations + lazy-backfill + `GET /chats`)?
