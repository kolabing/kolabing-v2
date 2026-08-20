# Profile & Portfolio Panel — past events, photos, gallery (BE-NF-35)

> Design spec. Date: 2026-08-20.
> Governed by `docs/ROLES-AND-PERMISSIONS.md` and `docs/ROLES-BACKEND-DB-MAP.md`.
> Applies to **business and community** accounts. Nothing here is paywalled.

---

## 1. Why this exists

A community or business builds credibility on **what they have already done** — the
events they ran, who they ran them with, and the photographs. The backend has carried
that concept from the start:

- **Past events** are first-class. `POST /events` has a retrospective branch
  (`name`, `partner_name`, `partner_type`, `date`, `attendee_count`, `photos[]`) that
  is distinct from the upcoming-community-event branch. `PUT`/`DELETE` work on them,
  and `GET /events?time=past&profile_id=…` lists them.
- **Event photos** have their own store (`event_photos`), endpoints
  (`POST|DELETE /events/{event}/photos`) and a cap of 20 per event, 5 per request.
- **Profile gallery** has its own store (`profile_gallery_photos`), endpoints
  (`GET|POST|DELETE /me/gallery`) and a cap of 20, 5 per request.
- `CommunityPublicProfileResource` **already publishes** `gallery`, `photos`,
  `past_events`, `past_events_count` and `past_collaborations`.

**None of it is reachable from the web.** There is no events UI and no gallery UI
anywhere in `resources/views/webapp/`. The only profile surface is `/account`, which
edits text fields and notification preferences. So a leader can publish a portfolio
that the public profile renders, but only from a mobile app that has not shipped the
screens either.

## 2. The three defects this closes

| # | Defect | Evidence |
|---|--------|----------|
| D1 | **No web UI for past events or their photos at all.** | `grep` over `resources/views/webapp/` finds no `/events` or `/me/gallery` call |
| D2 | **The gallery cannot actually be *managed*.** `profile_gallery_photos` has `caption` and `sort_order`, but no endpoint writes either after upload — there is no reorder and no caption edit. `event_photos.sort_order` is likewise set only at insert. | `routes/api.php`: gallery has only index/store/destroy |
| D3 | **Business accounts have no public portfolio.** `CommunityPublicProfileResource` emits `gallery` / `photos` / `past_events`; `PublicProfileResource` (business + attendee) emits none of them. A business can create past events today — `EventPolicy@create` returns `true` for everyone — and nothing ever renders them. | both resources compared |

## 3. Locked scope decisions

| Decision | Choice |
|---|---|
| Release scope | **Portfolio only** — past events + their photos + the profile gallery. Upcoming community events (capacity, tier gate, visibility, recurrence, signups, check-in QR, challenges, rewards) are a separate subsystem and get their own spec. |
| Roles | **Business and community at full parity**, including extending `PublicProfileResource` so a business portfolio is actually visible. |
| Placement | `/account` becomes a **tabbed Profile section** in the sidebar: Details · Gallery · Past events · Preview · Settings. Same place for both roles. |
| Photo model | **Kept separate, in their natural homes.** The gallery is its own tab; an event's photos are edited inside that event. No unified "Media" view — it would put two different tables behind one ambiguous uploader. |

**Out of scope by decision:** upcoming/recurring events, signups, check-in QR,
event challenges and rewards, blocks, account deletion, gamification views.

## 4. Backend

### 4.1 Gallery becomes manageable (D2)

Two new endpoints. Both are self-scoped (`/me/…`), so authorization is ownership of
the row — the existing `destroy` already enforces exactly that and is the pattern to
follow.

```
PATCH /me/gallery/{photo}        { caption?: string|null }         → 200
PUT   /me/gallery/order          { ids: [uuid, …] }                → 200
```

- `PATCH` edits the caption only. `caption` is `nullable|string|max:500`, matching the
  column.
- `PUT …/order` takes the full ordered list of the caller's photo ids and writes
  `sort_order = index`. Ids that do not belong to the caller are **ignored, not
  written** — the same rule the community bulk-update follows. Any of the caller's
  photos missing from the list keep their relative order after the supplied ones, so a
  partial list can never silently hide a photo.
- Both run in one transaction; the response is the caller's full ordered gallery, so
  the client never has to guess the resulting order.

`GET /me/gallery` must order by `sort_order` (then `created_at`) — verify it does, and
fix it if not, otherwise reordering has no visible effect.

### 4.2 Event photos become orderable (D2)

```
PUT /events/{event}/photos/order  { ids: [uuid, …] }               → 200
```

Same semantics and same guard as the existing photo endpoints (creator, or
`can_manage` on the event's community). Ids not belonging to `{event}` are ignored.

### 4.3 Business public portfolio (D3)

`PublicProfileResource` gains `gallery`, `past_events` and `past_events_count`, built
the same way `CommunityPublicProfileResource` builds them — via preloaded attributes
set by the controller, never a query per row inside the resource.

**This is an additive mobile contract change** and must be recorded as such. Nothing is
removed or renamed.

Attendees keep the current payload: the portfolio block is emitted for `business` and
`community` profiles only. An attendee's gallery stays private to their own account.

### 4.4 What is deliberately NOT changed

- `POST /events` keeps both branches exactly as they are. The retrospective branch
  already requires 1–5 photo **files** at create; the web form matches that rather than
  inventing a photo-less draft state.
- `EventPolicy` is untouched: `create` is open, `update`/`delete` are owner-only. The
  panel only ever lists and edits the caller's own events.
- No new table. No migration.

## 5. Frontend — `app.kolabing.com`

`/account` is promoted from a single page to a section with a tab strip, following the
Community Hub's pattern (`community-nav.blade.php`) so the two read as one system.

| Route | Tab | Contents |
|---|---|---|
| `/account` | **Details** | today's profile form, unchanged — name, about, type, categories, city, socials, logo/photo |
| `/account/gallery` | **Gallery** | grid of up to 20 photos; multi-select upload (≤5 per request, chunked so a 12-photo drop works); drag-to-reorder writing `PUT /me/gallery/order`; inline caption edit; delete with in-page confirm; a live "N/20" counter |
| `/account/events` | **Past events** | list of `GET /events?time=past&profile_id=<me>`, newest first; "Log a past event" form (name, partner name, partner type, date, attendee count, 1–5 photos); per-event editor with its own photo manager (add / delete / reorder, 20 cap) |
| `/account/preview` | **Preview** | the public profile exactly as others see it, from `GET /profiles/{me}/public-profile` — the answer to "where does this show up?" |
| `/account/settings` | **Settings** | notification preferences, already built, moved under the tab |

Conventions carried over from the Hub: Blade + Alpine + the existing `window.kb`
client, no npm change; `kb.uploadFile`-style multipart via `kb.upload`; no
`window.confirm` anywhere; empty states whose primary CTA is the upload; es/ca at 100%
key parity.

**Upload UX detail that matters:** both stores cap a request at 5 files but allow 20
total. The uploader therefore chunks a larger selection into sequential requests and
reports per-chunk failures, rather than rejecting the drop or silently truncating it.

## 6. Testing

`LazilyRefreshDatabase`, PHPUnit, factories.

| File | Covers |
|---|---|
| `tests/Feature/Api/V1/GalleryManageTest.php` | caption edit; caption cleared with `null`; reorder writes `sort_order`; foreign ids ignored, never written; omitted photos keep relative order; non-owner 403; `GET /me/gallery` returns in `sort_order` |
| `tests/Feature/Api/V1/EventPhotoOrderTest.php` | reorder; ids from another event ignored; creator and `can_manage` both allowed; a stranger 403 |
| `tests/Feature/Api/V1/BusinessPublicPortfolioTest.php` | a business public profile emits `gallery` + `past_events` + `past_events_count`; an attendee's does not; **a query-count assertion** proving the block is O(1) in photos/events |
| `tests/Feature/WebApp/WebAppRoutesTest.php` (extend) | the five `/account/*` routes render at `/`, `/es`, `/ca`; the new tabs are localised; the `public/` shadow check already added covers `account` |

## 7. Docs

- `docs/ROLES-AND-PERMISSIONS.md` — a short section: what a business/community may
  publish about past work, and that it is free for both roles. Bump *Last updated*.
- `docs/ROLES-BACKEND-DB-MAP.md` — the three new endpoints, the `PublicProfileResource`
  addition, and the gallery ordering guarantee. Bump *Last updated*.
- `BACKLOG.md` — BE-NF-35. Bump *Last updated*.
- PR: **Mobile impact is required** — `PublicProfileResource` gains three additive
  fields, and three new endpoints are available for the app to adopt.

## 8. Build order

1. Gallery manage endpoints + tests *(D2)*
2. Event photo order endpoint + tests *(D2)*
3. `PublicProfileResource` portfolio block + tests *(D3)*
4. `/account` → tabbed section; Details + Settings moved across unchanged
5. Gallery tab *(D1)*
6. Past events tab, including the per-event photo manager *(D1)*
7. Preview tab
8. es/ca, docs, pint, full suite
