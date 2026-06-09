# Reverb Realtime Chat Part A Ops Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** make the existing Laravel Reverb chat path production-ready by exposing the remaining ops knobs, documenting the daemon/TLS requirements, and preserving the current broadcast contract.

**Architecture:** the codebase already emits `NewChatMessage` broadcasts on `chat.thread.{id}` and authorizes subscriptions through `POST /broadcasting/auth`. This plan only tightens the Reverb configuration surface and records the exact production setup needed for `ws.kolabing.com`, a queue-backed broadcast path, and a mobile-friendly origin policy.

**Tech Stack:** Laravel 12, Laravel Reverb, Sanctum, queue workers, Nginx/Forge.

---

### Task 1: Make Reverb allowed origins configurable

**Files:**
- Modify: `config/reverb.php`
- Modify: `.env.example`

- [ ] **Step 1: Write the failing test**

This change is config-only, so there is no behavior test to write without adding a new config loader abstraction. Validate the default shape by inspecting the generated config after the patch.

- [ ] **Step 2: Implement the env-driven origin list**

```php
$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('REVERB_ALLOWED_ORIGINS', '*'))
)));

if ($allowedOrigins === []) {
    $allowedOrigins = ['*'];
}
```

Replace the hard-coded `allowed_origins` array with `$allowedOrigins` so production can keep `['*']` for native apps or narrow it to comma-separated domains later.

- [ ] **Step 3: Update the example environment**

```dotenv
# Laravel Reverb (WebSocket - Chat)
REVERB_APP_ID=my-app-id
REVERB_APP_KEY=my-app-key
REVERB_APP_SECRET=my-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SERVER_PORT=6001
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=*
```

Keep the local defaults untouched, but make the allowed-origin knob explicit so prod can override it without editing `config/reverb.php`.

- [ ] **Step 4: Run a quick config sanity check**

Run: `php artisan config:clear && php artisan tinker --execute="dump(config('reverb.apps.apps.0.allowed_origins'))"`

Expected: the default output includes `'*'`.

### Task 2: Record the production Reverb runbook

**Files:**
- Create: `docs/ops/reverb-realtime-chat.md`

- [ ] **Step 1: Write the production checklist**

Include the exact production values the ticket depends on:

```md
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=ws.kolabing.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_ALLOWED_ORIGINS=*
```

Document the two persistent daemons:

```bash
php artisan reverb:start
php artisan queue:work --queue=default
```

Document the websocket proxy requirements for `ws.kolabing.com`:

```nginx
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection "Upgrade";
proxy_http_version 1.1;
```

- [ ] **Step 2: Add the smoke test**

Record the exact verification path:

1. Send a chat message from the API.
2. Confirm `reverb:start` logs the broadcast on `chat.thread.{id}`.
3. Confirm `queue:work` drains the broadcast job.
4. Confirm an unauthorized profile is rejected by `POST /broadcasting/auth`.

- [ ] **Step 3: Commit**

```bash
git add config/reverb.php .env.example docs/plans/2026-06-09-reverb-realtime-chat-part-a-ops.md docs/ops/reverb-realtime-chat.md
git commit -m "docs: record reverb realtime chat ops"
```
