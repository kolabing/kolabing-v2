# Task: Business Profile & Subscription Management API

## Status
- Created: 2026-01-25 23:40
- Started: 2026-01-25 23:41
- Completed: 2026-01-26 00:05

## Description
Web'deki profile sayfası referans alınarak business kullanıcılar için:
1. Profile görüntüleme ve düzenleme API'leri
2. Subscription yönetimi API'leri
3. Notification preferences API'leri
4. Account yönetimi (delete account) API'leri

## Assigned Agents
- [x] @api-designer (API contract)
- [x] @database-planner (notification preferences schema)
- [x] @laravel-specialist (implementation)
- [x] @backend-developer (service layer)

## Progress

### API Contract ✅
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/me/profile` | Get full profile with subscription |
| PUT | `/api/v1/me/profile` | Update profile |
| DELETE | `/api/v1/me/account` | Soft delete account |
| GET | `/api/v1/me/notification-preferences` | Get preferences |
| PUT | `/api/v1/me/notification-preferences` | Update preferences |
| GET | `/api/v1/me/subscription` | Get subscription (business only) |
| POST | `/api/v1/me/subscription/checkout` | Create Stripe checkout |
| GET | `/api/v1/me/subscription/portal` | Get billing portal URL |
| POST | `/api/v1/me/subscription/cancel` | Cancel subscription |

### Database Changes ✅
- Migration: `create_notification_preferences_table.php`
  - profile_id (unique FK)
  - email_notifications, whatsapp_notifications
  - new_application_alerts, collaboration_updates
  - marketing_tips (all boolean)
- Migration: `add_deleted_at_to_profiles_table.php` (soft delete)

### Backend Implementation ✅

**Files Created:**
- `app/Http/Controllers/Api/V1/ProfileController.php`
- `app/Http/Controllers/Api/V1/NotificationPreferenceController.php`
- `app/Http/Controllers/Api/V1/SubscriptionController.php`
- `app/Models/NotificationPreference.php`
- `app/Services/SubscriptionService.php`
- `app/Http/Requests/Api/V1/UpdateProfileRequest.php`
- `app/Http/Requests/Api/V1/UpdateNotificationPreferencesRequest.php`
- `app/Http/Requests/Api/V1/CreateCheckoutSessionRequest.php`
- `app/Http/Resources/Api/V1/ProfileResource.php`
- `app/Http/Resources/Api/V1/NotificationPreferenceResource.php`
- `app/Http/Resources/Api/V1/SubscriptionResource.php`
- `database/factories/NotificationPreferenceFactory.php`

**Files Modified:**
- `app/Models/Profile.php` (SoftDeletes, notificationPreference relation)
- `app/Services/ProfileService.php` (new methods)
- `routes/api.php` (new routes)

### Documentation ✅
- Created: `.agent/documentations/mobile-profile-subscription-guide.md`
- Flutter code examples included
- Deep link handling for Stripe callbacks

## Test Results
- Profile tests: 14 passed
- Notification tests: 9 passed
- Subscription tests: 24 passed
- **Total: 47 new tests passing**

## Notes
- MVP'de credit sistemi yok, sadece monthly subscription
- Invitation codes Phase 2
- Stripe integration placeholder (real URLs after Stripe setup)
- Soft delete for account deletion with token revocation
