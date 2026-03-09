# Mobile Push Notifications API Guide (Firebase FCM)

## Overview

Kolabing uses Firebase Cloud Messaging (FCM) to deliver real-time push notifications to mobile devices. The system operates end-to-end as follows:

1. The mobile app obtains an FCM device token from Firebase SDK on startup.
2. The app sends the token to the backend via `POST /api/v1/me/device-token`.
3. The backend stores `device_token` and `device_platform` on the authenticated profile.
4. When a notifiable event occurs (new message, application accepted, badge awarded, etc.), the backend:
   - Creates a `Notification` database record.
   - Dispatches a `SendPushNotification` queued job if the recipient has a `device_token`.
5. The queued job sends the FCM message via `kreait/laravel-firebase` with both a visible notification (title + body) and a data payload (`type` + `id`).
6. The mobile app receives the push, reads the `type` and `id` from the data payload, and navigates the user to the appropriate screen.

If the FCM token is expired or unregistered, the backend automatically clears `device_token` and `device_platform` from the profile so no further retries are wasted.

---

## Backend Setup Notes (for DevOps / Backend Team)

### Environment Variables

| Variable | Description | Example |
|---|---|---|
| `FIREBASE_CREDENTIALS` | Absolute path to Firebase service account JSON file | `/var/www/kolabing/firebase-service-account.json` |
| `FIREBASE_PROJECT` | Firebase project identifier (optional, defaults to `app`) | `app` |

### Service Account JSON

1. Go to Firebase Console -> Project Settings -> Service Accounts.
2. Click "Generate new private key" and download the JSON file.
3. Place the file on the server at a secure location (not in the web root).
4. Set `FIREBASE_CREDENTIALS=/path/to/firebase-service-account.json` in `.env`.
5. Ensure the file is readable by the web server user but NOT publicly accessible.

### Queue Worker

Push notifications are dispatched as queued jobs with 3 retries and 10-second backoff. Ensure a queue worker is running:

```bash
php artisan queue:work --tries=3 --backoff=10
```

---

## Device Token Registration

### Endpoint

```
POST /api/v1/me/device-token
```

**Authentication:** Bearer token required (Sanctum).

### Request Body

```json
{
  "token": "fcm-device-token-string-from-firebase-sdk",
  "platform": "ios"
}
```

| Field | Type | Required | Validation |
|---|---|---|---|
| `token` | string | Yes | The FCM registration token obtained from the Firebase SDK |
| `platform` | string | Yes | Must be `"ios"` or `"android"` |

### Success Response (200)

```json
{
  "success": true,
  "message": "Device token registered successfully"
}
```

### Validation Error Response (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "token": ["The token field is required."],
    "platform": ["The selected platform is invalid."]
  }
}
```

### When to Call This Endpoint

- On every app launch after the user is authenticated.
- When the FCM SDK fires an `onTokenRefresh` callback (tokens can rotate at any time).
- After login or re-authentication.

### Token Lifecycle

- The backend stores one token per profile. Sending a new token overwrites the previous one.
- If a push delivery fails with `UNREGISTERED` or `INVALID_ARGUMENT`, the backend automatically sets `device_token = null` and `device_platform = null` on the profile. The next app launch will re-register a fresh token.

---

## Notification Data Payload Structure

Every FCM message sent by the backend contains two parts:

### 1. Notification (visible)

Displayed by the OS notification tray when the app is in the background or terminated.

```json
{
  "title": "New Application",
  "body": "Community X applied to your \"Summer Event\" opportunity."
}
```

### 2. Data Payload (for navigation)

Always present. Used by the app to determine where to navigate when the user taps the notification.

```json
{
  "type": "application_received",
  "id": "550e8400-e29b-41d4-a716-446655440000"
}
```

| Field | Type | Description |
|---|---|---|
| `type` | string | One of the `NotificationType` enum values (see reference table below) |
| `id` | string | UUID of the target entity. The meaning depends on `type` (see route mapping below) |

---

## Notification Types Reference Table

| Type Value | Title | Description | `id` refers to | Flutter Route |
|---|---|---|---|---|
| `new_message` | New Message | A new chat message was received in an application conversation | `application_id` (UUID) | `/chat/{id}` |
| `application_received` | New Application | Someone applied to your opportunity | `application_id` (UUID) | `/application/{id}` |
| `application_accepted` | Application Accepted | Your application was accepted by the opportunity owner | `application_id` (UUID) | `/application/{id}` |
| `application_declined` | Application Declined | Your application was declined | `application_id` (UUID) | `/application/{id}` |
| `badge_awarded` | Badge Awarded | You earned a new badge | `badge_id` (UUID) | `/badges` |
| `challenge_verified` | Challenge Verified | Your challenge completion was verified and points awarded | `challenge_completion_id` (UUID) | `/me/rewards` |
| `reward_won` | Reward Won | You won a reward from spin-the-wheel | `reward_claim_id` (UUID) | `/me/rewards` |

---

## Flutter Implementation Example

### 1. Add Dependencies

```yaml
# pubspec.yaml
dependencies:
  firebase_core: ^3.12.0
  firebase_messaging: ^15.2.0
```

### 2. Initialize Firebase and Request Permissions

```dart
// main.dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

// Top-level background message handler (must be a top-level function)
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Handle background message — typically just store or log.
  // Navigation should happen when the user taps the notification.
  print('Background message: ${message.data}');
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  // Register background handler
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  runApp(const KolabingApp());
}
```

### 3. FCM Token Registration Service

```dart
// services/push_notification_service.dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'dart:io' show Platform;

class PushNotificationService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  /// Initialize push notifications: request permissions, get token, listen for refresh.
  Future<void> initialize({
    required Future<void> Function(String token, String platform) onTokenReady,
  }) async {
    // 1. Request permission (critical for iOS, no-op on Android 12 and below)
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      print('Push notifications denied by user');
      return;
    }

    // 2. Get current token
    final token = await _messaging.getToken();
    if (token != null) {
      final platform = Platform.isIOS ? 'ios' : 'android';
      await onTokenReady(token, platform);
    }

    // 3. Listen for token refresh
    _messaging.onTokenRefresh.listen((newToken) {
      final platform = Platform.isIOS ? 'ios' : 'android';
      onTokenReady(newToken, platform);
    });
  }
}
```

### 4. Send Token to Backend

```dart
// Call this after user authentication succeeds
import 'package:http/http.dart' as http;
import 'dart:convert';

Future<void> registerDeviceToken(String token, String platform) async {
  final response = await http.post(
    Uri.parse('https://your-api-domain.com/api/v1/me/device-token'),
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer $authToken',
    },
    body: jsonEncode({
      'token': token,
      'platform': platform,
    }),
  );

  if (response.statusCode == 200) {
    print('Device token registered');
  } else {
    print('Failed to register device token: ${response.body}');
  }
}
```

### 5. Handle Notifications (Foreground, Background Tap, Terminated Tap)

```dart
// notification_handler.dart
import 'package:firebase_messaging/firebase_messaging.dart';

class NotificationHandler {
  final GlobalKey<NavigatorState> navigatorKey;

  NotificationHandler(this.navigatorKey);

  void setupListeners() {
    // --- FOREGROUND: App is open and in the foreground ---
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      // Show an in-app banner or snackbar instead of a system notification.
      // The system notification tray does NOT auto-display foreground messages.
      _showInAppNotification(message);
    });

    // --- BACKGROUND TAP: User tapped notification while app was in background ---
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      _navigateFromNotification(message.data);
    });

    // --- TERMINATED TAP: App was killed, user tapped notification to open it ---
    _handleInitialMessage();
  }

  Future<void> _handleInitialMessage() async {
    final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) {
      // Small delay to allow the app to finish building the widget tree
      await Future.delayed(const Duration(milliseconds: 500));
      _navigateFromNotification(initialMessage.data);
    }
  }

  /// Route navigation based on notification type and id.
  void _navigateFromNotification(Map<String, dynamic> data) {
    final String? type = data['type'];
    final String? id = data['id'];

    if (type == null) return;

    switch (type) {
      case 'new_message':
        if (id != null) navigatorKey.currentState?.pushNamed('/chat/$id');
        break;
      case 'application_received':
      case 'application_accepted':
      case 'application_declined':
        if (id != null) navigatorKey.currentState?.pushNamed('/application/$id');
        break;
      case 'badge_awarded':
        navigatorKey.currentState?.pushNamed('/badges');
        break;
      case 'challenge_verified':
      case 'reward_won':
        navigatorKey.currentState?.pushNamed('/me/rewards');
        break;
      default:
        // Unknown type — navigate to notifications list as fallback
        navigatorKey.currentState?.pushNamed('/me/notifications');
    }
  }

  void _showInAppNotification(RemoteMessage message) {
    // Implementation depends on your UI framework.
    // Example: show a Material banner or overlay widget with title + body.
    final notification = message.notification;
    if (notification != null) {
      // Show banner with notification.title and notification.body
      // Tap action should call _navigateFromNotification(message.data)
    }
  }
}
```

### 6. Wire Everything Together

```dart
// In your main app widget or auth state handler
class _KolabingAppState extends State<KolabingApp> {
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  final _pushService = PushNotificationService();
  late final NotificationHandler _notificationHandler;

  @override
  void initState() {
    super.initState();
    _notificationHandler = NotificationHandler(_navigatorKey);
    _notificationHandler.setupListeners();
  }

  /// Call this after successful login
  Future<void> onLoginSuccess(String authToken) async {
    await _pushService.initialize(
      onTokenReady: (token, platform) async {
        await registerDeviceToken(token, platform);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      navigatorKey: _navigatorKey,
      // ... routes, theme, etc.
    );
  }
}
```

---

## Swift (iOS) Implementation Example

### 1. Prerequisites

- Enable "Push Notifications" capability in Xcode (Signing & Capabilities tab).
- Enable "Background Modes" -> "Remote notifications" in Xcode.
- Upload your APNs Authentication Key (.p8) to Firebase Console -> Project Settings -> Cloud Messaging -> Apple app configuration.

### 2. Add Firebase SDK

```swift
// Package.swift or via CocoaPods/SPM
// SPM: https://github.com/firebase/firebase-ios-sdk
// Add FirebaseMessaging product
```

### 3. Configure in AppDelegate

```swift
// AppDelegate.swift
import UIKit
import FirebaseCore
import FirebaseMessaging
import UserNotifications

@main
class AppDelegate: UIResponder, UIApplicationDelegate, UNUserNotificationCenterDelegate, MessagingDelegate {

    func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {
        FirebaseApp.configure()

        // Set delegates
        UNUserNotificationCenter.current().delegate = self
        Messaging.messaging().delegate = self

        // Request notification permission
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

    // MARK: - APNs Token Registration

    func application(
        _ application: UIApplication,
        didRegisterForRemoteNotificationsWithDeviceToken deviceToken: Data
    ) {
        // Pass the APNs token to Firebase — FCM maps it to an FCM token
        Messaging.messaging().apnsToken = deviceToken
    }

    // MARK: - FCM Token Refresh

    func messaging(_ messaging: Messaging, didReceiveRegistrationToken fcmToken: String?) {
        guard let token = fcmToken else { return }
        print("FCM token: \(token)")
        // Send to your backend
        registerDeviceToken(token: token, platform: "ios")
    }

    // MARK: - Foreground Notification Display

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        // Show banner even when app is in foreground
        completionHandler([.banner, .sound, .badge])
    }

    // MARK: - Notification Tap Handler

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        let userInfo = response.notification.request.content.userInfo
        handleNotificationNavigation(userInfo: userInfo)
        completionHandler()
    }
}
```

### 4. Send Token to Backend

```swift
func registerDeviceToken(token: String, platform: String) {
    guard let url = URL(string: "https://your-api-domain.com/api/v1/me/device-token") else { return }
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.setValue("application/json", forHTTPHeaderField: "Accept")
    request.setValue("Bearer \(authToken)", forHTTPHeaderField: "Authorization")

    let body: [String: Any] = [
        "token": token,
        "platform": platform
    ]
    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    URLSession.shared.dataTask(with: request) { data, response, error in
        if let httpResponse = response as? HTTPURLResponse, httpResponse.statusCode == 200 {
            print("Device token registered successfully")
        } else {
            print("Failed to register device token: \(error?.localizedDescription ?? "unknown error")")
        }
    }.resume()
}
```

### 5. Navigation Based on Notification Type

```swift
func handleNotificationNavigation(userInfo: [AnyHashable: Any]) {
    guard let type = userInfo["type"] as? String else { return }
    let id = userInfo["id"] as? String

    switch type {
    case "new_message":
        if let id = id {
            // Navigate to chat screen with application ID
            NavigationManager.shared.navigate(to: .chat(applicationId: id))
        }
    case "application_received", "application_accepted", "application_declined":
        if let id = id {
            NavigationManager.shared.navigate(to: .application(id: id))
        }
    case "badge_awarded":
        NavigationManager.shared.navigate(to: .badges)
    case "challenge_verified", "reward_won":
        NavigationManager.shared.navigate(to: .rewards)
    default:
        NavigationManager.shared.navigate(to: .notifications)
    }
}
```

---

## Important Notes

### Invalid Token Auto-Cleanup

The backend automatically handles stale FCM tokens. When a push delivery fails with one of the following FCM errors, the profile's `device_token` and `device_platform` are set to `null`:

- `UNREGISTERED` -- the app was uninstalled or the token was invalidated.
- `INVALID_ARGUMENT` -- the token format is malformed.
- `registration-token-not-registered` -- the token is no longer valid.

This means the mobile app does not need to handle token revocation explicitly. Simply re-register the token on each app launch and on every `onTokenRefresh` callback.

### Retry Policy

- Push notifications are dispatched as **queued jobs** (`SendPushNotification`).
- Maximum retries: **3 attempts**.
- Backoff between retries: **10 seconds**.
- If all 3 attempts fail, the job is discarded (moved to `failed_jobs` table if configured).

### Testing with Log Driver

During development, you can test the notification flow without a real Firebase project by setting the log driver:

```env
# .env (development only)
LOG_CHANNEL=stack
FIREBASE_HTTP_LOG_CHANNEL=daily
```

This logs all FCM HTTP requests to `storage/logs/` so you can verify payloads without sending real push notifications.

To verify a notification is dispatched, check `storage/logs/laravel.log` for entries like:
```
[INFO] FCM: cleared invalid device token {"profile_id":"...","error":"..."}
```

You can also test the device token endpoint independently:

```bash
curl -X POST http://localhost/api/v1/me/device-token \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"token": "test-fcm-token", "platform": "ios"}'
```

### Notification Preferences

Users can manage their notification preferences via:

- `GET /api/v1/me/notification-preferences` -- retrieve current preferences.
- `PUT /api/v1/me/notification-preferences` -- update preferences.

The backend respects these preferences before sending push notifications. Check the notification preferences API documentation for details.

### Multiple Devices

The current implementation supports **one device token per profile**. If a user logs in on a second device, the new token overwrites the previous one. Only the most recently registered device will receive push notifications.

### Clearing Token on Logout

When a user logs out, the mobile app should clear the device token from the backend. This can be done by sending a request with an empty or null token before calling the logout endpoint. Alternatively, the backend logout endpoint may handle this automatically -- verify with the backend team.

### Firebase Project Configuration (Mobile)

Both iOS and Android apps must be registered in the same Firebase project that the backend uses:

- **Android:** Add `google-services.json` to `android/app/`.
- **iOS:** Add `GoogleService-Info.plist` to the Xcode project.
- Download these from Firebase Console -> Project Settings -> Your Apps.
