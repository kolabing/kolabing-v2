# DECISION (2026-06-10): community/business types — the **table** is the single source of truth

> Owner directive (Daniel). Written for whoever is on
> `feat/attendee-onboarding-identity` (the community-type remap) so we don't
> build two competing sources. **Please read before touching type vocabularies.**

## The decision
`community_types` and `business_types` **tables** become the **single source of
truth** for the type vocabularies. Admins must be able to **add / edit / remove
(deactivate) types and upload an SVG icon at runtime** — that requires a DB
store, so the PHP constant cannot remain the source.

This means **`CommunityOnboardingRequest::COMMUNITY_TYPES` and
`BusinessOnboardingRequest::BUSINESS_TYPES` are RETIRED as the source of truth**
(kept in code, marked `@deprecated`, never deleted — per "don't delete anything").

## Your remap is KEPT and stays compatible
`070000_remap_communities_type_to_real_slugs` is **correct and stays** — it
normalises `communities.type` onto the 17 real slugs, which is exactly right.
The table will be seeded with the **same 17 underscore slugs** (and the 10
business slugs), so every value your remap produces remains valid. Nothing you
shipped is being reverted.

## Target design (what I'm building, owning the type-source migrations)
1. **Seed** `community_types` (17 slugs) + `business_types` (10 slugs) to the
   **exact live underscore vocabulary** — matching your remap + stored data.
   Add `icon_url` (nullable) for uploaded SVGs; keep `icon` (bundled-SVG key).
2. **Repoint** `GET /lookup/community-types` + `/lookup/business-types` and all
   **validation** (`StoreCommunityRequest::type`, `UpdateProfileRequest`
   `community_type`/`business_type`/`categories`, kolab `community_types`) to
   read/validate against the **tables** (`exists:*_types,slug`), not the
   constants.
3. **Admin CRUD** at `/admin/types` (tabs: community / business): add, edit,
   reorder (drag-drop), deactivate (= "remove", never hard-delete when in use),
   in-use counts, and **SVG icon upload** (→ cloud disk → `icon_url`).
4. **App** (`kolabing-app`): `category_icon.dart` falls back to rendering the
   network SVG from `icon_url` when a slug has no bundled asset — so
   admin-added types show their uploaded icon (existing types keep bundled SVGs).

## Please DON'T (to avoid re-colliding)
- Don't add new type values to the constants — add them via the table (or ping).
- Don't repoint `/lookup/*-types` at the constants.
- Don't create new `*_types` seeding/normalising migrations — I'm owning the
  type-source migration set (coordinate timestamps with me to avoid the
  `community_types.slug` UNIQUE collision we already hit once).

## Status
Reverted my first (table-seed) attempt because it collided with your committed
`070000` remap + the `2026_05_20` `business-coworking` insert. Re-doing it
**on top of** your work per the above. Questions → leave them here.
