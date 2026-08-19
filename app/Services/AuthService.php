<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Support\ApiDebugLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use InvalidArgumentException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @phpstan-type AuthResult array{
 *     profile: Profile,
 *     is_new_user: bool,
 *     token: string,
 *     refresh_token: string,
 *     refresh_token_expires_at: string
 * }
 * @phpstan-type LoginResult array{
 *     profile: Profile,
 *     token: string,
 *     refresh_token: string,
 *     refresh_token_expires_at: string
 * }
 * @phpstan-type TokenPair array{
 *     token: string,
 *     refresh_token: string,
 *     refresh_token_expires_at: string
 * }
 * @phpstan-type GoogleUserData array{
 *     google_id: string,
 *     email: string,
 *     avatar_url: string|null,
 *     email_verified: bool
 * }
 * @phpstan-type AppleUserData array{
 *     apple_id: string,
 *     email: string|null,
 * }
 * @phpstan-type BusinessProfileData array{
 *     name: string,
 *     about: string|null,
 *     business_type: string|null,
 *     categories: array<int, string>,
 *     has_venue: bool,
 *     city_id: string|null,
 *     city_name: string|null,
 *     target_city_ids: array<int, string>,
 *     offering: string|null,
 *     offer_photos: array<int, string>,
 *     instagram: string|null,
 *     website: string|null,
 *     profile_photo: string|null,
 *     primary_venue: array<string, mixed>|null
 * }
 * @phpstan-type CommunityProfileData array{
 *     name: string,
 *     about: string|null,
 *     community_type: string,
 *     community_size: int|null,
 *     city_id: string,
 *     instagram: string|null,
 *     tiktok: string|null,
 *     website: string|null,
 *     profile_photo: string|null
 * }
 * @phpstan-type ProfileData array{
 *     email: string,
 *     password: string,
 *     phone_number: string|null
 * }
 */
class AuthService
{
    public function __construct(
        private readonly BusinessVenueService $businessVenueService,
        private readonly FileUploadService $fileUploadService,
        private readonly OnboardingService $onboardingService,
        private readonly OnboardingDripService $onboardingDripService,
        private readonly CommunityInvitationService $communityInvitationService,
        private readonly \App\Services\Admin\CompanySettingService $companySettings,
    ) {}

    /**
     * Authenticate or register a user via Apple Sign In.
     *
     * @param  AppleUserData  $appleUserData
     * @return AuthResult|array{error: string, code: int}|null
     */
    public function authenticateWithApple(array $appleUserData, ?UserType $userType = null, ?string $name = null): ?array
    {
        $query = Profile::query()->where('apple_id', $appleUserData['apple_id']);

        if (! empty($appleUserData['email'])) {
            $query->orWhere('email', $appleUserData['email']);
        }

        $profile = $query->first();

        if (! $profile) {
            if ($userType === null) {
                return null;
            }

            if (empty($appleUserData['email'])) {
                return [
                    'error' => __('Apple email is required to register a new account'),
                    'code' => 422,
                    'field' => 'email',
                ];
            }

            return $this->registerAppleUser($appleUserData, $userType, $name);
        }

        if ($userType !== null && $profile->user_type !== $userType) {
            return [
                'error' => 'User already exists with a different user type',
                'code' => 409,
            ];
        }

        $this->revokeExistingMobileTokens($profile);

        $updates = [];

        // Link apple_id if signing in by email for the first time with Apple
        if (! $profile->apple_id) {
            $updates['apple_id'] = $appleUserData['apple_id'];
        }

        if ($name !== null && $name !== '' && blank($profile->name)) {
            $updates['name'] = $name;
        }

        if (! empty($updates)) {
            $profile->update($updates);
            $profile->refresh();
        }

        $this->loadProfileRelationships($profile);

        return [
            'profile' => $profile,
            'is_new_user' => false,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Register a new Apple user.
     *
     * @param  AppleUserData  $appleUserData
     * @return AuthResult
     */
    private function registerAppleUser(array $appleUserData, UserType $userType, ?string $name = null): array
    {
        $profile = DB::transaction(function () use ($appleUserData, $userType, $name): Profile {
            $profile = Profile::query()->create([
                'email' => $appleUserData['email'],
                'apple_id' => $appleUserData['apple_id'],
                'user_type' => $userType,
                'name' => $name,
                'email_verified_at' => now(),
                ...$this->consentStamp(),
            ]);

            if ($userType === UserType::Business) {
                BusinessProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);

                BusinessSubscription::query()->create([
                    'profile_id' => $profile->id,
                    'source' => SubscriptionSource::AppleIap,
                    'status' => SubscriptionStatus::Inactive,
                ]);
            } elseif ($userType === UserType::Attendee) {
                AttendeeProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);
            } else {
                CommunityProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);
            }

            return $profile;
        });

        $this->loadProfileRelationships($profile);

        $this->afterRegistration($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Authenticate or register a user via Google OAuth.
     *
     * @param  GoogleUserData  $googleUserData
     * @return AuthResult|array{error: string, code: int}
     */
    public function authenticateWithGoogle(array $googleUserData, UserType $userType): array
    {
        // Check if user exists by google_id or email
        $existingProfile = Profile::query()
            ->where('google_id', $googleUserData['google_id'])
            ->orWhere('email', $googleUserData['email'])
            ->first();

        if ($existingProfile) {
            return $this->loginExistingUser($existingProfile, $googleUserData, $userType);
        }

        return $this->registerNewUser($googleUserData, $userType);
    }

    /**
     * Login an existing user.
     *
     * @param  GoogleUserData  $googleUserData
     * @return AuthResult|array{error: string, code: int}
     */
    private function loginExistingUser(Profile $profile, array $googleUserData, UserType $userType): array
    {
        // Verify user_type matches
        if ($profile->user_type !== $userType) {
            return [
                'error' => 'User already exists with a different user type',
                'code' => 409,
            ];
        }

        // Update avatar_url if changed
        $updates = [];
        if ($googleUserData['avatar_url'] && $profile->avatar_url !== $googleUserData['avatar_url']) {
            $updates['avatar_url'] = $googleUserData['avatar_url'];
        }

        // Update email_verified_at if not already set
        if (! $profile->email_verified_at && $googleUserData['email_verified']) {
            $updates['email_verified_at'] = now();
        }

        // Update google_id if not already set (user registered with email before)
        if (! $profile->google_id) {
            $updates['google_id'] = $googleUserData['google_id'];
        }

        if (! empty($updates)) {
            $profile->update($updates);
            $profile->refresh();
        }

        $this->revokeExistingMobileTokens($profile);

        // Load relationships
        $this->loadProfileRelationships($profile);

        return [
            'profile' => $profile,
            'is_new_user' => false,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Register a new user.
     *
     * @param  GoogleUserData  $googleUserData
     * @return AuthResult
     */
    private function registerNewUser(array $googleUserData, UserType $userType): array
    {
        $profile = DB::transaction(function () use ($googleUserData, $userType): Profile {
            // Create profile
            $profile = Profile::query()->create([
                'email' => $googleUserData['email'],
                'google_id' => $googleUserData['google_id'],
                'avatar_url' => $googleUserData['avatar_url'],
                'user_type' => $userType,
                'email_verified_at' => $googleUserData['email_verified'] ? now() : null,
                ...$this->consentStamp(),
            ]);

            // Create extended profile based on user type
            if ($userType === UserType::Business) {
                BusinessProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);

                // Create inactive subscription for business users
                BusinessSubscription::query()->create([
                    'profile_id' => $profile->id,
                    'source' => SubscriptionSource::AppleIap,
                    'status' => SubscriptionStatus::Inactive,
                ]);
            } elseif ($userType === UserType::Attendee) {
                AttendeeProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);
            } else {
                CommunityProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);
            }

            return $profile;
        });

        // Load relationships
        $this->loadProfileRelationships($profile);

        $this->afterRegistration($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Load profile relationships based on user type.
     */
    private function loadProfileRelationships(Profile $profile): void
    {
        if ($profile->isBusiness()) {
            $profile->load(['businessProfile.city', 'subscription']);
        } elseif ($profile->isAttendee()) {
            $profile->load(['attendeeProfile']);
        } else {
            $profile->load(['communityProfile.city']);
        }
    }

    /**
     * Peripheral work for a freshly-registered profile: the onboarding email
     * drip, and claiming any community invitations addressed to their email.
     *
     * Runs after the registration transaction has committed and each step is
     * fully isolated: these are peripheral concerns, so a failure must never
     * propagate into (and roll back) account creation. Mirrors the "email
     * failures never break the triggering request" rule in
     * {@see \App\Jobs\SendTransactionalEmail}.
     */
    private function afterRegistration(Profile $profile): void
    {
        try {
            $this->onboardingDripService->startForProfile($profile);
        } catch (\Throwable $e) {
            Log::warning('Failed to start onboarding drip for new profile', [
                'profile_id' => $profile->id,
                'exception' => $e->getMessage(),
            ]);
        }

        // A leader may have invited this email before the person had an
        // account; the pending invitation becomes a membership now. Guarded
        // inside the service.
        $this->communityInvitationService->claimForSafely($profile);
    }

    /**
     * Issue a new access token and refresh token pair.
     *
     * @return TokenPair
     */
    private function issueTokenPair(Profile $profile): array
    {
        $accessTokenExpiresAt = now()->addDays(30);
        $refreshTokenExpiresAt = now()->addDays(90);

        $accessToken = $profile->createToken(
            name: 'mobile-access',
            abilities: [$profile->user_type->value],
            expiresAt: $accessTokenExpiresAt
        );

        $refreshToken = $profile->createToken(
            name: 'mobile-refresh',
            abilities: ['refresh'],
            expiresAt: $refreshTokenExpiresAt
        );

        ApiDebugLogger::logTokenIssued($profile, $accessToken, $refreshToken);

        return [
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'refresh_token_expires_at' => $refreshTokenExpiresAt->toIso8601String(),
        ];
    }

    /**
     * Logout the current user by revoking their token.
     */
    public function logout(Profile $profile): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $profile->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        $this->revokeExistingMobileTokens($profile);
    }

    /**
     * Register a new business user with email and password.
     *
     * @param  ProfileData  $profileData
     * @param  BusinessProfileData  $businessProfileData
     * @return AuthResult
     */
    public function registerBusiness(array $profileData, array $businessProfileData): array
    {
        $profile = DB::transaction(function () use ($profileData, $businessProfileData): Profile {
            // Create profile
            $profile = Profile::query()->create([
                'email' => $profileData['email'],
                'password' => $profileData['password'],
                'phone_number' => $profileData['phone_number'],
                'user_type' => UserType::Business,
                ...$this->consentStamp(),
            ]);

            $profilePhotoUrl = $this->handleProfilePhoto(
                $businessProfileData['profile_photo'],
                $profile->id
            );

            $hasVenue = $businessProfileData['has_venue'] ?? true;
            $primaryVenueInput = $businessProfileData['primary_venue'] ?? null;

            $resolvedCity = $this->businessVenueService->resolveCity(
                $businessProfileData['city_id'],
                $businessProfileData['city_name'] ?? $primaryVenueInput['city'] ?? null
            );

            $primaryVenue = ($hasVenue && is_array($primaryVenueInput))
                ? $this->businessVenueService->normalizePrimaryVenue($primaryVenueInput, $profile->id)
                : null;

            $targetCityIds = array_values($businessProfileData['target_city_ids'] ?? []);
            $offerPhotos = $this->businessVenueService->normalizePhotos(
                $businessProfileData['offer_photos'] ?? [],
                $profile->id
            );

            // Create business profile with all data
            BusinessProfile::query()->create([
                'profile_id' => $profile->id,
                'name' => $businessProfileData['name'],
                'about' => $businessProfileData['about'],
                'offering' => $businessProfileData['offering'] ?? null,
                'product_type' => $businessProfileData['product_type'] ?? 'other',
                'business_type' => $businessProfileData['categories'][0] ?? $businessProfileData['business_type'],
                'has_venue' => $hasVenue,
                'categories' => $businessProfileData['categories'],
                'city_id' => $resolvedCity?->id,
                'city_name' => $resolvedCity?->name ?? $businessProfileData['city_name'] ?? $primaryVenue['city'] ?? null,
                'city_country' => $resolvedCity?->country ?? $primaryVenue['country'] ?? null,
                'target_city_ids' => $targetCityIds === [] ? null : $targetCityIds,
                'instagram' => $businessProfileData['instagram'],
                'website' => $businessProfileData['website'],
                'profile_photo' => $profilePhotoUrl,
                'primary_venue' => $primaryVenue,
                'offer_photos' => $offerPhotos === [] ? null : $offerPhotos,
            ]);

            // Create inactive subscription for business users
            BusinessSubscription::query()->create([
                'profile_id' => $profile->id,
                'source' => SubscriptionSource::AppleIap,
                'status' => SubscriptionStatus::Inactive,
            ]);

            // The app registers businesses in one shot via this path (it never
            // calls PUT /onboarding/business), so the free auto-offer must be
            // provisioned here too, using the SAME shared logic as the
            // onboarding-complete path. Runs after the business profile (name,
            // city, primary_venue) is persisted; idempotent + tolerant.
            $profile->refresh();
            $this->onboardingService->provisionBusinessAutoOffer($profile);

            return $profile;
        });

        // Load relationships
        $this->loadProfileRelationships($profile);

        $this->afterRegistration($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Handle profile photo upload.
     */
    private function handleProfilePhoto(?string $profilePhoto, string $profileId): ?string
    {
        if ($profilePhoto === null || $profilePhoto === '') {
            return null;
        }

        if (filter_var($profilePhoto, FILTER_VALIDATE_URL)) {
            return $profilePhoto;
        }

        return $this->fileUploadService->uploadFromBase64(
            $profilePhoto,
            \App\Enums\FileUploadType::ProfilePhoto,
            $profileId
        );
    }

    /**
     * The consent stamp recorded on every account created through the app's
     * sign-up / registration flows — the Terms/Privacy version in effect and
     * the moment the user accepted it during onboarding.
     *
     * @return array{terms_accepted_at: \Illuminate\Support\Carbon, terms_version: string}
     */
    private function consentStamp(): array
    {
        return [
            'terms_accepted_at' => now(),
            'terms_version' => $this->companySettings->termsVersion(),
        ];
    }

    /**
     * Register a new community user with email and password.
     *
     * @param  ProfileData  $profileData
     * @param  CommunityProfileData  $communityProfileData
     * @return AuthResult
     */
    public function registerCommunity(array $profileData, array $communityProfileData): array
    {
        $profile = DB::transaction(function () use ($profileData, $communityProfileData): Profile {
            // Create profile
            $profile = Profile::query()->create([
                'email' => $profileData['email'],
                'password' => $profileData['password'],
                'phone_number' => $profileData['phone_number'],
                'user_type' => UserType::Community,
                ...$this->consentStamp(),
            ]);

            // Create community profile with all data
            CommunityProfile::query()->create([
                'profile_id' => $profile->id,
                'name' => $communityProfileData['name'],
                'about' => $communityProfileData['about'],
                'community_type' => $communityProfileData['community_type'],
                'community_size' => $communityProfileData['community_size'] ?? null,
                'city_id' => $communityProfileData['city_id'],
                'instagram' => $communityProfileData['instagram'],
                'tiktok' => $communityProfileData['tiktok'],
                'website' => $communityProfileData['website'],
                'profile_photo' => $communityProfileData['profile_photo'],
                // Verification: present only when proof channels were submitted at
                // registration (status pre-resolved to pending by the request).
                'verification_channels' => $communityProfileData['verification_channels'] ?? null,
                'verification_status' => $communityProfileData['verification_status']
                    ?? VerificationStatus::Unverified->value,
            ]);

            // The app registers communities in one shot via this path (it never
            // calls PUT /onboarding/community), so the management community must
            // be provisioned here too, using the SAME shared logic as the
            // onboarding-complete path. Runs after the community profile (name,
            // type) is persisted; idempotent + tolerant.
            $profile->refresh();
            $this->onboardingService->provisionManagementCommunity($profile);

            return $profile;
        });

        // Load relationships
        $this->loadProfileRelationships($profile);

        $this->afterRegistration($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Register a new attendee user with email and password.
     *
     * @param  array{email: string, password: string}  $data
     * @return AuthResult
     */
    public function registerAttendee(array $data): array
    {
        $profile = DB::transaction(function () use ($data): Profile {
            $profile = Profile::query()->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'user_type' => UserType::Attendee,
                ...$this->consentStamp(),
            ]);

            AttendeeProfile::query()->create([
                'profile_id' => $profile->id,
            ]);

            return $profile;
        });

        $this->loadProfileRelationships($profile);

        $this->afterRegistration($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Login a user with email and password.
     *
     * @return LoginResult|array{error: string, code: int}
     */
    public function login(string $email, string $password): array
    {
        $profile = Profile::query()
            ->where('email', $email)
            ->first();

        if (! $profile) {
            return [
                'error' => __('Invalid credentials'),
                'code' => 401,
            ];
        }

        // Check if user has a password set
        if (! $profile->password) {
            return [
                'error' => __('This account uses Google Sign-In. Please login with Google.'),
                'code' => 400,
            ];
        }

        // Verify password
        if (! Hash::check($password, $profile->password)) {
            return [
                'error' => __('Invalid credentials'),
                'code' => 401,
            ];
        }

        $this->revokeExistingMobileTokens($profile);

        // Load relationships
        $this->loadProfileRelationships($profile);

        return [
            'profile' => $profile,
            ...$this->issueTokenPair($profile),
        ];
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @return LoginResult|array{error: string, code: int}
     */
    public function refresh(string $refreshToken): array
    {
        $storedToken = PersonalAccessToken::findToken($refreshToken);

        if (! $storedToken || ! in_array('refresh', $storedToken->abilities, true) || $storedToken->expires_at?->isPast()) {
            ApiDebugLogger::logRefreshAttempt('invalid_or_expired_refresh_token');

            return [
                'error' => __('Invalid refresh token'),
                'code' => 401,
            ];
        }

        $tokenable = $storedToken->tokenable;

        if (! $tokenable instanceof Profile) {
            ApiDebugLogger::logRefreshAttempt('invalid_refresh_token_owner', $storedToken);

            return [
                'error' => __('Invalid refresh token'),
                'code' => 401,
            ];
        }

        $this->loadProfileRelationships($tokenable);

        ApiDebugLogger::logRefreshAttempt('success', $storedToken, $tokenable);

        $storedToken->delete();

        return [
            'profile' => $tokenable,
            ...$this->issueTokenPair($tokenable),
        ];
    }

    private function revokeExistingMobileTokens(Profile $profile): void
    {
        $profile->tokens()
            ->where('name', 'like', 'mobile-%')
            ->delete();
    }

    /**
     * Send password reset link to user's email.
     *
     * @throws InvalidArgumentException
     */
    public function sendPasswordResetLink(string $email): string
    {
        $status = Password::broker()->sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw new InvalidArgumentException(__($status));
        }

        return $status;
    }

    /**
     * Reset user's password using token.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function resetPassword(array $data): string
    {
        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (Profile $profile, string $password): void {
                $profile->update(['password' => $password]);

                // Revoke all existing tokens for security
                $profile->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidArgumentException(__($status));
        }

        return $status;
    }
}
