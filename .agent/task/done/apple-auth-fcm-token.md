# Task: apple-auth-fcm-token

## Status
- Created: 2026-02-27 10:00
- Started: 2026-02-27 10:00
- Completed: (updated when moved to done)

## Description
Implement Apple Sign In endpoint (login-only, 404 if not found) and FCM device token registration endpoint.

## Assigned Agents
- [x] @laravel-specialist

## Progress
### Backend
- [ ] Migration: add apple_id, device_token, device_platform columns to profiles
- [ ] AppleAuthService: JWT verification against Apple JWKS
- [ ] AppleLoginRequest form request
- [ ] StoreDeviceTokenRequest form request
- [ ] AuthService: authenticateWithApple() method
- [ ] AuthController: apple() method
- [ ] DeviceTokenController
- [ ] Routes registered
- [ ] Tests written and passing

## Notes
- Apple auth is LOGIN ONLY - returns 404 if user not found (no registration)
- firebase/php-jwt already available via google/apiclient
- Device token stored on profiles table
