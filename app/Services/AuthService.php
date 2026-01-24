<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type AuthResult array{
 *     profile: Profile,
 *     is_new_user: bool,
 *     token: string
 * }
 * @phpstan-type GoogleUserData array{
 *     google_id: string,
 *     email: string,
 *     avatar_url: string|null,
 *     email_verified: bool
 * }
 */
class AuthService
{
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

        // Load relationships
        $this->loadProfileRelationships($profile);

        // Generate token with 30-day expiration
        $token = $this->createToken($profile);

        return [
            'profile' => $profile,
            'is_new_user' => false,
            'token' => $token,
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
            ]);

            // Create extended profile based on user type
            if ($userType === UserType::Business) {
                BusinessProfile::query()->create([
                    'profile_id' => $profile->id,
                ]);

                // Create inactive subscription for business users
                BusinessSubscription::query()->create([
                    'profile_id' => $profile->id,
                    'status' => SubscriptionStatus::Inactive,
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

        // Generate token with 30-day expiration
        $token = $this->createToken($profile);

        return [
            'profile' => $profile,
            'is_new_user' => true,
            'token' => $token,
        ];
    }

    /**
     * Load profile relationships based on user type.
     */
    private function loadProfileRelationships(Profile $profile): void
    {
        if ($profile->isBusiness()) {
            $profile->load(['businessProfile.city', 'subscription']);
        } else {
            $profile->load(['communityProfile.city']);
        }
    }

    /**
     * Create a Sanctum token for the profile with 30-day expiration.
     */
    private function createToken(Profile $profile): string
    {
        $abilities = [$profile->user_type->value];
        $expiresAt = now()->addDays(30);

        $token = $profile->createToken(
            name: 'mobile-app',
            abilities: $abilities,
            expiresAt: $expiresAt
        );

        return $token->plainTextToken;
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
    }
}
