# Task: Password Reset Endpoints

## Status
- Created: 2026-02-01
- Started: 2026-02-01
- Completed: 2026-02-01

## Description
Implement forgot-password and reset-password API endpoints for email/password users.

## Assigned Agents
- [x] @laravel-specialist (endpoints, service, tests)
- [x] @general-purpose (mobile documentation in English)

## Progress
### Backend
- ForgotPasswordRequest + ResetPasswordRequest form requests
- AuthService: sendPasswordResetLink() + resetPassword() methods
- AuthController: forgotPassword() + resetPassword() endpoints
- Routes: POST auth/forgot-password + POST auth/reset-password
- config/auth.php: password broker provider fixed to 'profiles'
- 13 feature tests, all passing

### Documentation
- Mobile implementation doc at .agent/documentations/mobile-password-reset-api.md (English)
- Flutter/Dart + Swift code examples
- Deep link structure for email → app navigation

## Notes
- password_reset_tokens table already existed from default Laravel migration
- Token expires in 60 minutes (Laravel default)
- All existing tokens are revoked on successful password reset
