# Multi-Kolab Event MVP — API Contract (frozen)

**Status:** Draft frozen for Task 1. Backend (Tasks 2–8) and Flutter (Tasks 9–11)
must both build against this document. Any deviation requires updating this file
in the same PR that introduces it.

**Source plan:** `2026-08-12-multi-kolab-event-mvp.md`.

## 0. Conventions carried over from the existing API (verified against live code)

- Envelope for list endpoints: `{"success": true, "data": [...], "meta": {...}}`
  (`app/Http/Controllers/Api/V1/KolabController.php`).
- Envelope for single-resource endpoints: `{"success": true, "data": {...}}`.
- Envelope for action endpoints with no payload: `{"success": true}`
  (`ReportController::store`).
- Error envelope (from `bootstrap/app.php` + Form Request validation):
  `{"success": false, "message": "...", "errors": {"field": ["..."]}}`.
- Domain errors (e.g. subscription/paywall) are custom exceptions caught by a
  controller/exception-handler mapping to a specific HTTP status — mirrored here
  for role-capacity conflicts and entitlement errors.
- Pagination `meta`: `current_page`, `last_page`, `per_page`, `total`.
- Resources are `JsonResource` classes; enums serialize as `.value` (string).
- All IDs are UUIDs (string).
- Route prefix: `/api/v1`, `auth:sanctum` guard, matching `routes/api.php` groups.

## 1. Entities and enums (per plan)

```
MultiKolabEventStatus: draft | recruiting | confirmed | completed | cancelled | expired
MultiKolabRoleStatus: open | filled | closed
MultiKolabRoleApplicationStatus: pending | shortlisted | accepted | declined | withdrawn
MultiKolabEligibleAccountType: business | community | either
MultiKolabCompensationType: paid | sponsored_in_kind | value_exchange | negotiable
```

## 2. Event Creator entitlement

`GET /api/v1/me/organizer-entitlement`

```json
{
  "success": true,
  "data": {
    "has_event_creator_entitlement": true,
    "capability": "event_creator",
    "granted_at": "2026-08-01T10:00:00Z",
    "expires_at": "2027-08-01T10:00:00Z",
    "source": "maintainer"
  }
}
```

Absence of entitlement: same shape with `has_event_creator_entitlement: false`,
`granted_at`/`expires_at`/`source` null.

## 3. Draft creation / update

`POST /api/v1/multi-kolab-events`

Request:

```json
{
  "title": "Kolabing Launch Weekend",
  "description": "A multi-partner launch event...",
  "value_summary": "Free entry, venue + brand partners wanted",
  "venue_needed": true,
  "date_mode": "exact",
  "event_date": "2026-09-12",
  "date_range_start": null,
  "date_range_end": null,
  "city": "Barcelona",
  "category": "Music",
  "rsvp_url": "https://lu.ma/kolabing-launch",
  "eligible_account_type": "either"
}
```

`date_mode` is `exact` (uses `event_date`) or `range` (uses
`date_range_start`/`date_range_end`). `rsvp_url` must be `https://` (HTTPS-only,
per Global Constraints) or omitted.

Response `201`:

```json
{
  "success": true,
  "data": {
    "id": "8f5b6e2a-...-uuid",
    "status": "draft",
    "creator_profile_id": "profile-uuid",
    "creator_profile_type": "business",
    "title": "Kolabing Launch Weekend",
    "description": "A multi-partner launch event...",
    "value_summary": "Free entry, venue + brand partners wanted",
    "venue_needed": true,
    "date_mode": "exact",
    "event_date": "2026-09-12",
    "date_range_start": null,
    "date_range_end": null,
    "city": "Barcelona",
    "category": "Music",
    "rsvp_url": "https://lu.ma/kolabing-launch",
    "eligible_account_type": "either",
    "roles": [],
    "role_counts": {"total": 0, "open": 0, "filled": 0},
    "created_at": "2026-08-12T09:00:00Z",
    "updated_at": "2026-08-12T09:00:00Z",
    "published_at": null
  }
}
```

`PATCH /api/v1/multi-kolab-events/{event}` — same request shape (partial),
same response shape. Owner-only (403 `not_owner` otherwise).

Draft validation is lenient (no required fields beyond `title`). Publish
validation (below) is strict.

## 4. Partner role

`POST /api/v1/multi-kolab-events/{event}/roles`

Request:

```json
{
  "title": "Run Club Partner",
  "eligible_account_type": "community",
  "positions_needed": 1,
  "required": true,
  "need": "A running route + 20-30 participants",
  "receive": "Free venue, post-run brunch, social tagging",
  "compensation_type": "value_exchange",
  "requirements": "Must be able to commit to the full route",
  "details": "Meet at 8am, 5k loop"
}
```

Response `201`:

```json
{
  "success": true,
  "data": {
    "id": "role-uuid",
    "multi_kolab_event_id": "8f5b6e2a-...-uuid",
    "status": "open",
    "title": "Run Club Partner",
    "eligible_account_type": "community",
    "positions_needed": 1,
    "positions_filled": 0,
    "required": true,
    "need": "A running route + 20-30 participants",
    "receive": "Free venue, post-run brunch, social tagging",
    "compensation_type": "value_exchange",
    "requirements": "Must be able to commit to the full route",
    "details": "Meet at 8am, 5k loop",
    "created_at": "2026-08-12T09:05:00Z",
    "updated_at": "2026-08-12T09:05:00Z"
  }
}
```

`PATCH /api/v1/multi-kolab-roles/{role}`, `DELETE /api/v1/multi-kolab-roles/{role}`
— owner-only. `DELETE` on a role with an accepted application returns `422
role_has_accepted_application`.

## 5. Publish / lifecycle

`POST /api/v1/multi-kolab-events/{event}/publish` → requires
`Profile::hasEventCreatorEntitlement()`. On success: `status → recruiting`,
`published_at` stamped, response = event detail shape (§6).

Entitlement failure: `403`

```json
{"success": false, "message": "Event Creator access is required to publish.", "errors": {"entitlement": ["event_creator_required"]}}
```

Validation failure (missing required fields / no roles / non-HTTPS RSVP):

```json
{"success": false, "message": "This event cannot be published yet.", "errors": {"roles": ["At least one role is required."], "rsvp_url": ["Must use https://."]}}
```

`POST /api/v1/multi-kolab-events/{event}/confirm` → owner-only, manual only,
`recruiting → confirmed`.

`POST /api/v1/multi-kolab-events/{event}/complete` → owner-only, `confirmed →
completed`.

`POST /api/v1/multi-kolab-events/{event}/cancel` → owner-only, body
`{"reason": "..."}` (required, moderated), any non-terminal status → `cancelled`.
Transition table: `cancelled` and `completed` are terminal — no route reopens
them (422 `invalid_transition` on any further mutation).

## 6. Event summary (Explore) / Event detail

Summary (list item, `GET /api/v1/multi-kolab-events?status=recruiting&city=&category=&eligible_account_type=`):

```json
{
  "id": "8f5b6e2a-...-uuid",
  "status": "recruiting",
  "title": "Kolabing Launch Weekend",
  "value_summary": "Free entry, venue + brand partners wanted",
  "city": "Barcelona",
  "category": "Music",
  "event_date": "2026-09-12",
  "date_mode": "exact",
  "role_counts": {"total": 4, "open": 2, "filled": 2},
  "eligible_account_type": "either",
  "creator_profile": {"id": "profile-uuid", "display_name": "Kolabing", "avatar_url": "https://..."}
}
```

Detail (`GET /api/v1/multi-kolab-events/{event}`) = the full shape in §3 plus
`roles: [PartnerRole, ...]` (§4 shape) and, for the viewer's own application if
any, `viewer_application` (§7 shape) or `null`. Detail never exposes other
applicants' pitches or private data (§7 note).

## 7. Role application

`GET /api/v1/multi-kolab-roles/{role}/applications` — **added Task 7** (not in
the original Task 1 freeze). Organizer-only, paginated list of a role's
applications using the same resource shape as the single-application response
below. Needed so the organizer dashboard/applicant-review UI (Task 10) has
something to actually shortlist/decline/accept — the dashboard endpoint (§9)
only returns counts, not the applications themselves. Mirrors the existing
`GET /api/v1/kolabs/{kolab}/applications` (`ApplicationController::forOpportunity`)
convention exactly: same envelope, same pagination `meta`, same ownership
check shape (`403 not_owner`). No new resource, no schema change — reuses the
role-application resource shape from below.

`POST /api/v1/multi-kolab-roles/{role}/applications`

Request:

```json
{
  "pitch": "We run a 150-member Saturday run club and can bring 30+ people.",
  "availability": "Any Saturday in September"
}
```

Response `201`:

```json
{
  "success": true,
  "data": {
    "id": "application-uuid",
    "multi_kolab_role_id": "role-uuid",
    "applicant_profile_id": "profile-uuid",
    "applicant_profile_type": "community",
    "status": "pending",
    "pitch": "We run a 150-member Saturday run club and can bring 30+ people.",
    "availability": "Any Saturday in September",
    "kolab_id": null,
    "created_at": "2026-08-12T09:10:00Z"
  }
}
```

Conflict (duplicate application, unique `(role, applicant)`):

```json
{"success": false, "message": "You have already applied to this role.", "errors": {"application": ["duplicate_application"]}}
```
(HTTP 409)

`POST /api/v1/multi-kolab-role-applications/{application}/shortlist` — owner-only.
`POST /api/v1/multi-kolab-role-applications/{application}/decline` — owner-only.
`POST /api/v1/multi-kolab-role-applications/{application}/withdraw` — applicant-only,
body `{"reason": "..."}` required once accepted (moderated).

## 8. Accept — child-Kolab linkage

`POST /api/v1/multi-kolab-role-applications/{application}/accept`

Response `200`:

```json
{
  "success": true,
  "data": {
    "application": {
      "id": "application-uuid",
      "status": "accepted",
      "kolab_id": "kolab-uuid"
    },
    "kolab": {
      "id": "kolab-uuid",
      "status": "published",
      "collaboration_id": "collaboration-uuid"
    },
    "collaboration": {
      "id": "collaboration-uuid",
      "status": "scheduled",
      "application_id": "canonical-application-uuid"
    }
  }
}
```

Idempotent: a second `accept` call on an already-accepted application with
`kolab_id` set returns the **same** `200` payload (same IDs), never a new Kolab.

Capacity conflict (role already filled by a concurrent accept):

```json
{"success": false, "message": "This role has no remaining positions.", "errors": {"role": ["role_capacity_exceeded"]}}
```
(HTTP 409)

## 9. Organizer dashboard

`GET /api/v1/multi-kolab-events/{event}/dashboard`

```json
{
  "success": true,
  "data": {
    "event_id": "8f5b6e2a-...-uuid",
    "status": "recruiting",
    "role_counts": {"total": 4, "open": 2, "filled": 2},
    "roles": [
      {
        "role_id": "role-uuid",
        "title": "Run Club Partner",
        "positions_needed": 1,
        "positions_filled": 1,
        "status": "filled",
        "application_counts": {"pending": 2, "shortlisted": 1, "accepted": 1, "declined": 0, "withdrawn": 0}
      }
    ]
  }
}
```

## 10. Authorization / validation error shapes (shared)

| Case | HTTP | `errors` key |
|---|---|---|
| Not the event/role owner | 403 | `owner` → `["not_owner"]` |
| Publish without entitlement | 403 | `entitlement` → `["event_creator_required"]` |
| Draft/publish validation | 422 | field-specific |
| RSVP not HTTPS | 422 | `rsvp_url` → `["must_be_https"]` |
| Duplicate application | 409 | `application` → `["duplicate_application"]` |
| Role capacity exceeded | 409 | `role` → `["role_capacity_exceeded"]` |
| Invalid lifecycle transition | 422 | `status` → `["invalid_transition"]` |
| Role removal with accepted application | 422 | `role` → `["role_has_accepted_application"]` |
| Applicant account type ineligible for the role | 422 | `role` → `["role_ineligible"]` |
| Event not accepting applications (not `recruiting`) | 422 | `event` → `["event_not_recruiting"]` |
| Role not accepting applications (not `open`) | 422 | `role` → `["role_not_open"]` |
| Applying to your own event | 422 | `application` → `["cannot_apply_to_own_event"]` (defense-in-depth only — the `create` policy already returns `403 not_owner` for this case before the service is reached; see §10 above) |

**Added in the Phase 5 hardening pass (2026-08 backend hardening turn):** these four codes were genuinely missing — `POST /multi-kolab-roles/{role}/applications` could already reach `role_ineligible`, `event_not_recruiting`, and `role_not_open` in production, but the response fell back to a generic `errors: {base: ["<message>"]}` shape with no stable code, forcing a client to match on the localized message. Thrown as a typed `App\Exceptions\MultiKolabApplicationRejectedException` (carries both `code()` and `field()`), mapped by `MapsMultiKolabExceptions::applicationRejectedResponse()`.

## 11. Canonical acceptance path this feature reuses (traced, Task 1)

- `ApplicationService::accept()` (`app/Services/ApplicationService.php:105`) is
  the **only** existing place that creates a `Collaboration` from an accepted
  `Application`. It is idempotent (`:114-124`: returns the existing pair if
  `application->isAccepted() && collaboration !== null`), runs inside
  `DB::transaction()` (`:128`), and calls the private
  `createCollaboration()` (`:443`) which does a bare `Collaboration::create()`.
  There is **no row locking** (`lockForUpdate()`) in the current method — Task 6
  must add explicit row locks on the `MultiKolabRoleApplication` and
  `MultiKolabRole` (not on the legacy `Application`) inside its own transaction.
- `MultiKolabRoleApplicationService::accept()` (Task 6) will **not** call
  `ApplicationService::accept()` directly (that path re-validates the business
  subscription paywall via `validateCanAccept()`, which must never apply to a
  free Multi-Kolab role application). Instead it creates the canonical
  `Application` (status `Accepted` directly, skipping `Pending`) and
  `Collaboration` records itself, reusing only `createCollaboration()`'s field
  mapping as a reference — mirrored, not called, to avoid the paywall gate.
  **This is a deviation Task 6 must decide explicitly** (see §12 below).

## 12. Moderation interface this feature must call (traced, Task 1)

**Discrepancy from the plan's assumption — flagged for the user, see chat report.**
`app/Services/ModerationService.php` is a **reactive** block/report system (App
Store Guideline 1.2): `block()`, `unblock()`, `blockedIds()`, `report()`. It does
**not** scan, filter, or reject free-text content proactively. There is no
existing profanity/banned-word/text-classifier pipeline in this codebase
(`rg -li "profanity|banned_word|content_filter|blocked_content" app` → no hits).

Consequence for Task 4/5: "route every UGC field through the inspected
moderation service" cannot mean pre-publish text rejection — no such service
exists to call. The two real hooks available are:
1. `ContentReport` — reactive, user-filed, post-publish (existing pattern).
2. Nothing currently blocks a bad word from being saved.

**Decided (founder, before Task 2):** option (a) — Multi-Kolab authored content
behaves exactly like existing `kolabs.title`/`description`: **no proactive
filter, no automatic text rejection, no external AI moderation provider, no new
moderation pipeline.** Do not build any of those. The existing reactive
block/report workflow is the only moderation mechanism, and it must be
extended to cover the new UGC surfaces.

**Binding on Tasks 4, 5, and 7** (not implemented in Task 2 — Task 2 only
establishes model identities/relations that make this possible later):
- `ContentReport.target_type` gains three new values, using the existing
  convention (`target_type` + `target_id` + optional `reported_profile_id`):
  `multi_kolab_event`, `multi_kolab_role`, `multi_kolab_role_application`.
- **Reportable surfaces:**
  - A published `MultiKolabEvent` — reportable by any viewer (mirrors how a
    `Kolab` is reportable today).
  - A `MultiKolabRole` — reportable by any viewer who can see the parent
    event.
  - A `MultiKolabRoleApplication` — reportable **only by the role's event
    organizer** (the only party who can see a given application's `pitch`/
    `availability`). An applicant is never shown another applicant's
    application, so there is no other viewer who could report it.
- **Never publicly expose:** `withdrawal_reason` (role application),
  `cancellation_reason` (event). These stay organizer/applicant-private in
  every public resource, matching how `collaborations.cancellation_reason`
  is never serialized to the counterparty-facing public resource today.
- The existing `ModerationService::notify()` → `ModerationAlertMail` →
  `config('mail.moderation_address')` email workflow is reused unchanged —
  no new notification channel for Multi-Kolab reports.
- This is a documentation-only decision as of Task 2; the report endpoints/UI
  wiring is implemented when Tasks 4/5/7 build the resources and controllers
  that make these surfaces visible.

## 13. Explore feed integration (Task 9 correction, backend)

**Decided:** Multi-Kolab roles are returned **inside the existing**
`GET /api/v1/discovery/opportunities` feed (`DiscoveryOpportunityController` /
`DiscoveryOpportunityService`), not a separate endpoint. This supersedes the
earlier Task 9 Flutter decision to add a standalone
`GET /api/v1/multi-kolab-events` Explore banner/screen — that endpoint (§6)
still exists for the organizer-facing "my events"/detail flows, but is no
longer the applicant discovery surface.

**Why server-side integration was safe here (not a parallel-endpoint
composition):** `DiscoveryOpportunityService::discover()` does not paginate
via SQL `LIMIT`/`OFFSET`. It already executes its full Kolab query with
`->get()`, computes an in-memory match score per row, sorts the resulting
`Illuminate\Support\Collection` in PHP, and only then slices a page with
`->forPage()` before wrapping the slice in a `LengthAwarePaginator`. Because
pagination and sorting already happen in application memory over a fully
materialized collection, merging a second, differently-typed collection
(open Multi-Kolab roles) into that same collection *before* the existing
sort/paginate step requires no SQL-level `UNION` and preserves the exact
same ordering/pagination guarantees the Kolab-only feed already had —
there was no parallel-endpoint-plus-client-composition path to justify
choosing over this.

**Mechanics:**
- Every Kolab row and every eligible open Multi-Kolab role is wrapped as
  `{item_type, model, score, timestamp, sort_date}` before merging; a new
  `DiscoveryOpportunityService::sortCombinedItems()` replaces the old
  Kolab-only `sortScoredResults()` and sorts on those four scalar keys only
  (never on model-specific columns), with the underlying model's UUID as
  the final tie-break for full determinism.
- `DiscoveryOpportunityService::makeMultiKolabRoleBaseQuery()` /
  `applyMultiKolabRoleFilters()` mirror the existing
  `makeBaseQuery()`/`applyCommonFilters()` split — a "before city/search
  filters" existence check feeds the same `empty_reason` logic the Kolab
  feed already had (`no_published_results` vs `no_results_after_filters`).
- Eligibility: `MultiKolabRole.status = open`, `positions_filled <
  positions_needed`, `eligible_account_type` matches the viewer
  (`business`/`either` for a Business viewer, `community`/`either` for a
  Community viewer), the role's event is `status = recruiting`, and the
  event's `creator_profile_id != viewer.id` (organizer exclusion) — all
  enforced in the query, not in PHP after the fact.
- N+1 prevention: one `MultiKolabRole::with(['event.creatorProfile.businessProfile',
  'event.creatorProfile.communityProfile'])->get()` call regardless of role
  count — no per-role query.
- `DiscoveryOpportunityCollection` was changed from a `ResourceCollection`
  (which auto-guesses a single `collects` resource class from its own class
  name and would force every item through `DiscoveryOpportunityResource`)
  to a plain `JsonResource` that inspects each wrapped item's `item_type`
  and routes it to `DiscoveryOpportunityResource` (ordinary Kolab) or the
  new `MultiKolabRoleExploreResource` (Multi-Kolab role).
- `DiscoveryOpportunityResource` gained one additive field,
  `item_type: "kolab"` — the discriminator required by the spec. No
  existing field was renamed, removed, or reshaped; existing clients that
  ignore unknown JSON keys are unaffected (verified by
  `test_ordinary_kolab_items_remain_backward_compatible` and the full
  pre-existing `DiscoveryOpportunityControllerTest` suite, unmodified and
  still green).

**`multi_kolab_role` feed item shape** (`MultiKolabRoleExploreResource`):

```json
{
  "item_type": "multi_kolab_role",
  "id": "role-uuid",
  "multi_kolab_event_id": "event-uuid",
  "role_title": "Run Club Partner",
  "looking_for": {
    "eligible_account_type": "community",
    "required": true
  },
  "event_title": "Kolabing Launch Weekend",
  "city": "Barcelona",
  "target_date": {
    "mode": "exact",
    "date": "2026-09-12",
    "range_start": null,
    "range_end": null
  },
  "compensation": {
    "type": "value_exchange",
    "need": "A running route + 20-30 participants",
    "receive": "Free venue, post-run brunch, social tagging",
    "value_summary": "Free entry, venue + brand partners wanted"
  },
  "positions_needed": 1,
  "positions_filled": 0,
  "positions_remaining": 1,
  "match_score": 40,
  "image_url": "https://example.com/organizer-avatar.jpg",
  "creator_profile": {
    "id": "profile-uuid",
    "display_name": "Kolabing",
    "avatar_url": "https://example.com/organizer-avatar.jpg"
  },
  "rsvp": {"url": "https://lu.ma/kolabing-launch"},
  "published_at": "2026-08-12T09:00:00Z"
}
```

Next to an ordinary item in the same page (`DiscoveryOpportunityResource`,
now carrying `item_type: "kolab"` as its only new field — every other field
is exactly as documented pre-Task-9).

**Deviations from the ideal spec, documented:**
- **Match %:** `match_score` for a Multi-Kolab role is a much simpler
  freshness (+10/+5 within 7/30 days) + city-match (+40) heuristic than the
  Kolab feed's full multi-signal breakdown (category/value/location/past-
  activity weighted `match_breakdown`). Building an equivalent
  role-vs-viewer affinity model was out of scope for this correction; there
  is no `match_breakdown` on Multi-Kolab items, only the scalar
  `match_score`. Flagged as a reasonable scope boundary, not an oversight.
- **Image:** `MultiKolabEvent` has no media/cover-photo column at all (the
  Task 2 migration never added one). `image_url` falls back to the
  organizer's `avatar_url` only — the same *ultimate* fallback
  `DiscoveryOpportunityResource::resolveCoverPhotoUrl()` uses for a Kolab
  with no media, but Multi-Kolab items never get the "actual uploaded
  photo" tier a Kolab can have. Adding an event cover-photo column was a
  schema change outside this correction's scope — flagged, not implemented.
- **Bookmarks:** `rg -li bookmark app/` returns no hits — this codebase has
  no bookmark/save model at all for either Kolabs or Multi-Kolab roles, so
  "provide a typed target if the current bookmark model can't safely
  reference a role id" is moot; there is nothing to extend. If a bookmark
  feature is added later, `(target_type, target_id)` should follow the same
  polymorphic convention already used by `ContentReport` (§12).
- **`ending_soon` sort for Multi-Kolab roles:** there is no per-role
  deadline field, so `sort_date` for a role reuses its parent event's
  target date (`event_date` for `date_mode = exact`, else
  `date_range_end ?? date_range_start`) — the closest existing analogue to
  a Kolab's `availability_end ?? availability_start`.
