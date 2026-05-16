# Google Places Onboarding Import

## Goal

Reduce business onboarding friction by importing venue data from Google Places after the user selects a venue from the picker, then saving only once at the end of onboarding.

## Mobile Flow

1. User searches and selects a venue in the Google Maps picker.
2. Mobile calls `GET /api/v1/places/details?place_id={place_id}`.
3. Show loading state: `Importing your business info from Google`.
4. Prefill the onboarding form from the response.
5. Let the user edit any field, remove photos, reorder photos, and add their own uploads.
6. On final submit, call `PUT /api/v1/onboarding/business`.

## Important Rules

- The mobile client must never hold the Google Places API key.
- The import endpoint does not save anything.
- Google photos are only rehosted when the final onboarding save request succeeds.
- `primary_venue.capacity` is still required on final save and must be collected manually because Google does not provide it.

## Import Endpoint

`GET /api/v1/places/details?place_id=google-place-id`

### Success Response

```json
{
  "success": true,
  "data": {
    "name": "Sol Studio Rooftop",
    "about": "A rooftop cafe for coffee, events, and community meetups.",
    "business_type": "cafe",
    "categories": ["cafe"],
    "city_id": "uuid-or-null",
    "city_name": "Barcelona",
    "phone_number": "+34931234567",
    "website": "https://solstudio.example.com",
    "primary_venue": {
      "name": "Sol Studio Rooftop",
      "venue_type": "cafe",
      "capacity": null,
      "place_id": "google-place-id",
      "formatted_address": "Carrer de Mallorca 1, Barcelona",
      "city": "Barcelona",
      "country": "Spain",
      "latitude": 41.3874,
      "longitude": 2.1686,
      "phone_number": "+34931234567",
      "website": "https://solstudio.example.com",
      "opening_hours": ["Monday: 09:00-17:00"],
      "description": "A rooftop cafe for coffee, events, and community meetups.",
      "price_level": "PRICE_LEVEL_MODERATE",
      "rating": 4.7,
      "user_ratings_total": 128,
      "google_place_types": ["cafe", "food", "establishment"],
      "photos": [
        {
          "resource_name": "places/google-place-id/photos/photo-1",
          "preview_url": "https://api.example.com/api/v1/places/photo?name=places%2Fgoogle-place-id%2Fphotos%2Fphoto-1",
          "width": 1200,
          "height": 800,
          "author_attributions": [
            {
              "display_name": "Photo One",
              "uri": "https://maps.google.com/photo-1",
              "photo_uri": "https://maps.google.com/author-1"
            }
          ]
        }
      ]
    }
  }
}
```

## Prefill Mapping

- Fill business name from `data.name`.
- Fill description from `data.about`.
- Fill business categories from `data.categories`.
- Fill phone number from `data.phone_number`.
- Fill website from `data.website`.
- Fill venue address and map marker from `data.primary_venue`.
- Leave `primary_venue.capacity` empty and require the user to enter it before final save.

## Photo Rendering

- Render imported photos using `primary_venue.photos[].preview_url`.
- Preserve the original `resource_name` in local state.
- Show `author_attributions` on the import screen or a photo credits sheet.
- Keep the existing `Powered by Google` attribution on the picker/import experience.

## Final Save Payload

Submit the existing onboarding endpoint:

`PUT /api/v1/onboarding/business`

### Photo Rule

`primary_venue.photos` must be sent back as an ordered array of strings:

- Imported Google photos: send the kept `resource_name`
- Newly uploaded local photos: send base64 data URIs
- Existing Kolabing-hosted photos: send their URLs

Removed photos should be omitted. The array order becomes the saved order.

### Example Save Payload

```json
{
  "name": "Sol Studio Rooftop",
  "about": "Custom business description edited by the owner.",
  "business_type": "cafe",
  "categories": ["cafe"],
  "city_id": "uuid-or-null",
  "city_name": "Barcelona",
  "phone_number": "+34931234567",
  "website": "https://solstudio.example.com",
  "primary_venue": {
    "name": "Sol Studio Rooftop",
    "venue_type": "cafe",
    "capacity": 80,
    "place_id": "google-place-id",
    "formatted_address": "Carrer de Mallorca 1, Barcelona",
    "city": "Barcelona",
    "country": "Spain",
    "latitude": 41.3874,
    "longitude": 2.1686,
    "phone_number": "+34931234567",
    "website": "https://solstudio.example.com",
    "opening_hours": ["Monday: 09:00-17:00"],
    "description": "Custom business description edited by the owner.",
    "price_level": "PRICE_LEVEL_MODERATE",
    "rating": 4.7,
    "user_ratings_total": 128,
    "google_place_types": ["cafe", "food", "establishment"],
    "photos": [
      "places/google-place-id/photos/photo-2",
      "data:image/png;base64,...",
      "places/google-place-id/photos/photo-1"
    ]
  }
}
```

## Failure Handling

If the import endpoint returns `503`, show this toast and continue with the manual form:

`We couldn't import from Google, please fill in manually.`

Do not block onboarding if the import fails.

## Backend Behavior Summary

- Place Details lookups happen server-side only.
- Photo previews use the backend photo proxy endpoint.
- Final save rehosts only the selected Google photos.
- Failed Google photo rehosts are skipped individually so the rest of the save can still succeed.
