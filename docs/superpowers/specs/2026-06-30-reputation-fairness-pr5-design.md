# Design: PR 5 — Reputation Fairness (computed at read time)

**Date:** 2026-06-30  
**Branch:** `feat/reputation-fairness-pr5` (to be created from `master`)  
**Scope:** Backend (`kolabing-v2`) only. No mobile contract breaking changes — the public `reputation` object already exists on `GET /api/v1/profiles/{id}`; we're adjusting its values and removing `unique_partner_count` from the public shape.

---

## Problem

The current `getReputationSummary` counts every review from every completed Kolab. A business and a community could do 5 Kolabs together, each leaving a review each time, and all 5 reviews would inflate the reviewed profile's public rating — even though a single partner relationship dominates the score.

---

## Solution: per-pair cap, computed at read time

A `(reviewer_profile_id, reviewed_profile_id)` pair may contribute **at most 2 reviews** to public reputation aggregation. Reviews beyond the 2nd are saved and remain visible on the Kolab record, but are excluded from all public aggregates. No schema migration needed — enforced entirely via a SQL window-function subquery at query time.

---

## Components

### 1. `ProfileService::getReputationSummary` — window-function subquery

Replace the flat `CollaborationReview::query()` aggregate with a two-layer query:

**Inner query** — selects all reviews for the profile from completed Kolabs with a `ROW_NUMBER()` window function:

```sql
SELECT *,
  ROW_NUMBER() OVER (
    PARTITION BY reviewer_profile_id
    ORDER BY created_at ASC
  ) AS pair_review_rank
FROM collaboration_reviews
WHERE reviewed_profile_id = :profile_id
  AND rating IS NOT NULL
  AND collaboration_id IN (
    SELECT id FROM collaborations WHERE status = 'completed'
  )
```

**Outer query** — wraps the inner as a subquery and filters `pair_review_rank <= 2` before aggregating AVG/COUNT.

> **Note on window function partitioning:** We partition by `reviewer_profile_id` only (not `(reviewer_profile_id, reviewed_profile_id)`) because `reviewed_profile_id` is already fixed to the profile being queried. The effect is identical to partitioning by the pair.

**Return shape (unchanged keys, `unique_partner_count` removed from public surface):**

```php
[
    'average_rating'  => float|null,   // rounded to 1 decimal
    'review_count'    => int,          // capped count (≤ 2 per pair)
    'breakdown'       => array|null,   // per-dimension averages, same as before
]
```

`unique_partner_count` is still computed internally (useful for admin) but **not** included in the value returned from this method — it would be misleading after the cap is applied.

### 2. Public profile resource — `reputation` shape

`PublicProfileResource::toArray()` calls `getReputationSummary`. After this change:

- Remove `unique_partner_count` from the returned array.
- Keep `completed_kolabs_count` as a **top-level field** on the resource (it is already there and displayed standalone by mobile).
- `reputation` object exposed to mobile: `{ average_rating, review_count, breakdown }`.

### 3. `recent_reviews` — `is_verified_kolab_review` flag

Every review surfaced via `buildRecentReviews()` already passes through `whereHas('collaboration', completed)`. Add `is_verified_kolab_review: true` as a constant field in `PublicProfileReviewResource`. This is always `true` for now (all surfaced reviews are from completed Kolabs) but wires the concept cleanly for future conditional logic.

### 4. Admin review list — `⚠ Excluded` badge

**`ReviewController::index`:** After paginating, compute which reviews on the current page are "excluded" (would be pair_review_rank > 2). Use the same window-function logic scoped to the reviewer/reviewed pairs present on the page. Pass a `$excludedReviewIds` collection to the view.

**`admin/reviews/index.blade.php`:** In the Reviewer column, append a `⚠ Excluded from reputation` badge (Bootstrap `badge-warning`) for any review whose ID is in `$excludedReviewIds`. No new route or action — read-only enrichment of the existing list.

---

## What does NOT change

- The unique constraint `(collaboration_id, reviewer_profile_id)` — one review per collaboration per reviewer. This stays and is orthogonal to the pair cap.
- The review submission flow — no blocking, no new validation. Reviews are always accepted.
- The `recent_reviews` list on public profile — still shows latest 3, regardless of cap status.
- The `note` / `body` / `public_comment` fields — still stored and visible on the Kolab record.

---

## Tests

Update / extend `ProfileReputationTest`:

| Scenario | Expected `review_count` | Expected `average_rating` |
|---|---|---|
| 1 review from pair A | 1 | average of that review |
| 2 reviews from pair A | 2 | average of both |
| 3 reviews from pair A (3rd is worst rating) | 2 | average of first 2 only |
| 2 reviews pair A + 1 review pair B | 3 | average of all 3 |
| 3 reviews pair A + 2 reviews pair B | 4 | average of first 2 from A + both from B |

Add an admin feature test: seed a pair with 3 reviews, hit `GET /admin/reviews`, assert the third review row contains "Excluded from reputation".

---

## Out of scope for PR 5

- Blocking or rate-limiting review submission.
- Surfacing excluded count on the profile admin page (Option B).
- `unique_partner_count` as a public-facing field.
- Any mobile changes (the `reputation` shape change is additive removal — `unique_partner_count` was never displayed by mobile per prior discussion).
