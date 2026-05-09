# Role-Aware Discovery Backend Contract

**Consumer:** Flutter mobile app  
**Primary KPI:** Higher `apply / match rate` in Explore  
**Source of truth:** Published `kolabs`

## Recommendation

Use a new endpoint instead of extending the old `/api/v1/opportunities` feed.

Preferred route:

- `GET /api/v1/discovery/opportunities`

Why:

- the current mobile `Opportunity` contract is shaped around the old opportunities system
- the new discovery flow needs kolab-specific fields the old contract does not carry
- a dedicated endpoint avoids breaking existing mobile and backend flows
- backend can iterate ranking logic without coupling it to old CRUD responses

If backend strongly prefers keeping a single route, the same contract can be mounted under `/api/v1/opportunities` behind a versioned or opt-in query param, but mobile preference is a dedicated endpoint.

## Authentication

- authenticated endpoint
- backend infers viewer role and viewer profile from the bearer token
- client should not be trusted to declare its own role

## Source Data

Return only published kolabs.

Role scoping:

- community viewer sees only business offers
- business viewer sees only community requests

Mapping:

- `community_seeking` => community request item
- `venue_promotion` => business offer item
- `product_promotion` => business offer item

## Request Contract

### Required base params

- `feed`: `recommended | all`
- `page`
- `per_page`

Defaults:

- `feed = recommended`
- `page = 1`
- `per_page = 15`

### Common filters

- `search`
- `city`
- `availability_mode`
- `availability_from`
- `availability_to`
- `sort`

Supported `sort`:

- `recommended`
- `recent`
- `ending_soon`

### Business viewer filters

For business users browsing community requests:

- `need_types[]`
- `community_types[]`
- `audience_size_band`
- `offers_in_return[]`
- `venue_preferences[]`

### Community viewer filters

For community users browsing business offers:

- `intent_types[]`
- `offer_types[]`
- `venue_types[]`
- `product_types[]`
- `expected_deliverables[]`
- `community_requirement_band`

## Band Definitions

Backend should normalize numeric filters into stable bands so the mobile app does not need to hardcode business logic in multiple places.

### `audience_size_band`

Used for community requests:

- `small` => `community_size` or `typical_attendance` under `100`
- `medium` => `100..499`
- `large` => `500..1999`
- `xlarge` => `2000+`

### `community_requirement_band`

Used for business offers:

- `open` => `min_community_size` is `null`
- `small` => under `100`
- `medium` => `100..499`
- `large` => `500..1999`
- `xlarge` => `2000+`

## Response Contract

### Envelope

```json
{
  "success": true,
  "data": {
    "data": [],
    "meta": {}
  }
}
```

Use the existing API envelope conventions if pagination meta already lives elsewhere in the project. The important point is the item shape and the discovery metadata.

### Discovery item shape

Each item should return a normalized discovery payload.

```json
{
  "id": "uuid",
  "creator_type": "business",
  "intent_type": "venue_promotion",
  "title": "Sunset rooftop collab",
  "description": "Host your creator event on our rooftop",
  "preferred_city": "Barcelona",
  "area": "Eixample",
  "cover_photo_url": "https://...",
  "published_at": "2026-05-09T12:00:00Z",
  "availability": {
    "mode": "one_time",
    "start": "2026-05-20",
    "end": "2026-05-20",
    "selected_time": "19:00",
    "recurring_days": []
  },
  "creator_profile": {
    "id": "uuid",
    "display_name": "Casa Sol",
    "avatar_url": "https://..."
  },
  "business_offer": {
    "offer_types": ["venue", "food_drink", "social_media"],
    "venue_type": "rooftop",
    "product_type": null,
    "seeking_communities": [
      { "key": "travel", "label": "Travel" },
      { "key": "lifestyle", "label": "Lifestyle" }
    ],
    "min_community_size": 100,
    "expected_deliverables": ["social_media", "community_reach"]
  },
  "community_request": null,
  "match": {
    "feed": "recommended",
    "score": 92,
    "tier": "high",
    "reasons": ["city_match", "community_type_match", "expected_deliverable_match"]
  }
}
```

For a community request item, invert the `business_offer` / `community_request` blocks:

```json
{
  "community_request": {
    "need_types": ["food_drink", "sponsor"],
    "community_types": [
      { "key": "wellness", "label": "Wellness" }
    ],
    "community_size": 1200,
    "typical_attendance": 250,
    "offers_in_return": ["social_media", "event_activation"],
    "venue_preference": "business_provides"
  }
}
```

## Normalization Rules

### `offer_types`

Backend should normalize `kolabs.offering` into stable API keys.

Current app-side source values already exist in business offering creation:

- `venue`
- `food_drink`
- `discount`
- `products`
- `social_media`
- `content_creation`
- `sponsorship`
- `other`

Return these exact keys for v1.

### `community_types` and `seeking_communities`

Current mobile app stores human-readable labels in kolab payloads, not stable slugs.

Backend should return normalized objects:

```json
{ "key": "food_drink", "label": "Food & Drink" }
```

This allows mobile to:

- render user-facing labels
- filter by stable keys
- migrate stored label-only data without another mobile change

### `expected_deliverables` and `offers_in_return`

Return stable keys using the existing mobile enum values:

- `social_media`
- `event_activation`
- `product_placement`
- `community_reach`
- `review_feedback`

### `need_types`

Return stable keys using the existing mobile enum values:

- `venue`
- `food_drink`
- `sponsor`
- `products`
- `discount`
- `other`

## Ranking Requirements

### Hard filters

Backend must always enforce:

- published only
- opposite-side only
- exclude viewer-owned records
- exclude expired availability windows

### Recommended ranking inputs

#### Business viewer

Recommended score should consider:

- city match with business profile city
- community type affinity with business type or selected filter
- overlap between community `need_types` and business capabilities
- overlap between `offers_in_return` and business goals
- venue preference compatibility
- audience size suitability
- freshness

#### Community viewer

Recommended score should consider:

- city match with community profile city
- community type affinity against `seeking_communities`
- overlap between business `offer_types` and community interests
- overlap between business `expected_deliverables` and community profile/filters
- intent type preference
- freshness

Important constraint:

- current community profile does **not** persist audience size
- therefore `min_community_size` should be returned and filterable, but it should not be treated as a mandatory community fit signal unless the client later sends an explicit audience-size filter

## Meta Block

`meta` should include at least:

- `current_page`
- `last_page`
- `per_page`
- `total`
- `feed`
- `applied_filters`

Recommended extra meta:

- `viewer_role`
- `empty_reason`

Example `empty_reason` values:

- `no_published_results`
- `no_results_after_filters`
- `no_recommended_matches`

## Mobile Must-Haves

These are the fields the frontend treats as required for the first iteration:

- `id`
- `creator_type`
- `intent_type`
- `title`
- `description`
- `preferred_city`
- `cover_photo_url`
- `published_at`
- `availability.mode`
- `availability.start`
- `availability.end`
- `creator_profile.display_name`
- `creator_profile.avatar_url`
- `match.score`
- `match.reasons`

Required by role:

- business offer item must include `offer_types`
- community request item must include `need_types`

## Nice-to-Haves

These are useful but not blockers for the first mobile iteration:

- facet counts
- `distance_km`
- `why_not_match` explanations
- `saved_for_later` state

## Backward Compatibility

- do not remove or silently repurpose the old `/api/v1/opportunities` payload
- new discovery contract should be additive
- mobile will migrate Explore to this endpoint without affecting old create/update/detail flows
