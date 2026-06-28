# Business Kolab creation flow redesign

**Date:** 2026-06-24
**Repos affected:** `kolabing-v2` (Laravel backend, this repo) + `kolabing-app` (Flutter app, sibling repo)
**Goal:** Make business Kolab creation feel like "tell us what you want to promote, we'll help shape it" instead of "fill a long campaign form" — mostly UX/copy/field-order changes, minimal backend changes.

## Background

Audited the current flow (`kolabing-app/lib/features/kolab/`):

- 7-step wizard: Venue/Product Details → Media → Offering → Ideal Community → Past Events → Availability → Review, orchestrated by `KolabFlowScreen` (`screens/kolab_flow_screen.dart`).
- State lives in `Kolab` model (`models/kolab.dart`) + `KolabFormNotifier` (`providers/kolab_form_provider.dart`).
- Submission: `KolabService.create/update/publish` → `POST/PUT /api/v1/kolabs`, body = `Kolab.toJson()`.
- "Best-fit communities" already exists as `seeking_communities` (mapped from the `ideal_community_screen.dart` chip picker). "What would you like from community" already exists as `expects` (a `DeliverableType` enum, validated server-side against the `offer_options` admin-managed taxonomy table). "What you'll offer back" is `offers_in_return`, same taxonomy (`OfferOption`, kind `deliverable`).
- No "goal" field or free metadata bucket exists anywhere on `kolabs` or `collab_opportunities`.
- `OfferOption` (`app/Models/OfferOption.php`, table `offer_options`) is an existing **admin-managed taxonomy**: columns `kind` (free string, no DB enum), `name`, `slug`, `icon`/`icon_url`, `sort_order`, `is_active`. Full CRUD admin UI (`OfferOptionController`), public lookup API (`LookupController::offerOptionResponse($kind)` backing `GET /api/v1/lookup/{offerings,deliverables,needs,product-types,venue-types}`). Existing kinds: `offering`, `deliverable`, `need`, `product_type`, `venue_type`.
- A separate, unrelated taxonomy (`CommunityType` model / `community_types` table, managed via `TypeController`) already backs `GET /api/v1/lookup/community-types` — currently NOT used by the business "Ideal Community" step, which instead hardcodes 16 chips directly in `ideal_community_screen.dart:37-54`.
- Media step already supports picking from existing gallery/venue photos (`ExistingPhotoPickerSheet`) but currently *requires* at least one photo even when defaults exist.
- Availability floor is "today" unconditionally (`availability_screen.dart:42-43`), enforced Flutter-side only.
- "Past Events" payload key (`past_events`) and field shape are unrelated to its on-screen label, so it can be relabeled freely.

## Decisions

1. **Goal field**: one migration adds two nullable additive columns to `kolabs` — `goal` (string) and `highlights` (json) — this is the only schema migration in this task. Goal *options* are NOT hardcoded — they're a new admin-managed `OfferOption` kind (`goal`), validated server-side, fetched dynamically by the app, just like the existing 5 kinds. Same for `highlights` (kind `kolab_highlight`) — these selections persist after submission (used on the review/listing card now, and for future filtering), not discarded.
2. **All other new chip/option lists** (product-interaction options, venue-fit options, "why communities will like this" highlight chips) are likewise new `OfferOption` kinds — no schema migration, just data + the same mechanical registration every existing kind already has (model constant, admin controller label/resolve/in-use-count entries, one `LookupController` method + route). Seeders provide only the **initial defaults**; admins can add/edit/deactivate/reorder afterward via the existing `OfferOptionController` UI without a migration or app release.
3. **`expects`/`offers_in_return`** (deliverable kind) gets its existing 5 broad seed rows replaced/extended with ~11 more granular rows (Minimum attendance, Minimum revenue/spend, Tagged stories, Instagram post/Reel, UGC/content, Reviews, Product feedback, Community photos, Newsletter mention, Long-term partnership, Open to ideas) — same kind, no new kind needed, no migration.
4. **Best-fit communities chips**: stop hardcoding in Dart; fetch the existing `GET /api/v1/lookup/community-types` endpoint instead (zero backend change — endpoint already exists and is unused by this screen today). Still writes to `seeking_communities` (no field rename).
5. Everything else (field reordering, copy, availability floor logic, media defaulting, review-screen layout, Past Events relabel) is **frontend-only** — no backend change, no payload key changes.

## Backend changes (kolabing-v2)

### Migration
- One migration, two nullable additive columns on `kolabs`: `goal` (`string`, 50) and `highlights` (`json`, defaults to `[]`). `highlights` holds the slugs selected on the "Why communities will like this" step (validated against the new `kolab_highlight` `OfferOption` kind, same pattern as `expects`/`offers_in_return`). These are additive-only — no existing column is touched, no data migration needed.
- Before adding any mirrored columns: first confirm whether `collab_opportunities` is still read in any live business Kolab creation/read path (vs. legacy/dead per the `inverse_bridge_backfill` migration noted in the audit). If it is legacy/read-only and not part of any live create/edit path, **do not touch it** — only mirror `goal`/`highlights` there if skipping them would break read/write compatibility for a still-active flow.

### `OfferOption` taxonomy additions
Register 3 new kinds, following the exact existing pattern for `venue_type`/`product_type`:
- `OfferOption::KIND_GOAL = 'goal'`
- `OfferOption::KIND_PRODUCT_INTERACTION = 'product_interaction'`
- `OfferOption::KIND_VENUE_FIT = 'venue_fit'`
- `OfferOption::KIND_KOLAB_HIGHLIGHT = 'kolab_highlight'`

For each: add to `OfferOption::KINDS`; add label + `resolveKind()` + `inUseCount()` entries in `OfferOptionController`; add one `LookupController` method + route:
- `GET /api/v1/lookup/goals`
- `GET /api/v1/lookup/product-interactions`
- `GET /api/v1/lookup/venue-fits`
- `GET /api/v1/lookup/kolab-highlights`

### Seeder updates (`OfferOptionSeeder`)
- `goal`: More visits, Product awareness, Content / tagged posts, Reviews, Sales / revenue, Community event, Product testing, Recurring partnership, Community perk / member discount, Open to ideas.
- `product_interaction`: Try samples, Review it, Create content, Use it during an event, Give feedback, Offer it as a giveaway, Promote a discount code, Sell it during an event, Open to ideas.
- `venue_fit`: Coffee, Brunch, Dinner, Drinks, Wellness, Shopping, Workshops, Content, After-run, After-work, Networking, Pop-ups, Recurring plans.
- `kolab_highlight`: Good location, Nice space for groups, Great photo spot, Healthy / sporty offer, Free samples, Discount for members, Good for after-work plans, Good after a workout, Can host recurring Kolabs, Unique experience, New product to try, Premium experience, Easy to reach by public transport, Outdoor-friendly, Cozy indoor space, Good for content.
- `deliverable`: additive only — **do not delete or replace** the existing 5 broad rows. Add the ~11 granular rows alongside them. Keep the existing 5 active if any live Kolab references them (check via `inUseCount()`/usage query before considering deactivation). Only deactivate an old broad option later, in a separate change, if confirmed unused — never as part of this PR.

### Request validation / resource output
- `CreateKolabRequest` / `UpdateKolabRequest`: add `'goal' => 'nullable|string|in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_GOAL))`.
- Kolab API resource: include `goal` in the response payload (mirrors `offer_headline`/`base_offer` handling).

## Frontend changes (kolabing-app)

All within `lib/features/kolab/`.

1. **New Goal step** (after Kolab type, before Details): fetch `/lookup/goals`, single-select chips, subtitle "Pick the main goal. This helps communities understand the opportunity." Stored on `Kolab.goal`, surfaced as a badge on Review.
2. **Details step** (`venue_details_screen.dart` / `product_details_screen.dart`): updated titles/subtitles/helper-text examples per spec. Venue screen gains a "Best for:" chip multi-select fetching `/lookup/venue-fits`. Product screen gains "How do you want communities to interact with your product?" multi-select fetching `/lookup/product-interactions`.
3. **Offering step** (`offering_screen.dart`): reorder to `offerHeadline` (top, visually primary, required, obviously marked) → `baseOffer` (main offer, clearly primary, required or explicitly explained when optional) → "what would you like from the community" (multi-select over expanded `deliverable` options fetched from `/lookup/deliverables`, includes "Open to ideas") → capacity/limits if already supported. Add tip-box copy per spec.
4. **Best-fit communities** (rename `ideal_community_screen.dart` title/copy; replace hardcoded `_communityTypes` list with a fetch from `/lookup/community-types`). Keep writing to `seeking_communities`. Drop "skip" framing in copy.
5. **Past Events → "Why communities will like this"** (`past_events_screen.dart`): relabel title/subtitle; add new chip multi-select fetching `/lookup/kolab-highlights`, writing to the new `Kolab.highlights` field; add optional free-text "Anything else communities should know?" field (folds into existing `description`/notes field if one already fits, otherwise frontend-only for now — no further backend field needed for the free text). Keep existing name/date/partner/photo fields but reframe as optional supporting evidence, not the primary ask. Payload key stays `past_events` — no other model/API change.
6. **Availability** (`availability_screen.dart`): add "Immediate / always available" mode option. Only that mode allows `today` as `firstAllowedDate`; all other modes default the floor to `tomorrow`. Update validator copy accordingly.
7. **Media** (`media_screen.dart`): when a business profile photo or existing gallery/venue photo is available, do not block submission on a fresh upload — default to "Use business profile photo" or "Choose from existing photos" as satisfying the requirement. Keep upload optional. If truly no photo exists anywhere, show a warning/tip instead of a hard block, if current product rules allow.
8. **Review** (`review_screen.dart`): restyle as a listing-style preview card: photo, offer headline, goal badge, type label, best-fit communities, main offer, what's asked of the community, availability, location, highlights. No match-quality scoring.

## Out of scope

- Community-side flow (not touched, except shared components must keep working).
- Match-quality scoring on review.
- Full admin-dashboard taxonomy rebuild beyond the mechanical per-kind registration described above.
- Renaming any existing payload field/key.

## Open implementation-time questions to confirm before coding starts

- Confirm `collab_opportunities` is fully legacy/read-only before deciding whether `goal`/`highlights` need mirroring there too.
