# BE-XXX · `POST /api/v1/collaborations/{id}/feedback`

**From**: Mobile post-completion feedback flow (D3 follow-up)

The mobile app now prompts the **business** to fill in a short feedback survey immediately after marking a collaboration as `completed`. Frontend is implemented and ships with the assumption that this endpoint exists. Until then, the mobile client logs the payload and surfaces a non-blocking error toast on failure — no UX regression — but the data is dropped on the floor.

This ticket adds the persistence + GET counterpart.

---

## Audience & Trigger

- Authored by the **business party** of the collaboration (community feedback is a v2 follow-up; do not block on it).
- Trigger: client calls this endpoint once after the business taps "Mark as completed" and submits the post-completion survey.
- The survey is also reachable from the completed-detail screen via a "Leave review" CTA if the user dismissed the sheet — server must allow a single submission only.

---

## Schema (new table)

`collaboration_feedback`

| Column | Type | Notes |
|---|---|---|
| `id` | `uuid` (pk) |  |
| `collaboration_id` | `uuid` (fk → `collaborations.id`, indexed, **unique per author_profile_id**) | one feedback per author per collab |
| `author_profile_id` | `uuid` (fk → `profiles.id`) | the business profile id |
| `author_user_type` | `text` (`business` only for v1; nullable-future for `community`) |  |
| `star_rating` | `smallint` (1–5, NOT NULL) | overall rating |
| `stories_posted_bucket` | `text` (`0`, `1-5`, `6-10`, `11-15`, `16-30`, `31-50`, `50+`) | nullable |
| `posts_reels_bucket` | `text` (same enum) | nullable |
| `revenue_amount_cents` | `bigint` (nullable) | EUR cents; null = skipped |
| `revenue_currency` | `text` default `EUR` | future-proofing |
| `met_expectations_rating` | `smallint` (1–5) nullable | optional |
| `met_expectations_comment` | `text` nullable | optional free-text |
| `would_recommend` | `boolean` NOT NULL |  |
| `created_at` / `updated_at` | timestamps | |

Recommended index: `(collaboration_id, author_profile_id)` unique to enforce single submission.

Also add a `feedback_submitted_at` column on `collaborations` (or compute via join) so the mobile client can hide the "Leave review" CTA once submitted — return it inside the completed collaboration JSON.

---

## Endpoints

### `POST /api/v1/collaborations/{id}/feedback`

**Auth:** business party of `{id}` only (Policy: `CollaborationFeedbackPolicy@create`).

**Pre-conditions:**
- Collaboration `status` must be `completed`. Otherwise 422 `collaboration_not_completed`.
- No prior feedback by this author for this collaboration. Otherwise 422 `feedback_already_submitted`.

**Request body:**

```json
{
  "star_rating": 4,
  "stories_posted_bucket": "11-15",
  "posts_reels_bucket": "1-5",
  "revenue_amount_cents": 28000,
  "revenue_currency": "EUR",
  "met_expectations_rating": 5,
  "met_expectations_comment": "Far exceeded what we hoped for — packed venue.",
  "would_recommend": true
}
```

- `star_rating` and `would_recommend` are **required**.
- All other fields are optional; null = user skipped.
- `revenue_amount_cents`: integer; reject negatives (422).
- Bucket enums: validate against the enum above; reject anything else (422).

**Response (201):**

```json
{
  "data": {
    "feedback": { ...row above with id + created_at },
    "collaboration": { ...updated collaboration with feedback_submitted_at }
  }
}
```

### `GET /api/v1/collaborations/{id}/feedback`

**Auth:** either party of the collaboration.

Returns the business's submitted feedback (404 if none).

Used by the mobile detail screen to:
- Show "Leave review" CTA only when this returns 404 (and the caller is the business).
- Display the submitted feedback summary inline once it exists.

---

## State & side-effects

- No status transition on the collaboration itself.
- Optional: add the feedback presence to the dashboard/analytics aggregate (avg rating per community, recommend rate). Not blocking — can ship without.
- Notification to the community party that they received a review: nice-to-have, not blocking.

---

## Acceptance

- Business can submit feedback exactly once per completed collaboration.
- Second submission returns 422 with `feedback_already_submitted`.
- Submitting before status is `completed` returns 422 `collaboration_not_completed`.
- Community party cannot POST (403). Community party CAN GET.
- Validation enforces enums, the 1–5 rating ranges, and non-negative cents.
- The completed collaboration JSON includes `feedback_submitted_at` (nullable) so the client knows whether to show the "Leave review" CTA.

---

## Mobile contract reference

Mobile-side implementation lives at:
- Model: `lib/features/collaboration/models/collaboration_feedback.dart`
- Submit provider: `lib/features/collaboration/providers/collaboration_feedback_provider.dart`
- UI: `lib/features/collaboration/widgets/collaboration_feedback_sheet.dart`
- Trigger: `_FinishCollaborationSection` in `lib/features/collaboration/screens/collaboration_detail_screen.dart` after `markCollaborationCompleted` resolves.

Until this endpoint ships, the mobile client logs the payload via `debugPrint('[FB]')` and surfaces a soft error toast — no crash, no data persisted server-side.
