# BE-2026-05-22 · Public Media URL Stability

**From**: `B7`, `C10`, `C15` in the mobile QA list

Flutter now normalizes relative media paths into usable absolute URLs and normalizes picked files from Google Photos/iCloud before upload. That closes the most obvious client-side breakage.

This backend ticket is the server-side half of the same defect cluster: stable public media references across publish, profile, discovery, and past-event surfaces.

---

## Required outcome

Any uploaded image/video used in mobile must remain renderable across:

- onboarding/profile screens
- public profile pages
- discovery cards
- kolab review/publish flows
- past-event cards

No screen should require a short-lived signed URL that expires before the user reopens the app.

---

## Backend requirements

### 1. Canonical URL/path contract

Where media is returned publicly, backend must emit either:

- a stable absolute URL, or
- a canonical storage path that the mobile app can deterministically expand

Do not mix short-lived signed URLs in one payload and relative paths in another unless documented and intentional.

### 2. Consistent media item shape

Media arrays should consistently use:

```json
{
  "url": "https://... or canonical/path.jpg",
  "type": "image",
  "thumbnail_url": null,
  "sort_order": 0
}
```

`image|video` only. No legacy `photo` values.

### 3. Publish flow must not reject valid uploaded media

If the user already uploaded media successfully, publish should not later fail with an `invalid image url` style error because the server expects a different shape or an expired value.

### 4. Public profile/gallery payloads must be render-safe

Profile photo, logo, gallery photos, and past-event media should all come back in a format that can be rendered after app restart and on a different device.

---

## Acceptance

- uploaded profile/kolab media renders after cold restart
- discovery/profile payloads do not depend on expiring signed URLs for normal rendering
- publish accepts the same media references that upload/create saved earlier
- past-event photos/videos and gallery images render from the public payload shape
- payloads use `type=image|video` consistently

---

## Mobile reference

Client-side hardening already exists in:

- `kolabing-app/lib/services/upload_service.dart`
- `kolabing-app/lib/utils/remote_media_url.dart`
- `kolabing-app/lib/utils/image_picker_normalize.dart`
- `kolabing-app/lib/features/auth/models/user_model.dart`
- `kolabing-app/lib/features/profile/providers/gallery_provider.dart`
- `kolabing-app/lib/features/kolab/providers/kolab_form_provider.dart`

If public media still breaks after this ticket, inspect the exact payload, not the picker UI.
