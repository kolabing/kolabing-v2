# Venue Pro — the hotel event dashboard (BE-NF-68)

- **Date:** 2026-09-24
- **Status:** Approved 2026-09-24. Step 1 (plan + entitlement + paywall card) built on `feat/venue-pro-plan`; steps 2–7 to follow.
- **Backlog:** BE-NF-68
- **Source:** Daniel's brief "hotel plan dashboard" (six points), plus Volkan's decisions of 2026-09-24:
  - €299/month is a **separate plan**, not a replacement for the €49 plan.
  - The **only** new paywall is the analytics (results + graphs) surface.
  - **Google Calendar** is the only external integration. No Luma or Eventbrite.
  - The plan is **sold on the web only**. No new in-app purchase product in the mobile app.
  - **New:** a hotel can send bulk email and push/in-app messages to the attendees of its events, from the web panel or from the app.
- **Revision 2026-09-24 (Volkan):** the hotel's own events live in the existing `events` table, not a new one. "Most successful businesses" does **not** mean the hotel's restaurants and bars, so outlets are dropped, and the item is deferred out of this spec entirely. Revenue is EUR only. The organiser does **not** approve hotel messages. There is **no** annual Pro price for now.

---

## 1. Who it is for

Hotel managers who often also run the hotel's restaurants and bars. They move around all day and use the phone more than the computer. Every surface in this spec has to work on **both** the web panel (`app.kolabing.com`) and the mobile app. The one exception is **buying** the plan, which only happens on the web.

The plan is marketed to hotels, but nothing in the code checks the business type. Any business can buy it. Gating on `venue_type = hotel` would add a rule with no benefit, and it would break when a beach club or coworking space wants the same thing.

## 2. What is free and what is paid

| Surface | Who gets it | Plan needed |
|---|---|---|
| Events calendar (§4) | Every business | none |
| The hotel's own events + Google Calendar sync (§5, §6) | Every business | none |
| Expected attendees (§7) | Business **and** the community organiser | none |
| Real attendance (§8) | Business **and** the community organiser | none |
| Logging revenue (§9) | Business | none |
| Bulk messages to attendees (§10) | Business that is party to the collaboration | the ordinary rules: an active subscription, and the §2.8 lapse re-gate applies |
| **Results + graphs (§11)** | Business | **Venue Pro** |

**Why logging is free and only the graphs are paid:** the data has to be collected before the graphs can be sold. A hotel that has logged three months of revenue for free has a concrete reason to upgrade. It also keeps the paywall down to one surface, as decided.

**Community side:** everything a community organiser sees here stays free (golden rule 1). The organiser sees expected and real attendance for their own events. The organiser never sees the hotel's revenue.

## 3. The plan

### 3.1 Data

- `business_subscriptions.plan`: `string(20)`, default `'standard'`, allowed values `standard | pro`. There is still **one** subscription row per business (`Profile::subscription` stays `hasOne`). Pro is a superset: a Pro business also has everything the €49 plan gives, meaning create and apply.
- `config/subscriptions.php` gains:
  ```php
  'stripe' => [
      // existing monthly / three_months …
      'pro_monthly' => [
          'price' => 299,
          'plan' => 'pro',
          'stripe_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
      ],
  ],
  ```
  The existing `monthly` and `three_months` entries gain `'plan' => 'standard'`. The price is still defined **only** in config, and views read it from there.
- There is no Apple IAP entry. `apple.*` stays exactly as it is.

### 3.2 Entitlement

```php
// Profile
public function hasVenueProAccess(): bool
{
    if (! $this->isBusiness()) {
        return false;
    }
    if ($this->is_test_user) {
        return true;
    }
    return $this->subscription?->isActive() === true
        && $this->subscription->plan === SubscriptionPlan::Pro;
}
```

This lives next to `hasActiveSubscription()`. A Pro subscription is also an active subscription, so the existing create/apply gates keep working unchanged.

New enum `App\Enums\SubscriptionPlan { Standard = 'standard'; Pro = 'pro'; }`.

### 3.3 Buying and changing plan (web only)

- `CreateCheckoutSessionRequest` accepts `plan ∈ {monthly, three_months, pro_monthly}`.
- `SubscriptionService::activateFromStripeSession()` and `syncFromStripeSubscription()` resolve `plan` from the Stripe Price id on the subscription item, using a reverse lookup over `config('subscriptions.business.stripe')`. Stripe is the source of truth. We never trust a plan value coming from the client.
- **Upgrade from €49 to €299:** a business that already has an active Stripe subscription does not get a second Checkout Session. It gets a subscription **update** through `POST /me/subscription/change-plan`: the price item is swapped, an upgrade to Pro invoices the prorated difference immediately (`proration_behavior = always_invoice`), and any other change credits it on the next invoice (`create_prorations`). The local row is written from Stripe's answer, and the webhook that follows converges on the same values. Plan switching in the Billing Portal is not needed.
- **Business on Apple IAP:** the web cannot change an Apple subscription. The upgrade page tells them to cancel in the App Store first. After that they can subscribe to Pro on the web. We never run both at once.
- **Maintainer grant (§2.10):** the admin grant form gains a plan select, so `source = maintainer` can grant `pro`.
- **Lapse:** Pro lapses exactly like standard. The graphs lock again. **No data is deleted**; logged revenue and events stay put.
- Pricing page (`/pricing`, `webapp/subscription.blade.php`) gains a third card "Venue Pro", with its price read from config.
- Monthly only. There is no annual or 3-month Pro price for now (decided 2026-09-24).

### 3.4 In the mobile app

- The app never shows a price, a purchase button, or a link to the web checkout (Apple guideline 3.1.1 / 3.1.3).
- A Pro business sees the graphs in the app; the data comes from the same API.
- A non-Pro business sees the graphs area **blurred** (golden rule 5: blur, never block) with a neutral line such as "Not enabled on this account". There is no call to action pointing to the web.

## 4. Events calendar

`GET /api/v1/me/venue/calendar?from=YYYY-MM-DD&to=YYYY-MM-DD` (business only, max range 120 days).

Returns one flat, date-sorted list of `events` rows. Everything the hotel sees on its calendar is an `events` row:

1. **Kolabing events:** the events linked to the business's collaborations (`collaborations.business_profile_id = me`, status `scheduled | active | completed`). Their time is `events.starts_at`, falling back to `collaborations.scheduled_date` for a collaboration whose event does not exist yet (§8 closes that gap).
2. **The hotel's own events:** `events` rows with `host_kind = venue` and `profile_id = me` (§5), including the ones imported from Google Calendar.

```json
{
  "success": true,
  "data": [
    {
      "kind": "collaboration",
      "id": "<event uuid>",
      "collaboration_id": "<collaboration uuid>",
      "title": "Sunset Run & Brunch",
      "starts_at": "2026-10-04T08:00:00+02:00",
      "ends_at": "2026-10-04T11:00:00+02:00",
      "status": "scheduled",
      "partner": { "id": "<community profile uuid>", "name": "…", "avatar_url": "…" },
      "event_type": { "slug": "running_club", "name": "Running club" },
      "attendance": { "expected": 42, "expected_basis": "signups", "going": 55, "checked_in": null },
      "revenue_cents": null
    },
    { "kind": "venue", "id": "<event uuid>", "collaboration_id": null, "source": "google_calendar", "…": "…" }
  ]
}
```

`kind` is `collaboration` when the event is linked to a collaboration, and `venue` when it is one of the hotel's own events. `id` is always the `events.id`. The `attendance` block is built by `AttendanceForecastService` (§7), and `revenue_cents` comes from the event row itself (§9). Every item carries the same keys, so the web and the app draw one list without branching on `kind`.

**Subscribing from their own calendar:** `GET /venue/calendar.ics?token=…` is a read-only ICS feed. It uses a per-profile secret stored in `profiles.calendar_feed_token` (a new column, rotatable). It is cheap to build (`CalendarInvitationService` already builds VEVENTs), and it works with Apple Calendar or Outlook for managers who do not want to connect Google.

## 5. The hotel's own events — in the `events` table

The hotel runs events that have nothing to do with Kolabing: a wine tasting, a DJ night. It wants to see them on the same calendar, log their revenue, and compare them against the Kolabing events. That comparison is the pitch: *"your Kolabing nights make more per head than your own ones"*.

**They are ordinary `events` rows** (decided 2026-09-24: no separate table). This brings a real benefit: a hotel's own event gets the existing check-in (BE-NF-35), signups and ICS machinery for free, and every metric in §7–§11 is computed over one table.

### 5.1 New columns on `events`

| column | type | notes |
|---|---|---|
| `host_kind` | string(20), default `'community'`, indexed | `community \| venue`. Every existing row is `community`, the default. `venue` = a business's own event |
| `source` | string(20), default `'kolabing'` | `kolabing \| manual \| google_calendar` |
| `description` | text, nullable | `name` is only `string(100)`; a longer Google title is cut to 100 and the full text kept here |
| `event_type_slug` | string(50), nullable | from `/lookup/community-types`, so own and Kolabing events fall into the same buckets |
| `expected_attendees` | unsigned int, nullable | typed in by hand, or the Google guest count |
| `cancelled_at` | timestamp, nullable | an event cancelled or deleted in Google is marked here, never deleted, so its logged revenue survives |
| `google_calendar_id` / `google_event_id` / `google_etag` | string, nullable | partial unique index on (`profile_id`, `google_event_id`) where `google_event_id` is not null |
| results columns | | see §9 |

The existing NOT NULL legacy columns are filled, not relaxed: `partner_name` = the hotel's display name, `partner_type` = `business` (**verify** that value is accepted wherever `partner_type` is read), `event_date` = the date of `starts_at`. `profile_id` = the business. `community_id` and `collaboration_id` stay null.

### 5.2 Keeping them out of the attendee surfaces

A private hotel wine tasting must never appear in Explore, the attendee "what's on" feed, a community's event list, or the leaderboard.

- Venue events are created with `visibility = private`. This is a **new `EventVisibility::Private` case** meaning "only the owner". No existing visibility check grants access to it, because they all match `public`, `followers`, `members`, `active_members` or `tier` explicitly.
- A global guard as well: a `scopeCommunityHosted()` on `Event`, added to `EventDiscoveryService::baseQuery()` and to every attendee- or community-facing listing. The first PR **audits all 34 `Event::query()` call sites** in `app/Services` and `app/Http/Controllers` and lists each one as "scoped" or "owner-only, safe" in its description.
- A test per public surface (discover, followed, community events, public web feed, sitemap) asserts that a `host_kind = venue` event is absent.
- **Mobile impact:** `private` is a new enum value. The app must treat an unknown visibility safely. In practice the app only ever receives venue events through the `/me/venue/*` endpoints.

Making a hotel's own event public, with signups and check-ins open to attendees, is **out of scope** for this spec. The columns allow it later, by switching `visibility`.

### 5.3 Endpoints

Business only, owner-scoped through a `VenueEventPolicy` (owner = `profile_id` and `host_kind = venue`):
`GET/POST /me/venue/events`, `GET/PATCH/DELETE /me/venue/events/{id}`. `DELETE` of a manual event deletes the row; for a Google-sourced one it sets `cancelled_at`. For a Google-sourced event, the Kolabing-side fields can be edited (type, expected, results), but its name and time come from Google and are overwritten on the next sync. The resource marks those fields `read_only_from_source`.

## 6. Google Calendar integration

The login flow is not touched. Google login keeps using its existing narrow scopes. Calendar access is a **separate, optional, incremental consent**, started from the web panel only (the app opens the web page for this one step, since OAuth consent is simplest in a browser).

### 6.1 Consent and storage

- Scopes: `https://www.googleapis.com/auth/calendar.events` plus `https://www.googleapis.com/auth/calendar.readonly` (to list calendars for import). **Both are Google "sensitive" scopes**, so the OAuth app needs Google verification before non-test users can connect. Start the verification when the spec is approved; it can take weeks. Until it is through, only test users listed in the Google Cloud console can connect.
- `GET /webapp/integrations/google-calendar/connect` → Google consent (`access_type=offline`, `prompt=consent`, `include_granted_scopes=true`) → callback stores the tokens.
- **New table `google_calendar_connections`** (UUID PK, unique `profile_id`): `google_email`, `access_token` (encrypted cast), `refresh_token` (encrypted cast), `token_expires_at`, `scopes` json, `export_calendar_id` nullable, `import_calendar_ids` json, `import_sync_tokens` json (keyed by calendar id), `status` (`active | revoked | error`), `last_synced_at`, `last_error` text, timestamps. This holds OAuth credentials, not events, so it is its own table.
- Disconnect: `DELETE /me/integrations/google-calendar` revokes the token at Google, deletes the row, and keeps the imported events (they become `source = manual`, and the `google_*` columns are cleared).

### 6.2 Export: Kolabing → Google

- On connect we create a dedicated secondary calendar called **"Kolabing"** and store its id in `export_calendar_id`. We never write into the user's primary calendar, so nothing we do can clutter or delete their own entries.
- Every calendar item of `kind = collaboration` (§4) is upserted there. The description carries the partner, expected attendance and a link back to the panel.
- Mapping table `google_calendar_exports` (composite PK `connection_id` + `event_id`, no UUID PK, per the pivot rule in memory): `google_event_id`, `google_etag`, `synced_at`.
- Triggers: `CollaborationCreated / Activated / Cancelled`, a date change on the event, and the nightly job. Everything runs through a queued `SyncGoogleCalendarExport` job, never inside the request.

### 6.3 Import: Google → the hotel's own events

- The user picks which of their calendars to import (for example "Rooftop events"). Their primary calendar is **not** imported by default, because personal appointments must not become "hotel events".
- `ImportGoogleCalendarEvents` job, scheduled every 15 minutes per active connection, uses Google's incremental `syncToken`. A `410 Gone` resets the token and triggers a full resync of the next 180 days.
- Each Google event upserts one `events` row with `host_kind = venue`, `source = google_calendar`, `visibility = private`. Attendees who accepted, if any, are written to `expected_attendees` unless the hotel has typed its own value.
- The events in our own "Kolabing" export calendar are skipped on import, so nothing loops.
- Push notifications (`events.watch`) are **out of scope** for the first cut. Polling every 15 minutes is enough for a calendar and needs no public webhook.

### 6.4 Failure

A refresh-token failure (`invalid_grant`) sets `status = revoked`. The panel then shows "Reconnect Google Calendar" and the jobs skip that connection. Other errors set `status = error` and store `last_error`; after 3 consecutive failures the business gets an in-app notification.

## 7. Expected attendees

**New `AttendanceForecastService`**, the one place this number is computed. The calendar, the collaboration detail, the insights and the organiser's own view all call it.

For a Kolabing event (`kind = collaboration`):

1. **Manual:** if the organiser has entered their own estimate (`events.organiser_estimate`), that is the headline number, with basis `organiser`. This is the number the hotel keeps texting the organiser for; the organiser can now type it once, where the hotel can see it.
2. **Signups:** otherwise, if there are `event_signups` with status `going`: `expected = round(going × show_rate)`, basis `signups`.
   `show_rate` is the community's own history: across its last 10 completed events with at least 5 signups, the sum of check-ins divided by the sum of going signups. With fewer than 3 such events, the platform-wide median is used instead (computed nightly and cached), and failing that `config('venue.default_show_rate', 0.6)`.
3. **Declared:** otherwise, `kolabs.typical_attendance`, basis `declared`.
4. **History:** otherwise, the community's median real attendance over its past events, basis `history`.
5. Otherwise `null`. We never make up a number.

The result is always capped at `events.capacity` when that is set. The response shape:

```json
"attendance": {
  "expected": 42, "expected_low": 35, "expected_high": 50,
  "expected_basis": "signups",
  "going": 55, "waitlist": 4, "show_rate": 0.76,
  "organiser_estimate": null,
  "checked_in": null, "manual_headcount": null, "actual": null
}
```

`expected_low` / `expected_high` are ±1 standard deviation of that community's historical show rate, and are null when that history does not exist.

For a hotel's own event (`kind = venue`): `events.expected_attendees` (manual or from Google), basis `manual | google_calendar`.

**Who sees it:** both parties of the collaboration. The organiser sets their estimate through `PATCH /collaborations/{id}/results` with `{ "organiser_estimate": 40 }` (community party only). Setting it notifies the business in-app, using the existing notification inbox.

## 8. Real attendance

- `checked_in` = the count of `event_checkins` on the event. Check-in at the door already exists (BE-NF-35), and because own events are `events` rows too, the hotel can use it for its own nights as well.
- **Gap to close:** a collaboration without a linked `events` row has no check-in at all. When a collaboration moves to `scheduled`, it should get its event created automatically, reusing what `CollaborationQrCodeController` already does lazily, and setting both `collaborations.event_id` and `events.collaboration_id`. The existing ones are backfilled by a one-off command, not by a migration, because it touches production data (see the memory note on the local `.env` pointing at prod).
- **Manual headcount:** people who walk in without the app are real attendees too. `events.manual_headcount` is settable by the business, and for Kolabing events also by the organiser.
- `actual = manual_headcount ?? checked_in`. Both raw numbers are always returned, so nobody has to guess which one they are looking at.

## 9. Revenue

### 9.1 Results columns on `events`

Every event already has exactly one row, so the results live on that row as well. There is no separate results table.

| column | type | notes |
|---|---|---|
| `revenue_cents` | unsigned bigint, nullable | integer cents; no floats for money |
| `manual_headcount` | unsigned int, nullable | §8 |
| `organiser_estimate` | unsigned int, nullable | §7, community-writable |
| `results_notes` | text, nullable | |
| `revenue_recorded_by_profile_id` | uuid FK profiles, nullable, nullOnDelete | |
| `revenue_recorded_at` | timestamp, nullable | |

Index: `events (profile_id, host_kind, starts_at)` for the own-events side of the insights. The Kolabing side is reached through `collaborations.business_profile_id`.

Endpoints: `PATCH /collaborations/{id}/results` (writes onto the collaboration's event) and `PATCH /me/venue/events/{id}/results`. A form request validates per role: the business may set `revenue_cents` (always EUR — decided 2026-09-24; no currency column, a non-euro market would add one later), `manual_headcount` and `results_notes`; the organiser may set `organiser_estimate` and `manual_headcount`.

**Revenue never leaks.** The existing `EventResource` is served to attendees and communities. It must **not** serialise `revenue_*`, `results_notes` or `revenue_recorded_by_profile_id`. Those only appear in the new venue resources, and only for the business party. `Event::$hidden` lists them as a safety net, and a test asserts their absence from every attendee- and community-facing event response.

**Relation to `collaboration_feedback.revenue`:** today revenue is typed once, inside the business's completion feedback, as a decimal. From now on `events.revenue_cents` is the single source for analytics:

- The completion feedback form keeps its revenue field, but saving it writes to the event when `revenue_cents` is still empty. It never overwrites a number the hotel has already logged.
- A one-off backfill command copies the existing non-null `collaboration_feedback.revenue` values onto their collaboration's event, creating the event first where §8's backfill has not done so yet.
- `collaboration_feedback.revenue` itself is left as it is, so the feedback history stays intact.

## 10. Bulk messages to attendees

A hotel can send a message to the people attending one of its events, by **email**, **push** and the **in-app notification inbox**, from the web panel or the app.

### 10.1 Rules

- **Who can send:** the business party of the collaboration the event belongs to, with an active subscription (the normal §2.8 lapse re-gate applies). No Pro plan is needed.
- **Only events with Kolabing attendees:** in practice Kolabing events. A private own event has no signups, so there is nobody to message.
- **The hotel never sees an attendee's email address or phone number.** It picks an audience, Kolabing delivers, and the hotel only ever sees counts. This is what keeps the feature safe under GDPR: the attendee gave their data to Kolabing and the community, not to the hotel.
- **Audiences:** `going` (includes checked-in), `checked_in` (after the event only), `no_show` (going but not checked in, after the event only).
- **Two kinds of message:**
  - `event_update`: logistics about this event ("the entrance is on the side street", "the brunch starts 30 minutes late"). Allowed from 7 days before the event until 2 days after it. Goes to everyone in the audience who has not muted this venue.
  - `promotion`: something beyond the event ("show this message for 15% off at the rooftop"). Allowed only **after** the event, and only to recipients whose `notification_preferences.marketing_tips` is on **and** who have not muted this venue. This is marketing, so it must be consent-based; see open question 1.
- **Limits:** at most 3 messages per event in total, and at most 1 `promotion` per event. The body is plain text, subject ≤ 120 chars, body ≤ 1,000 chars. No HTML, no attachments, and links are shown as text. The API rejects anything over these with `422` and a clear code (`broadcast_limit_reached`, `broadcast_window_closed`).
- **Excluded recipients:** deactivated profiles (BE-FX-54), profiles banned from the community, and profiles that have muted this venue. Email also respects the master `email_notifications` switch, and push respects `NotificationService::allowsPush()`.
- **No organiser approval** (decided 2026-09-24): the message goes out when the hotel presses send. The organiser is still always informed: every message appears in the collaboration's activity log and arrives in the organiser's notification inbox, so nothing reaches their community behind their back.

### 10.2 Data

- **`attendee_broadcasts`** (UUID PK): `event_id` FK, `collaboration_id` FK, `sender_profile_id` FK, `kind` (`event_update | promotion`), `audience` (`going | checked_in | no_show`), `channels` json (a subset of `email | push | in_app`), `subject`, `body`, `status` (`queued | sending | sent | failed`), `recipient_count`, `email_sent_count`, `push_sent_count`, `skipped_count`, `queued_at`, `sent_at`, timestamps.
- **`attendee_broadcast_recipients`** (composite PK `broadcast_id` + `profile_id`, no UUID): `email_status`, `push_status`, `in_app_status` (each `sent | skipped | failed`), `skip_reason` nullable, `delivered_at`. This is our own audit trail, used for "why didn't X get it", and it is never exposed to the hotel per recipient.
- **`venue_message_mutes`** (composite PK `profile_id` + `business_profile_id`): `created_at`. Written by the one-click "stop messages from this venue" link in every email footer (a signed URL) and by a toggle on the event page in the app.

### 10.3 Delivery

- `POST /collaborations/{id}/broadcasts` → validates → stores `queued` → dispatches `SendAttendeeBroadcast` (queued, chunked by 100 recipients) → returns `202` with the broadcast and its `recipient_count` estimate.
- `GET /collaborations/{id}/broadcasts` lists what has been sent, with counts. It is visible to both parties.
- `POST /collaborations/{id}/broadcasts/preview` returns the recipient count and a rendered email preview **without sending**. Both clients show this before the send button, which is the same "a human reads the full preview" rule as the admin sales mailing.
- **Email:** `EmailService::send()` with a new category `CATEGORY_EVENT_HOST` (respects the master switch; a promotion additionally requires `marketing_tips`) and a new Postmark template `event-host-message` (EN/ES/CA). The sender shows as "Hotel name via Kolabing" on our own domain. `Reply-To` is the hotel's public contact email if it has one, so an attendee who replies does so knowingly.
- **Push + in-app:** a new `NotificationType::EventHostMessage` (`'event_host_message'`), created through `NotificationService::createNotification()`, with a deeplink to the event page.

## 11. Results + graphs (Venue Pro)

`GET /api/v1/me/venue/insights?from=&to=&granularity=week|month`

- **Not Pro:** `200` with `{ "success": true, "data": { "locked": true, "required_plan": "pro" } }` and no numbers. The client draws the blurred placeholder. This is deliberately **not** a `403`: a 403 reads as "you may not be here", and golden rule 5 says the screen is allowed; only its content is withheld.
- **Pro:** everything below, computed over the chosen range, over the business's `events` rows (own events plus the events of its collaborations). Cancelled events are excluded.

```json
{
  "locked": false,
  "range": { "from": "2026-07-01", "to": "2026-09-30" },
  "totals": {
    "events": 18, "events_with_revenue": 15,
    "revenue_cents": 1843000, "revenue_per_event_cents": 122866,
    "attendees_actual": 812, "revenue_per_attendee_cents": 2270,
    "show_rate": 0.71, "expected_vs_actual": 0.94
  },
  "revenue_series": [ { "period": "2026-07", "revenue_cents": 512000, "events": 5 } ],
  "events": [ { "id": "…", "kind": "collaboration", "title": "…", "date": "…",
                "expected": 40, "actual": 46, "revenue_cents": 180000 } ],
  "top_communities": [ { "profile": { "id": "…", "name": "…" }, "events": 4,
                         "revenue_cents": 610000, "revenue_per_event_cents": 152500,
                         "avg_actual": 51, "show_rate": 0.78, "avg_rating": 4.8 } ],
  "top_collaborations": [ "…same shape as events, ranked…" ],
  "event_types": [ { "type": { "slug": "running_club", "name": "Running club" },
                     "events": 6, "revenue_per_event_cents": 140000, "avg_actual": 48 } ],
  "kolabing_vs_own": {
    "kolabing": { "events": 12, "revenue_per_attendee_cents": 2510 },
    "own":      { "events": 6,  "revenue_per_attendee_cents": 1790 }
  }
}
```

- **Ranking:** every "top" list is sorted by `?sort=revenue|revenue_per_event|attendance|show_rate|rating`, default `revenue`. We do **not** invent a single opaque "success score". A manager has to be able to tell why a community is first, and different hotels care about different things.
- **Small numbers:** a group with fewer than 2 events with revenue gets `"low_sample": true`, so the client can grey it out instead of crowning a community on one lucky night.
- **"Most successful businesses"** from the brief is **deferred** (decided 2026-09-24): it is not the hotel's restaurants and bars, and it is not part of this spec. There is no `top_businesses` block.
- **Event type** comes from the community's `community_type` (Kolabing events) or `events.event_type_slug` (own events), both from the admin-managed `community_types` lookup. **Verify** that `community_profiles.community_type` holds these slugs before relying on it.
- **Performance:** one aggregate query per block, filtered on the business plus date. The response is cached per profile for 10 minutes and busted on any results write.
- **Web page:** `/insights` (and `/es`, `/ca`), with a nav entry for every business, where non-Pro sees the blurred state with an upgrade card that reads its price from config. Charts: revenue over time (bars), revenue per event (sorted bars), top communities (ranked list with inline bars), event types (bars), and expected-vs-actual per event (dot pairs). Use the `dataviz` skill conventions. The panel has no bundler and the CSP forbids CDNs, so the charts are inline SVG rendered server-side or by a small hand-written script, the same approach as `kb-realtime.js`.
- **CSV export:** `GET /me/venue/insights.csv`, Pro only, one row per event.

## 12. API contract summary (mobile impact)

All changes are **additive**. No existing key is renamed or removed.

| Endpoint | New / changed | Gate |
|---|---|---|
| `GET /me/venue/calendar` | new | business |
| `GET /venue/calendar.ics?token=` | new (public, secret token) | token |
| `GET/POST/PATCH/DELETE /me/venue/events[/{id}]` | new | business, owner |
| `PATCH /me/venue/events/{id}/results` | new | business, owner |
| `PATCH /collaborations/{id}/results` | new | party (role-scoped fields) |
| `GET/POST /collaborations/{id}/broadcasts`, `POST …/preview` | new | GET: both parties; POST: business party + active subscription |
| `GET /me/venue/insights`, `.csv` | new | business; data only for Pro |
| `DELETE /me/integrations/google-calendar`, `GET /me/integrations` | new | business |
| `GET /me/subscription` | gains `plan: "standard" \| "pro"` | — |
| Collaboration resource | gains an `attendance` block (§7) and, for the business side only, `revenue_cents` | — |
| `EventVisibility` | gains `private` (owner-only) | — |
| `NotificationType` | gains `event_host_message` | — |
| `POST /me/subscription/checkout` | `plan` accepts `pro_monthly` (web only) | — |

**kolabing-app work (needs its own ticket):** calendar screen, own-events CRUD, results entry (revenue / headcount / organiser estimate), broadcast composer with preview, rendering `event_host_message` plus the mute toggle, the insights screens with a blurred state, reading `plan`, and tolerating the `private` visibility value. **No new IAP product, and no purchase or price UI for Pro.** Google Calendar connect opens the web page in a browser tab.

## 13. Build order

1. **Plan + entitlement** (§3): column, enum, config, checkout, webhook plan resolution, plan change, admin grant. Nothing visible changes for current subscribers.
2. **`events` columns + isolation** (§5.1, §5.2, §9.1): the migration, `EventVisibility::Private`, `scopeCommunityHosted()`, the 34-call-site audit and its tests, `Event::$hidden`. This lands **before** any venue event can be created, so there is never a window in which one could leak.
3. **Forecast + attendance + revenue** (§7–§9): `AttendanceForecastService`, the auto-created collaboration events plus the backfill command, and the revenue backfill.
4. **Calendar + own events** (§4, §5.3), including the ICS feed.
5. **Insights** (§11): API plus the web page. This is the first thing a Pro customer pays for, and it can be demoed to hotels from here on.
6. **Bulk messages** (§10).
7. **Google Calendar** (§6). It comes last, but the **Google OAuth verification is started in week 1**, because it is the slowest part and is outside our control.

Each step is its own PR, with the template filled in and the mobile impact stated.

## 14. Open questions

Decided 2026-09-24 and closed: own events live in `events`; outlets are dropped; no organiser approval of messages; no annual Pro price for now; "most successful businesses" is deferred (not in this spec); revenue is EUR only.

1. **Promotional messages:** is `marketing_tips` (all-on by default when no preference row exists) strong enough consent for a third party's promotion? It should go past legal (the legal-advisor agent) before `promotion` ships. The fallback is to ship `event_update` only.

## 15. Docs that must change with the code

- `docs/ROLES-AND-PERMISSIONS.md`: amend golden rule 2 to add "the Venue Pro analytics surface" as the one paid surface beyond create/apply; add new §2.19 (Venue Pro) and a §4 note on attendee messages; update the permission matrix and bump the date.
- `docs/ROLES-BACKEND-DB-MAP.md`: map every rule above to code and tables; add checklist items for the organiser-sees-no-revenue rule, the attendee-email-never-exposed rule, and the venue-events-never-in-attendee-surfaces rule.
- `CLAUDE.md`: the roles block gains "Venue Pro plan + attendee messages" as a role surface.
- The `kolabing-app` copies of the two roles docs.
- `BACKLOG.md`: BE-NF-68 moves to Incomplete when step 1 starts.
- `docs/BACKEND-SCHEMA.md` does not exist in the repo (see BE-FX-63). This spec was checked against the migrations directly.
