# Web App — external / browser setup runbook (for an aside agent)

**Context:** stand up the Kolabing Web App at **`app.kolabing.com`** (epic #128). The **code** is
shipped in PRs #127 (buy), #129 (web Google login), #130 (manage/cancel portal), #103 (paid-feed
blur). This runbook is the **browser-only / portal work** that Clark cannot do headless — hand it to
an agent (or person) with browser access **and** access to Daniel's Laravel Cloud, Google Cloud,
Stripe, and DNS accounts.

**Grounded facts (verified 2026-08-15):**
- Hosting: **Laravel Cloud**, app `kolabing-v2` (`app-a0ea8467-4a5b-4a8e-a513-7635aed3575a`),
  region `us-east-2`. Production environment = **`master`** (`env-a0ea8468-a467-4e9c-af33-25ea48957add`),
  vanity `kolabing-v2-master-tgxggi.laravel.cloud`, auto-deploys on push to `master`, deploy runs
  `php artisan migrate --force`.
- The `master` environment currently has **no `STRIPE_*` vars and no `GOOGLE_CLIENT_ID_WEB`** — those
  are added below.
- The web app is the **same Laravel app** on a second hostname, so `app.kolabing.com/api/v1` is
  same-origin — no CORS work.

> ⚠️ **Secrets discipline:** paste secrets **only** into the Laravel Cloud env var UI (or Stripe/Google
> consoles). Never paste a live secret into chat, a commit, a ticket, or a log. Do these in **Stripe
> test mode first**, verify a full round-trip, then repeat for live mode.

---

## Prerequisites / order
Do the merges first (Clark/Volkan), then this runbook. Merge order: **#127 → #130** (stacked), plus
#129 and #103. The `master` deploy runs migrations automatically; no manual migrate needed.

---

## Task 1 — Stripe: products, prices, webhook, billing portal (test mode first)
Console: https://dashboard.stripe.com/ (toggle **Test mode** ON, top-right).

1. **Products & prices** → https://dashboard.stripe.com/test/products
   - Create product **"Kolabing Business — Monthly"**, recurring price **€49.00 / month**. After save,
     open the price and **copy the Price ID** (`price_…`). → this is `STRIPE_MONTHLY_PRICE_ID`.
   - Create product **"Kolabing Business — 3 Months"**, recurring price **€129.00 every 3 months**
     (billing period = 3 months). Copy its Price ID. → `STRIPE_THREE_MONTHS_PRICE_ID`.
2. **API key** → https://dashboard.stripe.com/test/apikeys → reveal the **Secret key** (`sk_test_…`).
   → `STRIPE_SECRET_KEY`.
3. **Webhook endpoint** → https://dashboard.stripe.com/test/webhooks → **Add endpoint**:
   - Endpoint URL: **`https://app.kolabing.com/api/v1/webhooks/stripe`**
     *(the API is reachable on `kolabing.com` too; either host works — keep it consistent).*
   - **Select events:** `checkout.session.completed`, `customer.subscription.updated`,
     `customer.subscription.deleted`.
   - Save, then **reveal the Signing secret** (`whsec_…`). → `STRIPE_WEBHOOK_SECRET`.
4. **Customer Billing Portal** → https://dashboard.stripe.com/test/settings/billing/portal → **Activate**,
   and under *Subscriptions* enable **Cancel subscription** (and plan switch if desired). Save.

**Capture (into a secure note for Task 3, not chat):** `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
`STRIPE_MONTHLY_PRICE_ID`, `STRIPE_THREE_MONTHS_PRICE_ID`.

---

## Task 2 — Google Cloud: Web OAuth 2.0 client
Console: https://console.cloud.google.com/apis/credentials (select the existing Kolabing project —
the one that already holds the iOS/Android OAuth clients).

1. **Create credentials → OAuth client ID → Application type: Web application.** Name it
   "Kolabing Web".
2. **Authorized JavaScript origins:** add `https://app.kolabing.com` (and `http://localhost:5173` or the
   dev origin if the team wants local web dev).
3. **Authorized redirect URIs:** only needed if the redirect (code) flow is used. The plan uses Google
   **Identity Services** (id_token → posted to `POST /api/v1/auth/google`), which needs only the JS
   origin. Add a redirect URI only if the frontend later uses the redirect flow.
4. Create, then **copy the Client ID** (`…apps.googleusercontent.com`). → `GOOGLE_CLIENT_ID_WEB`.

---

## Task 3 — Laravel Cloud: env vars (production `master` env)
Console: https://cloud.laravel.com/ → app **kolabing-v2** → environment **master** → **Environment
variables**. Add/set the following, then **Deploy** (or redeploy) so they take effect:

| Key | Value |
|---|---|
| `STRIPE_SECRET_KEY` | from Task 1.2 |
| `STRIPE_WEBHOOK_SECRET` | from Task 1.3 |
| `STRIPE_MONTHLY_PRICE_ID` | from Task 1.1 |
| `STRIPE_THREE_MONTHS_PRICE_ID` | from Task 1.1 |
| `GOOGLE_CLIENT_ID_WEB` | from Task 2.4 |

Notes:
- `STRIPE_ALLOWED_RETURN_HOSTS` does **not** need setting — the code default already includes
  `kolabing.com,www.kolabing.com,app.kolabing.com`. Only set it if you want to restrict/extend that list.
- `APPLE_IAP_THREE_MONTHS_PRICE` should be `129` (matches the Stripe 3-month price); set if unset.
- Leave the iOS/Android/primary Google client ids untouched.

---

## Task 4 — DNS + domain for `app.kolabing.com`
1. **Laravel Cloud** → app **kolabing-v2** → env **master** → **Domains** → **Add domain**
   `app.kolabing.com`. Laravel Cloud will show the **DNS target** to point at (a `CNAME` target, e.g.
   `…laravel.cloud`) and will provision TLS automatically once DNS resolves. **Copy that target.**
2. **DNS provider** (registrar / Cloudflare for `kolabing.com`) → add a record:
   - Type `CNAME`, name `app`, value = the target from step 1.
   - If Cloudflare: set it **DNS-only (grey cloud)** initially so Laravel Cloud can issue the cert;
     you can enable proxying afterward.
3. Wait for propagation, then confirm Laravel Cloud shows the domain **Active** with a valid cert.

---

## Task 5 — Verify the round-trip (test mode)
1. `https://app.kolabing.com` loads over HTTPS (valid cert).
2. `https://app.kolabing.com/api/v1/cities` returns JSON (API reachable on the subdomain).
3. Google web sign-in: from the web login page, "Sign in with Google" returns a token and
   `POST /api/v1/auth/google` logs in (no "invalid client" — confirms `GOOGLE_CLIENT_ID_WEB`).
4. Checkout: as a business user, start checkout → pay with Stripe test card `4242 4242 4242 4242` →
   Stripe fires `checkout.session.completed` → the webhook activates the subscription
   (`business_subscriptions` row `source=stripe`, `status=active`). Confirm in-app the paywall is lifted.
5. Manage: `POST /api/v1/me/subscription/portal` returns a `portal_url`; opening it shows the Stripe
   portal; cancelling there fires `customer.subscription.updated/deleted` and the sub reflects it.

## Task 6 — Go live
Repeat Tasks 1–3 in **Stripe live mode** (new `sk_live_…`, `whsec_…`, live Price IDs, live webhook
endpoint + activate the live billing portal), update the Laravel Cloud `master` env vars to the live
values, and redeploy. The Google OAuth client and DNS are the same for test/live.

---

### Values the aside agent must report back (names only — NOT the secret values)
Confirm each was set: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_MONTHLY_PRICE_ID`,
`STRIPE_THREE_MONTHS_PRICE_ID`, `GOOGLE_CLIENT_ID_WEB`; the webhook endpoint URL; the billing portal
= activated; `app.kolabing.com` = Active + TLS; and the Task 5 checks = pass/fail.
