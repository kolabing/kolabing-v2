# PostHog Mobile Implementation

This document defines the mobile-side PostHog integration contract for Kolabing. It is written for a Flutter app, but the event taxonomy and privacy rules apply to any mobile client.

## Goals

- Track user behavior through registration, onboarding, discovery, collaboration, events, notifications, and subscription flows.
- Identify authenticated users consistently with the Laravel backend.
- Read feature flags for mobile-only UI rollouts and experiments.
- Enable session replay only with privacy masking and controlled sampling.
- Avoid sending secrets, credentials, tokens, payment data, message content, or unnecessary personal data to PostHog.

## PostHog Project Configuration

Use environment-specific values from the deployment configuration, not hard-coded tokens.

| Environment | Token source | Host |
| --- | --- | --- |
| Development | `POSTHOG_PROJECT_API_KEY_DEV` | `https://eu.i.posthog.com` |
| Staging | `POSTHOG_PROJECT_API_KEY_STAGING` | `https://eu.i.posthog.com` |
| Production | `POSTHOG_PROJECT_API_KEY_PRODUCTION` | `https://eu.i.posthog.com` |

If the PostHog project is created in the US region instead, use the matching PostHog ingestion host for every app and backend environment.

## Flutter SDK Setup

Add the SDK:

```yaml
dependencies:
  posthog_flutter: ^5.24.2
```

Initialize before `runApp`:

```dart
import 'package:flutter/foundation.dart';
import 'package:posthog_flutter/posthog_flutter.dart';

Future<void> configurePostHog({
  required String apiKey,
  required String host,
  required String environment,
  required String appVersion,
}) async {
  final config = PostHogConfig(apiKey);

  config.host = host;
  config.flushAt = 20;
  config.flushInterval = const Duration(seconds: 30);
  config.preloadFeatureFlags = true;
  config.sendFeatureFlagEvents = true;
  config.personProfiles = PostHogPersonProfiles.identifiedOnly;
  config.debug = !kReleaseMode;

  config.sessionReplay = false;
  config.sessionReplayConfig.maskAllTexts = true;
  config.sessionReplayConfig.maskAllImages = true;
  config.sessionReplayConfig.throttleDelay = const Duration(milliseconds: 500);
  config.sessionReplayConfig.sampleRate = 0.05;

  config.captureApplicationLifecycleEvents = true;
  config.captureScreenViews = false;

  await Posthog().setup(config);

  await Posthog().register({
    'environment': environment,
    'app_version': appVersion,
    'platform': defaultTargetPlatform.name,
  });
}
```

Wrap the app when session replay is enabled through a feature flag or remote config:

```dart
Widget buildPostHogRoot(Widget child) {
  return PostHogWidget(child: child);
}
```

Mask sensitive widgets:

```dart
PostHogMaskWidget(
  child: TextField(
    controller: phoneController,
  ),
);
```

## Identity Contract

Call `identify` after any successful login or registration response from Laravel.

Use the backend profile ID as the PostHog distinct ID:

```dart
Future<void> identifyKolabingUser(Profile profile) async {
  await Posthog().identify(
    userId: profile.id,
    userProperties: {
      'user_type': profile.userType,
      'email': profile.email,
      'city_id': profile.cityId,
      'created_at': profile.createdAt.toIso8601String(),
    },
  );
}
```

Rules:

- `distinct_id` must equal the Laravel `profiles.id`.
- Do not use email as `distinct_id`.
- Do not send access tokens, refresh tokens, Apple transaction IDs, passwords, private messages, or full address data.
- On logout or account deletion, call `Posthog().reset()`.

```dart
Future<void> clearPostHogUser() async {
  await Posthog().reset();
}
```

## Event Wrapper

Use one wrapper so common properties stay consistent.

```dart
class Analytics {
  const Analytics();

  Future<void> capture(
    String eventName, {
    Map<String, Object>? properties,
  }) {
    return Posthog().capture(
      eventName: eventName,
      properties: {
        ...?properties,
      },
    );
  }

  Future<void> screen(String screenName, {Map<String, Object>? properties}) {
    return Posthog().screen(
      screenName: screenName,
      properties: {
        ...?properties,
      },
    );
  }
}
```

## Event Taxonomy

Use snake_case event names. Prefer entity IDs and coarse metadata over free-text user content.

### App and Navigation

| Event | When | Properties |
| --- | --- | --- |
| `app_opened` | App starts or resumes from cold start | `source`, `app_version` |
| `screen_viewed` | User sees a meaningful screen | `screen_name` |
| `deep_link_opened` | App opens from a link | `link_type`, `target_type`, `target_id` |

### Auth and Onboarding

| Event | When | Properties |
| --- | --- | --- |
| `signup_started` | User starts registration | `user_type`, `method` |
| `signup_completed` | API registration succeeds | `user_type`, `method` |
| `login_completed` | API login succeeds | `user_type`, `method` |
| `logout_completed` | Logout succeeds | `user_type` |
| `onboarding_started` | User opens onboarding flow | `user_type` |
| `onboarding_completed` | Onboarding API succeeds | `user_type`, `city_id` |

### Discovery, Collaborations, and Events

| Event | When | Properties |
| --- | --- | --- |
| `discovery_opened` | User opens discovery/home feed | `user_type` |
| `collaboration_viewed` | User opens a collaboration | `collaboration_id`, `status` |
| `collaboration_applied` | User applies to a collaboration | `collaboration_id` |
| `event_viewed` | User opens event details | `event_id`, `community_id` |
| `event_signup_started` | User taps event signup | `event_id` |
| `event_signup_completed` | Event signup API succeeds | `event_id` |

### Subscription and Notifications

| Event | When | Properties |
| --- | --- | --- |
| `paywall_viewed` | User sees paywall | `entry_point`, `variant` |
| `subscription_cta_clicked` | User taps subscribe | `product_id`, `entry_point` |
| `subscription_purchase_started` | Native purchase sheet starts | `product_id` |
| `subscription_verified` | Laravel verification succeeds | `product_id`, `source` |
| `notification_opened` | Push notification opens app | `notification_type`, `target_type`, `target_id` |

## Feature Flags

Use mobile feature flags for client UI and rollout behavior only. Backend-owned rules such as payment access, subscription entitlement, challenge rewards, and security decisions must be enforced by Laravel.

Initial flags:

| Flag | Type | Owner | Default |
| --- | --- | --- | --- |
| `enable_session_replay` | boolean | Mobile | `false` |
| `new_onboarding_flow` | boolean | Mobile + Backend | `false` |
| `paywall_variant` | string | Mobile | `control` |
| `enable_new_discovery` | boolean | Mobile | `false` |

Example:

```dart
Future<bool> isEnabled(String key, {bool fallback = false}) async {
  final enabled = await Posthog().isFeatureEnabled(key);

  return enabled ?? fallback;
}

Future<String> getPaywallVariant() async {
  final value = await Posthog().getFeatureFlag('paywall_variant');

  return value is String ? value : 'control';
}
```

When a feature flag controls an experiment, include the variant in related events:

```dart
await analytics.capture('paywall_viewed', properties: {
  'entry_point': 'collaboration_apply',
  'variant': await getPaywallVariant(),
});
```

## Session Replay Privacy Rules

Production defaults:

- Disabled unless `enable_session_replay` is enabled.
- Sampling starts at `0.05`.
- `maskAllTexts = true`.
- `maskAllImages = true`.
- Explicitly mask phone, email, password, address, payment, search, private message, and profile edit fields.
- Do not record screens that show private chat contents unless masking is verified.

Acceptance check before production:

- Register, login, edit profile, chat, subscription, and onboarding screens are visually inspected in PostHog replay.
- No password, token, phone, private message, full address, or payment data is readable.
- Replay can be remotely disabled without app release.

## Error Tracking

Enable mobile error capture after the analytics baseline is stable:

```dart
config.errorTrackingConfig.captureFlutterErrors = true;
config.errorTrackingConfig.capturePlatformDispatcherErrors = true;
config.errorTrackingConfig.captureIsolateErrors = true;
config.errorTrackingConfig.captureNativeExceptions = true;
```

Do not attach request bodies or auth headers to error events.

## QA Checklist

- SDK initializes with the correct token and host per environment.
- `identify` uses Laravel profile ID after login/register.
- `reset` runs on logout and account deletion.
- Screen tracking does not duplicate events on tab changes.
- Auth, onboarding, subscription, discovery, and notification events appear in PostHog.
- Feature flag fallback behavior works when PostHog is unavailable.
- Session replay masking is verified before production rollout.
- Analytics opt-out is respected if the user disables tracking.
