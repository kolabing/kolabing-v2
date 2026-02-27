# Task: firebase-push-notifications

## Status
- Created: 2026-02-27 11:00
- Started: 2026-02-27 11:00
- Completed: (updated when moved to done)

## Description
Firebase FCM push notification sistemi implementasyonu:
1. kreait/laravel-firebase kurulumu + konfigürasyon
2. PushNotificationService (FCM gönderici)
3. SendPushNotification queued job
4. NotificationService entegrasyonu (DB kayıt + push aynı anda)
5. Mobile implementation documentation

## Assigned Agents
- [x] @laravel-specialist

## Progress
### Backend
- [ ] kreait/laravel-firebase install
- [ ] Firebase config
- [ ] PushNotificationService
- [ ] SendPushNotification Job
- [ ] NotificationService integration
- [ ] Tests

### Documentation
- [ ] Mobile implementation guide (docs/firebase-push-notifications-mobile.md)

## Notes
- NotificationType → Flutter route mapping:
  new_message → /chat/{application_id}
  application_* → /application/{id}
  badge_awarded → /badges
  challenge_verified / reward_won → /me/rewards
