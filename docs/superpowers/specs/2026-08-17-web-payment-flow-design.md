# Web payment flow completion (app.kolabing.com + kolabing.com/pricing)

> Design spec — 2026-08-17
> Epic #128 (Kolabing Web App), branch `feat/kolabing-web-app`.
> Backlog item: **BE-NF-26**. Builds on BE-NF-17 (Stripe web checkout) and
> BE-NF-19 (Billing Portal), whose code is already on this branch.

## 1. Problem

The Stripe plumbing exists — `StripeService`, `POST /me/subscription/checkout`,
`POST /me/subscription/portal`, `POST /webhooks/stripe` — and the web app has a
`/subscription` page that can start a Checkout Session. But the flow is not a
flow you can *sell* with. Six concrete gaps:

| # | Gap | Consequence |
|---|-----|-------------|
| P1 | Activation is webhook-only. `success_url` is `/welcome?paid=1`, a static "thanks" page. | A business pays, is told it worked, and is still paywalled — for as long as the webhook lags. If the webhook is not registered on prod at all (today's state), the sale never activates. |
| P2 | Checkout Session sends no `customer` / `customer_email`, no `allow_promotion_codes`, no `locale`. | Buyer retypes their email; a repeat buyer creates a duplicate Stripe customer (which breaks the Billing Portal lookup); sales cannot run a discount campaign; the checkout page is English for Spanish buyers. |
| P3 | The 3-month plan is a small text link under the monthly card. | No comparison, no anchoring, no visible saving — the higher-value plan is effectively hidden. |
| P4 | Paywall redirects are context-free (`window.nav('/subscription')`). | The user lands on a pricing page with no idea which action was blocked. |
| P5 | `past_due` (card declined) has no surface anywhere. | Access silently degrades; the business never learns to update its card, and churns. |
| P6 | No public pricing page. | Sales has no link to send; the price is only visible after registering *and* logging in as a business. |

## 2. Non-goals

- Not touching the Apple IAP path or `source = maintainer` grants.
- Not building an in-house billing UI. Stripe's hosted Checkout and Billing
  Portal remain the payment and management surfaces.
- Not adding trials, seats, annual plans, or proration logic.
- Not changing who is paywalled. The Business-only paywall and the rule that
  **communities never pay** are unchanged (`docs/ROLES-AND-PERMISSIONS.md` §3).

## 3. Design

### 3.1 Webhook-independent activation (fixes P1)

The webhook stays the source of truth for the *lifecycle* (renewal, cancellation,
payment failure). It stops being the only path to the *first* activation.

- `success_url` becomes `{base}/subscription/success?session_id={CHECKOUT_SESSION_ID}`
  (Stripe substitutes the placeholder on redirect; the host is unchanged, so the
  existing `ValidatesReturnUrl` allowlist still holds).
- New endpoint **`POST /api/v1/me/subscription/checkout/confirm`** `{session_id}`:
  1. business-only (403 otherwise), `session_id` must match `/^cs_[A-Za-z0-9_]+$/`;
  2. retrieve the Checkout Session from Stripe;
  3. **ownership check** — `client_reference_id` (falling back to
     `metadata.profile_id`) must equal the caller's profile id, else **403**.
     This is the security boundary: a session id is not a bearer token;
  4. if `payment_status !== 'paid'` and `status !== 'complete'` → **409** with
     `{"status": "pending"}`;
  5. otherwise call the *same* `SubscriptionService::activateFromStripeSession()`
     the webhook calls, then return `SubscriptionResource` of the fresh row.
- Idempotency is inherited: `activateFromStripeSession` upserts on
  `profile_id` (unique), and `ReferralService::rewardFirstPaidSubscription`
  already guards the first-paid case. Confirm-then-webhook and
  webhook-then-confirm converge on the same row.
- Stripe API failure → **502**, same shape as the other two endpoints.

New web page **`/subscription/success`**:
- reads `session_id`, calls confirm, and retries on 409 with backoff
  (5 attempts, ~1s → ~4s, ≈10s total);
- **activated** → success card: plan name, next billing date, a primary
  "Create your first Kolab" CTA, and the existing app-handoff nudge folded in;
- **still pending after the retries** → "Payment received — activating your
  plan" with a manual retry button and a support line. Never a dead end,
  never a false "you're live" claim;
- **no `session_id`** (someone opened the URL directly) → falls back to reading
  `GET /me/subscription` and renders whichever of the two states applies.

`/welcome?paid=1` keeps working (mobile deep-link handoff still points there),
but the web checkout no longer routes through it.

### 3.2 Checkout Session upgrades (fixes P2)

In `StripeService::createCheckoutSession`:

- if the profile already has a `stripe_customer_id`, pass `customer`;
  otherwise pass `customer_email` = the profile's email. Never both — Stripe
  rejects that. This kills duplicate customers, which is what silently breaks
  the Billing Portal for a returning buyer;
- `allow_promotion_codes: true` — sales creates codes in the Stripe dashboard;
  the buyer enters them on Stripe's own screen. Independent of the referral
  code, which rewards the *referrer* and is not a discount;
- `locale` mapped from the app locale: `en → en`, `es → es`, `ca → es`
  (Stripe Checkout has no Catalan locale; Spanish is the closest supported
  language for that audience). Documented in code.

### 3.3 Plan comparison (fixes P3)

`/subscription` renders two selectable cards driven by `config/subscriptions.php`
— no hardcoded prices, no hardcoded saving:

- Monthly — `€{monthly.price}` / month
- 3 months — `€{three_months.price}` billed once, shown as `€{round(price/3)}` /month
  with a `Save {n}%` badge where `n = round((1 - (three/3) / monthly) * 100)`.

One primary CTA acts on the selected plan. The referral input, benefits list and
footnote stay. If either plan has no `stripe_price_id` configured, that card is
hidden rather than rendering a button that 502s.

### 3.4 Paywall context (fixes P4)

Every paywall redirect gains a reason:

| Call site | Reason |
|---|---|
| `kolabs.blade.php` publish 402 | `publish` |
| `kolabs.blade.php` accept 402/403 | `accept` |
| `kolab-form.blade.php` publish 402 | `publish` |
| `partials/kolab-modals.blade.php` publish 402 | `publish` |
| `partials/kolab-modals.blade.php` apply 402/403 | `apply` |
| `login`/`register` post-signup business hop | `welcome` |

`/subscription?reason=…` renders a banner above the plans from
`webapp.subscription.reason.{publish,accept,apply,welcome}` in en/es/ca. An
unknown or absent reason renders no banner.

### 3.5 Payment-problem banner (fixes P5)

- `UserResource` gains, for business profiles only, two additive fields:
  `subscription_status` (the enum value, or `null` when there is no row) and
  `subscription_cancel_at_period_end` (bool). Additive — no existing key
  changes shape. **Mobile impact:** additive only; the Flutter client needs no
  change, and may adopt the same fields for its own banner.
- The web-app shell (`webapp/layout.blade.php`) shows a sticky banner when the
  viewer is a business whose `subscription_status === 'past_due'`:
  "We couldn't charge your card — update your payment method to keep publishing",
  with a button that opens the Billing Portal.
- A `cancel_at_period_end` subscription shows an informational line on
  `/subscription` ("Active until {date}, then it ends") plus a Resume hint that
  routes to the portal. No new endpoint.

### 3.6 Public pricing page (fixes P6)

`resources/views/pages/pricing.blade.php` on `<x-layouts.marketing-page>`,
following the site's existing per-locale-file convention (`pages/es/…`, route
name suffix `.es`) rather than inventing marketing i18n middleware:

- `GET /pricing` → `pricing` (en) and `GET /es/pricing` → `pricing.es`,
  cross-linked with `hreflang` `alternates` (the layout already supports this).
  Catalan is out of scope: the marketing site has no `ca` pages at all; the
  Catalan pricing surface is the in-app `/ca/subscription`, which already exists.
- The markup lives once in `pages/partials/pricing-content.blade.php`, which
  takes a copy array per locale. The site duplicates whole files for
  terms/privacy, but pricing carries derived numbers (per-month equivalent,
  saving %) — duplicating those is how the two pages drift apart.
- Prices read from `config/subscriptions.business.stripe.*` — one source of truth
  with the thing that actually bills.
- Both plans, the benefits list, a short FAQ (what happens after I pay, can I
  cancel, do communities pay — answer: never), and a primary CTA to
  `https://app.kolabing.com/register?type=business&plan={plan}`.
- `Product` + two `Offer` JSON-LD nodes plus `FAQPage`, canonical, OG image (the
  layout supplies Organization JSON-LD already).
- Added to `/sitemap.xml` and `/llms.txt`, plus a "Pricing" link in the
  marketing header and footer nav.

### 3.7 Marketing → checkout handoff

- `/register` already reads `?type=business|community` to skip the role picker.
  It now also reads `?plan=`, and `postAuthPath()` sends a freshly registered
  business to `/subscription?reason=welcome&plan=…`, which preselects the
  matching card. Two clicks from the pricing page to Stripe.

## 4. Files

**Backend**
- `app/Services/StripeService.php` — customer/email, promo codes, locale;
  `retrieveCheckoutSession()`.
- `app/Http/Requests/Api/V1/ConfirmCheckoutSessionRequest.php` — new.
- `app/Http/Controllers/Api/V1/SubscriptionController.php` — `confirmCheckout()`.
- `routes/api.php` — `POST me/subscription/checkout/confirm`.
- `app/Http/Resources/Api/V1/UserResource.php` — two additive fields.

**Web app (Blade + Alpine)**
- `resources/views/webapp/subscription.blade.php` — plan cards, reason banner,
  new success URL, cancel-at-period-end line.
- `resources/views/webapp/subscription-success.blade.php` — new.
- `resources/views/webapp/layout.blade.php` — `past_due` banner + shell state.
- `resources/views/webapp/{kolabs,kolab-form,register}.blade.php`,
  `resources/views/webapp/partials/kolab-modals.blade.php` — reason params,
  `?plan=` / `?intent=` handoff.
- `routes/web.php` — `/subscription/success` in the webapp route group.
- `lang/{en,es,ca}/webapp.php` — new `subscription.*` keys, 100% parity.

**Marketing**
- `resources/views/pages/pricing.blade.php`, `resources/views/pages/es/pricing.blade.php`
- `resources/views/components/layouts/marketing-page.blade.php` — nav links.
- `routes/web.php` — two routes, sitemap + llms.txt entries.

**Tests**
- `tests/Feature/Api/V1/CheckoutConfirmTest.php` — new (8 cases).
- `tests/Feature/Api/V1/CheckoutSessionParamsTest.php` — new (6 cases).
- `tests/Feature/Api/V1/SubscriptionControllerTest.php` — extended (the two
  additive `/auth/me` fields).
- `tests/Feature/WebApp/WebAppRoutesTest.php` — extended (plan cards, the
  session-id success URL, reason params, the past-due banner on every page).
- `tests/Feature/PricingPageTest.php` — new (5 cases).

**Docs** — `BACKLOG.md`, `docs/ROLES-AND-PERMISSIONS.md`,
`docs/ROLES-BACKEND-DB-MAP.md` (all three updated in this same change, per
`CLAUDE.md`).

## 5. Testing

| Case | Expectation |
|---|---|
| confirm, paid session owned by caller | 200, `BusinessSubscription` row `source=stripe`, `is_active` |
| confirm, session whose `client_reference_id` is another profile | 403, no row written |
| confirm, session not yet paid | 409, `status: pending`, no row |
| confirm twice | second call 200, still exactly one row (idempotent) |
| confirm as a community profile | 403 |
| confirm, malformed `session_id` | 422 |
| checkout for a profile with an existing `stripe_customer_id` | session built with `customer`, not `customer_email` |
| checkout for a new customer | session built with `customer_email`, `allow_promotion_codes` true |
| `/pricing`, `/es/pricing` | 200, price from config, Product JSON-LD, hreflang pair, present in sitemap |
| `/subscription/success` (+ `/es`, `/ca`) | 200 |

For the endpoint tests `StripeService` is mocked at the service seam, as the
existing `StripeCheckoutTest` and `BillingPortalTest` already do. The session
payload is asserted against the real service via `checkoutSessionParams()`, which
is why that method was split out of the SDK call. `LazilyRefreshDatabase`,
factories, PHPUnit. `vendor/bin/pint` clean.

**Doc drift found while building this:** `ROLES-AND-PERMISSIONS.md` §2.1 quoted
"€39.99/month", which no code path has ever billed — `config/subscriptions.php`
has said €49 / €129 since the Apple IAP work. §2.1 now points at the config as
the source of truth and states the launch prices, so the public pricing page and
the doc cannot disagree.

## 6. Ops (outside this change)

1. Set on prod: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
   `STRIPE_MONTHLY_PRICE_ID`, `STRIPE_THREE_MONTHS_PRICE_ID`.
2. Register the `POST /api/v1/webhooks/stripe` endpoint in the Stripe dashboard
   (`checkout.session.completed`, `customer.subscription.updated`,
   `customer.subscription.deleted`).
3. Enable the Billing Portal and configure cancellation in the dashboard.

§3.1 means a sale completes and activates even before (1)–(2) are done; the
webhook is still required for renewals, cancellations and dunning, so it is not
optional — only no longer a launch blocker.
