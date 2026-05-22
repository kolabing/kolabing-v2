# BE-2026-05-22 · Match Taxonomy Audit

**From**: `H4` in the discovery and matching feedback list

The mobile app now renders match breakdowns (`H1`), offer headlines (`H2`), and denser discovery cards (`H5`). That makes taxonomy problems more visible, not less.

This ticket is the backend/product audit that decides whether the category model and score weighting are actually coherent.

---

## Problem statement

External user feedback surfaced a trust gap:

- food community saw a higher score for a coworking than for a cafe
- score was opaque enough that the user could not tell whether that was intentional or a bug

Once the app shows score breakdowns, the backend signal list and category vocabulary must be defensible.

---

## Required work

### 1. Single source of truth for categories

Audit business types, community types, seeking-community labels, and match inputs.

Need one normalized vocabulary shared across:

- onboarding selection
- profile update
- kolab publish
- discovery matching
- score breakdown labels

### 2. Cross-mapping rules must be explicit

Define what qualifies as:

- direct category fit
- adjacent category fit
- non-category boosts from location, audience size, or freshness

If a food community matches a coworking highly, that needs to be because the weights intentionally say so, not because taxonomy is leaking.

### 3. Rebalance or justify weights

Decide whether category fit should be first-impression dominant in discovery.

If yes:

- increase category contribution enough that obvious vertical matches outrank distant ones unless other signals are very strong

If no:

- document the rationale and keep the breakdown labels honest so frontend can explain it clearly

---

## Acceptance

- category vocabulary is centralized enough that onboarding/publish/discovery do not drift
- score breakdown labels correspond to the actual live algorithm
- a seeded regression fixture proves the intended ordering for category-first cases
- backend/product sign off whether category or non-category signals dominate first-impression ranking

---

## Related mobile work already shipped

- `kolabing-app/lib/widgets/match_breakdown.dart`
- `kolabing-app/lib/features/discovery/models/discovery_item.dart`
- `kolabing-app/lib/widgets/explore_swipe_card.dart`

This ticket is not blocked on more mobile UI. It is a scoring and taxonomy decision.
