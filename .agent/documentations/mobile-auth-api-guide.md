# Mobile Authentication API Guide

Bu dokuman, mobile uygulama icin authentication API entegrasyonunu aciklar.

---

## Authentication Flow (Yeni)

```
┌─────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION FLOW                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐   │
│  │   WELCOME   │────▶│  ONBOARDING │────▶│  REGISTER   │   │
│  │   SCREEN    │     │    FLOW     │     │   SCREEN    │   │
│  └─────────────┘     └─────────────┘     └─────────────┘   │
│        │                    │                    │          │
│        │                    │                    ▼          │
│        │              Collect:            POST /register    │
│        │              - name              - email           │
│        │              - type              - password        │
│        │              - city              - onboarding data │
│        │              - photo                    │          │
│        │              - social                   ▼          │
│        │                                   ┌─────────┐      │
│        │                                   │  HOME   │      │
│        │                                   │ SCREEN  │      │
│        │                                   └─────────┘      │
│        │                                        ▲           │
│        │                                        │           │
│        │         ┌─────────────────────────────┘           │
│        │         │                                          │
│        ▼         │                                          │
│  ┌─────────────┐ │                                          │
│  │   LOGIN     │─┘  POST /login                             │
│  │   SCREEN    │    - email                                 │
│  │             │    - password                              │
│  └─────────────┘                                            │
│        │                                                    │
│        ▼                                                    │
│  ┌─────────────┐                                            │
│  │   GOOGLE    │    POST /auth/google                       │
│  │   LOGIN     │    (existing users only)                   │
│  └─────────────┘                                            │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Registration Endpoints

### 1.1 Business User Registration

```
POST /api/v1/auth/register/business
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "restaurant@example.com",
  "password": "securePassword123",
  "password_confirmation": "securePassword123",
  "name": "Mi Restaurante",
  "about": "El mejor restaurante de tapas en Barcelona",
  "business_type": "restaurante",
  "city_id": "019bf6b3-1234-5678-abcd-123456789abc",
  "phone_number": "+34612345678",
  "instagram": "mirestaurante",
  "website": "https://mirestaurante.es",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

**Required Fields:**
| Field | Type | Validation |
|-------|------|------------|
| email | string | required, valid email, unique |
| password | string | required, min:8 characters |
| password_confirmation | string | required, must match password |
| name | string | required, max:255 |
| business_type | string | required, must exist in business_types |
| city_id | uuid | required, must exist in cities |

**Optional Fields:**
| Field | Type | Notes |
|-------|------|-------|
| about | string | Business description |
| phone_number | string | E.164 format recommended |
| instagram | string | Without @ symbol |
| website | string | Valid URL |
| profile_photo | string | Base64 or URL |

**Success Response (201):**
```json
{
  "success": true,
  "message": "Business registration successful",
  "data": {
    "token": "1|abc123xyz...",
    "token_type": "Bearer",
    "user": {
      "id": "019bf6b3-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "email": "restaurant@example.com",
      "user_type": "business",
      "phone_number": "+34612345678",
      "avatar_url": null,
      "onboarding_completed": true,
      "has_active_subscription": false,
      "business_profile": {
        "id": "019bf6b3-yyyy-yyyy-yyyy-yyyyyyyyyyyy",
        "name": "Mi Restaurante",
        "about": "El mejor restaurante...",
        "business_type": "restaurante",
        "city": {
          "id": "019bf6b3-1234-5678-abcd-123456789abc",
          "name": "Barcelona",
          "country": "Spain"
        },
        "instagram": "mirestaurante",
        "website": "https://mirestaurante.es",
        "profile_photo": "https://fls-xxx.laravel.cloud/profiles/xxx/photo.jpg"
      }
    }
  }
}
```

---

### 1.2 Community User Registration

```
POST /api/v1/auth/register/community
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "runner@example.com",
  "password": "securePassword123",
  "password_confirmation": "securePassword123",
  "name": "Barcelona Runners",
  "about": "Running community in Barcelona",
  "community_type": "running-club",
  "city_id": "019bf6b3-1234-5678-abcd-123456789abc",
  "phone_number": "+34612345678",
  "instagram": "bcnrunners",
  "tiktok": "bcnrunners",
  "website": "https://bcnrunners.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

**Required Fields:**
| Field | Type | Validation |
|-------|------|------------|
| email | string | required, valid email, unique |
| password | string | required, min:8 characters |
| password_confirmation | string | required, must match password |
| name | string | required, max:255 |
| community_type | string | required, must exist in community_types |
| city_id | uuid | required, must exist in cities |

**Optional Fields:**
| Field | Type | Notes |
|-------|------|-------|
| about | string | Community description |
| phone_number | string | E.164 format recommended |
| instagram | string | Without @ symbol |
| tiktok | string | Without @ symbol |
| website | string | Valid URL |
| profile_photo | string | Base64 or URL |

**Success Response (201):**
```json
{
  "success": true,
  "message": "Community registration successful",
  "data": {
    "token": "2|def456uvw...",
    "token_type": "Bearer",
    "user": {
      "id": "019bf6b3-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "email": "runner@example.com",
      "user_type": "community",
      "phone_number": "+34612345678",
      "avatar_url": null,
      "onboarding_completed": true,
      "community_profile": {
        "id": "019bf6b3-zzzz-zzzz-zzzz-zzzzzzzzzzzz",
        "name": "Barcelona Runners",
        "about": "Running community in Barcelona",
        "community_type": "running-club",
        "city": {
          "id": "019bf6b3-1234-5678-abcd-123456789abc",
          "name": "Barcelona",
          "country": "Spain"
        },
        "instagram": "bcnrunners",
        "tiktok": "bcnrunners",
        "website": "https://bcnrunners.com",
        "profile_photo": "https://fls-xxx.laravel.cloud/profiles/xxx/photo.jpg",
        "is_featured": false
      }
    }
  }
}
```

---

## 2. Login Endpoint

### 2.1 Email/Password Login

```
POST /api/v1/auth/login
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "3|ghi789rst...",
    "token_type": "Bearer",
    "user": {
      "id": "019bf6b3-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
      "email": "user@example.com",
      "user_type": "business",
      "onboarding_completed": true,
      "business_profile": { ... }
    }
  }
}
```

**Error: Invalid Credentials (401):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

**Error: Google-Only User (401):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": {
    "email": ["This account uses Google login. Please sign in with Google."]
  }
}
```

---

## 3. Google Login (Existing Users Only)

```
POST /api/v1/auth/google
Content-Type: application/json
```

**Request Body:**
```json
{
  "id_token": "google_oauth_id_token_here",
  "user_type": "business"
}
```

**Note:** Google login is for **existing users only**. New users must register via `/register/business` or `/register/community`.

---

## 4. Get Current User

```
GET /api/v1/auth/me
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "...",
    "email": "...",
    "user_type": "business",
    "onboarding_completed": true,
    ...
  }
}
```

---

## 5. Logout

```
POST /api/v1/auth/logout
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

## 6. Error Responses

### Validation Error (422):
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."],
    "business_type": ["The selected business type is invalid."]
  }
}
```

### Common Error Codes:
| Code | Meaning |
|------|---------|
| 400 | Bad Request (invalid data format) |
| 401 | Unauthorized (invalid credentials / no token) |
| 409 | Conflict (user type mismatch for Google login) |
| 422 | Validation Error |
| 500 | Server Error |

---

## 7. Mobile Implementation

### 7.1 Registration Flow (Flutter)

```dart
class AuthService {
  final String baseUrl = 'https://your-api.com/api/v1';

  Future<AuthResponse> registerBusiness({
    required String email,
    required String password,
    required String name,
    required String businessType,
    required String cityId,
    String? about,
    String? phoneNumber,
    String? instagram,
    String? website,
    String? profilePhotoBase64,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/register/business'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
        'password_confirmation': password,
        'name': name,
        'business_type': businessType,
        'city_id': cityId,
        if (about != null) 'about': about,
        if (phoneNumber != null) 'phone_number': phoneNumber,
        if (instagram != null) 'instagram': instagram,
        if (website != null) 'website': website,
        if (profilePhotoBase64 != null) 'profile_photo': profilePhotoBase64,
      }),
    );

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      // Save token
      await SecureStorage.write('token', data['data']['token']);
      return AuthResponse.fromJson(data);
    } else {
      throw ApiException.fromResponse(response);
    }
  }

  Future<AuthResponse> login({
    required String email,
    required String password,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      await SecureStorage.write('token', data['data']['token']);
      return AuthResponse.fromJson(data);
    } else {
      throw ApiException.fromResponse(response);
    }
  }
}
```

### 7.2 Token Storage

```dart
// Flutter Secure Storage kullanin
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SecureStorage {
  static const _storage = FlutterSecureStorage();

  static Future<void> write(String key, String value) async {
    await _storage.write(key: key, value: value);
  }

  static Future<String?> read(String key) async {
    return await _storage.read(key: key);
  }

  static Future<void> delete(String key) async {
    await _storage.delete(key: key);
  }
}
```

### 7.3 Authenticated Requests

```dart
Future<http.Response> authenticatedRequest(
  String method,
  String endpoint,
  {Map<String, dynamic>? body}
) async {
  final token = await SecureStorage.read('token');

  final headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (token != null) 'Authorization': 'Bearer $token',
  };

  // ... make request with headers
}
```

---

## 8. Complete Onboarding + Registration Flow

```
1. User selects: Business or Community
      ↓
2. Onboarding screens collect:
   - Name
   - Type (business_type or community_type)
   - City
   - About (optional)
   - Social links (optional)
   - Profile photo (optional)
      ↓
3. Final screen collects:
   - Email
   - Password
   - Password confirmation
      ↓
4. Submit to:
   POST /api/v1/auth/register/business
   OR
   POST /api/v1/auth/register/community
      ↓
5. On success:
   - Store token in secure storage
   - Navigate to home screen
      ↓
6. On error:
   - Show validation errors
   - Allow user to correct and retry
```

---

## 9. API Endpoints Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/auth/register/business` | No | Register business user |
| POST | `/api/v1/auth/register/community` | No | Register community user |
| POST | `/api/v1/auth/login` | No | Login with email/password |
| POST | `/api/v1/auth/google` | No | Login with Google (existing users) |
| GET | `/api/v1/auth/me` | Yes | Get current user |
| POST | `/api/v1/auth/logout` | Yes | Logout |

---

## 10. Password Requirements

- Minimum 8 characters
- Must match password_confirmation field
- Stored securely with bcrypt hashing
