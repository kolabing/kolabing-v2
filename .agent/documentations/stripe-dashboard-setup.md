# Stripe Dashboard Setup Checklist

Bu adımları sırayla yap. Sonunda 4 key elde edeceksin.

---

## 1. API Keys

**Developers → API Keys**

| Key | .env değişkeni |
|---|---|
| Secret key (`sk_live_...`) | `STRIPE_SECRET` |
| Publishable key (`pk_live_...`) | `STRIPE_PUBLISHABLE` |

> Test için `sk_test_` / `pk_test_` kullanabilirsin.

---

## 2. Ürün ve Fiyat Oluştur

**Products → Add Product**

- **Name:** Kolabing Business
- **Description:** Aylık abonelik planı
- Pricing bölümünde:
  - **Pricing model:** Standard pricing
  - **Recurring:** seç
  - **Billing period:** Monthly
  - **Price:** istediğin fiyat (örn. €29.00)
  - **Currency:** EUR
- **Save product**

Kaydettikten sonra fiyatın altında `price_xxxxxxxxxxxxxxxx` formatında bir ID görürsün.

| Key | .env değişkeni |
|---|---|
| `price_xxxxxxx` | `STRIPE_MONTHLY_PRICE_ID` |

---

## 3. Webhook Endpoint Ekle

**Developers → Webhooks → Add endpoint**

- **Endpoint URL:**
  ```
  https://api.kolabing.com/api/v1/webhooks/stripe
  ```
- **Listen to:** Select events → şunları seç:
  - `checkout.session.completed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `invoice.payment_failed`
  - `invoice.payment_succeeded`
- **Add endpoint**

Endpoint oluştuktan sonra sayfada **Signing secret** → **Reveal** butonu çıkar.

| Key | .env değişkeni |
|---|---|
| `whsec_...` | `STRIPE_WEBHOOK_SECRET` |

---

## 4. .env Dosyasını Doldur

```env
STRIPE_SECRET=sk_live_...
STRIPE_PUBLISHABLE=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_MONTHLY_PRICE_ID=price_...
```

---

## Özet — Elde Edilecek 4 Key

| .env | Nereden |
|---|---|
| `STRIPE_SECRET` | API Keys → Secret key |
| `STRIPE_PUBLISHABLE` | API Keys → Publishable key |
| `STRIPE_WEBHOOK_SECRET` | Webhooks → endpoint → Signing secret |
| `STRIPE_MONTHLY_PRICE_ID` | Products → fiyat ID'si |
