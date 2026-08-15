# Web App external setup — runbook for an operator with no prior context

You've been asked to do the **browser/console setup** to launch the **Kolabing Web App**. This document
assumes you know nothing about Kolabing. Read "Background" once, then do the tasks in order. You will be
clicking around four services (Stripe, Google Cloud, Laravel Cloud, and a DNS provider) and copying a
handful of values between them. **No coding.**

---

## Background — what you're setting up and why

**Kolabing** is a marketplace that connects **local businesses** (cafés, restaurants, gyms) with
**community organizers** (run clubs, social groups). A collaboration between them is called a **"Kolab."**
Businesses pay a **subscription** to publish Kolabs; communities use it for free. Today Kolabing is a
**mobile app** backed by a web API. The marketing website is **kolabing.com**.

**What we're launching:** a **web version** of the app at a new address, **`app.kolabing.com`**, so people
can **sign up and pay by credit card in a browser** (not only in the mobile app), and then get nudged into
the mobile app. The programming is already done and under review; what remains is **external account
setup that can only be done by clicking in web consoles** — which is this runbook.

**How the pieces fit:**
- **Stripe** is the payment processor. It holds the subscription **products/prices** (€49/month and
  €129/3-months), processes card payments in a hosted **Checkout** page, and provides a hosted **Billing
  Portal** where a customer can cancel. When a payment succeeds, Stripe calls our server at a **webhook**
  URL to tell it to switch the subscription on.
- **Google Cloud** issues the credential ("Web OAuth client") that makes the **"Sign in with Google"**
  button work on the website.
- **Laravel Cloud** is where the Kolabing server runs. You'll (a) paste the secret values from Stripe and
  Google into its **Environment variables**, and (b) attach the **`app.kolabing.com`** domain.
- **DNS** (wherever `kolabing.com` is managed — likely Cloudflare or a registrar) is where you point
  `app.kolabing.com` at Laravel Cloud so the address resolves.

**The values you'll create and where each goes** (you'll collect these into a scratch note as you go):

| Value | Created in | Looks like | Pasted into (Laravel Cloud env) |
|---|---|---|---|
| Secret API key | Stripe | `sk_test_…` / `sk_live_…` | `STRIPE_SECRET_KEY` |
| Webhook signing secret | Stripe (webhook) | `whsec_…` | `STRIPE_WEBHOOK_SECRET` |
| Monthly price id | Stripe (product) | `price_…` | `STRIPE_MONTHLY_PRICE_ID` |
| 3-month price id | Stripe (product) | `price_…` | `STRIPE_THREE_MONTHS_PRICE_ID` |
| Web OAuth client id | Google Cloud | `…apps.googleusercontent.com` | `GOOGLE_CLIENT_ID_WEB` |

> ⚠️ **Security — read before you start.**
> - Do **everything in Stripe TEST mode first** (there's a Test/Live toggle top-right in Stripe). Verify
>   it works end-to-end, *then* repeat in Live mode. Test mode uses fake cards and cannot charge anyone.
> - The `sk_…` and `whsec_…` values are **secrets**. Paste them **only** into the Stripe/Google consoles
>   and the Laravel Cloud env-var screen. **Never** put them in chat, email, a doc, a screenshot, or a
>   commit. If a secret is ever exposed, roll it (regenerate) in the provider.
> - You need access to Kolabing's Stripe, Google Cloud, Laravel Cloud, and DNS accounts. If you don't have
>   one, stop and ask the owner (Daniel) rather than creating new accounts.

**Prerequisite:** the code must be merged first (the engineer/Volkan handles that). The Laravel Cloud
production environment is named **`master`** and it **auto-deploys and runs database migrations** whenever
code is merged — you don't run any migrations yourself.

---

## Task 1 — Stripe: products, prices, webhook, billing portal

Go to **https://dashboard.stripe.com/** and make sure the **"Test mode"** toggle (top-right) is **ON**.

### 1a. Create the two subscription products/prices
1. Left sidebar → **Product catalog** (or go to https://dashboard.stripe.com/test/products) → **+ Add product**.
2. Product 1: Name = **`Kolabing Business — Monthly`**. Under *Pricing*: **Recurring**, Amount **`49.00`**,
   Currency **EUR**, Billing period **Monthly**. Save.
   - Open the product, find the **Price** you just made, and **copy its API ID** (starts with `price_`).
     Save it as **`STRIPE_MONTHLY_PRICE_ID`**. *(The `price_…` ID, not the `prod_…` product ID.)*
3. Product 2: Name = **`Kolabing Business — 3 Months`**. Pricing: **Recurring**, Amount **`129.00`** EUR,
   Billing period = **Custom / every 3 months** (set the interval to 3 months). Save.
   - Copy its **Price ID** → **`STRIPE_THREE_MONTHS_PRICE_ID`**.

### 1b. Copy the secret API key
1. Go to **https://dashboard.stripe.com/test/apikeys** (Developers → API keys).
2. Under *Standard keys*, reveal the **Secret key** (`sk_test_…`) → save as **`STRIPE_SECRET_KEY`**.
   *(Ignore the Publishable key — not needed.)*

### 1c. Create the webhook endpoint
This is how Stripe tells our server a payment happened.
1. Go to **https://dashboard.stripe.com/test/webhooks** (Developers → Webhooks) → **+ Add endpoint**.
2. **Endpoint URL:** `https://app.kolabing.com/api/v1/webhooks/stripe`
3. **Select events to send** → add exactly these three:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
4. Add endpoint. Then open it and **reveal the "Signing secret"** (`whsec_…`) →
   save as **`STRIPE_WEBHOOK_SECRET`**.

### 1d. Turn on the Billing Portal (so customers can cancel)
1. Go to **https://dashboard.stripe.com/test/settings/billing/portal**.
2. Click **Activate** (or "Save"). Under *Subscriptions*, enable **Cancel subscriptions** (and optionally
   "Switch plans"). Save.

At the end of Task 1 your scratch note should have: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`,
`STRIPE_MONTHLY_PRICE_ID`, `STRIPE_THREE_MONTHS_PRICE_ID`.

---

## Task 2 — Google Cloud: the "Sign in with Google" web credential

1. Go to **https://console.cloud.google.com/apis/credentials**. In the project picker (top bar), select the
   **existing Kolabing project** — the one that already contains the iOS/Android OAuth clients. *(Don't
   create a new project.)*
2. **+ Create credentials → OAuth client ID.**
3. **Application type: Web application.** Name it **`Kolabing Web`**.
4. Under **Authorized JavaScript origins**, click **+ Add URI** and enter: **`https://app.kolabing.com`**.
   *(Optionally also add `http://localhost:5173` if the dev team wants local web testing.)*
5. Leave **Authorized redirect URIs** empty (the website uses Google's token flow, which only needs the
   origin above).
6. **Create**, then **copy the Client ID** (ends in `…apps.googleusercontent.com`) →
   save as **`GOOGLE_CLIENT_ID_WEB`**. *(You do NOT need the client secret.)*

---

## Task 3 — Laravel Cloud: paste the values as environment variables

1. Go to **https://cloud.laravel.com/** and open the app **`kolabing-v2`**.
2. Select the environment named **`master`** (this is production).
3. Open **Environment variables**. **Add or update** each of these keys with the values from Tasks 1–2:

   | Key | Value |
   |---|---|
   | `STRIPE_SECRET_KEY` | the `sk_test_…` from Task 1b |
   | `STRIPE_WEBHOOK_SECRET` | the `whsec_…` from Task 1c |
   | `STRIPE_MONTHLY_PRICE_ID` | the `price_…` from Task 1a (monthly) |
   | `STRIPE_THREE_MONTHS_PRICE_ID` | the `price_…` from Task 1a (3-month) |
   | `GOOGLE_CLIENT_ID_WEB` | the `…apps.googleusercontent.com` from Task 2 |

4. Save, then **Deploy / redeploy** the `master` environment so the new variables take effect (there's a
   Deploy button; a redeploy of the current version is fine).

Notes / don't-touch:
- Do **not** change the existing iOS/Android Google keys or any other variable.
- You do **not** need to set `STRIPE_ALLOWED_RETURN_HOSTS` — the app already allows `app.kolabing.com`.

---

## Task 4 — Point `app.kolabing.com` at Laravel Cloud

1. In **Laravel Cloud** → app `kolabing-v2` → environment **`master`** → **Domains** → **Add domain**:
   enter **`app.kolabing.com`**. Laravel Cloud will display a **DNS target** to point at (usually a
   `CNAME` target ending in `.laravel.cloud`) and will issue an HTTPS certificate automatically once DNS
   resolves. **Copy that target value.**
2. Go to the **DNS provider that manages `kolabing.com`** (likely **Cloudflare**, or the domain registrar).
   Add a record:
   - **Type:** `CNAME`  ·  **Name/Host:** `app`  ·  **Value/Target:** the target from step 1.
   - **If Cloudflare:** set the record to **DNS only (grey cloud, not orange/proxied)** at first, so
     Laravel Cloud can validate and issue the certificate. You can switch it to proxied afterward.
3. Wait for DNS to propagate (minutes to ~an hour). Return to Laravel Cloud and confirm the domain shows
   **Active** with a valid **TLS/SSL** certificate.

---

## Task 5 — Verify it works (still in Stripe test mode)

1. Open **`https://app.kolabing.com`** in a browser — it should load over HTTPS with no certificate warning.
2. Open **`https://app.kolabing.com/api/v1/cities`** — it should return **JSON** (proves the API is reachable
   on the subdomain).
3. On **`https://app.kolabing.com/login`**, the **"Sign in with Google"** button should appear and let you
   sign in (this proves `GOOGLE_CLIENT_ID_WEB` is correct). *If the button is absent, the Google env var
   isn't set/deployed yet.*
4. **Payment round-trip:** sign up / log in as a **business** account, go to the subscription page, click
   **Subscribe**. On Stripe's checkout page use the **test card `4242 4242 4242 4242`**, any future expiry,
   any CVC and postal code. After paying you should land back on the app's "you're subscribed" page, and the
   account should now show an **active** subscription. *(Behind the scenes Stripe called the webhook from
   Task 1c to switch it on.)*
5. On the subscription page, **Manage** should open the Stripe **Billing Portal**; cancelling there should
   flip the subscription off in the app shortly after.

If step 4 doesn't activate the subscription, re-check the webhook (Task 1c): the URL is exactly
`https://app.kolabing.com/api/v1/webhooks/stripe`, the three events are selected, and
`STRIPE_WEBHOOK_SECRET` in Laravel Cloud matches that endpoint's signing secret.

---

## Task 6 — Go live (real payments)

Once test mode passes end-to-end, switch **Stripe to Live mode** (toggle top-right) and **repeat Tasks 1a–1c**
there: the live products/prices give **new** `price_…` IDs, the live secret key is `sk_live_…`, and you must
create the **live** webhook endpoint (same URL + same 3 events) for a new `whsec_…`. Also **activate the
Billing Portal in live mode** (Task 1d). Then update the same five Laravel Cloud env vars (Task 3) with the
**live** values and redeploy. Google (Task 2) and DNS (Task 4) are the same for test and live — no change.

---

## What to report back (names only — never the secret values)
- ✅/❌ each set in Laravel Cloud: `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_MONTHLY_PRICE_ID`,
  `STRIPE_THREE_MONTHS_PRICE_ID`, `GOOGLE_CLIENT_ID_WEB`.
- The webhook endpoint URL you created, and that the 3 events are attached.
- Billing Portal = activated (test / live).
- `app.kolabing.com` = Active with valid TLS in Laravel Cloud.
- Task 5 checks 1–5 = pass/fail (note which failed).
- Whether you completed **test mode only**, or **also went live** (Task 6).
