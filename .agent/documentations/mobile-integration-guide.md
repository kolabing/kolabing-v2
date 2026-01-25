# Mobile Integration Guide - Lookup Tables & File Upload

Bu dokuman, mobile uygulama icin backend API entegrasyonunu aciklar.

---

## 1. Lookup API Endpoints

Onboarding sirasinda kullanilacak lookup tablolari icin API endpointleri.

### 1.1 Cities (Sehirler)

```
GET /api/v1/cities
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid-here",
      "name": "Barcelona",
      "country": "Spain"
    },
    {
      "id": "uuid-here",
      "name": "Madrid",
      "country": "Spain"
    }
    // ... 126 sehir
  ]
}
```

**Notlar:**
- 126 Ispanya sehri mevcut
- Tum otonom bolgeleri kapsiyor
- `id` UUID formatinda, onboarding'de `city_id` olarak gonderilecek

---

### 1.2 Business Types (Isletme Turleri)

```
GET /api/v1/business-types
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid-here",
      "name": "Restaurante",
      "slug": "restaurante",
      "icon": "utensils"
    },
    {
      "id": "uuid-here",
      "name": "Cafeteria",
      "slug": "cafeteria",
      "icon": "coffee"
    },
    {
      "id": "uuid-here",
      "name": "Bar",
      "slug": "bar",
      "icon": "beer"
    }
    // ... 15 tur
  ]
}
```

**Mevcut Business Types:**

| Name | Slug | Icon |
|------|------|------|
| Restaurante | restaurante | utensils |
| Cafeteria | cafeteria | coffee |
| Bar | bar | beer |
| Hotel | hotel | bed |
| Gimnasio | gimnasio | dumbbell |
| Spa y Bienestar | spa-y-bienestar | spa |
| Tienda de Moda | tienda-de-moda | shirt |
| Tienda de Deportes | tienda-de-deportes | basketball |
| Peluqueria | peluqueria | scissors |
| Centro de Belleza | centro-de-belleza | sparkles |
| Clinica Dental | clinica-dental | tooth |
| Centro Medico | centro-medico | stethoscope |
| Coworking | coworking | building |
| Discoteca | discoteca | music |
| Otro | otro | ellipsis |

**Notlar:**
- `slug` API'de filtre olarak kullanilabilir
- `icon` degeri FontAwesome/Heroicons icon adi
- Onboarding'de `business_type` olarak `slug` veya `name` gonderilecek

---

### 1.3 Community Types (Topluluk Turleri)

```
GET /api/v1/community-types
```

**Response:**
```json
{
  "data": [
    {
      "id": "uuid-here",
      "name": "Running Club",
      "slug": "running-club",
      "icon": "running"
    },
    {
      "id": "uuid-here",
      "name": "Club de Ciclismo",
      "slug": "club-de-ciclismo",
      "icon": "bicycle"
    }
    // ... 15 tur
  ]
}
```

**Mevcut Community Types:**

| Name | Slug | Icon |
|------|------|------|
| Running Club | running-club | running |
| Club de Ciclismo | club-de-ciclismo | bicycle |
| Grupo de Yoga | grupo-de-yoga | yoga |
| Club de Fitness | club-de-fitness | dumbbell |
| Grupo de Senderismo | grupo-de-senderismo | mountain |
| Club de Padel | club-de-padel | racket |
| Grupo de Arte | grupo-de-arte | palette |
| Club de Lectura | club-de-lectura | book |
| Grupo de Fotografia | grupo-de-fotografia | camera |
| Comunidad Tech | comunidad-tech | laptop |
| Grupo de Networking | grupo-de-networking | users |
| Club Gastronomico | club-gastronomico | utensils |
| Grupo de Viajes | grupo-de-viajes | plane |
| Comunidad de Musica | comunidad-de-musica | music |
| Otro | otro | ellipsis |

---

## 2. File Upload Service

### 2.1 Genel Bakis

FileUploadService, tum dosya yuklemelerini merkezi olarak yonetir:
- Profile fotograflari
- Opportunity fotograflari

**Storage URL:** `https://fls-a0ec5ae0-8506-4673-8ef9-046f680a9a08.laravel.cloud/`

### 2.2 Desteklenen Upload Yontemleri

#### A) Base64 Upload (Onerilir)

Mobile uygulamadan fotograf gondermek icin en kolay yontem.

```json
{
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

**Format:**
```
data:image/{type};base64,{base64_encoded_data}
```

**Desteklenen tipler:** `jpeg`, `jpg`, `png`, `gif`, `webp`

#### B) URL Upload

Harici bir URL'den fotograf yuklemek icin:

```json
{
  "profile_photo": "https://example.com/image.jpg"
}
```

**Notlar:**
- URL'deki fotograf indirilip cloud storage'a yuklenir
- Eski URL yerine yeni cloud URL doner

#### C) Multipart Form Data

Dosya olarak gondermek icin (ozellikle web icin):

```
POST /api/v1/onboarding/business
Content-Type: multipart/form-data

profile_photo: [file]
```

### 2.3 Dosya Kisitlamalari

| Kisitlama | Deger |
|-----------|-------|
| Max Boyut | 5 MB |
| Desteklenen Formatlar | JPEG, JPG, PNG, GIF, WEBP |
| MIME Types | image/jpeg, image/jpg, image/png, image/gif, image/webp |

### 2.4 Storage Yapisi

Dosyalar asagidaki yapiyla depolanir:

```
https://fls-xxx.laravel.cloud/
├── profiles/
│   └── {profile_id}/
│       └── 20260125123456_abcd1234.jpg
└── opportunities/
    └── {opportunity_id}/
        └── 20260125123456_efgh5678.jpg
```

### 2.5 Response'ta Donen URL

Basarili upload sonrasi tam URL doner:

```json
{
  "data": {
    "id": "uuid",
    "profile_photo": "https://fls-a0ec5ae0-8506-4673-8ef9-046f680a9a08.laravel.cloud/profiles/abc123/20260125123456_xyz789.jpg"
  }
}
```

---

## 3. Onboarding API Entegrasyonu

### 3.1 Business Onboarding

```
POST /api/v1/onboarding/business
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Mi Restaurante",
  "about": "Descripcion del negocio...",
  "business_type": "restaurante",
  "city_id": "uuid-of-city",
  "phone_number": "+34612345678",
  "instagram": "mirestaurante",
  "website": "https://mirestaurante.es",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

**Zorunlu Alanlar:**
- `name` (string, max 255)
- `business_type` (string, lookup tablosundan slug veya name)
- `city_id` (UUID, cities tablosundan)

**Opsiyonel Alanlar:**
- `about` (text)
- `phone_number` (string, E.164 format onerilir)
- `instagram` (string, @ olmadan)
- `website` (URL)
- `profile_photo` (base64 veya URL)

### 3.2 Community Onboarding

```
POST /api/v1/onboarding/community
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Barcelona Runners",
  "about": "Grupo de running en Barcelona...",
  "community_type": "running-club",
  "city_id": "uuid-of-city",
  "phone_number": "+34612345678",
  "instagram": "bcnrunners",
  "tiktok": "bcnrunners",
  "website": "https://bcnrunners.es",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

**Zorunlu Alanlar:**
- `name` (string, max 255)
- `community_type` (string, lookup tablosundan slug veya name)
- `city_id` (UUID, cities tablosundan)

**Opsiyonel Alanlar:**
- `about` (text)
- `phone_number` (string)
- `instagram` (string, @ olmadan)
- `tiktok` (string, @ olmadan)
- `website` (URL)
- `profile_photo` (base64 veya URL)

---

## 4. Mobile Implementation Checklist

### 4.1 Onboarding Akisi

```
1. Login/Register (Google OAuth)
      ↓
2. GET /api/v1/cities         → Sehir listesini cache'le
   GET /api/v1/business-types → Business type listesini cache'le
   GET /api/v1/community-types → Community type listesini cache'le
      ↓
3. Onboarding Form
   - Isim gir
   - Tur sec (dropdown/picker)
   - Sehir sec (searchable dropdown)
   - Fotograf sec (camera/gallery)
   - Diger bilgiler
      ↓
4. POST /api/v1/onboarding/{business|community}
   - Fotografi base64 olarak gonder
      ↓
5. Ana ekrana yonlendir
```

### 4.2 Fotograf Yukleme Implementasyonu

**React Native / Flutter icin:**

```javascript
// React Native ornegi
const pickImage = async () => {
  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ImagePicker.MediaTypeOptions.Images,
    allowsEditing: true,
    aspect: [1, 1],
    quality: 0.8,
    base64: true, // Onemli!
  });

  if (!result.canceled) {
    const base64Image = `data:image/jpeg;base64,${result.assets[0].base64}`;
    setProfilePhoto(base64Image);
  }
};

// API'ye gonderirken
const submitOnboarding = async (data) => {
  const response = await fetch('/api/v1/onboarding/business', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      ...data,
      profile_photo: profilePhoto, // base64 string
    }),
  });
};
```

```dart
// Flutter ornegi
Future<void> pickImage() async {
  final ImagePicker picker = ImagePicker();
  final XFile? image = await picker.pickImage(
    source: ImageSource.gallery,
    imageQuality: 80,
  );

  if (image != null) {
    final bytes = await image.readAsBytes();
    final base64Image = 'data:image/jpeg;base64,${base64Encode(bytes)}';
    setState(() => profilePhoto = base64Image);
  }
}
```

### 4.3 Error Handling

**Olasi Hatalar:**

| HTTP Code | Anlami | Cozum |
|-----------|--------|-------|
| 400 | Validation hatasi | Response body'deki errors'u goster |
| 401 | Unauthorized | Login'e yonlendir |
| 413 | Dosya cok buyuk | 5MB limitini belirt |
| 415 | Desteklenmeyen format | JPEG/PNG/GIF/WEBP kullan |
| 422 | Validation hatasi | Response body'deki errors'u goster |

**Validation Error Response:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "business_type": ["The selected business type is invalid."],
    "city_id": ["The selected city is invalid."]
  }
}
```

---

## 5. Caching Onerileri

### 5.1 Lookup Verileri

| Endpoint | Cache Suresi | Strateji |
|----------|--------------|----------|
| /cities | 24 saat | Stale-while-revalidate |
| /business-types | 24 saat | Stale-while-revalidate |
| /community-types | 24 saat | Stale-while-revalidate |

### 5.2 Fotograflar

- Cloud URL'leri kalici, degismez
- CDN uzerinden serve edilir
- Image caching kutuphanesi kullanin (React Native: FastImage, Flutter: cached_network_image)

---

## 6. Test Icin Ornek Veriler

### Cities (Ilk 10)
```json
["Barcelona", "Madrid", "Valencia", "Sevilla", "Bilbao",
 "Malaga", "Zaragoza", "Palma", "Las Palmas de Gran Canaria", "Murcia"]
```

### Business Types Test
```
POST /api/v1/onboarding/business
{
  "name": "Test Restaurant",
  "business_type": "restaurante",
  "city_id": "<barcelona-uuid>"
}
```

### Community Types Test
```
POST /api/v1/onboarding/community
{
  "name": "Test Running Club",
  "community_type": "running-club",
  "city_id": "<barcelona-uuid>"
}
```
