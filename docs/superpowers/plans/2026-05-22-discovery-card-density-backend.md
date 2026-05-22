# Discovery Card Density Backend Note

Date: 2026-05-22
Owner: mobile/backend alignment
Scope: `GET /api/v1/discovery/opportunities`

## Why this note exists

Mobile discovery cards were intentionally kept light at launch volume. The card layout in `kolabing-app` is now being prepared for a larger browse surface where users may swipe through 500+ business opportunities.

The frontend now has a denser card shell ready to render:

- business name
- primary photo
- neighborhood-first location
- match % with mini breakdown
- offer headline
- activity/meta badge

Most of that contract already exists in `kolabing-v2`. This note isolates what is already available vs. what backend still needs to guarantee for the card to stay useful at scale.

## Current frontend expectation

For each discovery item shown to a community user, the app now expects these fields to be available and stable:

```json
{
  "id": "uuid",
  "creator_type": "business",
  "intent_type": "venue_promotion",
  "title": "Sunset rooftop collab",
  "description": "Host your creator event on our rooftop",
  "offer_headline": "Post-run cafe takeovers in central Barcelona",
  "preferred_city": "Barcelona",
  "area": "Eixample",
  "cover_photo_url": "https://cdn.example.com/cover.jpg",
  "published_at": "2026-05-09T12:00:00Z",
  "creator_profile": {
    "id": "uuid",
    "display_name": "Casa Sol",
    "avatar_url": "https://cdn.example.com/avatar.jpg"
  },
  "match_score": 92,
  "match_breakdown": [
    {
      "key": "category_fit",
      "label": "Category fit",
      "weight": 0.4,
      "score": 0.8
    }
  ],
  "past_events_count": 7,
  "active_this_month": true,
  "active_this_month_label": "Active this month"
}
```

Notes:

- Mobile prefers `area` over `preferred_city` when present, and renders `area, preferred_city`.
- Mobile prefers `active_this_month` over `past_events_count` when both exist.
- `offer_headline` is rendered as the one-line value pinned inside the card.
- `cover_photo_url` is treated as the primary image. Avatar is now only a fallback if no cover exists.

## Already present in backend

These are already implemented and should remain stable:

- `creator_profile.display_name`
- `cover_photo_url`
- `preferred_city`
- `area`
- `offer_headline`
- `match_score`
- `match_breakdown`

Relevant code today:

- `app/Http/Resources/Api/V1/DiscoveryOpportunityResource.php`
- `app/Services/DiscoveryOpportunityService.php`
- `tests/Feature/Api/V1/DiscoveryOpportunityControllerTest.php`

## Remaining backend need

### 1. Activity/meta signal on every business discovery card

The card now has a dedicated meta slot below the headline. Backend should fill that slot with one of these signals:

1. `active_this_month`
2. `past_events_count`

Preferred contract:

```json
{
  "past_events_count": 7,
  "active_this_month": true,
  "active_this_month_label": "Active this month"
}
```

Rules:

- `active_this_month`: boolean
- `active_this_month_label`: optional string, only needed if product wants custom copy later
- `past_events_count`: integer, public-safe count
- If there is no recent activity and no past events, all three may be `null` / omitted

Frontend behavior:

- If `active_this_month == true`, card shows `active_this_month_label ?? "Active this month"`
- Else if `past_events_count > 0`, card shows `"N past event(s)"`
- Else no activity chip is rendered

### 2. Neighborhood quality for `area`

`area` is already on the contract, but the value now matters more because it is part of the top-level card scan path.

Backend requirement:

- For business opportunities, populate `area` with a neighborhood/district-level label whenever possible
- Avoid returning the city again in `area`
- Good: `Eixample`, `Gracia`, `El Born`
- Bad: `Barcelona`, `Madrid city center`, empty string when neighborhood is known elsewhere

If backend cannot determine a neighborhood, returning `null` is better than returning a duplicate city string.

## Suggested derivation for activity fields

### Option A: profile-based public stats

Use the business profile's public stats source and expose:

- `past_events_count`: count of public past events already visible on the profile

Pros:

- cheap
- already public-safe
- consistent with what users can verify on profile pages

### Option B: recent collaboration activity

Set `active_this_month = true` when the business had at least one of these in the current calendar month:

- a published kolab
- a completed collaboration
- a hosted past event

Pros:

- stronger freshness signal
- better than raw lifetime count for swipe decisions

Recommendation:

- expose both when possible
- if only one can be shipped now, ship `past_events_count` first because it is deterministic and easier to test

## Resource change proposal

Extend `DiscoveryOpportunityResource` with:

```php
'past_events_count' => $this->getAttribute('discovery_past_events_count'),
'active_this_month' => $this->getAttribute('discovery_active_this_month'),
'active_this_month_label' => $this->getAttribute('discovery_active_this_month_label'),
```

Populate those attributes upstream in `DiscoveryOpportunityService` while building the discovery dataset.

## Acceptance

### Required now

- Every business discovery item returns usable values for:
  - `creator_profile.display_name`
  - `cover_photo_url`
  - `preferred_city`
  - `area`
  - `offer_headline`
  - `match_score`
  - `match_breakdown`

### Needed to complete the scaled card contract

- Every business discovery item returns at least one activity signal:
  - `active_this_month == true`, or
  - `past_events_count > 0`, or
  - both omitted/null if genuinely unavailable

- `area` is neighborhood-grade when known, not a duplicate city label

## Test coverage to add in backend

- discovery response includes `past_events_count`
- discovery response includes `active_this_month`
- `active_this_month` wins over `past_events_count` when both are present
- `area` stays neighborhood-specific in seeded fixtures
- no regression to existing `offer_headline` and `match_breakdown` fields

## Mobile status

No additional mobile API shape is needed beyond the fields above. The app has already been updated to consume:

- `offer_headline`
- `area`
- `match_breakdown`
- `past_events_count`
- `active_this_month`
- `active_this_month_label`

If backend ships the missing activity fields on the existing endpoint, no further mobile contract redesign should be necessary for this card iteration.
