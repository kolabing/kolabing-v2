# Past Events API - Mobile Implementation Guide

## Overview

Business ve Community kullanicilari gecmiste gerceklestirdikleri etkinlikleri profillerinde sergileyebilir. Her etkinlik bir partner (karsi taraf) ile gerceklestirilmis isbirligini temsil eder.

**Hem business hem community kullanicilar** event olusturabilir, guncelleyebilir ve silebilir. Herkes herkesin eventlerini goruntuleyebilir (public profile icin).

---

## Endpoints

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/v1/events` | Etkinlikleri listele (kendi veya baskasinin) |
| GET | `/api/v1/events/{id}` | Tek bir etkinligin detayi |
| POST | `/api/v1/events` | Yeni etkinlik olustur (foto yuklemeli) |
| PUT | `/api/v1/events/{id}` | Etkinlik guncelle (sadece sahip) |
| DELETE | `/api/v1/events/{id}` | Etkinlik sil (sadece sahip) |

**Auth:** Tum endpointler `Bearer token` gerektirir.

---

## 1. List Events

### GET /api/v1/events

Kullanicinin gecmis etkinliklerini listeler. `profile_id` verilmezse kendi etkinliklerini dondurur.

```
GET /api/v1/events?profile_id={uuid}&page=1&limit=10
Authorization: Bearer {token}
Accept: application/json
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| profile_id | string (UUID) | No | Auth user | Baskasinin eventlerini gormek icin |
| page | int | No | 1 | Sayfa numarasi |
| limit | int | No | 10 | Sayfa basi item (max: 50) |

### Success Response (200)

```json
{
  "success": true,
  "data": {
    "events": [
      {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Summer Music Festival",
        "partner": {
          "id": "550e8400-e29b-41d4-a716-446655440001",
          "name": "Rock Community Istanbul",
          "profile_photo": "https://storage.example.com/profiles/uuid/photo.jpg",
          "type": "community"
        },
        "date": "2025-08-15",
        "attendee_count": 1250,
        "photos": [
          {
            "id": "photo-uuid-1",
            "url": "https://storage.example.com/events/uuid/photo1.jpg",
            "thumbnail_url": null
          }
        ],
        "created_at": "2025-08-20T10:00:00+00:00",
        "updated_at": "2025-08-20T10:00:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "total_count": 25,
      "per_page": 10
    }
  }
}
```

### Empty Response (200)

```json
{
  "success": true,
  "data": {
    "events": [],
    "pagination": {
      "current_page": 1,
      "total_pages": 0,
      "total_count": 0,
      "per_page": 10
    }
  }
}
```

**Not:** Eventler `date` alanina gore en yeniden eskiye siralanir.

---

## 2. Get Single Event

### GET /api/v1/events/{id}

```
GET /api/v1/events/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer {token}
Accept: application/json
```

### Success Response (200)

```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Summer Music Festival",
    "partner": {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Rock Community Istanbul",
      "profile_photo": "https://storage.example.com/profiles/uuid/photo.jpg",
      "type": "community"
    },
    "date": "2025-08-15",
    "attendee_count": 1250,
    "photos": [
      {
        "id": "photo-uuid-1",
        "url": "https://storage.example.com/events/uuid/photo1.jpg",
        "thumbnail_url": null
      },
      {
        "id": "photo-uuid-2",
        "url": "https://storage.example.com/events/uuid/photo2.jpg",
        "thumbnail_url": null
      }
    ],
    "created_at": "2025-08-20T10:00:00+00:00",
    "updated_at": "2025-08-20T10:00:00+00:00"
  }
}
```

### Not Found (404)

Gecersiz UUID icin standart 404 doner.

---

## 3. Create Event

### POST /api/v1/events

**Content-Type: multipart/form-data** (foto yukleme icin)

```
POST /api/v1/events
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Etkinlik adi (min: 3, max: 100 karakter) |
| partner_id | string (UUID) | Yes | Partner profil UUID (profiles tablosunda olmali) |
| partner_type | string | Yes | `business` veya `community` |
| date | string | Yes | `YYYY-MM-DD` format, gelecek tarih olamaz |
| attendee_count | integer | Yes | Katilimci sayisi (min: 1) |
| photos[] | File | Yes | Foto dosyalari (min: 1, max: 5 adet) |

### Photo Validation

- Her foto max **5MB**
- Kabul edilen formatlar: `jpeg`, `jpg`, `png`, `gif`, `webp`
- Minimum 1, maximum 5 foto

### Dart/Flutter Request Example

```dart
Future<EventResponse> createEvent({
  required String name,
  required String partnerId,
  required String partnerType,
  required String date,
  required int attendeeCount,
  required List<File> photos,
}) async {
  final formData = FormData.fromMap({
    'name': name,
    'partner_id': partnerId,
    'partner_type': partnerType,
    'date': date,
    'attendee_count': attendeeCount,
    'photos[]': photos
        .map((file) => MultipartFile.fromFileSync(
              file.path,
              filename: file.path.split('/').last,
            ))
        .toList(),
  });

  final response = await _dio.post('/api/v1/events', data: formData);
  return EventResponse.fromJson(response.data);
}
```

### Success Response (201)

```json
{
  "success": true,
  "message": "Event created successfully.",
  "data": {
    "id": "new-event-uuid",
    "name": "Summer Music Festival",
    "partner": {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Rock Community Istanbul",
      "profile_photo": "https://storage.example.com/profiles/uuid/photo.jpg",
      "type": "community"
    },
    "date": "2025-08-15",
    "attendee_count": 1250,
    "photos": [
      {
        "id": "photo-uuid-1",
        "url": "https://storage.example.com/events/new-event-uuid/20250820_AbCdEfGh.jpg",
        "thumbnail_url": null
      },
      {
        "id": "photo-uuid-2",
        "url": "https://storage.example.com/events/new-event-uuid/20250820_XyZwKlMn.jpg",
        "thumbnail_url": null
      }
    ],
    "created_at": "2025-08-20T10:00:00+00:00",
    "updated_at": "2025-08-20T10:00:00+00:00"
  }
}
```

### Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["Event name is required."],
    "partner_id": ["Partner is required."],
    "date": ["Event date cannot be in the future."],
    "photos": ["At least one photo is required."],
    "photos.0": ["Each photo must be an image file."]
  }
}
```

---

## 4. Update Event

### PUT /api/v1/events/{id}

Sadece event sahibi guncelleyebilir. Tum alanlar opsiyonel - sadece gonderilen alanlar guncellenir.

**Not:** Fotolar guncellenemez. Foto degistirmek icin eventi silip yeniden olusturun.

```
PUT /api/v1/events/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body

```json
{
  "name": "Updated Festival Name",
  "attendee_count": 1500
}
```

### Updatable Fields

| Field | Type | Rules |
|-------|------|-------|
| name | string | min: 3, max: 100 |
| partner_id | string (UUID) | Gecerli profil UUID |
| partner_type | string | `business` veya `community` |
| date | string | `YYYY-MM-DD`, gelecek tarih olamaz |
| attendee_count | integer | min: 1 |

### Success Response (200)

```json
{
  "success": true,
  "message": "Event updated successfully.",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "name": "Updated Festival Name",
    "partner": {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "name": "Rock Community Istanbul",
      "profile_photo": null,
      "type": "community"
    },
    "date": "2025-08-15",
    "attendee_count": 1500,
    "photos": [...],
    "created_at": "2025-08-20T10:00:00+00:00",
    "updated_at": "2025-08-21T14:30:00+00:00"
  }
}
```

### Forbidden (403)

```json
{
  "success": false,
  "message": "You are not authorized to update this event."
}
```

---

## 5. Delete Event

### DELETE /api/v1/events/{id}

Sadece event sahibi silebilir. Event silindiginde iliskili fotolar da storage'dan silinir.

```
DELETE /api/v1/events/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer {token}
```

### Success Response (200)

```json
{
  "success": true,
  "message": "Event deleted successfully."
}
```

### Forbidden (403)

```json
{
  "success": false,
  "message": "You are not authorized to delete this event."
}
```

### Not Found (404)

Gecersiz UUID icin standart 404.

---

## Response Data Models

### Event Object

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | string (UUID) | No | Event ID |
| name | string | No | Etkinlik adi |
| partner | EventPartner | No | Partner bilgisi |
| date | string | No | `YYYY-MM-DD` format |
| attendee_count | integer | No | Katilimci sayisi |
| photos | EventPhoto[] | No | Foto listesi (bos array olabilir) |
| created_at | string (ISO 8601) | No | Olusturulma zamani |
| updated_at | string (ISO 8601) | No | Guncellenme zamani |

### EventPartner Object

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | string (UUID) | No | Partner profil ID |
| name | string | Yes | Partner adi (extended profile'dan gelir) |
| profile_photo | string (URL) | Yes | Profil fotosu URL |
| type | string | No | `business` veya `community` |

### EventPhoto Object

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| id | string (UUID) | No | Foto ID |
| url | string (URL) | No | Full-size foto URL |
| thumbnail_url | string (URL) | Yes | Thumbnail URL (su an her zaman `null`) |

### Pagination Object

| Field | Type | Description |
|-------|------|-------------|
| current_page | integer | Mevcut sayfa |
| total_pages | integer | Toplam sayfa sayisi |
| total_count | integer | Toplam event sayisi |
| per_page | integer | Sayfa basi item |

---

## Flutter/Dart Models

```dart
class EventModel {
  final String id;
  final String name;
  final EventPartner partner;
  final String date;
  final int attendeeCount;
  final List<EventPhoto> photos;
  final String createdAt;
  final String updatedAt;

  EventModel({
    required this.id,
    required this.name,
    required this.partner,
    required this.date,
    required this.attendeeCount,
    required this.photos,
    required this.createdAt,
    required this.updatedAt,
  });

  factory EventModel.fromJson(Map<String, dynamic> json) {
    return EventModel(
      id: json['id'],
      name: json['name'],
      partner: EventPartner.fromJson(json['partner']),
      date: json['date'],
      attendeeCount: json['attendee_count'],
      photos: (json['photos'] as List)
          .map((p) => EventPhoto.fromJson(p))
          .toList(),
      createdAt: json['created_at'],
      updatedAt: json['updated_at'],
    );
  }
}

class EventPartner {
  final String id;
  final String? name;
  final String? profilePhoto;
  final String type;

  EventPartner({
    required this.id,
    this.name,
    this.profilePhoto,
    required this.type,
  });

  factory EventPartner.fromJson(Map<String, dynamic> json) {
    return EventPartner(
      id: json['id'],
      name: json['name'],
      profilePhoto: json['profile_photo'],
      type: json['type'],
    );
  }
}

class EventPhoto {
  final String id;
  final String url;
  final String? thumbnailUrl;

  EventPhoto({
    required this.id,
    required this.url,
    this.thumbnailUrl,
  });

  factory EventPhoto.fromJson(Map<String, dynamic> json) {
    return EventPhoto(
      id: json['id'],
      url: json['url'],
      thumbnailUrl: json['thumbnail_url'],
    );
  }
}

class PaginationMeta {
  final int currentPage;
  final int totalPages;
  final int totalCount;
  final int perPage;

  PaginationMeta({
    required this.currentPage,
    required this.totalPages,
    required this.totalCount,
    required this.perPage,
  });

  factory PaginationMeta.fromJson(Map<String, dynamic> json) {
    return PaginationMeta(
      currentPage: json['current_page'],
      totalPages: json['total_pages'],
      totalCount: json['total_count'],
      perPage: json['per_page'],
    );
  }
}

class EventListResponse {
  final bool success;
  final List<EventModel> events;
  final PaginationMeta pagination;

  EventListResponse({
    required this.success,
    required this.events,
    required this.pagination,
  });

  factory EventListResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return EventListResponse(
      success: json['success'],
      events: (data['events'] as List)
          .map((e) => EventModel.fromJson(e))
          .toList(),
      pagination: PaginationMeta.fromJson(data['pagination']),
    );
  }
}

class EventResponse {
  final bool success;
  final String? message;
  final EventModel? event;

  EventResponse({
    required this.success,
    this.message,
    this.event,
  });

  factory EventResponse.fromJson(Map<String, dynamic> json) {
    return EventResponse(
      success: json['success'],
      message: json['message'],
      event: json['data'] != null ? EventModel.fromJson(json['data']) : null,
    );
  }
}
```

---

## Flutter/Dart EventService

```dart
class EventService {
  final Dio _dio;

  EventService(this._dio);

  /// Kendi veya baskasinin eventlerini listele
  Future<EventListResponse> getEvents({
    String? profileId,
    int page = 1,
    int limit = 10,
  }) async {
    final queryParams = <String, dynamic>{
      'page': page,
      'limit': limit,
    };
    if (profileId != null) {
      queryParams['profile_id'] = profileId;
    }

    final response = await _dio.get(
      '/api/v1/events',
      queryParameters: queryParams,
    );
    return EventListResponse.fromJson(response.data);
  }

  /// Tek bir event detayi
  Future<EventResponse> getEvent(String eventId) async {
    final response = await _dio.get('/api/v1/events/$eventId');
    return EventResponse.fromJson(response.data);
  }

  /// Yeni event olustur
  Future<EventResponse> createEvent({
    required String name,
    required String partnerId,
    required String partnerType,
    required String date,
    required int attendeeCount,
    required List<File> photos,
  }) async {
    final formData = FormData.fromMap({
      'name': name,
      'partner_id': partnerId,
      'partner_type': partnerType,
      'date': date,
      'attendee_count': attendeeCount,
      'photos[]': photos
          .map((file) => MultipartFile.fromFileSync(
                file.path,
                filename: file.path.split('/').last,
              ))
          .toList(),
    });

    final response = await _dio.post('/api/v1/events', data: formData);
    return EventResponse.fromJson(response.data);
  }

  /// Event guncelle (sadece sahip)
  Future<EventResponse> updateEvent({
    required String eventId,
    String? name,
    String? partnerId,
    String? partnerType,
    String? date,
    int? attendeeCount,
  }) async {
    final data = <String, dynamic>{};
    if (name != null) data['name'] = name;
    if (partnerId != null) data['partner_id'] = partnerId;
    if (partnerType != null) data['partner_type'] = partnerType;
    if (date != null) data['date'] = date;
    if (attendeeCount != null) data['attendee_count'] = attendeeCount;

    final response = await _dio.put(
      '/api/v1/events/$eventId',
      data: data,
    );
    return EventResponse.fromJson(response.data);
  }

  /// Event sil (sadece sahip)
  Future<bool> deleteEvent(String eventId) async {
    final response = await _dio.delete('/api/v1/events/$eventId');
    return response.data['success'] == true;
  }
}
```

---

## Error Responses Summary

| Status | Durum | Aciklama |
|--------|-------|----------|
| 401 | Unauthorized | Token eksik veya gecersiz |
| 403 | Forbidden | Yetki yok (baskasinin eventini guncelleme/silme) |
| 404 | Not Found | Event bulunamadi |
| 422 | Validation Error | Gecersiz veya eksik alanlar |

---

## Important Notes

1. **Photo Upload**: Fotolar `multipart/form-data` ile gonderilir, base64 degil
2. **Photo Storage**: Fotolar `events/{event_id}/` klasorunde saklanir
3. **Thumbnail**: `thumbnail_url` su an her zaman `null` donuyor (ileride eklenecek)
4. **Partner**: `partner_id` gercek bir profil UUID olmali, `partner_type` o profilin tipi olmali
5. **Date**: Gelecek tarih kabul edilmez (gecmis etkinlikler icin)
6. **Photo Limit**: Event basina max 5 foto
7. **Photo Update**: PUT ile foto guncellenemez. Degistirmek icin sil + yeniden olustur
8. **Siralama**: Eventler tarih sirasiyla doner (en yeni first)
9. **Authorization**: Herkes herkesin eventlerini gorebilir, ama sadece sahip guncelleme/silme yapabilir
10. **Pagination**: `limit` max 50 ile sinirli, daha yuksek deger verilse bile 50 olarak uygulanir

---

## Migration Checklist (Mock -> Real API)

Flutter `EventService` sinifindaki mock fonksiyonlari yukaridaki gercek API cagrilariyla degistirin:

- [ ] `EventService` sinifini gercek Dio HTTP cagrilariyla guncelle
- [ ] `EventModel.fromJson()` ve diger model'leri bu dokumandaki JSON yapisina gore dogrula
- [ ] Create event'te `multipart/form-data` ile foto yukleme implement et
- [ ] Pagination support ekle (infinite scroll veya page buttons)
- [ ] Error handling ekle (401, 403, 404, 422)
- [ ] Profile sayfasinda `profile_id` query param ile baskasinin eventlerini goster
- [ ] Delete confirmation dialog ekle
- [ ] Update event formu implement et
