# Backend Tickets — Generated from Bug List & Phase Work (2026-05-21)

Bu doküman Flutter app'in 32 bug'lık QA listesi üzerindeki çalışma sırasında
ortaya çıkan tüm backend gereksinimlerini içerir. Backend repo'sunda 1:1 ticket
açılabilir.

**Source phases**: `.agent/done/phase1-*` → `phase7-*`
**Product decisions captured**: H3 = **base + negotiable**, H4 = audit
**food community → coworking 95% anomaly** specifically.

---

## CRITICAL — Launch blockers

### BE-001 · Confirm Kolab media type accepts `'image'` / `'video'`
**From**: B7 (publish errors with image URL)
**Status**: Frontend already shipping `type: 'image'` (was `'photo'`).
- `KolabMedia.fromJson` default + frontend sends → `{ url, type: 'image' | 'video', sort_order }`.
- Verify backend validators allow `'image'` and `'video'` (and reject `'photo'` if it was previously accepted).
- If backend was accepting `'photo'`: drop it to converge on `'image'` / `'video'`.
- Endpoints affected: `POST /api/v1/kolabs`, `PUT /api/v1/kolabs/{id}`, `POST /api/v1/kolabs/{id}/publish`.
- **Acceptance**: a kolab with `media[*].type='image'` saves + publishes successfully.

### BE-002 · Kolab publish must return 402 (or `requires_subscription`) when unsubscribed
**From**: B1 (publish silently leaves kolab as draft)
- Currently appears to return 200 OK while leaving `status='draft'`.
- Frontend client-side now has a guard that flips to paywall if `published.status != 'published'` — backend should be the source of truth.
- **Required**: `POST /kolabs/{id}/publish` returns `402 { message, requires_subscription: true }` for accounts without an active subscription.
- **Acceptance**: unsubscribed account → 402 + frontend's existing paywall modal triggers.

### BE-003 · Surface field-level validation errors on `register/business`
**From**: B6 (cannot create business account, exact failure point unknown)
- Frontend now shows persistent error banner with `errors.{field}` map + status + message — but the backend response must populate `errors` fully.
- **Required**: every 422 from `POST /api/v1/auth/register/business` must include nested field errors, e.g.:
  ```json
  {
    "message": "Validation failed",
    "errors": {
      "primary_venue.photos.0": ["The photo URL is invalid."],
      "business_type": ["The business type is required."]
    }
  }
  ```
- Also log full request body on 4xx for the next 2 weeks (debug Daniel's next QA pass).
- **Acceptance**: Daniel reproduces signup failure, frontend banner shows full server message + Copy-details lets QA paste it back.

### BE-004 · Auth refresh investigation (fresh-sign-in "session expired")
**From**: B2 + B4 + B8 (login broken, fresh sign-in throws "auth expired" on multiple screens)
- Hypothesis: fresh login issues a token that backend or refresh-token rotation invalidates within seconds, OR clock skew, OR backend nodes haven't synced new token.
- **Required investigation**:
  1. Confirm refresh-token rotation policy: does the token issued by `/auth/login` work immediately at every node?
  2. Verify `POST /auth/refresh` accepts the refresh_token from a freshly-issued login response (no eventual-consistency window).
  3. Add backend logs: `token_issued_at`, `first_use_at`, `refresh_attempts`, `refresh_outcome` for accounts created in last 5 minutes.
- Frontend now logs `[AUTH] login success` and `[AUTH] refresh start/done`. Cross-reference with backend logs to identify the gap.
- **Acceptance**: Daniel logs in → calls upload immediately → 200 OK, no "session expired".

---

## HIGH — Phase 2/3 follow-ups

### BE-005 · Accept application: drop `contact_methods` requirement
**From**: C13 (contact-methods step removed, chat is canonical channel)
- Frontend now sends `contact_methods: {}` (empty) on `POST /applications/{id}/accept`.
- **Required**: accept empty `contact_methods` (or drop the validation entirely).
- Optionally: deprecate the field — both parties communicate via in-app chat after accept.
- **Acceptance**: `acceptApplication` succeeds with `{ scheduled_date, contact_methods: {} }`.

### BE-006 · Accept date server-side validation matches publisher's mode
**From**: C12 (accept date constrained to publisher's selected dates)
- Frontend client-side now filters allowed dates based on `availability_mode`:
  - `one_time`: only the start date
  - `recurring`: only weekdays in `recurring_days`
  - `flexible`: full `[availability_start, availability_end]` range
- **Required**: backend must enforce the same rule on accept submission — reject mismatches with 422.
- **Acceptance**: Daniel publishes Kolab on Monday (recurring=[Mon, Wed]), backend rejects accept payload with `scheduled_date=2026-05-23` (Saturday).

### BE-007 · Community Kolab flow has no venue step (frontend audit)
**From**: C11 — frontend verified no venue step in community flow.
- No backend change needed unless your validation requires venue fields for community Kolabs.
- **Acceptance**: confirm `POST /kolabs` accepts a community kolab payload with no `primary_venue` / `venue_*` fields.

---

## MEDIUM — Phase 6 / new features

### BE-008 · `POST /api/v1/collaborations/{id}/complete`
**From**: D3 (Finish Collaboration action)
- Frontend has the UI + button + mock-state plumbing in place. Production needs the real endpoint.
- **Spec**:
  - Method: `POST`
  - Path: `/api/v1/collaborations/{id}/complete`
  - Auth: business or community party of the collaboration
  - Body: empty (or `{ rating?: int 1-5, note?: string }` if you want to inline a review prompt)
  - Response: `200 { data: { ...updatedCollaboration with status: 'completed' } }`
- **State transition**: only allowed from `scheduled` or `in_progress` → `completed`. From `cancelled` or already `completed`: 422.
- **Side effects**: dashboard counts decrement active collaborations; enable rating/review prompt for both parties.
- **Acceptance**: both parties can mark as completed exactly once; subsequent calls return 422 with a clear message.

### BE-009 · Public-facing community profile endpoint
**From**: C9 (Discover → community profile needs primary CTA)
- Businesses tapping a community card in Discover currently see the community's OWN settings screen (wrong audience).
- Frontend will build a new `CommunityPublicProfileScreen` for the view-only experience — needs an endpoint.
- **Spec**:
  - Method: `GET`
  - Path: `/api/v1/communities/{id}/public-profile`
  - Auth: any authenticated business / attendee / other community
  - Response: name, photos, category, city, about, public stats (e.g. past collaborations count), past events portfolio
  - Excludes: email, settings, internal flags
- **Acceptance**: response is safe to expose to a business viewing the community for the first time.

---

## DISCOVERY & MATCHING (Phase 5 — PM decisions confirmed)

### BE-010 · Offer model: base + negotiable (Option B confirmed)
**From**: H3 — **PM decision: base + negotiable**
- Schema additions to Kolab / Opportunity:
  - `base_offer`: existing offer fields — what every community sees publicly
  - `negotiation_triggers`: optional list of `{ condition: string, additional_offer: string }` — surfaces only after a community sends a Kolab proposal
- Example:
  ```json
  {
    "base_offer": "20% off Tuesdays for groups of 10+",
    "negotiation_triggers": [
      {
        "condition": "recurring monthly events",
        "additional_offer": "Free venue rental for 3rd visit onward"
      },
      {
        "condition": "communities of 30+",
        "additional_offer": "Sponsored drinks for the first 30 attendees"
      }
    ]
  }
  ```
- **API changes**:
  - Publish/update Kolab: accept new fields
  - List/detail (public): return `base_offer` only
  - Detail (when application exists between business + community): return both `base_offer` + `negotiation_triggers`
- **Migration**: backfill `base_offer` from existing offer text; `negotiation_triggers` defaults to `[]`.

### BE-011 · Category taxonomy audit — food community → coworking 95% anomaly
**From**: H4 — **PM decision: explicit audit of food community ranking**
- **Reported anomaly**: a food community sees 95% match with a coworking, 80% match with a café — counterintuitive, erodes user trust.
- **Required**:
  1. **Single source of truth**: consolidate community + business categories into one shared table (or enum). Same vocabulary on both sides.
  2. **Cross-mapping audit**: document which business categories are "adjacent" to which community categories. Specifically:
     - `food_community` should rank `cafe`, `restaurant`, `food_truck` highest.
     - `coworking` should rank LOWER than direct food businesses.
     - If `coworking` ranks higher today, the non-category signals (location, audience size, activity freshness) are overpowering category — decide if this is intentional.
  3. **Re-balance algorithm OR surface signals**: either rebalance so category dominates first-impression matches, OR ensure the signal breakdown (BE-012) surfaces *why* coworking scored higher.
  4. **Test fixture**: add a regression test with a seeded food community + coworking + café — assert café > coworking in match score.
- **Acceptance**: the specific food→coworking>café anomaly is either fixed by rebalancing, or every match score comes with a per-signal breakdown that explains it.

### BE-012 · Match score returns per-signal breakdown
**From**: H1 (mini-model match breakdown widget)
- Match algorithm should expose signal contributions in every list/detail response.
- **Required shape**:
  ```json
  {
    "match_score": 95,
    "match_breakdown": [
      { "key": "category_fit",   "label": "Category fit",   "weight": 0.40, "score": 0.80 },
      { "key": "location",       "label": "Location",       "weight": 0.30, "score": 1.00 },
      { "key": "audience_size",  "label": "Audience size",  "weight": 0.20, "score": 0.50 },
      { "key": "past_activity",  "label": "Past activity",  "weight": 0.10, "score": 0.40 }
    ]
  }
  ```
- Pick 3-4 signals. Keep stable across versions (frontend renders bars in order).
- **Acceptance**: every business card in Discover comes back with a `match_breakdown` array; the displayed percentage equals `Σ(weight × score) × 100`.

### BE-013 · Offer headline field
**From**: H2 (one-line offer pinned to card preview)
- New required-on-publish field for venue + product promotion Kolabs.
- **Spec**:
  - Field: `offer_headline`
  - Type: string, max 50 chars
  - Required: yes, for `intent_type IN ('venue_promotion', 'product_promotion')`
  - Returned in: list, detail, card preview payloads
- **Migration**: backfill existing kolabs from the first 50 chars of the description, or mark as required-on-next-edit.
- **Acceptance**: list endpoint returns `offer_headline` for every venue/product kolab.

---

## CROSS-CUTTING / SMALLER

### BE-014 · Welcome email banner CDN upload (G1)
- Upload `community/kolabing/marketing/brand/logo-wordmark-banner-dark.png` (1200×320, 49 KB) to `https://kolabing.com/brand/logo-wordmark-banner-dark.png`
- `Content-Type: image/png`, `Cache-Control: public, max-age=31536000, immutable`
- Daniel swaps the `<img src>` in both Postmark welcome templates after URL is live.
- **Acceptance**: Gmail iOS dark-mode shows the banner without inversion artifacts.

### BE-015 · Document API contract changes for FE alignment
- `media[].type`: now `'image'` / `'video'` (no longer `'photo'`)
- `accept_application.contact_methods`: now optional / empty `{}` allowed
- New: `complete_collaboration` endpoint
- New: `community_public_profile` endpoint
- New: `base_offer` + `negotiation_triggers` + `offer_headline` fields
- New: `match_breakdown` array on every match response
- Update OpenAPI / API docs.

### BE-016 · Structured logging for B6/B2/B8 debug window
- For the next 2 weeks (or until reproduction captured):
  - Log full request body on 4xx from `/auth/register/*` and `/kolabs/*/publish`
  - Log token lifecycle: `issued_at`, `first_used_at`, `refreshed_at` per session
- Drop the verbose logs once root causes are confirmed.

---

## ADDENDUM — Phase 9 follow-ups (2026-05-21 afternoon)

The first backend round closed everything in BE-001..BE-016. These items
surfaced while wiring the FE to the new endpoints.

### BE-017 · `POST /kolabs/{id}/publish` accepts optional `recipient_community_id`
**From**: C9 / Phase 9.1+9.2 — Send-Kolab CTA from a community public profile
- The Flutter client now passes `recipient_community_id` in the publish body
  when the user reached the create flow via the "Send a Kolab proposal" CTA
  on a community profile.
- **Required**: backend accepts the field (optional, nullable). When present
  and the publisher has an active subscription, the Kolab is scoped as a
  direct proposal to that community.
- **Suggested behaviour**:
  - On accept: notify the target community immediately (push + email).
  - On listing: keep the Kolab visible to the recipient community only
    (don't surface in the public Discover feed), OR surface it but
    pre-fill the application for the recipient. PM call.
- Payload: `{ "recipient_community_id": "<uuid>" }`. Frontend sends `null`
  / omits the field when the publish is the standard public flow.
- **Acceptance**: Daniel taps Send Kolab on a community profile → completes
  the create flow → publishes → only that community sees the Kolab in their
  Discover (or receives a direct notification, per the PM call above).

### BE-018 · Application detail returns `opportunity` with `negotiation_triggers`
**From**: H3 reader-side / Phase 9.3
- When a community user has applied to a venue/product Kolab, the
  application detail endpoint must include the parent opportunity payload
  WITH `negotiation_triggers` populated (these were previously gated to
  non-applicants).
- Frontend renders an "Extra terms unlocked" card on `community_offer_detail_screen`
  when the array is non-empty.
- **Acceptance**: community user applies → opens the kolab detail again →
  sees triggers card. Before applying → triggers array is empty / absent.

### BE-019 · `GET /communities/{id}/public-profile` body shape
**From**: C9 / Phase 8.2 — already deployed
- Just documenting the shape the FE expects so the contract stays stable:
  ```json
  {
    "data": {
      "id": "<uuid>",
      "user_type": "community",
      "display_name": "Barcelona Food Lovers",
      "avatar_url": "https://...",
      "about": "...",
      "type": "Food blogger",
      "city_name": "Barcelona",
      "instagram": "...",
      "tiktok": "...",
      "website": "...",
      "gallery": [ { "id": "...", "url": "..." } ],
      "past_collaborations": [
        { "id": "...", "title": "...", "partner_name": "...",
          "partner_avatar_url": "...", "completed_at": "...", "status": "completed" }
      ]
    }
  }
  ```
- The FE PublicProfileScreen already consumes this shape (mirrors the
  legacy `/profiles/{id}` endpoint).

---

## Suggested launch sequence

1. **Day 1**: BE-001 + BE-002 + BE-003 + BE-004 (unblock signup/publish/auth)
2. **Day 2-3**: BE-005 + BE-006 + BE-008 (accept flow + collab complete)
3. **Day 3-5**: BE-010 + BE-011 + BE-012 + BE-013 (Phase 5 discovery)
4. **Anytime**: BE-009 + BE-014 + BE-015 + BE-016 (parallel work)

## Verification checklist (full bug list closure)

After all backend tickets land, re-run Daniel's 2026-05-17 QA against:
- B1, B2, B3, B4, B6, B7, B8, C8, C12, C13, D3, E5 — frontend already fixed, backend confirms
- H1, H2, H3, H4 — full discovery & matching redesign
- C9 — frontend builds new public community profile screen (Phase 6.1)
- C7, C10 — frontend Phase 4.1 follow-ups (no backend involvement)
- C1, C2, C4, C11, D1, D2, E1, E2, E3 — already fixed/verified in this Flutter pass
