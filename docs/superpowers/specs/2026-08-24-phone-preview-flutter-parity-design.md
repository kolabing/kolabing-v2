# Phone preview ↔ Flutter parity (drift audit + fix)

> Spec date: 2026-08-24 · Scope: **web panel only** (`kolabing-v2`). No API contract
> change, no mobile change. Read with `docs/ROLES-AND-PERMISSIONS.md` §7 (attendee is
> explicitly out of scope here).

## Problem

`resources/views/webapp/partials/phone-preview.blade.php` claims to mirror the Flutter
public-profile screen, and its header comment names the three Dart files it mirrors. It
has drifted from all three — and, more seriously, it is fed by an endpoint that does not
carry the data the Flutter screen renders. A business/community user editing their
profile sees a phone that is confidently wrong.

Nothing here is about adding a preview: the preview exists (commit `5a44115`) and is
already wired into all four Profile tabs (`account`, `account-gallery`, `account-events`,
`account-preview`) via `kbPhonePreview()` in `webapp/layout.blade.php`, refreshing on
every successful mutation. This spec fixes **fidelity**.

## Mirror sources (authoritative)

| Web | Flutter |
|---|---|
| phone-preview → whole screen | `lib/features/profile/screens/public_profile_screen.dart` (`_buildProfileContent`, `_ProfileSliverHeader`, `_SectionCard`, `_SocialLinkChip`, `_RecentReviewsSection`, `_PublicProfileReviewCard`, `_buildCollaborationsSection`, `_buildSocialLinksSection`) |
| reputation card | `lib/features/profile/widgets/reputation_summary_card.dart` + `lib/widgets/cards/kolabing_cards.dart` (`PrimaryContentCard`, `EmptyStateCard`) |
| gallery card | `lib/widgets/gallery/public_gallery_section.dart` |
| past-events card | `lib/features/event/widgets/past_events_section.dart` + `lib/features/event/widgets/event_card.dart` |
| collaboration card | `lib/features/profile/widgets/past_collaboration_card.dart` |
| tokens | `lib/config/theme/{colors,typography}.dart`, `lib/config/constants/{spacing,radius,layout}.dart` |

## A. Data-source drift (the root cause)

The Flutter screen makes **three** calls. The web preview makes **one**, to a different
endpoint.

| Data | Flutter | Web preview today | Consequence |
|---|---|---|---|
| Profile | `GET /profiles/{id}` → `PublicProfileResource` | `GET /profiles/{id}/public-profile` → `CommunityPublicProfileResource` | The resource has **no `reputation`, no `recent_reviews`, no `completed_kolabs_count`, no `type_label`**. So `previewRating` is permanently `—`, the review/completed counts are permanently `0`, the reviews section cannot exist, and the header prints the raw slug (`run_club`) instead of `Run Club`. |
| Past collaborations | `GET /profiles/{id}/collaborations?per_page=10` → `PublicCollaborationResource` (`title`, `partner_name`, `partner_avatar_url`, `completed_at`, `status`) | embedded `past_collaborations` from the detail payload | different shape — no partner avatar, no `completed_at` |
| Past events | `GET /events?profile_id={id}` → `EventResource` (`name`, `date`, `partner_name`, `attendee_count`, `photos[]`) | embedded `past_events` (`name`, `date`, `partner_name`, `attendee_count`, `media[]`) | close, but the app derives its cover from `photos.first.url` and shows a photo-count badge from `photos.length` |

**Fix:** `refreshPreview()` fans out all three in parallel (`Promise.all`) against the
same endpoints the app uses, and the partial reads only those fields.

Two honest limits, to be stated in the partial's header comment rather than faked:

- `EventResource` emits `partner_name`/`partner_type` but **no `partner` object and no
  `videos`**. The Flutter `Event` model looks for `json['partner'].profile_photo` and
  `json['videos']`, so in the real app the partner avatar always falls back to its
  initial placeholder and the video badge never renders. The preview mirrors that
  reality: initial-placeholder avatar, no video badge.
- `GET /events?profile_id=` carries no time filter in the app's call, so the app's
  "Past events" rail can include a future-dated event. The preview mirrors the call
  rather than "correcting" it.

## B. Global token drift

| Token | Flutter | Web today |
|---|---|---|
| phone body background | `background` **#FAF5EA** | `#FAF7F0` ✗ |
| secondary text | `onSurfaceVariant` **#3F3A32** | `black/60` ✗ |
| tertiary text | `textTertiary` **#8C8A82** | `black/45`, `black/50` ✗ |
| borders | `hairline` **#EDE5D5** | `black/[.06]` ✗ |
| muted fills | `surfaceVariant` **#F5EFE3** | `black/[.04]`, `black/5` ✗ |
| success | **#56624D** | absent |
| soft yellow | **#FFF4C2** | absent |
| primary / ink | #FFE28C / #19150F | ✓ |
| section title | `titleMedium` = Inter **20/700, lh 28** | `14px` bold ✗ |
| card body | `bodySmall` = **14/400, lh 20** | `11–13px` ✗ |
| section card | padding 16, radius 16, `0 2px 10px rgba(0,0,0,.05)`, header→child gap **always 16** | shadow `0 1px 4px`, gaps `mt-2/3/4` ✗ |
| bottom spacer | `xl` = 32 | 24 ✗ |

Spacing scale: `xxxs 2 · xxs 4 · xs 8 · sm 12 · md 16 · lg 24 · xl 32`.
Radius scale: `xs 4 · sm 8 · md 12 · lg 16 · card 24 · round pill`.

## C. Section-by-section drift and target

Order in Flutter — **the web is missing #6**:
`reputation → about → gallery → past events → past collaborations → recent reviews → social links`.

### 1. Header (`_ProfileSliverHeader`) — small fixes
180px, gradient `#FFE28C → rgba(255,226,140,.7)`, content inset `16 / 56-top / 16 / 16`,
avatar 64 round, name **22/700, 2 lines then ellipsis**, then 2px gaps: `type_label`
(13/500 onSurfaceVariant), then map-pin 12 + city (13/400).
Fix: use `type_label` not `type`; allow 2 lines (currently `truncate`); keep the 56px top
inset (it exists for the app's pinned back button).

### 2. Reputation (`ReputationSummaryCard`) — rewrite, two states
- **No reviews** (`reputation == null || review_count == 0`) → `EmptyStateCard`:
  padding 32, radius 16, 1px hairline, shadow `0 2px 8px rgba(25,21,15,.04), 0 8px 24px rgba(25,21,15,.024)`;
  56px circle `#FFF4C2` + star icon 26 onSurface; gap 16; title 18/700 centered; gap 12;
  message 14/400 onSurfaceVariant centered.
- **Has reviews** → `PrimaryContentCard` (padding 16, radius 16, **1px hairline**, same
  designCardShadow — not the section-card shadow) holding **one** `justify-between` row:
  `[★22 primary + 4 + rating 20/700]` · `reviews count` · `partners count` **only when
  `unique_partner_count > 0`** · `completed count` **only when > 0**. Stats are 14/400
  onSurfaceVariant, each ellipsised.

Today's card is a two-row layout, always rendered, missing `unique_partner_count`, with
no hide-when-zero rule and no empty state.

### 3. About — fix type only
Card ✓, icon `file-text` 20 primary ✓, title → 20/700, body → 16/400 with `line-height:1.5`.

### 4. Gallery (`PublicGallerySection`) — nearly right
112px thumbs, radius 12, 8px gaps, count as **plain** 14/400 textTertiary (this section
deliberately does *not* use the count pill). Fix: title size, count colour, empty-thumb
placeholder = `surfaceVariant` fill + `image-off` icon 24 textTertiary.

### 5. Past events (`PastEventsSection` + `EventCard`) — card is a full rewrite
Container: standard section card, `calendar` 20 primary, title 20/700, then the count as a
**pill**: `primary @10%`, radius **8**, px 8 / py 2, 11/600 **primary-coloured** text.
Section hidden entirely when the list is empty (matches today). Rail: height 220, gap 12.

`EventCard` — 180 × 220, radius 16, shadow `0 2px 8px rgba(0,0,0,.1)`, and it is a
**full-bleed cover with overlaid text**, not today's white card with a 120px image on top:
- cover = `photos[0].url`; placeholder = `surfaceVariant` + `image` icon 32 textTertiary
- gradient overlay top→bottom: `transparent 30% → rgba(0,0,0,.3) 60% → rgba(0,0,0,.8) 100%`
- date badge top-right, 12/12 inset: `rgba(0,0,0,.6)`, radius 8, px 12 / py 4, **10px white**, format **`Mar 12, 2026`**
- photo-count badge top-left when `photos.length > 1`: same fill, radius 8, px 8 / py 2, `image` icon 12 + 2 + 10px white
- bottom block, 12 inset: name 14/600 white, 2 lines → 6 → `[20px partner initial circle, primary fill, 1px rgba(255,255,255,.5) ring, 10px/700 onSurface]` + 6 + partner name 11px `rgba(255,255,255,.9)` → 6 → `users` icon 12 + 4 + `"{n} attendees"` 10px `rgba(255,255,255,.8)` (n formatted `1.2K` above 1000)

The web's `kbDateShort()` does not produce `Mar 12, 2026`; add a preview-local formatter
rather than changing the shared helper.

### 6. Past collaborations (`_buildCollaborationsSection` + `PastCollaborationCard`) — rewrite
Always rendered (today: only when non-empty). Section card, **`trophy`** icon (today:
`users`), title 20/700, count pill = **`kolabsCount`** (`completed_kolabs_count`, falling
back to the list length) in the `_SectionCard` style: `primary @15%`, radius **10**,
px 6 / py 2, 12/600 onSurface.

- **Empty** → centred block, py 16: `users` icon 32 textTertiary + 8 + message 16/400 textTertiary.
- **Non-empty** → horizontal rail height **110**, gap 12, cards **240** wide, padding 12,
  radius 12, 1px hairline, shadow `0 4px 24px rgba(28,28,22,.04)`:
  title 14/600 one line → 8 → `[24px partner avatar (or primary @20% circle with initial 16/600) + 8 + "with {partner}" 14/400 onSurfaceVariant, one line]` → 8 →
  `[calendar 12 textTertiary + 4 + "MMM yyyy" 12px textTertiary + spacer + "Completed" pill: success @15%, radius 4, px 6 / py 2, check-circle 10 + 3 + 10/600 #56624D]`

Today: a conditional vertical list of the first three titles as plain text.

### 7. Recent reviews (`_RecentReviewsSection`) — **missing entirely, add**
Rendered when `recent_reviews` is non-empty (the API returns at most 3). Section card,
`star` icon, title 20/700, trailing "View more" text button (primary-coloured, inert in
the preview — the preview has no tap targets by design).
Each review card: padding 12, radius 12, fill `background` #FAF5EA, 1px hairline; rows
separated by 12:
`[36px avatar + 12 + [name 14/600 onSurface, date 14/400 textTertiary] + 5 stars 16 primary (filled to `rating`, outline after)]`,
then, when a body exists, gap 12 + body 16/400 onSurfaceVariant `line-height:1.45`.
Date format: **`12 Mar 2026`** (day-first — note it differs from the event badge).

### 8. Social links (`_SocialLinkChip`) — rewrite the chip
Chip: `surfaceVariant` fill, pill radius, **1px hairline border**, px 12 / py 8,
**icon 16 primary** + 6 + label 13/500 onSurface. Labels: `@{instagram}`, `@{tiktok}`,
and the website raw. Wrap gaps 12/12.
Today: borderless `black/4` pills, no icons, raw handle values, 11px.

### 9. Trailing spacer
32, not 24.

## D. Out of scope (stated, not silently dropped)

- **Attendee profiles.** The Flutter attendee screen is `_MemberProfileContent` /
  `attendee_profile_screen.dart` — a game-card hub (points / events attended / badges),
  a different payload (`GET /profiles/{id}/game-card`) and a different layout. The
  preview stays business/community-only.
- **Live-as-you-type preview.** Still after-save only.
- **Visibility below `xl`.** Still `hidden xl:block`.
- Per-section loading/error/retry states inside the phone (the app shimmers the events
  rail and offers a retry); the preview keeps its single whole-phone skeleton.

## E. Files touched

| File | Change |
|---|---|
| `resources/views/webapp/partials/phone-preview.blade.php` | rewrite the six drifted cards, add the reviews card, retoken throughout, refresh the header comment (mirrored files + measurements + the two honest limits) |
| `resources/views/webapp/layout.blade.php` (`kbPhonePreview`) | three parallel fetches; new derived getters (`previewTypeLabel`, `previewReputation`, `previewHasReviews`, `previewReviews`, `previewKolabsCount`, `previewEventCover`, `previewEventPhotoCount`, `previewAttendeeCount`, `previewPartnerInitial`, `previewDateBadge`, `previewReviewDate`, `previewCollabMonth`) |
| `lang/{en,es,ca,tr}/webapp.php` | new `account.phone.*` keys: `reputation_empty_title`, `reputation_empty_body`, `partners`, `past_kolabs`, `no_past_kolabs`, `recent_reviews`, `view_more`, `completed_badge`, `with_partner`, `attendees` |
| `tests/Feature/Webapp/…` | see below |

## F. Verification

The partial is Blade + Alpine with no PHP logic, so the meaningful automated coverage is:

1. **Render test** — each of the four Profile tabs renders the partial and the new
   markup/keys are present (extend the existing webapp page test for `/account*`).
2. **Lang parity test** — every new `account.phone.*` key exists in all four locales.
   Prefer extending the existing locale-parity test if one exists; otherwise add one.
3. **Endpoint contract assertions** — feature tests asserting `GET /profiles/{id}`
   returns `reputation`, `recent_reviews`, `completed_kolabs_count`, `type_label`, and
   that `GET /profiles/{id}/collaborations` and `GET /events?profile_id=` return the
   fields the preview binds. These lock the preview's inputs without testing markup.
4. **Manual** — one business and one community account at ≥1280px, walking all four tabs
   against the app on a device, plus the empty-profile case (no reviews, no gallery, no
   events, no collaborations) which is where the empty states now differ most.

`php artisan test` green and `vendor/bin/pint` clean, counts pasted into the PR template.

## G. Docs & tracking

- No role, permission, paywall, or schema change → `ROLES-AND-PERMISSIONS.md`,
  `ROLES-BACKEND-DB-MAP.md` and `BACKEND-SCHEMA.md` need no edit. Say so explicitly in
  the PR rather than leaving the section blank.
- `BACKLOG.md`: add as a Fix (the preview misreports reputation), move to Incomplete
  Features while in flight, bump *Last updated*.
- Mobile impact: **none** — read-only web mirror, no API change. State it deliberately.
- Branch off `master` as `fix/phone-preview-flutter-parity`, GitHub Projects item first,
  PR with `Closes #<id>`.
