# Bug: No onboarding resume mechanism for interrupted flows

## Problem
isNewUser is a transient in-memory flag set only at OAuth sign-in moment.
If a new user force-quits mid-onboarding and relaunches, isNewUser = false
on session restore → they land on dashboard with incomplete profile.
No persistent flag, no route guard, no redirect back to onboarding.

## Impact
- New users who interrupt onboarding are silently dropped into a broken dashboard
- No recovery path without re-registering

## Fix (when prioritised)
Add profile_completed boolean to the profiles table (or check if 
profile.displayName is null as a proxy). In resolveAuthDestination(), 
check this flag on session restore — not just isNewUser.
Route guard: if profile incomplete → redirect to correct onboarding step.

## Files to change
- lib/features/auth/utils/auth_navigation.dart (resolveAuthDestination)
- lib/features/auth/providers/auth_provider.dart (session restore, line 207)
- lib/features/auth/services/auth_service.dart

## Status: documented, not blocking redesign branch
