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

**Decision needed before Task 4** (flagged to the user, not resolved by Task 1):
either (a) treat "moderation" for Multi-Kolab text fields as identical to how
`kolabs.title`/`description` are handled today (no proactive filter, reactive
report only — consistent with existing UGC), or (b) introduce a new proactive
filter as net-new scope, which is not in the plan's file list and would need
its own task. Recommendation: (a), for consistency and to avoid scope creep;
`ContentReport` `target_type` gains `multi_kolab_event`, `multi_kolab_role`,
`multi_kolab_role_application` values so existing reporting UI/flow covers the
new UGC surfaces without new code paths.
