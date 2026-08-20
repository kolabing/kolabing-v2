# Mobile phone preview of the public profile (BE-NF-37)

> Design spec. Date: 2026-08-20. Web-app only — no API, schema or role change.
> Extends the Profile section shipped in BE-NF-36.

---

## 1. Why

BE-NF-36 gave business and community accounts a place to manage their portfolio, and a
Preview tab showing the public profile as a **web page**. But the profile most people
will actually see is the one in the Flutter app, and there is no way to check that from
the panel. Someone reordering a gallery cannot tell which photo will lead on a phone.

## 2. What it is

A phone frame — notch, bezel, ~390×760 viewport — pinned to the right of every tab in
the Profile section, rendering a faithful read-only replica of the app's public profile
screen from the same `GET /profiles/{me}/public-profile` payload the Preview tab
already loads. Reorder a photo and the phone updates.

## 3. The source of truth being replicated

`kolabing-app/lib/features/profile/screens/public_profile_screen.dart` (+
`lib/widgets/gallery/public_gallery_section.dart`,
`lib/features/event/widgets/past_events_section.dart`).

**Header** — 180px tall, gradient from `primary` to `primary` at 70% opacity, 64px
avatar, display name at 22/700, type label, then a map-pin icon and city name.

**Body** — 16px padding, cards stacked with 16px gaps. Each card: surface background,
16px padding, `rgba(0,0,0,.05)` shadow, and a title row of a 20px primary-coloured icon
plus the title. In order:

1. Reputation summary — 22px star in primary, rating at 20px, then review / partner /
   completed-kolab stat labels
2. About — only when the profile has one
3. Gallery — horizontal scroll, **112px** square thumbs, 8px gaps
4. Past events — horizontal scroll, **180px** wide cards in a **220px** tall rail,
   12px gaps
5. Past collaborations
6. Recent reviews — only when there are any
7. Social links — only when there are any

**Tokens** (`lib/config/constants/spacing.dart`, `radius.dart`,
`lib/config/theme/colors.dart`): `primary` is `#FFE28C` — the same yellow the web app
already uses, so the palettes align with no new colour. Spacing xs 8 / sm 12 / md 16 /
lg 24. Radius sm 8 / md 12 / lg 16 / card 24.

## 4. The honest trade-off

**This is a second rendering of the profile UI.** I argued against exactly that for the
web page in BE-NF-36, where the answer was to render the real page. Here it is
unavoidable: the target is a Flutter screen and cannot be embedded.

Three things keep the cost bounded, and they are part of the design rather than
afterthoughts:

- **Read-only.** No lightbox, no pagination, no tap targets. It shows; it does not do.
- **One file.** Everything lives in `webapp/partials/phone-preview.blade.php`, so when
  the Flutter screen changes there is exactly one place to update.
- **Documented pairing.** The partial's header names the Dart files it mirrors, and the
  backend map records the pairing, so the next person knows the two must move together.

## 5. Architecture

**`resources/views/webapp/partials/phone-preview.blade.php`** — the frame plus the
replica markup, driven by an Alpine component `kbPhonePreview()` that owns:

- `previewProfile` — the payload, loaded once on init
- `refreshPreview()` — re-fetches; pages call it after any successful mutation
- `previewLoading`, `previewError`

It is spread into each page's `x-data` alongside `kbShell()`, the same way pages already
compose `kbMerge(kbShell(), somePage())`. No global state.

**Live updates.** Gallery reorder / caption / delete / upload, and past-event create /
edit / delete / photo changes, each call `refreshPreview()` on success. The phone is
never stale relative to what the tab just did.

**Layout.** A sticky right column, `hidden xl:block`, so it appears only where there is
real room; below `xl` the tab keeps its current full-width layout. The Preview tab shows
the frame at full size next to its existing wide rendering, which is what makes the two
formats comparable.

**Empty and error states.** A profile with no gallery and no past events renders the
header and the sections that do have content — matching the app, which hides empty
optional sections. A failed fetch shows a short line inside the frame rather than an
empty phone.

## 6. Scope

**In:** the frame, the replica, the four mount points, live refresh, es/ca copy.

**Out:** attendee profiles (they have a different app layout — the member social hub —
and no public portfolio; the frame is not shown for them), any interactivity inside the
frame, and a device picker. One phone size is enough to answer "which photo leads?".

## 7. Testing

`WebAppRoutesTest` extensions:

- the phone frame renders on all four `/account/*` tabs
- it carries the `hidden xl:block` responsive gate rather than always rendering
- `kbPhonePreview` and `refreshPreview` ship in the page source
- the frame's copy is localised at `/es` and `/ca`

es/ca verified at 100% key parity, with the duplicate-top-level-key check that caught a
real bug in BE-NF-34.

## 8. Docs

- `docs/ROLES-BACKEND-DB-MAP.md` §17 — a short note recording that the partial mirrors
  named Dart files and that the two must change together.
- `BACKLOG.md` — BE-NF-37.
- No `ROLES-AND-PERMISSIONS.md` change: no role, gate or permission is affected.
