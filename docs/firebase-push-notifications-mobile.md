# Firebase Push Notifications (FCM) — Flutter Integration Guide

**Platform:** Flutter (iOS & Android)
**Backend:** Kolabing Laravel API v1
**Last Updated:** 2026-02-27

---

## Table of Contents

1. [Backend Context](#1-backend-context)
2. [Firebase Project Setup](#2-firebase-project-setup)
3. [Flutter Package Setup](#3-flutter-package-setup)
4. [Android Configuration](#4-android-configuration)
5. [iOS Configuration](#5-ios-configuration)
6. [FCM Service Class](#6-fcm-service-class)
7. [App Lifecycle Integration](#7-app-lifecycle-integration)
8. [Foreground Notification Display](#8-foreground-notification-display)
9. [Background / Terminated Handler](#9-background--terminated-handler)
10. [Deep Link Navigation with GoRouter](#10-deep-link-navigation-with-gorouter)
11. [Token Lifecycle](#11-token-lifecycle)
12. [Testing](#12-testing)

---

## 1. Backend Context

Backend zaten tam anlamıyla implemente edilmiş durumda. Flutter tarafının yapması gereken tek şey doğru entegrasyonu kurmak.

### Device Token Endpoint

```
POST /api/v1/me/device-token
Authorization: Bearer <sanctum-token>
Content-Type: application/json

{
  "token": "<fcm-token>",
  "platform": "ios" | "android"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Device token registered successfully"
}
```

### Bilmeniz Gerekenler

- Her login sonrası FCM token backend'e kaydedilmelidir.
- Backend, her in-app notification oluştururken otomatik olarak FCM push da gönderir (asenkron, kuyruk ile).
- Token süresi dolmuş veya geçersizse backend otomatik olarak temizler — Flutter tarafında bunu takip etmenize gerek yok.
- FCM Data Payload formatı (backend'den gelen):

```json
{
  "type": "new_message",
  "id": "uuid-of-target-entity"
}
```

### Notification Type → Flutter Route Mapping

| `type` değeri | Flutter route |
|---|---|
| `new_message` | `/chat/:application_id` |
| `application_received` | `/application/:id` |
| `application_accepted` | `/application/:id` |
| `application_declined` | `/application/:id` |
| `badge_awarded` | `/badges` |
| `challenge_verified` | `/me/rewards` |
| `reward_won` | `/me/rewards` |

---

## 2. Firebase Project Setup

### 2.1 Firebase Console

1. [Firebase Console](https://console.firebase.google.com)'a gidin.
2. Kolabing projesini seçin (veya yeni proje oluşturun).
3. **Project Settings → Your apps** kısmından Android ve iOS app'lerini ekleyin.

### 2.2 FlutterFire CLI ile Otomatik Konfigürasyon (Önerilen)

```bash
# FlutterFire CLI kur
dart pub global activate flutterfire_cli

# Firebase projesine bağla (flutter projesinin root'unda çalıştır)
flutterfire configure --project=<your-firebase-project-id>
```

Bu komut otomatik olarak şunları yapar:
- `lib/firebase_options.dart` oluşturur
- `android/app/google-services.json` oluşturur
- `ios/Runner/GoogleService-Info.plist` oluşturur

### 2.3 Manuel Kurulum (FlutterFire CLI kullanmıyorsanız)

**Android:** Firebase Console'dan `google-services.json` indirin ve `android/app/` klasörüne yerleştirin.

**iOS:** Firebase Console'dan `GoogleService-Info.plist` indirin ve Xcode'da `Runner` target'ına ekleyin (dosyayı sürükleyip bırakın, "Copy items if needed" işaretleyin).

---

## 3. Flutter Package Setup

### pubspec.yaml

```yaml
dependencies:
  flutter:
    sdk: flutter

  # Firebase
  firebase_core: ^3.6.0
  firebase_messaging: ^15.1.3

  # Local notifications (foreground'da bildirim göstermek için)
  flutter_local_notifications: ^18.0.1

  # HTTP (backend ile iletişim)
  dio: ^5.7.0

  # Navigation
  go_router: ^14.3.0
```

```bash
flutter pub get
```

---

## 4. Android Configuration

### 4.1 android/build.gradle (project-level)

```gradle
buildscript {
    dependencies {
        // Google Services plugin
        classpath 'com.google.gms:google-services:4.4.2'
    }
}
```

### 4.2 android/app/build.gradle (app-level)

```gradle
plugins {
    id 'com.android.application'
    id 'com.google.gms.google-services' // En alta ekle
}

android {
    compileSdkVersion 34
    defaultConfig {
        minSdkVersion 21  // FCM için minimum 21
        targetSdkVersion 34
    }
}
```

### 4.3 android/app/src/main/AndroidManifest.xml

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">

    <!-- İnternet izni (zaten olmalı) -->
    <uses-permission android:name="android.permission.INTERNET"/>

    <!-- Android 13+ için bildirim izni -->
    <uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>

    <application
        android:label="Kolabing"
        android:icon="@mipmap/ic_launcher"
        android:enableOnBackInvokedCallback="true">

        <!-- FCM Default Notification Channel (Android 8+) -->
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_channel_id"
            android:value="kolabing_default"/>

        <!-- FCM Default Notification Icon -->
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_icon"
            android:resource="@drawable/ic_notification"/>

        <!-- FCM Default Notification Color -->
        <meta-data
            android:name="com.google.firebase.messaging.default_notification_color"
            android:resource="@color/notification_color"/>

        <activity
            android:name=".MainActivity"
            android:exported="true"
            android:launchMode="singleTop"
            android:theme="@style/LaunchTheme">
            <intent-filter>
                <action android:name="android.intent.action.MAIN"/>
                <category android:name="android.intent.category.LAUNCHER"/>
            </intent-filter>
        </activity>

        <!-- FCM Background Handler için gerekli -->
        <service
            android:name="com.google.firebase.messaging.FirebaseMessagingService"
            android:exported="false">
            <intent-filter>
                <action android:name="com.google.firebase.MESSAGING_EVENT"/>
            </intent-filter>
        </service>

    </application>
</manifest>
```

### 4.4 Notification Channel Oluşturma

`android/app/src/main/res/values/strings.xml` dosyasına ekleyin:

```xml
<resources>
    <string name="app_name">Kolabing</string>
</resources>
```

`android/app/src/main/res/values/colors.xml` dosyasını oluşturun:

```xml
<?xml version="1.0" encoding="utf-8"?>
<resources>
    <color name="notification_color">#6B4EFF</color>
</resources>
```

---

## 5. iOS Configuration

### 5.1 Xcode'da Push Notification Capability Ekle

1. Xcode'da `Runner.xcworkspace` dosyasını açın.
2. `Runner` target'ını seçin → **Signing & Capabilities** sekmesi.
3. **+ Capability** butonuna tıklayın → **Push Notifications** ekleyin.
4. Yine aynı yerden **Background Modes** ekleyin → **Remote notifications** işaretleyin.

### 5.2 ios/Runner/Info.plist

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "...">
<plist version="1.0">
<dict>
    <!-- Mevcut key'lerin yanına ekleyin -->

    <!-- Kullanıcıya gösterilecek izin açıklamaları -->
    <key>NSUserNotificationsUsageDescription</key>
    <string>Kolabing, yeni mesajlar ve işbirliği güncellemeleri için bildirim göndermek ister.</string>

    <!-- Background fetch için -->
    <key>UIBackgroundModes</key>
    <array>
        <string>fetch</string>
        <string>remote-notification</string>
    </array>

</dict>
</plist>
```

### 5.3 ios/Runner/AppDelegate.swift

```swift
import UIKit
import Flutter
import FirebaseCore

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    FirebaseApp.configure()

    // flutter_local_notifications için gerekli
    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self as? UNUserNotificationCenterDelegate
    }

    GeneratedPluginRegistrant.register(with: self)
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
```

---

## 6. FCM Service Class

Bu servis sınıfı tüm FCM mantığını tek bir yerde toplar.

### lib/services/fcm_service.dart

```dart
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../api/api_client.dart'; // Kendi Dio client'ınız

/// FCM token ve mesaj yönetimi için servis sınıfı.
///
/// Kullanım:
///   await FCMService.instance.initialize();
///   await FCMService.instance.registerTokenWithBackend(sanctumToken);
class FCMService {
  FCMService._();
  static final FCMService instance = FCMService._();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();

  /// GoRouter ile navigation yapabilmek için dışarıdan set edilir.
  /// Bkz: [AppLifecycleIntegration]
  Function(String type, String? id)? onNotificationTap;

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------

  /// Uygulama başlangıcında bir kez çağrılır.
  /// Foreground, background ve terminated handler'larını kurar.
  Future<void> initialize() async {
    await _setupLocalNotifications();
    await _setupForegroundHandler();
    _setupBackgroundHandlers();
    await _checkInitialMessage();
  }

  /// Kullanıcıdan bildirim izni ister.
  /// iOS'ta dialog gösterir. Android 13+ için de gereklidir.
  Future<bool> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    final granted =
        settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;

    debugPrint(
      'FCM: permission status = ${settings.authorizationStatus}, granted = $granted',
    );

    return granted;
  }

  /// Cihaza ait FCM token'ı döner.
  /// Token yoksa ya da hata olursa null döner.
  Future<String?> getToken() async {
    try {
      final token = await _messaging.getToken();
      debugPrint('FCM: token = $token');
      return token;
    } catch (e) {
      debugPrint('FCM: getToken error = $e');
      return null;
    }
  }

  /// FCM token'ı Kolabing backend'ine kaydeder.
  ///
  /// Login sonrası hemen çağrılmalıdır.
  /// [sanctumToken]: Bearer token (login response'dan alınan)
  Future<void> registerTokenWithBackend(String sanctumToken) async {
    final token = await getToken();
    if (token == null) {
      debugPrint('FCM: no token available, skipping registration');
      return;
    }

    final platform = Platform.isIOS ? 'ios' : 'android';

    try {
      await ApiClient.instance.post(
        '/api/v1/me/device-token',
        data: {
          'token': token,
          'platform': platform,
        },
        options: Options(
          headers: {'Authorization': 'Bearer $sanctumToken'},
        ),
      );
      debugPrint('FCM: token registered with backend (platform=$platform)');
    } catch (e) {
      // Non-fatal: backend token'ı bir sonraki login'de yeniden alacak.
      debugPrint('FCM: failed to register token with backend: $e');
    }
  }

  /// Token yenilendiğinde backend'e otomatik güncelleme gönderir.
  ///
  /// [sanctumToken] getter: token değiştiğinde güncel Sanctum token'ı almak için.
  void listenTokenRefresh(String Function() getSanctumToken) {
    _messaging.onTokenRefresh.listen((newToken) async {
      debugPrint('FCM: token refreshed');
      final sanctumToken = getSanctumToken();
      if (sanctumToken.isNotEmpty) {
        await registerTokenWithBackend(sanctumToken);
      }
    });
  }

  // ---------------------------------------------------------------------------
  // Private Setup
  // ---------------------------------------------------------------------------

  Future<void> _setupLocalNotifications() async {
    const androidSettings = AndroidInitializationSettings('@drawable/ic_notification');

    final iosSettings = DarwinInitializationSettings(
      requestAlertPermission: false, // Biz manuel requestPermission() çağırıyoruz
      requestBadgePermission: false,
      requestSoundPermission: false,
      onDidReceiveLocalNotification: _onDidReceiveLocalNotification,
    );

    await _localNotifications.initialize(
      InitializationSettings(android: androidSettings, iOS: iosSettings),
      onDidReceiveNotificationResponse: _onLocalNotificationTap,
      onDidReceiveBackgroundNotificationResponse: _onBackgroundLocalNotificationTap,
    );

    // Android 8+ için notification channel oluştur
    await _createNotificationChannel();
  }

  Future<void> _createNotificationChannel() async {
    const channel = AndroidNotificationChannel(
      'kolabing_default',
      'Kolabing Notifications',
      description: 'Kolabing uygulaması bildirimleri',
      importance: Importance.high,
      playSound: true,
    );

    await _localNotifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  Future<void> _setupForegroundHandler() async {
    // Uygulama açıkken gelen mesajları yakala
    FirebaseMessaging.onMessage.listen(_onForegroundMessage);

    // iOS'ta foreground'da da bildirim göster
    await _messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  void _setupBackgroundHandlers() {
    // Uygulama arka planda iken bildirime tıklanınca
    FirebaseMessaging.onMessageOpenedApp.listen(_handleMessage);
  }

  /// Uygulama tamamen kapalıyken gelen bildirime tıklanıp app açıldığında.
  Future<void> _checkInitialMessage() async {
    final initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      // Splash/init tamamlanana kadar bekle, sonra navigate et
      Future.delayed(const Duration(milliseconds: 500), () {
        _handleMessage(initialMessage);
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Message Handlers
  // ---------------------------------------------------------------------------

  /// Foreground'da gelen mesajı local notification olarak gösterir.
  Future<void> _onForegroundMessage(RemoteMessage message) async {
    debugPrint('FCM: foreground message received: ${message.data}');

    final notification = message.notification;
    if (notification == null) return;

    await _localNotifications.show(
      message.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          'kolabing_default',
          'Kolabing Notifications',
          channelDescription: 'Kolabing uygulaması bildirimleri',
          importance: Importance.high,
          priority: Priority.high,
          icon: '@drawable/ic_notification',
          color: const Color(0xFF6B4EFF),
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
      // Tıklanınca navigate için payload olarak data'yı encode et
      payload: '${message.data['type']}|${message.data['id'] ?? ''}',
    );
  }

  /// Notification'a tıklandığında yönlendirme yapar.
  /// [RemoteMessage.data] içindeki `type` ve `id` kullanılır.
  void _handleMessage(RemoteMessage message) {
    final type = message.data['type'] as String?;
    final id = message.data['id'] as String?;

    debugPrint('FCM: handling message tap type=$type id=$id');

    if (type != null && onNotificationTap != null) {
      onNotificationTap!(type, id?.isNotEmpty == true ? id : null);
    }
  }

  // Local notification'a tıklanınca (foreground notification)
  void _onLocalNotificationTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload == null) return;

    final parts = payload.split('|');
    final type = parts.isNotEmpty ? parts[0] : null;
    final id = parts.length > 1 && parts[1].isNotEmpty ? parts[1] : null;

    debugPrint('FCM: local notification tap type=$type id=$id');

    if (type != null && onNotificationTap != null) {
      onNotificationTap!(type, id);
    }
  }

  // iOS'ta eski API için (iOS < 10)
  void _onDidReceiveLocalNotification(
    int id,
    String? title,
    String? body,
    String? payload,
  ) {
    // iOS 10+ kullanılıyor, bu callback artık çağrılmaz
  }
}

/// Background'da local notification'a tıklanınca çağrılan top-level function.
/// Dart isolate sınırlaması nedeniyle class method olamaz.
@pragma('vm:entry-point')
void _onBackgroundLocalNotificationTap(NotificationResponse response) {
  // Background'da navigation doğrudan yapılamaz.
  // _checkInitialMessage ile app açıldığında işlenir.
  debugPrint('FCM: background local notification tapped: ${response.payload}');
}
```

---

## 7. App Lifecycle Integration

### lib/main.dart

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';

import 'firebase_options.dart'; // FlutterFire CLI tarafından oluşturulur
import 'router/app_router.dart';
import 'services/fcm_service.dart';

/// Background mesajları işlemek için top-level function.
/// Bu function mutlaka @pragma('vm:entry-point') ile işaretlenmeli.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Background'da Firebase'in başlatılması gerekebilir
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

  debugPrint('FCM: background message received: ${message.messageId}');
  // Burada sadece veri işleme yapılabilir, UI güncellenemez.
  // Navigation açıldığında onMessageOpenedApp veya getInitialMessage ile yapılır.
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Firebase'i başlat
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  // Background handler'ı kaydet (Firebase.initializeApp'dan ÖNCE değil, SONRA)
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  // FCM servisini başlat
  await FCMService.instance.initialize();

  runApp(const KolabingApp());
}

class KolabingApp extends StatelessWidget {
  const KolabingApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'Kolabing',
      routerConfig: AppRouter.router,
    );
  }
}
```

### Login Sonrası Token Kaydı

Her login metodunun (Google, Apple, email) tamamlandığı yerde şu pattern uygulanır:

```dart
// lib/features/auth/auth_controller.dart (veya BLoC/Provider)

class AuthController {
  final AuthService _authService;
  final FCMService _fcmService = FCMService.instance;

  AuthController(this._authService);

  /// Google ile giriş
  Future<void> signInWithGoogle() async {
    try {
      final response = await _authService.googleSignIn();
      final sanctumToken = response.token;

      // 1. Token'ı local'e kaydet
      await _saveAuthToken(sanctumToken);

      // 2. FCM token'ı backend'e kaydet
      await _fcmService.registerTokenWithBackend(sanctumToken);

      // 3. Token refresh listener'ı başlat
      _fcmService.listenTokenRefresh(() => _getStoredSanctumToken());

      // 4. Navigate to home
      AppRouter.router.go('/home');
    } catch (e) {
      // Handle error
    }
  }

  /// Apple ile giriş (iOS)
  Future<void> signInWithApple() async {
    try {
      final response = await _authService.appleSignIn();
      final sanctumToken = response.token;

      await _saveAuthToken(sanctumToken);
      await _fcmService.registerTokenWithBackend(sanctumToken);
      _fcmService.listenTokenRefresh(() => _getStoredSanctumToken());

      AppRouter.router.go('/home');
    } catch (e) {
      // Handle error
    }
  }

  String _getStoredSanctumToken() {
    // SharedPreferences veya SecureStorage'dan token'ı oku
    return SecureStorage.get('sanctum_token') ?? '';
  }
}
```

### Bildirim İzni İsteme

Kullanıcı ilk kez login olduğunda veya onboarding'de izin isteyin:

```dart
// Onboarding ya da home screen'in initState'inde çağırın
Future<void> _requestNotificationPermission() async {
  final granted = await FCMService.instance.requestPermission();

  if (!granted) {
    // Kullanıcı reddetmiş — kritik değil, tekrar sormayın
    debugPrint('FCM: user denied notification permission');
  }
}
```

---

## 8. Foreground Notification Display

Uygulama açıkken FCM bildirimleri varsayılan olarak gösterilmez. `flutter_local_notifications` kullanarak gösterilir.

Bu kurulum [FCM Service Class](#6-fcm-service-class) içindeki `_onForegroundMessage` metodunda zaten yapılmış durumda.

### Önemli Notlar

- **Android:** `ic_notification` adında bir drawable resource gereklidir. `android/app/src/main/res/drawable/ic_notification.png` (veya XML vector) olarak ekleyin.
- **iOS:** `setForegroundNotificationPresentationOptions` ile sistem notification'ı direkt gösterilir, local notification'a gerek yoktur (kod her iki platformu da destekler).

### Foreground'da Badge Güncellemesi

```dart
// Bildirim badge'ini güncelle (iOS)
import 'package:firebase_messaging/firebase_messaging.dart';

// Badge'i temizle
await FirebaseMessaging.instance.setAutoInitEnabled(true);

// flutter_app_badger paketi ile badge sayısını güncelle (opsiyonel)
// FlutterAppBadger.updateBadgeCount(unreadCount);
// FlutterAppBadger.removeBadge();
```

---

## 9. Background / Terminated Handler

### Background Handler (main.dart'ta tanımlandı)

```dart
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

  // Background'da yapılabilecekler:
  // - Local storage'a veri kaydet
  // - Badge count'u güncelle
  // - Yerel bildirim göster (flutter_local_notifications ile)

  // YAPILAMAYACAKlar:
  // - setState() çağır
  // - Navigator kullan
  // - Provider/BLoC güncelle

  debugPrint('FCM: background message: ${message.data}');
}
```

### Terminated State'de Açılış

Uygulama tamamen kapalıyken bildirime tıklanınca app açılır. `FCMService.initialize()` içindeki `_checkInitialMessage()` bunu handle eder:

```dart
Future<void> _checkInitialMessage() async {
  final initialMessage = await _messaging.getInitialMessage();
  if (initialMessage != null) {
    // Router hazır olana kadar bekle
    Future.delayed(const Duration(milliseconds: 500), () {
      _handleMessage(initialMessage);
    });
  }
}
```

---

## 10. Deep Link Navigation with GoRouter

### lib/router/app_router.dart

```dart
import 'package:go_router/go_router.dart';

import '../services/fcm_service.dart';
import '../features/chat/chat_screen.dart';
import '../features/application/application_detail_screen.dart';
import '../features/badges/badges_screen.dart';
import '../features/rewards/my_rewards_screen.dart';

class AppRouter {
  static final _rootNavigatorKey = GlobalKey<NavigatorState>();

  static final GoRouter router = GoRouter(
    navigatorKey: _rootNavigatorKey,
    initialLocation: '/home',
    routes: [
      GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
      GoRoute(
        path: '/chat/:applicationId',
        builder: (context, state) => ChatScreen(
          applicationId: state.pathParameters['applicationId']!,
        ),
      ),
      GoRoute(
        path: '/application/:id',
        builder: (context, state) => ApplicationDetailScreen(
          applicationId: state.pathParameters['id']!,
        ),
      ),
      GoRoute(
        path: '/badges',
        builder: (_, __) => const BadgesScreen(),
      ),
      GoRoute(
        path: '/me/rewards',
        builder: (_, __) => const MyRewardsScreen(),
      ),
    ],
  );

  /// FCMService ile router'ı bağla.
  /// main.dart'ta Firebase.initializeApp sonrası çağrılır.
  static void connectFCMService() {
    FCMService.instance.onNotificationTap = (String type, String? id) {
      _navigateFromNotification(type, id);
    };
  }

  /// Notification type'a göre doğru route'a yönlendirir.
  static void _navigateFromNotification(String type, String? id) {
    switch (type) {
      case 'new_message':
        if (id != null) {
          router.push('/chat/$id');
        }
        break;

      case 'application_received':
      case 'application_accepted':
      case 'application_declined':
        if (id != null) {
          router.push('/application/$id');
        }
        break;

      case 'badge_awarded':
        router.push('/badges');
        break;

      case 'challenge_verified':
      case 'reward_won':
        router.push('/me/rewards');
        break;

      default:
        debugPrint('FCM: unknown notification type: $type');
    }
  }
}
```

### main.dart'a Ekleme

```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );

  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  // FCM'i başlat
  await FCMService.instance.initialize();

  // GoRouter ile FCM'i bağla
  AppRouter.connectFCMService(); // <-- Bunu ekle

  runApp(const KolabingApp());
}
```

---

## 11. Token Lifecycle

### Login Sonrası (Her zaman kaydet)

```dart
// Login başarılıysa
await FCMService.instance.registerTokenWithBackend(sanctumToken);
FCMService.instance.listenTokenRefresh(() => getStoredToken());
```

### Logout Sonrası

Backend invalid token'ları otomatik temizlediği için logout'ta FCM token silme zorunluluğu yok. Ancak isteğe bağlı olarak local token listener'ı durdurmak iyi pratik:

```dart
Future<void> signOut() async {
  // 1. Backend'e logout
  await _authService.logout();

  // 2. Local token'ı temizle
  await SecureStorage.delete('sanctum_token');

  // 3. FCM token'ı Firebase'den sil (opsiyonel ama önerilen)
  //    Böylece bu cihaza artık bildirim gönderilmez
  await FirebaseMessaging.instance.deleteToken();

  // 4. Login ekranına git
  AppRouter.router.go('/login');
}
```

### Token Refresh (Otomatik)

`listenTokenRefresh` çağrıldıktan sonra token değiştiğinde backend otomatik güncellenir. Bu genellikle şu durumlarda olur:
- Uygulama ilk kez yüklendiğinde
- Cihazın backup'tan restore edilmesinde
- Kullanıcı uygulama verilerini temizlediğinde

### Özet Akış

```
App açılır
    └─ Firebase.initializeApp()
    └─ FCMService.initialize()
        └─ Handler'lar kurulur
        └─ getInitialMessage() kontrol edilir

Login başarılı
    └─ Sanctum token alınır
    └─ registerTokenWithBackend(token) çağrılır
        └─ POST /api/v1/me/device-token
    └─ listenTokenRefresh() aktif edilir

Token yenilenir
    └─ onTokenRefresh callback tetiklenir
    └─ registerTokenWithBackend() otomatik çağrılır

Logout
    └─ FirebaseMessaging.deleteToken() (opsiyonel)
    └─ Sanctum token silinir
```

---

## 12. Testing

### 12.1 FCM Token'ı Cihazdan Alma

Geliştirme sırasında token'ı almak için geçici bir debug ekranı veya log kullanın:

```dart
// Geçici debug butonu (sadece development)
ElevatedButton(
  onPressed: () async {
    final token = await FCMService.instance.getToken();
    debugPrint('FCM Token: $token');
    // Token'ı clipboard'a kopyala
    await Clipboard.setData(ClipboardData(text: token ?? ''));
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('FCM token copied to clipboard')),
    );
  },
  child: const Text('Copy FCM Token'),
),
```

### 12.2 Firebase Console'dan Test Mesajı Gönderme

1. [Firebase Console](https://console.firebase.google.com) → **Messaging** → **New campaign**.
2. **Notification** sekmesinde title ve body girin.
3. **Target** → **Single device** → Token'ı yapıştırın.
4. **Additional options** → **Custom data** kısmına şunu ekleyin:

| Key | Value |
|-----|-------|
| `type` | `new_message` |
| `id` | `test-application-uuid` |

5. **Review → Publish** ile gönderin.

### 12.3 cURL ile Backend'den Manuel Test

```bash
# Önce login olun ve token alın
LOGIN_RESPONSE=$(curl -s -X POST https://api.kolabing.com/api/v1/auth/google \
  -H "Content-Type: application/json" \
  -d '{"id_token": "your-google-id-token"}')

SANCTUM_TOKEN=$(echo $LOGIN_RESPONSE | jq -r '.data.token')

# FCM token'ı kaydet
curl -X POST https://api.kolabing.com/api/v1/me/device-token \
  -H "Authorization: Bearer $SANCTUM_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "your-fcm-token-from-device",
    "platform": "ios"
  }'
```

### 12.4 Farklı Durumları Test Etme

| Durum | Test Yöntemi |
|-------|-------------|
| Foreground notification | App açıkken Firebase Console'dan gönder |
| Background notification | App arka planda iken Firebase Console'dan gönder |
| Terminated notification | App'i tamamen kapat, Firebase Console'dan gönder, simgéye dokun |
| Token refresh | `await FirebaseMessaging.instance.deleteToken()` çağır, sonra `getToken()` |
| Invalid token cleanup | Backend loglarını kontrol et (`FCM: cleared invalid device token`) |

### 12.5 iOS Simulator Notları

- iOS Simulator'da FCM push notification **çalışmaz**. Fiziksel cihaz gereklidir.
- Flutter local notifications (foreground) simulator'da çalışır.
- Android Emulator'da FCM çalışır (Google Play Services yüklü emulator).

### 12.6 Yaygın Hatalar ve Çözümleri

**`MissingPluginException`**
```bash
flutter clean && flutter pub get
```

**iOS'ta bildirim gelmiyor**
- Xcode'da Push Notifications capability eklendiğini kontrol edin.
- `GoogleService-Info.plist`'in Runner target'ına eklendiğini doğrulayın.
- Gerçek cihaz kullandığınızdan emin olun.

**Android'de bildirim gelmiyor**
- `google-services.json`'ın `android/app/` klasöründe olduğunu kontrol edin.
- `minSdkVersion` 21 veya üstü olduğunu doğrulayın.
- Emulator'da Google Play Services'ın aktif olduğunu kontrol edin.

**Token null geliyor**
- `requestPermission()` çağrıldığını kontrol edin.
- Internet bağlantısı olduğundan emin olun.
- `Firebase.initializeApp()` çağrıldığını doğrulayın.

---

## Tam Kurulum Kontrol Listesi

- [ ] `flutterfire configure` komutu çalıştırıldı
- [ ] `firebase_messaging` ve `flutter_local_notifications` pubspec.yaml'a eklendi
- [ ] Android: `google-services.json` `android/app/` içinde
- [ ] Android: `google-services` plugin `android/app/build.gradle`'a eklendi
- [ ] Android: `POST_NOTIFICATIONS` izni `AndroidManifest.xml`'de
- [ ] Android: Notification channel `AndroidManifest.xml`'de tanımlandı
- [ ] Android: `ic_notification` drawable resource eklendi
- [ ] iOS: `GoogleService-Info.plist` Runner target'ına eklendi
- [ ] iOS: Push Notifications capability Xcode'da eklendi
- [ ] iOS: Background Modes → Remote notifications aktif
- [ ] iOS: `AppDelegate.swift` güncellendi
- [ ] `_firebaseMessagingBackgroundHandler` `main.dart`'ta `@pragma('vm:entry-point')` ile tanımlandı
- [ ] `FCMService.initialize()` `main.dart`'ta Firebase init'ten sonra çağrılıyor
- [ ] `AppRouter.connectFCMService()` `main.dart`'ta çağrılıyor
- [ ] Login sonrası `registerTokenWithBackend()` çağrılıyor
- [ ] Logout'ta `FirebaseMessaging.instance.deleteToken()` çağrılıyor
