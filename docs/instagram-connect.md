# Instagram Connect (BE-NF-75)

Businesses and communities connect their own Instagram **professional** account (Business or Creator) and import their
own photos and videos into their Kolabing gallery or one of their own Kolabs. Rules: `ROLES-AND-PERMISSIONS.md` §2.21.
Code map: `ROLES-BACKEND-DB-MAP.md` §41. Tracking: #362 (part of BE-NF-75 / #345).

API used: **Instagram API with Instagram Login** (`graph.instagram.com`), permission `instagram_business_basic`.
Personal accounts cannot connect (Meta shut the Basic Display API in Dec 2024); the app shows the
"switch to professional" helper. Business Discovery by handle (no login) is separate: #345.

## Meta app setup checklist (Daniel)

Kolabing uses its **own** Meta app (one Meta app per brand, decision 24 Sep 2026).

1. developers.facebook.com → Create app → type **Business**, linked to the Kolabing business portfolio.
2. Add the product **Instagram** → **API setup with Instagram business login**.
3. Business login settings:
   - **OAuth redirect URI:** `https://kolabing.com/instagram/callback` (must equal `INSTAGRAM_REDIRECT_URI` exactly).
   - **Deauthorize callback URL:** `https://kolabing.com/instagram/deauthorize`
   - **Data deletion request URL:** `https://kolabing.com/instagram/data-deletion`
4. Permission: **`instagram_business_basic`** only.
5. Copy the **Instagram app ID** and **Instagram app secret** (Instagram product page, not the Facebook app id) into
   Laravel Cloud env: `INSTAGRAM_APP_ID`, `INSTAGRAM_APP_SECRET`.
6. **Testers** (before App Review): App roles → Roles → Add people → **Instagram Tester**, enter the Instagram username;
   the person accepts in Instagram → Settings → Website permissions → Apps and websites → Tester invites. Then add
   their **Kolabing profile id** to `INSTAGRAM_TESTER_PROFILE_IDS` (comma-separated) so the app shows them the card.
7. App settings → Basic: privacy policy URL `https://kolabing.com/privacy`, terms URL `https://kolabing.com/terms`, app icon, category.
8. **App Review** for `instagram_business_basic` (Advanced Access): needs a **screencast** of the full flow — log in to
   Kolabing, Connect Instagram, Instagram consent screen, return to the app, pick media, import, see it on the
   profile, disconnect — plus a written use-case description and test credentials. Business verification of the
   portfolio is required for Advanced Access.
9. After approval: switch the app to **Live** and set `INSTAGRAM_ENABLED=true`.

## Config

| Env | Default | Meaning |
|---|---|---|
| `INSTAGRAM_ENABLED` | `false` | On for every business and community. Off = only testers. |
| `INSTAGRAM_APP_ID` / `INSTAGRAM_APP_SECRET` | — | Instagram app credentials. Missing = feature off for everyone. |
| `INSTAGRAM_REDIRECT_URI` | `https://kolabing.com/instagram/callback` | OAuth redirect URI. |
| `INSTAGRAM_TESTER_PROFILE_IDS` | empty | Kolabing profile ids that may connect while disabled. |
| `INSTAGRAM_MAX_VIDEO_MB` | `50` | Per-video size cap on import (the API returns no duration; the size cap bounds length). |
| `INSTAGRAM_WEB_RETURN_URL` | `https://app.kolabing.com/settings` | Where the web flow lands (`?instagram=ok|error&reason=`). |

The app flow lands on `kolabing://instagram/connected?status=ok|error&reason=…`.
Reasons: `denied`, `invalid_state`, `personal_account`, `permission_denied`, `disabled`, `error`.

## Endpoints (all JSON `{"success": true, "data": …}`)

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/me/instagram` | `{connected, username, account_type, profile_picture_url, connected_at, last_synced_at, enabled, avatar_suggested}` |
| POST | `/api/v1/me/instagram/connect-url` | body `{return?: "app"\|"web"}` → `{url}`; `state` encrypted, bound to the profile, 15 min, single use |
| GET | `/api/v1/me/instagram/media?cursor=` | `{items:[{id, media_type, media_url, thumbnail_url, permalink, caption, timestamp, imported}], next_cursor}` |
| POST | `/api/v1/me/instagram/media/import` | `{ids:[…] (max 10), target:"gallery"\|"kolab", kolab_id?}` → `{target, items, skipped:[{id, reason}]}` |
| POST | `/api/v1/me/instagram/sync` | 202 `{queued:true}`; refreshes profile + first media page |
| POST | `/api/v1/me/instagram/avatar` | uses the Instagram picture only when the profile has no photo; else 409 |
| DELETE | `/api/v1/me/instagram` | forgets the token; imported media stays |
| GET | `/instagram/callback` | OAuth redirect URI (web) |
| POST | `/instagram/deauthorize` | Meta, `signed_request`; forgets the token |
| POST | `/instagram/data-deletion` | Meta, `signed_request`; deletes connection + imported media; returns `{url, confirmation_code}` |
| GET | `/instagram/data-deletion/{code}` | human-readable status page |

Error codes (`code` on non-2xx): `INSTAGRAM_UNAVAILABLE` 404, `INSTAGRAM_NOT_CONNECTED` 409,
`INSTAGRAM_TOKEN_EXPIRED` 409 (the account is disconnected; ask to connect again), `INSTAGRAM_PERSONAL_ACCOUNT` 422,
`INSTAGRAM_PERMISSION_DENIED` 403, `INSTAGRAM_GALLERY_FULL` 422, `INSTAGRAM_AVATAR_ALREADY_SET` 409,
`INSTAGRAM_API_ERROR` 502.

Import skip reasons: `already_imported`, `not_found`, `not_owner`, `no_media_url` (Instagram omits the file for media
with copyrighted audio), `no_thumbnail`, `too_large`, `download_failed`, `invalid_file`, `unsupported_type`,
`limit_reached`.

## Tokens

Code (1 h, single use) → short-lived token (1 h) → long-lived token (60 days), stored encrypted. The nightly
`instagram:refresh-tokens` (05:00) refreshes tokens last refreshed 50+ days ago and disconnects expired or refused ones.
A token Instagram rejects during any call (error 190) disconnects the account and the app gets `INSTAGRAM_TOKEN_EXPIRED`.
