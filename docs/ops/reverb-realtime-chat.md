# Reverb Realtime Chat Ops Runbook

## Production environment

Set these values on the production app server:

```dotenv
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

Keep `QUEUE_CONNECTION` backed by Redis or the database. Do not set it to `sync`, or `ShouldBroadcast` events will appear wired but never leave the process.

## Persistent daemons

Run these as long-lived processes on the host:

```bash
php artisan reverb:start
php artisan queue:work --queue=default
```

Reverb handles websocket connections and queue work drains the broadcast jobs.

## TLS and proxy

Expose `wss://ws.kolabing.com` on port 443 and proxy websocket traffic to the local Reverb port. The proxy needs the standard upgrade headers:

```nginx
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection "Upgrade";
proxy_http_version 1.1;
```

Use Forge's Reverb integration when available; otherwise configure the reverse proxy and certificate manually.

## Auth and contract

The app authorizes private chat subscriptions through `POST /broadcasting/auth` at the app root with a Sanctum Bearer token.

Do not change these values without coordinating the mobile app:

- Channel: `private-chat.thread.{threadId}`
- Event name: `message.sent`
- Payload wrapper: `{ "message": <ChatMessageResource> }`

## Smoke test

1. Send a message through the API.
2. Confirm the `reverb:start` process logs the broadcast for `chat.thread.{id}`.
3. Confirm `queue:work` consumes the job instead of leaving it queued.
4. Confirm an unauthorized profile gets rejected by `/broadcasting/auth`.
