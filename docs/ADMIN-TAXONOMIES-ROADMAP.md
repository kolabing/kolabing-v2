# Admin-managed taxonomies roadmap — remove all hard-wired pickable data

> Goal (Daniel, 2026-06-16): every USER-PICKABLE taxonomy comes from an
> admin-managed source (DB + admin CRUD + `/lookup/*` endpoint), the app fetches
> it dynamically, and no hardcoded list/enum drives a picker. Status/role/lifecycle
> enums stay code-fixed (they are contracts, not catalogs).
> Source: cross-repo audit 2026-06-16. Reuse the existing AdminLTE Blade CRUD
> pattern (`AdminTypeController` / the `feat/admin-offer-taxonomy` `OfferOptionController`).

## A. Needs admin CRUD (still hard-wired) — the work

| Taxonomy | Used for | Backend today | App today | Work needed |
|---|---|---|---|---|
| **product_type** ⭐ | kolab Product-promotion picker + (new) product onboarding | hardcoded enum `app/Enums/ProductType.php` (8) + inline `in:` in `CreateKolabRequest.php:94`/`UpdateKolabRequest.php:76`; column is unconstrained string | hardcoded Dart enum `lib/features/kolab/enums/product_type.dart` | add as `offer_options` kind (or `product_types` table) + seed 8 + `/lookup/product-types` + switch validation to DB slugs; app `productTypesProvider`; point BOTH kolab picker and product-onboarding picker at it |
| **venue_type** | venue onboarding + kolab venue-promotion | hardcoded enum `app/Enums/VenueType.php` (11) + inline `in:` `CreateKolabRequest.php:88` | hardcoded Dart enum `lib/features/kolab/enums/venue_type.dart` | same pattern: table/kind + seed 11 + `/lookup/venue-types` + DB-slug validation; app `venueTypesProvider` |
| **offering** | kolab business "what I offer" | hardcoded `OFFERING_VALUES` `CreateKolabRequest.php:18-29` — **fixed on branch `feat/admin-offer-taxonomy`** (`offer_options` kind=offering, `/lookup/offerings`) | hardcoded `_OfferingOption` `offering_screen.dart:27-76` | merge branch (backend done); app `offeringsProvider` to replace static list |
| **needs** | kolab community "what I need" | hardcoded `in:` `CreateKolabRequest.php:77` — **fixed on branch** (kind=need, `/lookup/needs`) | hardcoded enum `need_type.dart` | merge branch; app `needsProvider` |
| **deliverables** (offers_in_return / expects) | kolab offered-in-return | hardcoded `in:` `CreateKolabRequest.php:83,117` — **fixed on branch** (kind=deliverable, `/lookup/deliverables`) | hardcoded enum `deliverable_type.dart` | merge branch; app `deliverablesProvider` |

⭐ = the item that triggered this roadmap (product_type picker must match the kolab one and be admin-managed).

## B. Already admin-managed (verify on deploy branch)
- **business_types** — DB table + `applies_to`/`icon`, `AdminTypeController` `/admin/types?kind=business`, `/lookup/business-types` (DB-backed on master), app `businessTypesProvider` ✅.
- **community_types** (17) — DB table, `AdminTypeController` `/admin/types?kind=community`, `/lookup/community-types`, app `communityTypesProvider` ✅.
- **cities** — `cities` table (is_active/sort_order) + `city_suggestions` + `POST /cities/suggest`, app `citiesProvider` ✅. **Gap:** no admin Cities UI → add `AdminCityController` (CRUD + activate/reorder + suggestions inbox).
- **gamification** (badges/challenges/xp levels/earn rules/economics/partner rewards) — full admin CRUD on master ✅ (the pattern to copy).
- ⚠️ The checked-out branch `feat/onboarding-backend-local` LookupController still hardcodes the type lists — it is behind master; **merge master forward** so business/community types serve DB rows.

## C. Leave code-fixed (NOT admin-editable — contracts/state machines)
`intent_type`, `venue_preference`, `availability_mode`, `media.type`, `ApplicationStatus`, `CollaborationStatus`, `KolabStatus`, `UserType`, `OfferStatus`, `RewardClaimStatus`, `SubscriptionStatus`, `WithdrawalStatus`, `NotificationType`, `EventSignupStatus`, `CommunityMemberStatus`. These are lifecycle/role/branching enums, not pickable catalogs.

## D. App-only cleanup
- Retire the PLACEHOLDER `enum CommunityType {greek,fitness,running,business,other}` in `lib/features/community/models/community.dart:7-12` (collapses unknowns to `other`); use raw `typeSlug` + `communityTypesProvider`. (Violates CANONICAL-LISTS.md today.)
- After A lands, replace the 5 hardcoded kolab enums/lists with the new providers.
- Update `docs/CANONICAL-LISTS.md` to add product_type, venue_type, offering, needs, deliverables with their `/lookup/*` endpoints + providers (currently undocumented).
- **categories**: no first-class managed taxonomy exists (only Google Places import categories) — DECISION NEEDED on whether categories become a managed taxonomy.

## E. Sequencing
1. Merge `feat/admin-offer-taxonomy` → master (offering/needs/deliverables admin-managed; backend done).
2. Add `product_type` + `venue_type` as offer_options kinds (or sibling tables) + `/lookup/product-types`, `/lookup/venue-types` + DB-slug validation.
3. Add Cities admin module (+ suggestions inbox).
4. App: 5 new providers (offerings, needs, deliverables, product_types, venue_types) replacing hardcoded enums; retire community.dart placeholder enum; product-onboarding product_type picker reads `productTypesProvider`.
5. Ensure deploy branch == master for the DB-backed type lookups.
6. Extend CANONICAL-LISTS.md.
