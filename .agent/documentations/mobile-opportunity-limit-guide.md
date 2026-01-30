# Mobile Implementation Guide: Opportunity Creation Limit & Subscription Paywall

## Genel Bakis

Business kullanicilar aktif bir Stripe aboneligi olmadan en fazla **3 adet** collaboration opportunity olusturabilir. 3. opportunity'den sonra yeni opportunity olusturmaya calistiklarinda API `403` hatasi doner ve mobile uygulama subscription paywall modal'ini gostermeli.

Community kullanicilar icin bu limit gecerli degildir, sinirsiz opportunity olusturabilirler.

---

## Akis Diagrami

```
Business kullanici "Opportunity Olustur" butonuna tiklar
    │
    ▼
POST /api/v1/opportunities
    │
    ├── Opportunity sayisi < 3  ──► 201 Created (basarili)
    │
    ├── Opportunity sayisi >= 3 VE abonelik YOK ──► 403 + requires_subscription: true
    │                                                    │
    │                                                    ▼
    │                                            Subscription Paywall Modal
    │                                                    │
    │                                        ┌───────────┴───────────┐
    │                                        │                       │
    │                                   "Abone Ol"              "Daha Sonra"
    │                                        │                       │
    │                                        ▼                       ▼
    │                              POST /subscription/checkout   Modal kapanir
    │                                        │
    │                                        ▼
    │                              Stripe Checkout acilir
    │
    └── Opportunity sayisi >= 3 VE abonelik AKTIF ──► 201 Created (basarili)
```

---

## API Degisiklikleri

### POST /api/v1/opportunities - Limit Hatasi

Aboneligi olmayan business kullanici 3 opportunity limitine ulastiginda:

**Response (403 Forbidden):**

```json
{
  "success": false,
  "message": "You have reached the free opportunity limit. Please subscribe to create more opportunities.",
  "requires_subscription": true
}
```

### Onemli Alanlar

| Alan | Tip | Aciklama |
|------|-----|----------|
| `requires_subscription` | boolean | `true` ise subscription paywall modal'i gosterilmeli |
| `message` | string | Kullaniciya gosterilecek mesaj |

### Limit Kurallari

| Kural | Deger |
|-------|-------|
| Ucretsiz limit | 3 opportunity |
| Sayilan statusler | Tumu (draft, published, closed, completed) |
| Aktif abonelik | Limitsiz |
| Iptal edilmis abonelik | Limit gecerli |
| Past due abonelik | Limit gecerli |
| Community kullanicilar | Limit yok |

---

## Mevcut Opportunity Sayisini Kontrol Etme

Kullanicinin kac opportunity olusturdugunu ve limit durumunu bilmek icin mevcut `GET /api/v1/me/opportunities` endpoint'ini kullanabilirsiniz:

```
GET /api/v1/me/opportunities
```

Response'taki `meta.total` alani toplam opportunity sayisini verir. Bunu subscription durumu ile birlestirerek UI'da proaktif olarak limit gosterilebilir.

```json
{
  "success": true,
  "data": { "data": [...] },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 2
  }
}
```

Subscription durumunu kontrol etmek icin:

```
GET /api/v1/me/subscription
```

---

## Mobile Implementation Ornekleri

### TypeScript / React Native

```typescript
// Constants
const FREE_OPPORTUNITY_LIMIT = 3;

// Types
interface CreateOpportunityError {
  success: false;
  message: string;
  requires_subscription?: boolean;
}

interface OpportunityLimitState {
  currentCount: number;
  hasSubscription: boolean;
  remainingFree: number;
  isLimited: boolean;
}

// Limit durumunu kontrol et
const checkOpportunityLimit = async (): Promise<OpportunityLimitState> => {
  const [opportunitiesRes, subscriptionRes] = await Promise.all([
    fetch(`${API_BASE_URL}/api/v1/me/opportunities`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }),
    fetch(`${API_BASE_URL}/api/v1/me/subscription`, {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }),
  ]);

  const opportunities = await opportunitiesRes.json();
  const subscription = await subscriptionRes.json();

  const currentCount = opportunities.meta.total;
  const hasSubscription = subscription.data?.status === 'active';
  const remainingFree = Math.max(0, FREE_OPPORTUNITY_LIMIT - currentCount);

  return {
    currentCount,
    hasSubscription,
    remainingFree,
    isLimited: !hasSubscription && currentCount >= FREE_OPPORTUNITY_LIMIT,
  };
};

// Opportunity olustur - requires_subscription hatasini handle et
const createOpportunity = async (data: CreateOpportunityRequest): Promise<Opportunity> => {
  const response = await fetch(`${API_BASE_URL}/api/v1/opportunities`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(data),
  });

  const result = await response.json();

  if (!result.success) {
    // Subscription paywall tetikle
    if (result.requires_subscription) {
      throw new SubscriptionRequiredError(result.message);
    }
    throw new Error(result.message || 'Failed to create opportunity');
  }

  return result.data;
};

// Custom error class
class SubscriptionRequiredError extends Error {
  public readonly requiresSubscription = true;

  constructor(message: string) {
    super(message);
    this.name = 'SubscriptionRequiredError';
  }
}

// React Component: Opportunity olusturma butonu
import React, { useState, useEffect } from 'react';
import { View, Text, TouchableOpacity, Modal } from 'react-native';

const CreateOpportunityButton: React.FC = () => {
  const [limitState, setLimitState] = useState<OpportunityLimitState | null>(null);
  const [showPaywall, setShowPaywall] = useState(false);

  useEffect(() => {
    checkOpportunityLimit().then(setLimitState);
  }, []);

  const handleCreate = async (data: CreateOpportunityRequest) => {
    try {
      const opportunity = await createOpportunity(data);
      // Basarili: opportunity detay sayfasina git
      navigation.navigate('OpportunityDetail', { id: opportunity.id });
    } catch (error) {
      if (error instanceof SubscriptionRequiredError) {
        setShowPaywall(true);
        return;
      }
      // Diger hatalari handle et
      showErrorToast(error.message);
    }
  };

  const handleSubscribe = async () => {
    const result = await createCheckoutSession(
      'kolabing://subscription/success',
      'kolabing://subscription/cancel'
    );
    if (result.success) {
      await InAppBrowser.open(result.data.checkout_url);
    }
    setShowPaywall(false);
  };

  return (
    <>
      {/* Limit uyarisi - buton uzerinde kalan hak gosterimi */}
      {limitState && !limitState.hasSubscription && (
        <Text>
          {limitState.remainingFree > 0
            ? `${limitState.remainingFree} ucretsiz hak kaldi`
            : 'Ucretsiz limit doldu'}
        </Text>
      )}

      {/* Subscription Paywall Modal */}
      <Modal visible={showPaywall} animationType="slide" transparent>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>
              Abonelik Gerekli
            </Text>
            <Text style={styles.modalMessage}>
              Ucretsiz 3 opportunity limitinize ulastiniz.
              Sinirsiz opportunity olusturmak icin abone olun.
            </Text>
            <Text style={styles.price}>75 EUR / ay</Text>

            <TouchableOpacity
              style={styles.subscribeButton}
              onPress={handleSubscribe}
            >
              <Text style={styles.subscribeButtonText}>Abone Ol</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={styles.laterButton}
              onPress={() => setShowPaywall(false)}
            >
              <Text style={styles.laterButtonText}>Daha Sonra</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </>
  );
};
```

### Swift / iOS

```swift
// Constants
let freeOpportunityLimit = 3

// Models
struct OpportunityLimitState {
    let currentCount: Int
    let hasSubscription: Bool
    var remainingFree: Int { max(0, freeOpportunityLimit - currentCount) }
    var isLimited: Bool { !hasSubscription && currentCount >= freeOpportunityLimit }
}

struct CreateOpportunityError: Decodable {
    let success: Bool
    let message: String
    let requiresSubscription: Bool?

    enum CodingKeys: String, CodingKey {
        case success, message
        case requiresSubscription = "requires_subscription"
    }
}

// Custom Error
enum OpportunityError: Error {
    case subscriptionRequired(String)
    case validationFailed(String)
    case unknown(String)
}

// Service
class OpportunityAPIService {
    private let baseURL: String
    private let token: String

    init(baseURL: String, token: String) {
        self.baseURL = baseURL
        self.token = token
    }

    /// Limit durumunu kontrol et
    func checkOpportunityLimit() async throws -> OpportunityLimitState {
        async let opportunitiesTask = fetchMyOpportunities()
        async let subscriptionTask = fetchSubscription()

        let (opportunities, subscription) = try await (opportunitiesTask, subscriptionTask)

        return OpportunityLimitState(
            currentCount: opportunities.meta.total,
            hasSubscription: subscription.data?.status == "active"
        )
    }

    /// Opportunity olustur - requires_subscription hatasini handle et
    func createOpportunity(_ data: CreateOpportunityRequest) async throws -> Opportunity {
        let url = URL(string: "\(baseURL)/api/v1/opportunities")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONEncoder().encode(data)

        let (responseData, httpResponse) = try await URLSession.shared.data(for: request)

        guard let statusCode = (httpResponse as? HTTPURLResponse)?.statusCode else {
            throw OpportunityError.unknown("Invalid response")
        }

        if statusCode == 201 {
            let result = try JSONDecoder().decode(APIResponse<Opportunity>.self, from: responseData)
            guard let opportunity = result.data else {
                throw OpportunityError.unknown("Missing data")
            }
            return opportunity
        }

        // Error handling
        let error = try JSONDecoder().decode(CreateOpportunityError.self, from: responseData)

        if error.requiresSubscription == true {
            throw OpportunityError.subscriptionRequired(error.message)
        }

        throw OpportunityError.validationFailed(error.message)
    }

    private func fetchMyOpportunities() async throws -> PaginatedResponse<Opportunity> {
        let url = URL(string: "\(baseURL)/api/v1/me/opportunities")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(PaginatedResponse<Opportunity>.self, from: data)
    }

    private func fetchSubscription() async throws -> APIResponse<Subscription?> {
        let url = URL(string: "\(baseURL)/api/v1/me/subscription")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(APIResponse<Subscription?>.self, from: data)
    }
}

// SwiftUI - Subscription Paywall View
import SwiftUI
import SafariServices

struct SubscriptionPaywallView: View {
    @Binding var isPresented: Bool
    let onSubscribe: () async -> Void

    var body: some View {
        VStack(spacing: 24) {
            Image(systemName: "star.circle.fill")
                .font(.system(size: 64))
                .foregroundColor(.orange)

            Text("Abonelik Gerekli")
                .font(.title2)
                .fontWeight(.bold)

            Text("Ücretsiz 3 opportunity limitinize ulaştınız.\nSınırsız opportunity oluşturmak için abone olun.")
                .multilineTextAlignment(.center)
                .foregroundColor(.secondary)

            Text("75 EUR / ay")
                .font(.title)
                .fontWeight(.bold)
                .foregroundColor(.blue)

            Button("Abone Ol") {
                Task { await onSubscribe() }
            }
            .buttonStyle(.borderedProminent)
            .controlSize(.large)

            Button("Daha Sonra") {
                isPresented = false
            }
            .foregroundColor(.secondary)
        }
        .padding(32)
    }
}

// Usage in ViewModel
@MainActor
class CreateOpportunityViewModel: ObservableObject {
    @Published var showPaywall = false
    @Published var limitState: OpportunityLimitState?

    private let opportunityService: OpportunityAPIService
    private let subscriptionService: SubscriptionAPIService

    func checkLimit() async {
        do {
            limitState = try await opportunityService.checkOpportunityLimit()
        } catch {
            // Handle error
        }
    }

    func createOpportunity(_ data: CreateOpportunityRequest) async {
        do {
            let opportunity = try await opportunityService.createOpportunity(data)
            // Basarili: navigate to detail
        } catch OpportunityError.subscriptionRequired {
            showPaywall = true
        } catch {
            // Handle other errors
        }
    }

    func handleSubscribe() async {
        do {
            let session = try await subscriptionService.createCheckoutSession(
                successUrl: "https://app.kolabing.com/subscription/success",
                cancelUrl: "https://app.kolabing.com/subscription/cancel"
            )
            if let url = URL(string: session.checkoutUrl) {
                // Open SFSafariViewController
            }
        } catch {
            // Handle error
        }
        showPaywall = false
    }
}
```

---

## UI/UX Onerileri

### 1. Proaktif Limit Gosterimi

Kullanici opportunity olusturma sayfasina girmeden once kalan hak sayisini gosterin:

```
┌─────────────────────────────────────────┐
│  📝 Yeni Opportunity Olustur            │
│                                         │
│  ⚠️ 1 ucretsiz hak kaldiniz (3 icerisinden 2 kullanildi)  │
│                                         │
│  [Form alanlari...]                     │
│                                         │
│  [ Olustur ]                            │
└─────────────────────────────────────────┘
```

### 2. Subscription Paywall Modal

Limit asildiktan sonra gosterilecek modal:

```
┌─────────────────────────────────────────┐
│                                         │
│              ⭐ PRO                      │
│                                         │
│      Abonelik Gerekli                   │
│                                         │
│  Ucretsiz 3 opportunity limitinize      │
│  ulastiniz. Sinirsiz olusturmak         │
│  icin abone olun.                       │
│                                         │
│         75 EUR / ay                     │
│                                         │
│  ┌─────────────────────────────────┐    │
│  │         Abone Ol                │    │
│  └─────────────────────────────────┘    │
│                                         │
│          Daha Sonra                     │
│                                         │
└─────────────────────────────────────────┘
```

### 3. Olusturma Butonunda Durum Gosterimi

```
Abonelik yok, limit yok:
  [ + Yeni Opportunity ] (2/3 ucretsiz)

Abonelik yok, limite ulasti:
  [ + Yeni Opportunity ] (🔒 Abonelik gerekli)

Abonelik var:
  [ + Yeni Opportunity ] (✅ PRO)
```

### 4. "My Opportunities" Ekraninda Banner

Limit yaklasirken veya doluyken banner gosterin:

```
Yaklasirken (2/3):
┌──────────────────────────────────────────────┐
│ ℹ️ Son 1 ucretsiz opportunity hakkiniz kaldi. │
│    Sinirsiz erisim icin PRO'ya gecin →        │
└──────────────────────────────────────────────┘

Doluyken (3/3):
┌──────────────────────────────────────────────┐
│ ⚠️ Ucretsiz limitinize ulastiniz.             │
│    Yeni opportunity icin abone olun →          │
└──────────────────────────────────────────────┘
```

---

## Error Handling

| HTTP Code | `requires_subscription` | Anlam | Aksiyon |
|-----------|------------------------|-------|---------|
| 201 | - | Basarili | Detay sayfasina git |
| 403 | `true` | Limit asildi | Subscription paywall modal goster |
| 403 | `false` / yok | Yetki yok | "Yetkisiz islem" mesaji goster |
| 401 | - | Oturum suresi dolmus | Login'e yonlendir |
| 422 | - | Validation hatasi | Form hatalarini goster |

### `requires_subscription` Flag Kontrolu

API response'unda `requires_subscription: true` alani **sadece** limit asildiginda doner. Bu alan:
- Diger 403 hatalarindan (ornegin baskasinin opportunity'sini duzenleme girisimi) ayirt etmek icin kullanilir
- Mobile uygulamanin dogru modal'i (paywall vs genel hata) gostermesini saglar

```typescript
// Dogru kontrol
if (response.status === 403 && result.requires_subscription === true) {
  showSubscriptionPaywall();
} else if (response.status === 403) {
  showGenericForbiddenError();
}
```

---

## Test Senaryolari

| # | Senaryo | Beklenen Sonuc |
|---|---------|----------------|
| 1 | Yeni business kullanici 1. opportunity olusturur | 201 - Basarili |
| 2 | Business kullanici 2. opportunity olusturur | 201 - Basarili |
| 3 | Business kullanici 3. opportunity olusturur | 201 - Basarili |
| 4 | Business kullanici 4. opportunity olusturur (abonelik yok) | 403 + `requires_subscription: true` |
| 5 | Business kullanici abone olur, sonra 4. opportunity olusturur | 201 - Basarili |
| 6 | Aboneligi iptal edilmis kullanici (3 mevcut) yeni olusturur | 403 + `requires_subscription: true` |
| 7 | Past due aboneligi olan kullanici (3 mevcut) yeni olusturur | 403 + `requires_subscription: true` |
| 8 | Community kullanici 10. opportunity olusturur | 201 - Basarili (limit yok) |
| 9 | Business kullanicinin draft+published+closed = 3 | 403 (tum statusler sayilir) |

---

## Ilgili Endpointler

| Endpoint | Method | Kullanim |
|----------|--------|----------|
| `POST /api/v1/opportunities` | POST | Opportunity olustur |
| `GET /api/v1/me/opportunities` | GET | Mevcut opportunity sayisini ogren (`meta.total`) |
| `GET /api/v1/me/subscription` | GET | Abonelik durumunu kontrol et |
| `POST /api/v1/me/subscription/checkout` | POST | Stripe Checkout baslatma |
| `GET /api/v1/me/subscription/portal` | GET | Stripe Portal (odeme yonetimi) |

Detayli subscription API dokumantasyonu icin: `mobile-subscription-api.md`

---

## Changelog

- **2026-01-30**: Initial documentation
  - Opportunity creation limit (3 free for unsubscribed business users)
  - `requires_subscription` flag in 403 response
  - Subscription paywall modal flow
  - React Native ve Swift/iOS implementation ornekleri
