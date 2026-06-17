# Ticket — Turn on real-time chat: Laravel Reverb (Part A — backend/ops)

> **Owner:** Volkan (ops/infra).
> **Companion:** the Flutter client (Part B) is **already built** in `kolabing-app`
> on branch `feat/realtime-chat-reverb-client` — it ships dormant and activates
> the moment this is deployed + `REVERB_APP_KEY` is handed back (see "What the app
> needs from you" at the bottom).
> **Status:** the broadcast CODE already exists. This is an **env + daemons + TLS**
> job — almost no new PHP.

## TL;DR — what's already done (do NOT rebuild)
- `app/Events/NewChatMessage.php` — `ShouldBroadcast`, broadcasts on
  `PrivateChannel('chat.thread.{id}')` (+ legacy `chat.application.{id}`),
  `broadcastAs() = 'message.sent'`, `broadcastWith() = ['message' => ChatMessageResource]`.
- `routes/channels.php` — `Broadcast::channel('chat.thread.{threadId}', ...)` auth
  is already wired (reuses `ChatService::canAccessThread`).
- `bootstrap/app.php` — `withBroadcasting()` registers `POST /broadcasting/auth`
  (Sanctum-guarded) and loads `routes/channels.php`.

So the app already *emits* broadcasts and the channel is *authorized*. They go
nowhere only because **no Reverb server is running and no queue worker drains the
broadcast job**. This ticket makes them flow.

---

## The work (ops)

### 1. Install Reverb (if not already)
```
composer require laravel/reverb
php artisan reverb:install   # writes REVERB_* to .env, sets BROADCAST_CONNECTION
```

### 2. Production env
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...      REVERB_APP_KEY=...      REVERB_APP_SECRET=...
REVERB_HOST=ws.kolabing.com   REVERB_PORT=443   REVERB_SCHEME=https
QUEUE_CONNECTION=redis        # or database — just NOT sync (see #4)
```
The client-facing trio the app needs: **`REVERB_APP_KEY`, `REVERB_HOST` (`ws.kolabing.com`), `REVERB_PORT` (443), `REVERB_SCHEME` (https → app uses `wss`)**.

### 3. Reverb daemon  ← required
`php artisan reverb:start` as a **persistent Forge daemon** (Supervisor-managed,
auto-restart on deploy). Forge → Daemons → command `php artisan reverb:start`,
directory = site root.

### 4. Queue worker  ← #1 cause of "it's silent"
`NewChatMessage implements ShouldBroadcast` → the broadcast is **queued**. With
`QUEUE_CONNECTION` not `sync`, nothing is delivered without a worker:
`php artisan queue:work --queue=default` as a **second Forge daemon**. Skip this and
everything looks wired but no message ever arrives.

### 5. Nginx / TLS for WebSockets
Expose `wss://` on 443 for `ws.kolabing.com` → proxy to the Reverb port
(`127.0.0.1:8080`) with upgrade headers:
```nginx
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection "Upgrade";
proxy_http_version 1.1;
```
Forge's built-in Reverb integration sets most of this up — prefer it if available.
Issue/renew the TLS cert for `ws.kolabing.com`.

### 6. CORS / allowed origins
Ensure Reverb `config/reverb.php` `apps.*.allowed_origins` permits the mobile app
(usually `['*']` for a native app, or your domains). Sanctum stateful domains are
irrelevant here — the app authorizes with a **Bearer token**, not cookies.

### 7. Smoke test (server side)
With both daemons up: send a message via the API, then:
- `reverb:start` logs show a broadcast on `chat.thread.{id}`.
- `queue:work` drains the job (queue does not pile up in `jobs`/Horizon).

---

## ⚠️ Contract the app depends on — do NOT change without telling Volkan
The Flutter client (Part B) is coded to today's contract. If you change any of
these, ping so the app config is updated in lockstep:
- **Channel:** `private-chat.thread.{threadId}` (Pusher `private-` prefix).
- **Event name:** `message.sent` (from `broadcastAs()`). *(The app also binds the
  class name `NewChatMessage` and `App\Events\NewChatMessage` as a safety net, but
  `message.sent` is the real one.)*
- **Payload:** `{ "message": <ChatMessageResource> }` — the app reads `data.message`
  and parses the resource (`id`, `thread_id`, `content`, `sender_profile{id,name,
  avatar_url}`, `created_at`, `is_own`). Keep the `message` wrapper.
- **Auth route:** `POST /broadcasting/auth` at the **app root** (NOT under
  `/api/v1`), Sanctum Bearer. The app posts `socket_id` + `channel_name` with the
  `Authorization: Bearer` header. If you relocate this route, tell Volkan (app has
  `RealtimeConfig.authEndpoint`).

## Acceptance criteria
- Two devices, same thread: a message sent on A appears on B **without refresh**, < 1s.
- A user NOT permitted in the thread is rejected at `/broadcasting/auth` (cannot subscribe).
- Killing the queue worker stops delivery; restarting resumes it (confirms the queue path).
- App reconnects after backgrounding and still receives (client auto-reconnects).

## Gotchas
- **No queue worker = no delivery** (#4) — check this first if it's silent.
- Use `wss://` (not `ws://`) in prod or iOS ATS blocks the socket.
- `BROADCAST_CONNECTION` must be `reverb` and `QUEUE_CONNECTION` must not be `sync`.

---

## What the app needs back from you (to flip it live)
The Flutter client is **dormant until a Reverb app key is set** — by design (no
reconnect storms before the server exists). Once deployed, send Volkan:
1. `REVERB_APP_KEY` (public key) — goes into the app build via
   `--dart-define=REVERB_APP_KEY=...` (read by `lib/config/constants/realtime.dart`).
2. Confirm `REVERB_HOST=ws.kolabing.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`
   (app defaults already assume these — correct if different).
3. Confirm `/broadcasting/auth` path if you moved it.

That's the only handoff — no further app code change is required to go live.

## References
- Event: `app/Events/NewChatMessage.php`
- Channel auth: `routes/channels.php` (`chat.thread.{threadId}`) + `app/Services/ChatService.php::canAccessThread`
- Broadcast registration: `bootstrap/app.php` (`withBroadcasting`)
- App client (Part B): `kolabing-app` → `lib/features/chat/services/realtime_chat_service.dart`,
  `lib/config/constants/realtime.dart`, wired in `lib/features/chat/screens/chat_thread_screen.dart`.
