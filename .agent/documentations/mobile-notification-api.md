# Bildirim Sistemi - Mobil Uygulama API Dokumantasyonu

> **Son Guncelleme:** Ocak 2026
> **API Versiyonu:** v1
> **Kimlik Dogrulama:** Bearer Token (Laravel Sanctum)

---

## Icindekiler

1. [Genel Bakis](#1-genel-bakis)
2. [Notification Tipleri](#2-notification-tipleri)
3. [API Endpoints](#3-api-endpoints)
4. [Response Yapisi](#4-response-yapisi)
5. [Firebase Cloud Messaging Entegrasyonu](#5-firebase-cloud-messaging-entegrasyonu)
6. [Flutter/Dart Implementasyon Ornegi](#6-flutterdart-implementasyon-ornegi)
7. [Swift Implementasyon Ornegi](#7-swift-implementasyon-ornegi)
8. [Entegrasyon Notlari](#8-entegrasyon-notlari)

---

## 1. Genel Bakis

Kolabing bildirim sistemi, kullanicilarin uygulama icindeki onemli olaylari gercek zamanli olarak takip edebilmesini saglar. Sistem iki ana bilesendan olusur:

### Veritabani Tabanli Bildirimler (REST API)

Backend, tum bildirimleri PostgreSQL veritabaninda saklar ve REST API endpoint'leri uzerinden sunar. Bu yaklasim sayesinde:

- Bildirim gecmisi kalici olarak tutulur
- Sayfalama (pagination) ile verimli listeleme yapilir
- Okundu/okunmadi durumu takip edilir
- Bildirim listesi her zaman API uzerinden erisilebilir olur

### Gercek Zamanli Push Bildirimler (Firebase Cloud Messaging)

Firebase Cloud Messaging (FCM), kullanicilara anlik push bildirimi gondermek icin kullanilacaktir. FCM entegrasyonu su adimlari icerir:

1. **Login sonrasi FCM token gonderimi:** Kullanici basarili giris yaptiktan sonra, mobil uygulama FCM token'ini backend'e gondermelidir. Bu islem icin backend endpoint'i daha sonra eklenecektir.
2. **Push bildirim alimi:** FCM uzerinden gelen her push bildiriminde, uygulama API'den bildirim listesini yenileyerek guncel veriyi gostermelidir.
3. **Cift katmanli yaklasim:** FCM gercek zamanli uyari icin, REST API ise bildirim listesi ve gecmis icin kullanilir.

### Mimari Akis

```
[Olay Tetiklenir] --> [NotificationService] --> [Veritabanina Kaydet]
                                             --> [FCM Push Gonder (gelecekte)]
                                                       |
                                                       v
                                              [Mobil Uygulama]
                                                       |
                                            +----------+----------+
                                            |                     |
                                    [Push Bildirim]      [REST API Sorgusu]
                                    (gercek zamanli)     (liste & gecmis)
```

---

## 2. Notification Tipleri

Sistemde 4 farkli bildirim tipi bulunmaktadir:

| Tip | Deger (`type`) | Tetikleyen Olay | Alici | Ornek Senaryo |
|-----|---------------|-----------------|-------|---------------|
| **Yeni Mesaj** | `new_message` | Basvuru sohbetinde yeni mesaj gonderildiginde | Mesajin gonderildigi karsi taraf (basvuru sahibi veya firsat olusturucu) | CafeX Istanbul, "Yaz Networking Etkinligi" basvurusu hakkinda mesaj gonderdi |
| **Basvuru Alindi** | `application_received` | Bir firsat icin yeni basvuru yapildiginda | Firsati olusturan is sahibi (business user) | Kadikoy Toplulugu, "Yaz Networking Etkinligi" firsatiniza basvurdu |
| **Basvuru Kabul Edildi** | `application_accepted` | Basvuru kabul edildiginde | Basvuruyu yapan kullanici (community user) | "Yaz Networking Etkinligi" basvurunuz kabul edildi! |
| **Basvuru Reddedildi** | `application_declined` | Basvuru reddedildiginde | Basvuruyu yapan kullanici (community user) | "Yaz Networking Etkinligi" basvurunuz reddedildi |

### Tip Akis Semalari

```
IS SAHIBI (Business) tarafindan alinan bildirimler:
  - application_received  --> Birisinin firsata basvurdugu bilgisi
  - new_message           --> Basvuru sohbetinden gelen mesaj

TOPLULUK (Community) tarafindan alinan bildirimler:
  - application_accepted  --> Basvurunun kabul edildigi bilgisi
  - application_declined  --> Basvurunun reddedildigi bilgisi
  - new_message           --> Basvuru sohbetinden gelen mesaj
```

---

## 3. API Endpoints

Tum endpoint'ler kimlik dogrulama gerektirir. Her istekte `Authorization: Bearer {token}` header'i gonderilmelidir.

**Base URL:** `{API_URL}/api/v1`

---

### 3.1 Bildirimleri Listele

Oturum acmis kullanicinin bildirimlerini sayfalanmis olarak getirir. En yeni bildirimler basta olmak uzere siralanir.

| Ozellik | Deger |
|---------|-------|
| **Method** | `GET` |
| **URL** | `/me/notifications` |
| **Kimlik Dogrulama** | Gerekli |

#### Query Parametreleri

| Parametre | Tip | Zorunlu | Varsayilan | Aciklama |
|-----------|-----|---------|------------|----------|
| `page` | integer | Hayir | `1` | Sayfa numarasi |
| `per_page` | integer | Hayir | `20` | Sayfa basina bildirim sayisi (maks: 100) |

#### Basarili Response (200 OK)

```json
{
  "success": true,
  "data": [
    {
      "id": "9e2f4a8b-1c3d-4e5f-a6b7-8c9d0e1f2a3b",
      "type": "application_received",
      "title": "New Application",
      "body": "Kadikoy Toplulugu applied to your \"Yaz Networking Etkinligi\" opportunity.",
      "is_read": false,
      "read_at": null,
      "created_at": "2026-01-30T14:30:00+00:00",
      "actor_name": "Kadikoy Toplulugu",
      "actor_avatar_url": "https://storage.kolabing.com/avatars/kadikoy-toplulugu.jpg",
      "target_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
      "target_type": "application"
    },
    {
      "id": "8d1e3b7a-2c4d-5e6f-b7a8-9c0d1e2f3a4b",
      "type": "new_message",
      "title": "New Message",
      "body": "Merhaba, etkinlik icin mekan detaylarini paylasabilir misiniz?",
      "is_read": true,
      "read_at": "2026-01-30T13:45:00+00:00",
      "created_at": "2026-01-30T13:30:00+00:00",
      "actor_name": "CafeX Istanbul",
      "actor_avatar_url": "https://storage.kolabing.com/avatars/cafx-istanbul.jpg",
      "target_id": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
      "target_type": "application"
    },
    {
      "id": "7c0d2a69-3b5e-6f7a-c8b9-0d1e2f3a4b5c",
      "type": "application_accepted",
      "title": "Application Accepted",
      "body": "Your application for \"Besiktas Kahve Festivali\" has been accepted!",
      "is_read": true,
      "read_at": "2026-01-29T16:00:00+00:00",
      "created_at": "2026-01-29T15:30:00+00:00",
      "actor_name": "CafeX Istanbul",
      "actor_avatar_url": "https://storage.kolabing.com/avatars/cafx-istanbul.jpg",
      "target_id": "c3d4e5f6-a7b8-9012-cdef-123456789012",
      "target_type": "application"
    },
    {
      "id": "6b9c1958-4a6f-7a8b-d9c0-1e2f3a4b5c6d",
      "type": "application_declined",
      "title": "Application Declined",
      "body": "Your application for \"Atasehir Spor Gunleri\" was declined.",
      "is_read": false,
      "read_at": null,
      "created_at": "2026-01-28T10:00:00+00:00",
      "actor_name": "FitZone Gym",
      "actor_avatar_url": "https://storage.kolabing.com/avatars/fitzone-gym.jpg",
      "target_id": "d4e5f6a7-b8c9-0123-defa-234567890123",
      "target_type": "application"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 47
  }
}
```

#### Kimlik Dogrulama Hatasi (401 Unauthorized)

```json
{
  "message": "Unauthenticated."
}
```

---

### 3.2 Okunmamis Bildirim Sayisi

Oturum acmis kullanicinin okunmamis bildirim sayisini dondurur. Badge (rozet) sayisi icin bu endpoint kullanilir.

| Ozellik | Deger |
|---------|-------|
| **Method** | `GET` |
| **URL** | `/me/notifications/unread-count` |
| **Kimlik Dogrulama** | Gerekli |

#### Query Parametreleri

Yok.

#### Basarili Response (200 OK)

```json
{
  "success": true,
  "data": {
    "count": 5
  }
}
```

---

### 3.3 Tek Bildirimi Okundu Olarak Isaretle

Belirli bir bildirimi okundu olarak isaretler. Kullanici bir bildirime tikladiginda bu endpoint cagirilmalidir.

| Ozellik | Deger |
|---------|-------|
| **Method** | `POST` |
| **URL** | `/me/notifications/{notification}/read` |
| **Kimlik Dogrulama** | Gerekli |

#### Path Parametreleri

| Parametre | Tip | Zorunlu | Aciklama |
|-----------|-----|---------|----------|
| `notification` | UUID | Evet | Bildirim ID'si |

#### Request Body

Yok. Body gonderilmesine gerek yoktur.

#### Basarili Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": "9e2f4a8b-1c3d-4e5f-a6b7-8c9d0e1f2a3b",
    "is_read": true,
    "read_at": "2026-01-30T15:00:00+00:00"
  }
}
```

#### Yetkilendirme Hatasi (403 Forbidden)

Kullanici baskasina ait bir bildirimi okundu olarak isaretlemeye calistiginda:

```json
{
  "success": false,
  "message": "You are not authorized to access this notification."
}
```

#### Bulunamadi Hatasi (404 Not Found)

Gecersiz bildirim ID'si gonderildiginde:

```json
{
  "message": "No query results for model [App\\Models\\Notification]."
}
```

---

### 3.4 Tum Bildirimleri Okundu Olarak Isaretle

Oturum acmis kullanicinin tum okunmamis bildirimlerini topluca okundu olarak isaretler.

| Ozellik | Deger |
|---------|-------|
| **Method** | `POST` |
| **URL** | `/me/notifications/read-all` |
| **Kimlik Dogrulama** | Gerekli |

#### Request Body

Yok. Body gonderilmesine gerek yoktur.

#### Basarili Response (200 OK)

```json
{
  "success": true,
  "data": {
    "updated_count": 5
  }
}
```

`updated_count`, okundu olarak isaretlenen bildirim sayisini gosterir. Eger tum bildirimler zaten okunmussa `0` doner.

---

## 4. Response Yapisi

### Bildirim Nesnesi Alanlari

| Alan | Tip | Nullable | Aciklama |
|------|-----|----------|----------|
| `id` | `string` (UUID) | Hayir | Bildirimin benzersiz kimlik numarasi |
| `type` | `string` (enum) | Hayir | Bildirim tipi. Olasi degerler: `new_message`, `application_received`, `application_accepted`, `application_declined` |
| `title` | `string` | Hayir | Bildirim basligi. Ornek: "New Application", "New Message", "Application Accepted", "Application Declined" |
| `body` | `string` | Hayir | Bildirim icerik metni. Olayin detaylarini aciklar. Mesaj bildirimleri icin mesajin ilk 100 karakteri gosterilir |
| `is_read` | `boolean` | Hayir | Bildirimin okunup okunmadigi. `true` = okundu, `false` = okunmadi |
| `read_at` | `string` (ISO 8601) | Evet | Bildirimin okundugu tarih ve saat. Okunmamissa `null` doner. Format: `2026-01-30T15:00:00+00:00` |
| `created_at` | `string` (ISO 8601) | Evet | Bildirimin olusturuldugu tarih ve saat. Format: `2026-01-30T14:30:00+00:00` |
| `actor_name` | `string` | Evet | Bildirimi tetikleyen kullanicinin adi. Business profili icin isletme adi, community profili icin topluluk adi. Ornek: "CafeX Istanbul", "Kadikoy Toplulugu" |
| `actor_avatar_url` | `string` (URL) | Evet | Bildirimi tetikleyen kullanicinin profil fotografinin URL'si. Profil fotografsi yoksa `null` doner |
| `target_id` | `string` (UUID) | Evet | Bildirimin iliskili oldugu kayngin ID'si. Su an icin her zaman bir application ID'sidir |
| `target_type` | `string` | Evet | Bildirimin iliskili oldugu kaynak tipi. Su an icin her zaman `"application"` degeridir |

### Meta Nesnesi Alanlari (Sayfalama)

| Alan | Tip | Aciklama |
|------|-----|----------|
| `current_page` | `integer` | Suanki sayfa numarasi |
| `last_page` | `integer` | Son sayfa numarasi |
| `per_page` | `integer` | Sayfa basina gosterilen kayit sayisi |
| `total` | `integer` | Toplam bildirim sayisi |

---

## 5. Firebase Cloud Messaging Entegrasyonu

FCM, kullanicilara gercek zamanli push bildirimi gondermek icin kullanilacaktir. Asagida mobil uygulama tarafinda yapilmasi gerekenler detayli olarak aciklanmistir.

### 5.1 FCM Token Yonetimi

Kullanici basarili bir sekilde giris yaptiktan sonra, mobil uygulama FCM token'ini alip backend'e gondermelidir.

```
[Kullanici Giris Yapar] --> [FCM Token Al] --> [Backend'e Token Gonder]
                                                     |
                                              POST /api/v1/me/fcm-token
                                              (Bu endpoint ileride eklenecektir)
```

**Onemli noktalar:**
- Her login isleminden sonra token gonderilmelidir (token degisebilir)
- Token yenilendigi durumlarda da backend'e guncel token gonderilmelidir
- Logout sirasinda token backend'den silinmelidir

### 5.2 Push Bildirim Payload Yapisi

FCM uzerinden gonderilecek push bildirim payload'u asagidaki formatta olacaktir:

```json
{
  "notification": {
    "title": "Yeni Basvuru",
    "body": "Kadikoy Toplulugu \"Yaz Networking Etkinligi\" firsatiniza basvurdu."
  },
  "data": {
    "type": "application_received",
    "target_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "target_type": "application",
    "notification_id": "9e2f4a8b-1c3d-4e5f-a6b7-8c9d0e1f2a3b"
  }
}
```

#### `notification` alani

Isletim sistemi tarafindan otomatik olarak gosterilen bildirim baslik ve icerigini tasir:

| Alan | Aciklama |
|------|----------|
| `title` | Push bildirim basligi |
| `body` | Push bildirim icerik metni |

#### `data` alani

Uygulama icinde islenmek uzere ek veri tasir. Bu veriler kullaniciya dogrudan gosterilmez:

| Alan | Tip | Aciklama |
|------|-----|----------|
| `type` | `string` | Bildirim tipi (`new_message`, `application_received`, `application_accepted`, `application_declined`) |
| `target_id` | `string` (UUID) | Ilgili kaynagin ID'si (su an icin application ID) |
| `target_type` | `string` | Ilgili kaynak tipi (`application`) |
| `notification_id` | `string` (UUID) | Veritabanindaki bildirim kaydinin ID'si. Okundu isaretleme icin kullanilir |

### 5.3 Her Bildirim Tipi Icin Ornek Payload'lar

#### Yeni Mesaj (`new_message`)

```json
{
  "notification": {
    "title": "Yeni Mesaj",
    "body": "Merhaba, etkinlik icin mekan detaylarini paylasabilir misiniz?"
  },
  "data": {
    "type": "new_message",
    "target_id": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
    "target_type": "application",
    "notification_id": "8d1e3b7a-2c4d-5e6f-b7a8-9c0d1e2f3a4b"
  }
}
```

#### Basvuru Alindi (`application_received`)

```json
{
  "notification": {
    "title": "Yeni Basvuru",
    "body": "Kadikoy Toplulugu \"Yaz Networking Etkinligi\" firsatiniza basvurdu."
  },
  "data": {
    "type": "application_received",
    "target_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "target_type": "application",
    "notification_id": "9e2f4a8b-1c3d-4e5f-a6b7-8c9d0e1f2a3b"
  }
}
```

#### Basvuru Kabul Edildi (`application_accepted`)

```json
{
  "notification": {
    "title": "Basvuru Kabul Edildi",
    "body": "\"Besiktas Kahve Festivali\" basvurunuz kabul edildi!"
  },
  "data": {
    "type": "application_accepted",
    "target_id": "c3d4e5f6-a7b8-9012-cdef-123456789012",
    "target_type": "application",
    "notification_id": "7c0d2a69-3b5e-6f7a-c8b9-0d1e2f3a4b5c"
  }
}
```

#### Basvuru Reddedildi (`application_declined`)

```json
{
  "notification": {
    "title": "Basvuru Reddedildi",
    "body": "\"Atasehir Spor Gunleri\" basvurunuz reddedildi."
  },
  "data": {
    "type": "application_declined",
    "target_id": "d4e5f6a7-b8c9-0123-defa-234567890123",
    "target_type": "application",
    "notification_id": "6b9c1958-4a6f-7a8b-d9c0-1e2f3a4b5c6d"
  }
}
```

### 5.4 Foreground ve Background Islemleri

#### Foreground (Uygulama On Planda)

Uygulama acikken gelen push bildirimleri:
- Isletim sistemi otomatik bildirim gostermez (varsayilan davranis)
- Uygulama icinde ozel bir in-app bildirim banner'i gosterilmelidir
- Bildirim listesi otomatik olarak API'den yenilenmelidir
- Badge sayisi guncellenmelidir

#### Background (Uygulama Arka Planda)

Uygulama kapali veya arka plandayken gelen push bildirimleri:
- Isletim sistemi `notification` alanindaki bilgileri kullanarak sistem bildirimini gosterir
- Kullanici bildirime tikladiginda uygulama acilir
- `data` alanindaki bilgiler uygulamaya iletilir

### 5.5 Bildirime Tiklandiginda Yonlendirme

Kullanici push bildirimine tikladiginda, `data` alanindaki `target_type` ve `target_id` degerlerine gore ilgili ekrana yonlendirilmelidir:

| `target_type` | `type` | Yonlendirilecek Ekran |
|---------------|--------|-----------------------|
| `application` | `new_message` | Basvuru sohbet ekrani (Chat Screen) |
| `application` | `application_received` | Basvuru detay ekrani (Application Detail) |
| `application` | `application_accepted` | Basvuru detay ekrani (Application Detail) |
| `application` | `application_declined` | Basvuru detay ekrani (Application Detail) |

```
[Push Bildirime Tikla]
       |
       v
[data.target_type kontrol et]
       |
       +--> "application" + "new_message"
       |         --> Chat ekranini ac (application_id = data.target_id)
       |
       +--> "application" + diger tipler
                 --> Application detay ekranini ac (application_id = data.target_id)
```

---

## 6. Flutter/Dart Implementasyon Ornegi

### 6.1 NotificationModel

```dart
import 'package:freezed_annotation/freezed_annotation.dart';

part 'notification_model.freezed.dart';
part 'notification_model.g.dart';

@freezed
class NotificationModel with _$NotificationModel {
  const factory NotificationModel({
    required String id,
    required String type,
    required String title,
    required String body,
    @JsonKey(name: 'is_read') required bool isRead,
    @JsonKey(name: 'read_at') String? readAt,
    @JsonKey(name: 'created_at') String? createdAt,
    @JsonKey(name: 'actor_name') String? actorName,
    @JsonKey(name: 'actor_avatar_url') String? actorAvatarUrl,
    @JsonKey(name: 'target_id') String? targetId,
    @JsonKey(name: 'target_type') String? targetType,
  }) = _NotificationModel;

  factory NotificationModel.fromJson(Map<String, dynamic> json) =>
      _$NotificationModelFromJson(json);
}

@freezed
class NotificationListResponse with _$NotificationListResponse {
  const factory NotificationListResponse({
    required bool success,
    required List<NotificationModel> data,
    required PaginationMeta meta,
  }) = _NotificationListResponse;

  factory NotificationListResponse.fromJson(Map<String, dynamic> json) =>
      _$NotificationListResponseFromJson(json);
}

@freezed
class PaginationMeta with _$PaginationMeta {
  const factory PaginationMeta({
    @JsonKey(name: 'current_page') required int currentPage,
    @JsonKey(name: 'last_page') required int lastPage,
    @JsonKey(name: 'per_page') required int perPage,
    required int total,
  }) = _PaginationMeta;

  factory PaginationMeta.fromJson(Map<String, dynamic> json) =>
      _$PaginationMetaFromJson(json);
}

@freezed
class UnreadCountResponse with _$UnreadCountResponse {
  const factory UnreadCountResponse({
    required bool success,
    required UnreadCountData data,
  }) = _UnreadCountResponse;

  factory UnreadCountResponse.fromJson(Map<String, dynamic> json) =>
      _$UnreadCountResponseFromJson(json);
}

@freezed
class UnreadCountData with _$UnreadCountData {
  const factory UnreadCountData({
    required int count,
  }) = _UnreadCountData;

  factory UnreadCountData.fromJson(Map<String, dynamic> json) =>
      _$UnreadCountDataFromJson(json);
}
```

### 6.2 NotificationService (Dio HTTP Calls)

```dart
import 'package:dio/dio.dart';
import '../models/notification_model.dart';

class NotificationApiService {
  final Dio _dio;

  NotificationApiService(this._dio);

  /// Bildirimleri sayfalanmis olarak getirir.
  /// [page] - Sayfa numarasi (varsayilan: 1)
  /// [perPage] - Sayfa basina bildirim sayisi (varsayilan: 20, maks: 100)
  Future<NotificationListResponse> getNotifications({
    int page = 1,
    int perPage = 20,
  }) async {
    final response = await _dio.get(
      '/api/v1/me/notifications',
      queryParameters: {
        'page': page,
        'per_page': perPage,
      },
    );
    return NotificationListResponse.fromJson(response.data);
  }

  /// Okunmamis bildirim sayisini dondurur.
  Future<int> getUnreadCount() async {
    final response = await _dio.get(
      '/api/v1/me/notifications/unread-count',
    );
    final parsed = UnreadCountResponse.fromJson(response.data);
    return parsed.data.count;
  }

  /// Tek bir bildirimi okundu olarak isaretler.
  /// [notificationId] - Bildirim UUID'si
  Future<void> markAsRead(String notificationId) async {
    await _dio.post(
      '/api/v1/me/notifications/$notificationId/read',
    );
  }

  /// Tum bildirimleri okundu olarak isaretler.
  /// Guncellenen bildirim sayisini dondurur.
  Future<int> markAllAsRead() async {
    final response = await _dio.post(
      '/api/v1/me/notifications/read-all',
    );
    return response.data['data']['updated_count'] as int;
  }
}
```

### 6.3 Firebase Messaging Setup

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Arka plan mesaj handler'i - top-level fonksiyon olmalidir
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Arka planda gelen mesaji isle
  debugPrint('Arka plan mesaji alindi: ${message.messageId}');
}

class FirebaseMessagingService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  /// Firebase Messaging'i baslatir ve gerekli izinleri ister.
  Future<void> initialize() async {
    // Arka plan handler'ini kaydet
    FirebaseMessaging.onBackgroundMessage(
      _firebaseMessagingBackgroundHandler,
    );

    // Bildirim izinlerini iste
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      debugPrint('Bildirim izni verildi');
    } else if (settings.authorizationStatus ==
        AuthorizationStatus.provisional) {
      debugPrint('Gecici bildirim izni verildi');
    } else {
      debugPrint('Bildirim izni reddedildi');
      return;
    }

    // Yerel bildirim kanalini ayarla (Android)
    const androidChannel = AndroidNotificationChannel(
      'kolabing_notifications',
      'Kolabing Bildirimleri',
      description: 'Kolabing uygulama bildirimleri',
      importance: Importance.high,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(androidChannel);

    // Yerel bildirimleri baslat
    const initSettings = InitializationSettings(
      android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      iOS: DarwinInitializationSettings(),
    );

    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: _onNotificationTapped,
    );

    // On planda gelen mesajlari dinle
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // Bildirime tiklanarak uygulamanin acilmasini dinle
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);

    // Uygulama kapali iken bildirime tiklanarak acilmayi kontrol et
    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      _handleNotificationTap(initialMessage);
    }
  }

  /// FCM token'ini alir. Login sonrasi backend'e gonderilmelidir.
  Future<String?> getToken() async {
    final token = await _messaging.getToken();
    debugPrint('FCM Token: $token');
    return token;
  }

  /// Token yenilendiginde dinleyici ekler.
  void onTokenRefresh(Function(String) callback) {
    _messaging.onTokenRefresh.listen(callback);
  }

  /// On planda gelen mesaji isler.
  void _handleForegroundMessage(RemoteMessage message) {
    debugPrint('On plan mesaji: ${message.notification?.title}');

    final notification = message.notification;
    if (notification == null) return;

    // In-app bildirim goster (yerel bildirim olarak)
    _localNotifications.show(
      notification.hashCode,
      notification.title,
      notification.body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'kolabing_notifications',
          'Kolabing Bildirimleri',
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
      payload: message.data['notification_id'],
    );
  }

  /// Bildirime tiklandiginda uygulamayi yonlendirir.
  void _handleNotificationTap(RemoteMessage message) {
    final data = message.data;
    final type = data['type'];
    final targetId = data['target_id'];
    final targetType = data['target_type'];

    if (targetType == 'application') {
      if (type == 'new_message') {
        // Sohbet ekranina yonlendir
        _navigateToChat(targetId);
      } else {
        // Basvuru detay ekranina yonlendir
        _navigateToApplicationDetail(targetId);
      }
    }
  }

  /// Yerel bildirime tiklandiginda
  void _onNotificationTapped(NotificationResponse response) {
    final notificationId = response.payload;
    if (notificationId != null) {
      // Bildirim ekranina yonlendir
      _navigateToNotifications();
    }
  }

  void _navigateToChat(String applicationId) {
    // Router kullanarak sohbet ekranina git
    // Ornek: GoRouter.of(context).push('/chat/$applicationId');
  }

  void _navigateToApplicationDetail(String applicationId) {
    // Router kullanarak basvuru detayina git
    // Ornek: GoRouter.of(context).push('/applications/$applicationId');
  }

  void _navigateToNotifications() {
    // Router kullanarak bildirim listesine git
    // Ornek: GoRouter.of(context).push('/notifications');
  }
}
```

### 6.4 NotificationProvider (Riverpod)

```dart
import 'package:riverpod_annotation/riverpod_annotation.dart';
import '../models/notification_model.dart';
import '../services/notification_api_service.dart';

part 'notification_provider.g.dart';

/// Bildirim listesi state'i
class NotificationState {
  final List<NotificationModel> notifications;
  final PaginationMeta? meta;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;

  const NotificationState({
    this.notifications = const [],
    this.meta,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
  });

  NotificationState copyWith({
    List<NotificationModel>? notifications,
    PaginationMeta? meta,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
  }) {
    return NotificationState(
      notifications: notifications ?? this.notifications,
      meta: meta ?? this.meta,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: error,
    );
  }

  bool get hasMore =>
      meta != null && meta!.currentPage < meta!.lastPage;
}

@riverpod
class NotificationNotifier extends _$NotificationNotifier {
  late final NotificationApiService _service;

  @override
  NotificationState build() {
    _service = ref.read(notificationApiServiceProvider);
    return const NotificationState();
  }

  /// Ilk sayfa bildirimlerini yukler.
  Future<void> loadNotifications() async {
    state = state.copyWith(isLoading: true, error: null);

    try {
      final response = await _service.getNotifications(page: 1);
      state = state.copyWith(
        notifications: response.data,
        meta: response.meta,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: 'Bildirimler yuklenemedi: $e',
      );
    }
  }

  /// Sonraki sayfa bildirimlerini yukler (infinite scroll).
  Future<void> loadMore() async {
    if (!state.hasMore || state.isLoadingMore) return;

    final nextPage = (state.meta?.currentPage ?? 0) + 1;
    state = state.copyWith(isLoadingMore: true);

    try {
      final response = await _service.getNotifications(page: nextPage);
      state = state.copyWith(
        notifications: [...state.notifications, ...response.data],
        meta: response.meta,
        isLoadingMore: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoadingMore: false,
        error: 'Daha fazla bildirim yuklenemedi: $e',
      );
    }
  }

  /// Tek bildirimi okundu olarak isaretler ve listeyi gunceller.
  Future<void> markAsRead(String notificationId) async {
    try {
      await _service.markAsRead(notificationId);
      state = state.copyWith(
        notifications: state.notifications.map((n) {
          if (n.id == notificationId) {
            return n.copyWith(
              isRead: true,
              readAt: DateTime.now().toIso8601String(),
            );
          }
          return n;
        }).toList(),
      );
      // Badge sayisini da guncelle
      ref.invalidate(unreadCountProvider);
    } catch (e) {
      // Sessizce hata logla
      debugPrint('Okundu isaretleme hatasi: $e');
    }
  }

  /// Tum bildirimleri okundu olarak isaretler.
  Future<void> markAllAsRead() async {
    try {
      await _service.markAllAsRead();
      state = state.copyWith(
        notifications: state.notifications.map((n) {
          return n.copyWith(
            isRead: true,
            readAt: DateTime.now().toIso8601String(),
          );
        }).toList(),
      );
      ref.invalidate(unreadCountProvider);
    } catch (e) {
      debugPrint('Tumunu okundu isaretleme hatasi: $e');
    }
  }

  /// Push bildirim geldiginde listeyi yeniler.
  Future<void> refresh() async {
    await loadNotifications();
  }
}

/// Okunmamis bildirim sayisi provider'i.
/// Badge gosterimi icin kullanilir.
@riverpod
Future<int> unreadCount(UnreadCountRef ref) async {
  final service = ref.read(notificationApiServiceProvider);
  return service.getUnreadCount();
}

/// NotificationApiService provider'i.
@riverpod
NotificationApiService notificationApiService(
    NotificationApiServiceRef ref) {
  final dio = ref.read(dioProvider);
  return NotificationApiService(dio);
}
```

### 6.5 Widget Kullanim Ornegi

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/notification_provider.dart';
import '../models/notification_model.dart';

/// Bildirim listesi ekrani
class NotificationListScreen extends ConsumerStatefulWidget {
  const NotificationListScreen({super.key});

  @override
  ConsumerState<NotificationListScreen> createState() =>
      _NotificationListScreenState();
}

class _NotificationListScreenState
    extends ConsumerState<NotificationListScreen> {
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    // Bildirimleri yukle
    Future.microtask(() {
      ref.read(notificationNotifierProvider.notifier).loadNotifications();
    });

    // Infinite scroll icin dinleyici ekle
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(notificationNotifierProvider.notifier).loadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(notificationNotifierProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Bildirimler'),
        actions: [
          TextButton(
            onPressed: () {
              ref
                  .read(notificationNotifierProvider.notifier)
                  .markAllAsRead();
            },
            child: const Text('Tumunu Oku'),
          ),
        ],
      ),
      body: _buildBody(state),
    );
  }

  Widget _buildBody(NotificationState state) {
    if (state.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.error != null && state.notifications.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(state.error!),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () {
                ref
                    .read(notificationNotifierProvider.notifier)
                    .loadNotifications();
              },
              child: const Text('Tekrar Dene'),
            ),
          ],
        ),
      );
    }

    if (state.notifications.isEmpty) {
      return const Center(
        child: Text('Henuz bildiriminiz bulunmamaktadir.'),
      );
    }

    return RefreshIndicator(
      onRefresh: () {
        return ref
            .read(notificationNotifierProvider.notifier)
            .refresh();
      },
      child: ListView.builder(
        controller: _scrollController,
        itemCount: state.notifications.length +
            (state.isLoadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index == state.notifications.length) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(16),
                child: CircularProgressIndicator(),
              ),
            );
          }

          final notification = state.notifications[index];
          return _NotificationTile(
            notification: notification,
            onTap: () => _onNotificationTap(notification),
          );
        },
      ),
    );
  }

  void _onNotificationTap(NotificationModel notification) {
    // Okundu olarak isaretle
    if (!notification.isRead) {
      ref
          .read(notificationNotifierProvider.notifier)
          .markAsRead(notification.id);
    }

    // Ilgili ekrana yonlendir
    if (notification.targetType == 'application') {
      if (notification.type == 'new_message') {
        // Sohbet ekranina git
        context.push('/chat/${notification.targetId}');
      } else {
        // Basvuru detayina git
        context.push('/applications/${notification.targetId}');
      }
    }
  }
}

/// Tek bildirim satirini gosteren widget
class _NotificationTile extends StatelessWidget {
  final NotificationModel notification;
  final VoidCallback onTap;

  const _NotificationTile({
    required this.notification,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      tileColor: notification.isRead
          ? null
          : Theme.of(context).colorScheme.primaryContainer.withOpacity(0.1),
      leading: CircleAvatar(
        backgroundImage: notification.actorAvatarUrl != null
            ? NetworkImage(notification.actorAvatarUrl!)
            : null,
        child: notification.actorAvatarUrl == null
            ? Icon(_getIcon(notification.type))
            : null,
      ),
      title: Text(
        notification.actorName ?? notification.title,
        style: TextStyle(
          fontWeight:
              notification.isRead ? FontWeight.normal : FontWeight.bold,
        ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            notification.body,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 4),
          Text(
            _formatDate(notification.createdAt),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.grey,
                ),
          ),
        ],
      ),
      trailing: notification.isRead
          ? null
          : Container(
              width: 10,
              height: 10,
              decoration: const BoxDecoration(
                color: Colors.blue,
                shape: BoxShape.circle,
              ),
            ),
    );
  }

  IconData _getIcon(String type) {
    switch (type) {
      case 'new_message':
        return Icons.chat_bubble_outline;
      case 'application_received':
        return Icons.inbox;
      case 'application_accepted':
        return Icons.check_circle_outline;
      case 'application_declined':
        return Icons.cancel_outlined;
      default:
        return Icons.notifications_outlined;
    }
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null) return '';
    final date = DateTime.parse(dateStr);
    final now = DateTime.now();
    final diff = now.difference(date);

    if (diff.inMinutes < 1) return 'Az once';
    if (diff.inMinutes < 60) return '${diff.inMinutes} dk once';
    if (diff.inHours < 24) return '${diff.inHours} saat once';
    if (diff.inDays < 7) return '${diff.inDays} gun once';
    return '${date.day}.${date.month}.${date.year}';
  }
}

/// Badge gosterimi icin kullanilan widget (AppBar veya BottomNav icinde)
class NotificationBadge extends ConsumerWidget {
  final Widget child;

  const NotificationBadge({super.key, required this.child});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unreadAsync = ref.watch(unreadCountProvider);

    return unreadAsync.when(
      data: (count) {
        if (count == 0) return child;
        return Badge(
          label: Text(count > 99 ? '99+' : '$count'),
          child: child,
        );
      },
      loading: () => child,
      error: (_, __) => child,
    );
  }
}
```

---

## 7. Swift Implementasyon Ornegi

### 7.1 NotificationModel (Swift Codable)

```swift
import Foundation

// MARK: - Bildirim Modeli

struct NotificationItem: Codable, Identifiable {
    let id: String
    let type: String
    let title: String
    let body: String
    let isRead: Bool
    let readAt: String?
    let createdAt: String?
    let actorName: String?
    let actorAvatarUrl: String?
    let targetId: String?
    let targetType: String?

    enum CodingKeys: String, CodingKey {
        case id, type, title, body
        case isRead = "is_read"
        case readAt = "read_at"
        case createdAt = "created_at"
        case actorName = "actor_name"
        case actorAvatarUrl = "actor_avatar_url"
        case targetId = "target_id"
        case targetType = "target_type"
    }
}

// MARK: - Bildirim Tipi Enum

enum NotificationType: String, Codable {
    case newMessage = "new_message"
    case applicationReceived = "application_received"
    case applicationAccepted = "application_accepted"
    case applicationDeclined = "application_declined"
}

// MARK: - API Response Modelleri

struct NotificationListResponse: Codable {
    let success: Bool
    let data: [NotificationItem]
    let meta: PaginationMeta
}

struct PaginationMeta: Codable {
    let currentPage: Int
    let lastPage: Int
    let perPage: Int
    let total: Int

    enum CodingKeys: String, CodingKey {
        case currentPage = "current_page"
        case lastPage = "last_page"
        case perPage = "per_page"
        case total
    }
}

struct UnreadCountResponse: Codable {
    let success: Bool
    let data: UnreadCountData
}

struct UnreadCountData: Codable {
    let count: Int
}

struct MarkAsReadResponse: Codable {
    let success: Bool
    let data: MarkAsReadData
}

struct MarkAsReadData: Codable {
    let id: String
    let isRead: Bool
    let readAt: String?

    enum CodingKeys: String, CodingKey {
        case id
        case isRead = "is_read"
        case readAt = "read_at"
    }
}

struct MarkAllAsReadResponse: Codable {
    let success: Bool
    let data: MarkAllAsReadData
}

struct MarkAllAsReadData: Codable {
    let updatedCount: Int

    enum CodingKeys: String, CodingKey {
        case updatedCount = "updated_count"
    }
}
```

### 7.2 NotificationService (URLSession / async-await)

```swift
import Foundation

// MARK: - Bildirim API Servisi

actor NotificationService {
    private let baseURL: String
    private let session: URLSession
    private let tokenProvider: () -> String?

    init(
        baseURL: String,
        session: URLSession = .shared,
        tokenProvider: @escaping () -> String?
    ) {
        self.baseURL = baseURL
        self.session = session
        self.tokenProvider = tokenProvider
    }

    // MARK: - Bildirimleri Listele

    /// Sayfalanmis bildirim listesini getirir.
    /// - Parameters:
    ///   - page: Sayfa numarasi (varsayilan: 1)
    ///   - perPage: Sayfa basina bildirim sayisi (varsayilan: 20, maks: 100)
    func getNotifications(
        page: Int = 1,
        perPage: Int = 20
    ) async throws -> NotificationListResponse {
        var components = URLComponents(
            string: "\(baseURL)/api/v1/me/notifications"
        )!
        components.queryItems = [
            URLQueryItem(name: "page", value: "\(page)"),
            URLQueryItem(name: "per_page", value: "\(perPage)"),
        ]

        let request = try makeRequest(url: components.url!, method: "GET")
        let (data, response) = try await session.data(for: request)
        try validateResponse(response)

        return try JSONDecoder().decode(
            NotificationListResponse.self,
            from: data
        )
    }

    // MARK: - Okunmamis Bildirim Sayisi

    /// Okunmamis bildirim sayisini dondurur.
    func getUnreadCount() async throws -> Int {
        let url = URL(string: "\(baseURL)/api/v1/me/notifications/unread-count")!
        let request = try makeRequest(url: url, method: "GET")
        let (data, response) = try await session.data(for: request)
        try validateResponse(response)

        let decoded = try JSONDecoder().decode(
            UnreadCountResponse.self,
            from: data
        )
        return decoded.data.count
    }

    // MARK: - Tek Bildirimi Okundu Isaretle

    /// Belirli bir bildirimi okundu olarak isaretler.
    /// - Parameter notificationId: Bildirim UUID'si
    func markAsRead(notificationId: String) async throws -> MarkAsReadResponse {
        let url = URL(
            string: "\(baseURL)/api/v1/me/notifications/\(notificationId)/read"
        )!
        let request = try makeRequest(url: url, method: "POST")
        let (data, response) = try await session.data(for: request)
        try validateResponse(response)

        return try JSONDecoder().decode(
            MarkAsReadResponse.self,
            from: data
        )
    }

    // MARK: - Tum Bildirimleri Okundu Isaretle

    /// Tum okunmamis bildirimleri okundu olarak isaretler.
    func markAllAsRead() async throws -> MarkAllAsReadResponse {
        let url = URL(
            string: "\(baseURL)/api/v1/me/notifications/read-all"
        )!
        let request = try makeRequest(url: url, method: "POST")
        let (data, response) = try await session.data(for: request)
        try validateResponse(response)

        return try JSONDecoder().decode(
            MarkAllAsReadResponse.self,
            from: data
        )
    }

    // MARK: - Private Helpers

    private func makeRequest(url: URL, method: String) throws -> URLRequest {
        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        guard let token = tokenProvider() else {
            throw NotificationError.unauthorized
        }
        request.setValue(
            "Bearer \(token)",
            forHTTPHeaderField: "Authorization"
        )

        return request
    }

    private func validateResponse(_ response: URLResponse) throws {
        guard let httpResponse = response as? HTTPURLResponse else {
            throw NotificationError.invalidResponse
        }

        switch httpResponse.statusCode {
        case 200...299:
            return
        case 401:
            throw NotificationError.unauthorized
        case 403:
            throw NotificationError.forbidden
        case 404:
            throw NotificationError.notFound
        default:
            throw NotificationError.serverError(
                statusCode: httpResponse.statusCode
            )
        }
    }
}

// MARK: - Hata Tipleri

enum NotificationError: LocalizedError {
    case unauthorized
    case forbidden
    case notFound
    case invalidResponse
    case serverError(statusCode: Int)

    var errorDescription: String? {
        switch self {
        case .unauthorized:
            return "Oturum suresi dolmus. Lutfen tekrar giris yapin."
        case .forbidden:
            return "Bu islemi yapmaya yetkiniz bulunmamaktadir."
        case .notFound:
            return "Bildirim bulunamadi."
        case .invalidResponse:
            return "Sunucudan gecersiz yanit alindi."
        case .serverError(let code):
            return "Sunucu hatasi (\(code)). Lutfen daha sonra tekrar deneyin."
        }
    }
}
```

### 7.3 Firebase Setup (Swift)

```swift
import Firebase
import FirebaseMessaging
import UserNotifications

// MARK: - AppDelegate Firebase Ayarlari

class AppDelegate: NSObject, UIApplicationDelegate, MessagingDelegate,
    UNUserNotificationCenterDelegate
{
    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions:
            [UIApplication.LaunchOptionsKey: Any]? = nil
    ) -> Bool {
        // Firebase baslatma
        FirebaseApp.configure()

        // Messaging delegate
        Messaging.messaging().delegate = self

        // Bildirim izinleri
        UNUserNotificationCenter.current().delegate = self
        UNUserNotificationCenter.current().requestAuthorization(
            options: [.alert, .badge, .sound]
        ) { granted, error in
            if granted {
                DispatchQueue.main.async {
                    application.registerForRemoteNotifications()
                }
            }
        }

        return true
    }

    // MARK: - FCM Token

    func messaging(
        _ messaging: Messaging,
        didReceiveRegistrationToken fcmToken: String?
    ) {
        guard let token = fcmToken else { return }
        print("FCM Token: \(token)")

        // Token'i backend'e gonder
        // NotificationTokenService.shared.sendToken(token)
    }

    // MARK: - On Plan Bildirimi

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler:
            @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        // On planda banner, ses ve badge goster
        completionHandler([.banner, .sound, .badge])
    }

    // MARK: - Bildirime Tiklanma

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo = response.notification.request.content.userInfo

        if let type = userInfo["type"] as? String,
           let targetId = userInfo["target_id"] as? String,
           let targetType = userInfo["target_type"] as? String
        {
            handleNotificationTap(
                type: type,
                targetId: targetId,
                targetType: targetType
            )
        }

        completionHandler()
    }

    private func handleNotificationTap(
        type: String,
        targetId: String,
        targetType: String
    ) {
        guard targetType == "application" else { return }

        if type == "new_message" {
            // Sohbet ekranina yonlendir
            NotificationCenter.default.post(
                name: .navigateToChat,
                object: nil,
                userInfo: ["applicationId": targetId]
            )
        } else {
            // Basvuru detay ekranina yonlendir
            NotificationCenter.default.post(
                name: .navigateToApplication,
                object: nil,
                userInfo: ["applicationId": targetId]
            )
        }
    }
}

// MARK: - Notification Names

extension Foundation.Notification.Name {
    static let navigateToChat = Foundation.Notification.Name("navigateToChat")
    static let navigateToApplication = Foundation.Notification.Name(
        "navigateToApplication"
    )
}
```

### 7.4 SwiftUI Kullanim Ornegi

```swift
import SwiftUI

// MARK: - Bildirim ViewModel

@MainActor
class NotificationViewModel: ObservableObject {
    @Published var notifications: [NotificationItem] = []
    @Published var unreadCount: Int = 0
    @Published var isLoading = false
    @Published var isLoadingMore = false
    @Published var errorMessage: String?

    private let service: NotificationService
    private var currentPage = 1
    private var lastPage = 1

    var hasMore: Bool { currentPage < lastPage }

    init(service: NotificationService) {
        self.service = service
    }

    /// Ilk sayfa bildirimlerini yukler.
    func loadNotifications() async {
        isLoading = true
        errorMessage = nil

        do {
            let response = try await service.getNotifications(page: 1)
            notifications = response.data
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch {
            errorMessage = error.localizedDescription
        }

        isLoading = false
    }

    /// Sonraki sayfa bildirimlerini yukler.
    func loadMore() async {
        guard hasMore, !isLoadingMore else { return }

        isLoadingMore = true

        do {
            let nextPage = currentPage + 1
            let response = try await service.getNotifications(page: nextPage)
            notifications.append(contentsOf: response.data)
            currentPage = response.meta.currentPage
            lastPage = response.meta.lastPage
        } catch {
            errorMessage = error.localizedDescription
        }

        isLoadingMore = false
    }

    /// Okunmamis bildirim sayisini gunceller.
    func refreshUnreadCount() async {
        do {
            unreadCount = try await service.getUnreadCount()
        } catch {
            // Badge hatalarini sessizce logla
            print("Badge guncelleme hatasi: \(error)")
        }
    }

    /// Tek bildirimi okundu olarak isaretler.
    func markAsRead(_ notification: NotificationItem) async {
        guard !notification.isRead else { return }

        do {
            _ = try await service.markAsRead(notificationId: notification.id)
            if let index = notifications.firstIndex(where: { $0.id == notification.id }) {
                notifications[index] = NotificationItem(
                    id: notification.id,
                    type: notification.type,
                    title: notification.title,
                    body: notification.body,
                    isRead: true,
                    readAt: ISO8601DateFormatter().string(from: Date()),
                    createdAt: notification.createdAt,
                    actorName: notification.actorName,
                    actorAvatarUrl: notification.actorAvatarUrl,
                    targetId: notification.targetId,
                    targetType: notification.targetType
                )
            }
            await refreshUnreadCount()
        } catch {
            print("Okundu isaretleme hatasi: \(error)")
        }
    }

    /// Tum bildirimleri okundu olarak isaretler.
    func markAllAsRead() async {
        do {
            _ = try await service.markAllAsRead()
            notifications = notifications.map { notification in
                NotificationItem(
                    id: notification.id,
                    type: notification.type,
                    title: notification.title,
                    body: notification.body,
                    isRead: true,
                    readAt: ISO8601DateFormatter().string(from: Date()),
                    createdAt: notification.createdAt,
                    actorName: notification.actorName,
                    actorAvatarUrl: notification.actorAvatarUrl,
                    targetId: notification.targetId,
                    targetType: notification.targetType
                )
            }
            unreadCount = 0
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}

// MARK: - Bildirim Listesi Ekrani

struct NotificationListView: View {
    @StateObject private var viewModel: NotificationViewModel

    init(service: NotificationService) {
        _viewModel = StateObject(
            wrappedValue: NotificationViewModel(service: service)
        )
    }

    var body: some View {
        NavigationStack {
            Group {
                if viewModel.isLoading {
                    ProgressView("Bildirimler yukleniyor...")
                } else if let error = viewModel.errorMessage,
                          viewModel.notifications.isEmpty
                {
                    ContentUnavailableView {
                        Label("Hata", systemImage: "exclamationmark.triangle")
                    } description: {
                        Text(error)
                    } actions: {
                        Button("Tekrar Dene") {
                            Task { await viewModel.loadNotifications() }
                        }
                    }
                } else if viewModel.notifications.isEmpty {
                    ContentUnavailableView {
                        Label("Bildirim Yok", systemImage: "bell.slash")
                    } description: {
                        Text("Henuz bildiriminiz bulunmamaktadir.")
                    }
                } else {
                    notificationList
                }
            }
            .navigationTitle("Bildirimler")
            .toolbar {
                ToolbarItem(placement: .topBarTrailing) {
                    Button("Tumunu Oku") {
                        Task { await viewModel.markAllAsRead() }
                    }
                    .disabled(viewModel.unreadCount == 0)
                }
            }
            .refreshable {
                await viewModel.loadNotifications()
                await viewModel.refreshUnreadCount()
            }
            .task {
                await viewModel.loadNotifications()
                await viewModel.refreshUnreadCount()
            }
        }
    }

    private var notificationList: some View {
        List {
            ForEach(viewModel.notifications) { notification in
                NotificationRow(notification: notification)
                    .onTapGesture {
                        Task {
                            await viewModel.markAsRead(notification)
                        }
                        handleNavigation(notification)
                    }
                    .onAppear {
                        // Infinite scroll: son elemana yaklasinca daha fazla yukle
                        if notification.id ==
                            viewModel.notifications.last?.id
                        {
                            Task { await viewModel.loadMore() }
                        }
                    }
            }

            if viewModel.isLoadingMore {
                HStack {
                    Spacer()
                    ProgressView()
                    Spacer()
                }
            }
        }
        .listStyle(.plain)
    }

    private func handleNavigation(_ notification: NotificationItem) {
        guard let targetType = notification.targetType,
              let targetId = notification.targetId
        else { return }

        if targetType == "application" {
            if notification.type == NotificationType.newMessage.rawValue {
                // Sohbet ekranina yonlendir
                // router.navigate(to: .chat(applicationId: targetId))
            } else {
                // Basvuru detay ekranina yonlendir
                // router.navigate(to: .applicationDetail(id: targetId))
            }
        }
    }
}

// MARK: - Bildirim Satiri

struct NotificationRow: View {
    let notification: NotificationItem

    var body: some View {
        HStack(alignment: .top, spacing: 12) {
            // Avatar
            AsyncImage(url: URL(string: notification.actorAvatarUrl ?? "")) {
                image in
                image.resizable().aspectRatio(contentMode: .fill)
            } placeholder: {
                Image(systemName: iconName)
                    .foregroundColor(.accentColor)
            }
            .frame(width: 44, height: 44)
            .clipShape(Circle())

            // Icerik
            VStack(alignment: .leading, spacing: 4) {
                Text(notification.actorName ?? notification.title)
                    .font(.subheadline)
                    .fontWeight(notification.isRead ? .regular : .bold)

                Text(notification.body)
                    .font(.caption)
                    .foregroundColor(.secondary)
                    .lineLimit(2)

                Text(formattedDate)
                    .font(.caption2)
                    .foregroundColor(.gray)
            }

            Spacer()

            // Okunmadi gostergesi
            if !notification.isRead {
                Circle()
                    .fill(.blue)
                    .frame(width: 10, height: 10)
            }
        }
        .padding(.vertical, 4)
        .background(
            notification.isRead
                ? Color.clear
                : Color.accentColor.opacity(0.05)
        )
    }

    private var iconName: String {
        switch notification.type {
        case NotificationType.newMessage.rawValue:
            return "message"
        case NotificationType.applicationReceived.rawValue:
            return "tray.and.arrow.down"
        case NotificationType.applicationAccepted.rawValue:
            return "checkmark.circle"
        case NotificationType.applicationDeclined.rawValue:
            return "xmark.circle"
        default:
            return "bell"
        }
    }

    private var formattedDate: String {
        guard let dateStr = notification.createdAt,
              let date = ISO8601DateFormatter().date(from: dateStr)
        else {
            return ""
        }

        let formatter = RelativeDateTimeFormatter()
        formatter.locale = Locale(identifier: "tr_TR")
        formatter.unitsStyle = .abbreviated
        return formatter.localizedString(for: date, relativeTo: Date())
    }
}

// MARK: - Badge Gosterimi (Tab Bar icin)

struct NotificationBadgeModifier: ViewModifier {
    @ObservedObject var viewModel: NotificationViewModel

    func body(content: Content) -> some View {
        content
            .badge(viewModel.unreadCount)
            .task {
                await viewModel.refreshUnreadCount()
            }
    }
}
```

---

## 8. Entegrasyon Notlari

### 8.1 Badge Sayisi Yonetimi

Okunmamis bildirim sayisi (badge) asagidaki durumlarda yenilenmelidir:

| Durum | Ne Zaman |
|-------|----------|
| Uygulama on plana geldiginde | `AppLifecycleState.resumed` (Flutter) / `scenePhase == .active` (SwiftUI) |
| Basvuru gonderildikten sonra | `POST /opportunities/{id}/applications` basarili response sonrasi |
| Basvuru kabul/red edildikten sonra | `POST /applications/{id}/accept` veya `decline` basarili response sonrasi |
| Mesaj gonderildikten sonra | `POST /applications/{id}/messages` basarili response sonrasi |
| Push bildirim alindiginda | FCM foreground handler icinde |
| Pull-to-refresh yapildiginda | Bildirim listesi yenilendiginde |

### 8.2 Okundu Isaretleme Stratejisi

- **Tek bildirim:** Kullanici bir bildirime tikladiginda `POST /me/notifications/{id}/read` cagirilir
- **Toplu isaretleme:** Bildirim ekraninda "Tumunu Oku" butonu ile `POST /me/notifications/read-all` cagirilir
- **Optimistic update:** API cagrisini beklemeden UI'da hemen okundu olarak gosterin, hata durumunda geri alin

### 8.3 Deep Linking

Push bildirimden uygulamaya geciste deep linking mantigi:

```
Push Bildirim Data:
{
  "type": "application_received",
  "target_type": "application",
  "target_id": "uuid-xxx"
}

Yonlendirme Kurallari:
- target_type="application" + type="new_message"
    --> /chat/{target_id}        (Sohbet ekrani)
- target_type="application" + type="application_received"
    --> /applications/{target_id} (Basvuru detayi - is sahibi gorunumu)
- target_type="application" + type="application_accepted"
    --> /applications/{target_id} (Basvuru detayi - topluluk gorunumu)
- target_type="application" + type="application_declined"
    --> /applications/{target_id} (Basvuru detayi - topluluk gorunumu)
```

**Onemli:** Uygulama kapali iken gelen bildirime tiklandiginda:
1. Uygulama acilir
2. Splash/Auth kontrolu yapilir
3. Kullanici oturum acmissa ilgili ekrana yonlendirilir
4. Oturum acmamissa, login sonrasi yonlendirme bilgisi saklanir ve login tamamlandiktan sonra yonlendirilir

### 8.4 Sayfalama (Infinite Scroll)

Bildirim listesi icin infinite scroll implementasyonu:

1. Ilk yukleme: `GET /me/notifications?page=1&per_page=20`
2. Response'daki `meta.last_page` degerini kontrol edin
3. Kullanici listenin sonuna yaklastiginda (son 3-5 eleman gorunur oldugunda):
   - `meta.current_page < meta.last_page` ise sonraki sayfayi yukleyin
   - `GET /me/notifications?page={current_page + 1}&per_page=20`
4. Yeni gelen verileri mevcut listeye ekleyin (append)
5. Tum sayfalar yuklendiyse daha fazla istek gondermeyin

### 8.5 Hata Yonetimi

| HTTP Kodu | Anlami | Mobil Uygulama Aksiyonu |
|-----------|--------|------------------------|
| `200` | Basarili | Normal isleme devam |
| `401` | Oturum suresi dolmus | Login ekranina yonlendir, token'i temizle |
| `403` | Yetkisiz erisim | Hata mesaji goster, geri don |
| `404` | Kaynak bulunamadi | Hata mesaji goster, listeyi yenile |
| `422` | Validasyon hatasi | Hata detaylarini goster |
| `429` | Rate limit asildi | Kisa sure bekle, tekrar dene (exponential backoff) |
| `500` | Sunucu hatasi | Genel hata mesaji goster, tekrar dene secenegi sun |

### 8.6 Performans Oneriler

1. **Cache:** Bildirim listesini lokal olarak cache'leyin (SQLite/Hive/CoreData). API'den sadece delta (yeni) verileri cekin
2. **Debounce:** Badge sayisi isteklerini debounce edin (500ms). Art arda gelen aksiyonlarda gereksiz istek gondermekten kacinin
3. **Background Fetch:** iOS'ta background fetch ile periyodik olarak badge sayisini guncelleyin
4. **Image Cache:** Actor avatar resimlerini cache'leyin (CachedNetworkImage / Kingfisher)
5. **Lazy Loading:** Bildirim listesinde sadece gorunen elemanlari render edin

### 8.7 Test Senaryolari

Mobil uygulama tarafinda test edilmesi gereken senaryolar:

| Senaryo | Beklenen Davranis |
|---------|-------------------|
| Uygulama acildiginda bildirim listesi | Yuklenirken spinner, sonra liste gosterilir |
| Bos bildirim listesi | "Henuz bildiriminiz bulunmamaktadir" mesaji |
| Bildirime tiklanma | Okundu isaretlenir + ilgili ekrana yonlendirilir |
| "Tumunu Oku" butonuna tiklanma | Tum bildirimler okundu olur + badge sifirlanir |
| Infinite scroll | Asagi kaydirildiginda sonraki sayfa yuklenir |
| Pull-to-refresh | Liste basa doner ve yeniden yuklenir |
| Push bildirim (on plan) | In-app banner gosterilir + badge guncellenir |
| Push bildirim (arka plan) | Sistem bildirimi gosterilir |
| Push bildirime tiklanma | Uygulama acilir + ilgili ekrana gidilir |
| Ag hatasi | Hata mesaji + "Tekrar Dene" butonu gosterilir |
| 401 hatasi | Login ekranina yonlendirilir |
| 403 hatasi (baskasinin bildirimi) | "Yetkisiz erisim" mesaji gosterilir |

---

## Endpoint Hizli Referans Tablosu

| # | Method | Endpoint | Aciklama |
|---|--------|----------|----------|
| 1 | `GET` | `/api/v1/me/notifications` | Bildirim listesi (sayfalanmis) |
| 2 | `GET` | `/api/v1/me/notifications/unread-count` | Okunmamis bildirim sayisi |
| 3 | `POST` | `/api/v1/me/notifications/{notification}/read` | Tek bildirimi okundu isaretle |
| 4 | `POST` | `/api/v1/me/notifications/read-all` | Tum bildirimleri okundu isaretle |

**Tum endpoint'ler `Authorization: Bearer {token}` header'i gerektirir.**
