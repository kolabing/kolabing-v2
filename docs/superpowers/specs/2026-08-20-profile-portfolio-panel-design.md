# Profile & Portfolio Panel — past events, photos, gallery (BE-NF-35)

> Design spec. Date: 2026-08-20. **Revision 2** — revision 1 mis-diagnosed the
> public-profile gap; corrected below against the live schema and prod data.
> Governed by `docs/ROLES-AND-PERMISSIONS.md` and `docs/ROLES-BACKEND-DB-MAP.md`.
> Applies to **business and community** accounts. Nothing here is paywalled.

---

## 1. Why this exists

A business or community earns trust on **what they have already done** — the events
they ran, who they ran them with, and the photographs. The backend has carried that
concept from the start; none of it is reachable from the web.

`grep` over `resources/views/webapp/` finds no call to `/events` or `/me/gallery`.
The only profile surface is `/account`, which edits text fields and notification
preferences.

## 2. The finding that shapes this design: there are TWO past-event stores

This is the correction to revision 1, and it drives everything below.

| Store | What it is | Written by | Prod volume | Rendered on the public profile? |
|---|---|---|---|---|
| **`events` table** (rows with `event_date` in the past) | Real rows with `partner_name`, `partner_type`, `attendee_count`, and their own `event_photos` (≤20 each) | the retrospective branch of `POST /events` | **60 events, 173 photos** — business 26/76, community 34/97 | **No** |
| **`kolabs.past_events`** | A free-form JSON array on each Kolab: `{name, date, partner_name, photos[≤3 urls]}` | `PUT /kolabs/{id}` (`UpdateKolabRequest` accepts it) | **7 kolabs** | **Yes** — `ProfileService::buildCommunityPastEvents()` reads only this |

So the store almost nobody uses is the one on show, and **173 photographs that
businesses and communities already uploaded are invisible to the public.** That is
the single highest-value fix in this spec, and it is a service-layer change, not a
migration.

**Revision 1 was wrong about two things**, both corrected here:
- It claimed businesses have no public portfolio. They do:
  `GET /profiles/{profile}/public-profile` serves **business or community** the rich
  payload (`CommunityPublicProfileResource`), and `getPublicProfileDetail()` explicitly
  accepts both. Only the *light* `GET /profiles/{profile}` lacks it.
- It assumed "past events" meant the `events` table. The public profile has never
  read that table.

## 3. The three defects this closes

| # | Defect | Evidence |
|---|--------|----------|
| D1 | **No web UI for past events, event photos, or the gallery.** | no `/events` or `/me/gallery` call anywhere in `resources/views/webapp/` |
| D2 | **The gallery cannot be *managed*.** `profile_gallery_photos` carries `caption` and `sort_order`, but no endpoint writes either after upload — no reorder, no caption edit. `event_photos.sort_order` is likewise only set at insert. | `routes/api.php`: gallery has index/store/destroy only; `EventService` writes `sort_order` at insert only |
| D3 | **The `events` store is publicly invisible.** 60 past events and 173 photos exist; the public profile reads only `kolabs.past_events` (7 kolabs). | `ProfileService::buildCommunityPastEvents()` queries `Kolab` only |

## 4. Locked scope decisions

| Decision | Choice |
|---|---|
| Which past events | **Both stores.** Manage `events`-table past events in the panel, make `kolabs.past_events` editable on web, and **merge both into the public `past_events`** so the 173 photos surface. |
| Roles | **Business and community at parity.** |
| Light endpoint | `GET /profiles/{profile}` gains the **full portfolio** (`gallery`, `past_events`, `past_events_count`). Safe: `PublicProfileResource` is instantiated in exactly one place, for a single profile — never in a collection — so there is no list-payload cost. (Revision 1 warned about list bloat; that warning was unfounded.) |
| Placement | `/account` becomes a **tabbed Profile section**: Details · Gallery · Past events · Preview · Settings. Same for both roles. |
| Photo model | **Separate, in their natural homes.** Gallery in its own tab; an event's photos inside that event. No unified "Media" view — it would put three different stores behind one ambiguous uploader. |

**Out of scope by decision:** upcoming/recurring community events, signups, check-in
QR, event challenges and rewards, blocks, account deletion, gamification views.

## 5. Backend

### 5.1 Merge both past-event stores (D3)

`ProfileService::buildCommunityPastEvents()` currently returns Kolab-sourced items:

```php
['source_kolab_id' => …, 'name' => …, 'date' => …, 'partner_name' => …, 'media' => [...]]
```

It gains a second source — the caller's own past `events` rows with their
`event_photos` — and returns the union, newest first.

**Unified item shape** (additive; every existing key is preserved so no client breaks):

```php
[
    'source'          => 'kolab'|'event',   // NEW discriminator
    'source_kolab_id' => string|null,       // null for event-sourced items
    'source_event_id' => string|null,       // NEW, null for kolab-sourced items
    'name'            => string|null,
    'date'            => string|null,
    'partner_name'    => string|null,
    'attendee_count'  => int|null,          // NEW, only ever set for event-sourced
    'media'           => array,             // same normalizeMediaCollection shape
]
```

Rules:
- **Source query:** `events` where `profile_id = <profile>` and `event_date < today`,
  eager-loading `photos` ordered by `sort_order`. One query plus one for the photos —
  never per-event.
- **Ordering:** by `date` descending across the merged set; items with a null date sort
  last, so a malformed Kolab entry can never take the top slot.
- **Dedup:** two items with the same case-insensitive `name` **and** the same `date`
  collapse to one, keeping the **event-sourced** copy (it carries `attendee_count` and
  a real photo store). This stops a Kolab entry that describes the same evening from
  showing twice.
- `past_events_count` in `community_public_stats` follows the merged list.
- `buildCommunityPhotos()` already folds past-event `media` into `photos`, so the newly
  surfaced images flow into that block with no change.

**Mobile impact:** additive keys on an existing array, plus more items in it.

### 5.2 The light public profile gains the portfolio

`ProfileController@publicProfile` hydrates via `getPublicProfileDetail()` for
`business` and `community` profiles, and `PublicProfileResource` emits `gallery`,
`past_events` and `past_events_count`.

`getPublicProfileDetail()` throws `ModelNotFoundException` for an attendee, so the
attendee path must **not** call it: attendees keep exactly the payload they have today,
and their gallery stays private to their own account. This is a guard, not an
afterthought — getting it wrong turns every attendee profile into a 404.

### 5.3 Gallery becomes manageable (D2)

Two new self-scoped endpoints; authorization is ownership of the row, matching the
existing `destroy`.

```
PATCH /me/gallery/{photo}   { caption?: string|null }   → 200, the updated photo
PUT   /me/gallery/order      { ids: [uuid, …] }         → 200, the full ordered gallery
```

- `caption`: `nullable|string|max:500`, matching the column.
- `order`: writes `sort_order = index` for the supplied ids. Ids not belonging to the
  caller are **ignored, never written**. Any of the caller's photos absent from the list
  keep their relative order *after* the supplied ones — a partial list can never hide a
  photo. One transaction; the response is the full ordered gallery so the client never
  guesses.

`GET /me/gallery` already orders by `sort_order` then `created_at desc` — verified, no
change needed.

### 5.4 Event photos become orderable (D2)

```
PUT /events/{event}/photos/order   { ids: [uuid, …] }   → 200, the ordered photos
```

Same guard as the existing photo endpoints — `EventPhotoController::canManageEvent()`
(creator, or `can_manage` on the event's community). Ids not belonging to `{event}` are
ignored.

### 5.5 What is deliberately NOT changed

- `POST /events` keeps both branches. The retrospective branch already requires 1–5
  photo **files** at create; the web form matches that rather than inventing a
  photo-less draft.
- `UpdateKolabRequest` already accepts `past_events` (name/date/partner_name/≤3 photo
  URLs) — the web Kolab form simply starts sending it. No request change.
- `EventPolicy` untouched: `create` open, `update`/`delete` owner-only.
- **No new table, no migration.**

## 6. Frontend — `app.kolabing.com`

`/account` is promoted to a section with a tab strip, following
`community-nav.blade.php` so the two panels read as one system.

| Route | Tab | Contents |
|---|---|---|
| `/account` | **Details** | today's profile form, moved across unchanged |
| `/account/gallery` | **Gallery** | grid of ≤20; multi-select upload chunked at 5/request; drag-to-reorder → `PUT /me/gallery/order`; inline caption edit → `PATCH /me/gallery/{photo}`; delete with in-page confirm; live "N/20" counter |
| `/account/events` | **Past events** | `GET /events?time=past&profile_id=<me>`, newest first. "Log a past event" form (name, partner name, partner type, date, attendee count, 1–5 photos). Per-event editor with its own photo manager: add (≤5/request, ≤20 total), delete, drag-to-reorder |
| `/account/preview` | **Preview** | the public profile as others see it, from `GET /profiles/{me}/public-profile` — the answer to "where does this show up?" |
| `/account/settings` | **Settings** | notification preferences, moved across unchanged |

Plus one addition outside the section: **`kolab-form.blade.php` gains a "Past events"
repeater** (name, date, partner name, up to 3 photo URLs via `kb.uploadFile`), sending
`past_events` on `PUT /kolabs/{id}`. This is the other authoring path for the same
public block, and leaving it app-only would keep half the feature unreachable.

Conventions carried over: Blade + Alpine + the existing `window.kb` client, no npm
change; no `window.confirm`; purposeful empty states whose CTA is the upload; es/ca at
100% key parity.

**Upload detail that matters:** every store caps a request at 5 files but allows 20
total, so the uploader chunks a larger selection into sequential requests and reports
per-chunk failures — rather than rejecting the drop or silently truncating it.

## 7. Testing

`LazilyRefreshDatabase`, PHPUnit, factories.

| File | Covers |
|---|---|
| `tests/Feature/Api/V1/GalleryManageTest.php` | caption set and cleared with `null`; reorder writes `sort_order`; foreign ids ignored and unwritten; omitted photos keep relative order; non-owner 403; `GET /me/gallery` returns in `sort_order` |
| `tests/Feature/Api/V1/EventPhotoOrderTest.php` | reorder; ids from another event ignored; creator and `can_manage` both allowed; a stranger 403 |
| `tests/Feature/Api/V1/PastEventsMergeTest.php` | both sources appear; `source`/`source_event_id`/`attendee_count` correct per source; ordering by date desc with nulls last; same name+date dedupes to the event-sourced copy; `past_events_count` matches; **a query-count assertion** proving the merge is O(1) in events |
| `tests/Feature/Api/V1/PublicProfilePortfolioTest.php` | business and community `GET /profiles/{id}` emit `gallery` + `past_events` + `past_events_count`; **an attendee profile still returns 200** with the old payload and no portfolio |
| `tests/Feature/WebApp/WebAppRoutesTest.php` (extend) | the five `/account/*` routes render at `/`, `/es`, `/ca`; new tabs localised |

## 8. Docs

- `docs/ROLES-AND-PERMISSIONS.md` — what a business/community may publish about past
  work; free for both roles. Bump *Last updated*.
- `docs/ROLES-BACKEND-DB-MAP.md` — the three new endpoints, the two-store merge and its
  item shape, the light-profile portfolio, and the attendee guard. Bump *Last updated*.
- `BACKLOG.md` — BE-NF-35. Bump *Last updated*.
- PR **Mobile impact is required**: `past_events` items gain `source`,
  `source_event_id`, `attendee_count` and the array grows; `GET /profiles/{id}` gains
  three fields; three new endpoints are available to adopt.

## 9. Build order

1. Gallery manage endpoints + tests *(D2)*
2. Event photo order endpoint + tests *(D2)*
3. Past-events merge in `ProfileService` + tests *(D3 — the highest-value change)*
4. Light public profile portfolio + attendee guard + tests
5. `/account` → tabbed section; Details + Settings moved across unchanged
6. Gallery tab *(D1)*
7. Past events tab incl. the per-event photo manager *(D1)*
8. Preview tab
9. Kolab form past-events repeater
10. es/ca, docs, pint, full suite
