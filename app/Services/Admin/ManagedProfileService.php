<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class ManagedProfileService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Profile
    {
        return DB::transaction(function () use ($data): Profile {
            $userType = UserType::from((string) $data['user_type']);

            $profile = Profile::query()->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'phone_number' => ($data['phone_number'] ?? null) ?: null,
                'user_type' => $userType,
                'email_verified_at' => $this->resolveVerifiedAt($data),
            ]);

            $this->upsertDetailProfile($profile, $data);

            if ($profile->isBusiness()) {
                BusinessSubscription::query()->firstOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'source' => SubscriptionSource::AppleIap,
                        'status' => SubscriptionStatus::Inactive,
                    ]
                );
            }

            return $profile->fresh([
                'businessProfile',
                'communityProfile',
                'attendeeProfile',
                'subscription',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Profile $profile, array $data): Profile
    {
        return DB::transaction(function () use ($profile, $data): Profile {
            $attributes = [
                'email' => $data['email'],
                'phone_number' => ($data['phone_number'] ?? null) ?: null,
                'email_verified_at' => $this->resolveVerifiedAt($data),
            ];

            if (filled($data['password'] ?? null)) {
                $attributes['password'] = $data['password'];
            }

            $profile->update($attributes);

            $this->upsertDetailProfile($profile, $data);

            if ($profile->isBusiness()) {
                BusinessSubscription::query()->firstOrCreate(
                    ['profile_id' => $profile->id],
                    [
                        'source' => SubscriptionSource::AppleIap,
                        'status' => SubscriptionStatus::Inactive,
                    ]
                );
            }

            return $profile->fresh([
                'businessProfile',
                'communityProfile',
                'attendeeProfile',
                'subscription',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertDetailProfile(Profile $profile, array $data): void
    {
        if ($profile->isBusiness()) {
            BusinessProfile::query()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'name' => ($data['name'] ?? null) ?: null,
                    'about' => ($data['about'] ?? null) ?: null,
                    'instagram' => ($data['instagram'] ?? null) ?: null,
                    'website' => ($data['website'] ?? null) ?: null,
                ]
            );

            return;
        }

        if ($profile->isCommunity()) {
            CommunityProfile::query()->updateOrCreate(
                ['profile_id' => $profile->id],
                [
                    'name' => ($data['name'] ?? null) ?: null,
                    'about' => ($data['about'] ?? null) ?: null,
                    'instagram' => ($data['instagram'] ?? null) ?: null,
                    'tiktok' => ($data['tiktok'] ?? null) ?: null,
                    'website' => ($data['website'] ?? null) ?: null,
                ]
            );

            return;
        }

        AttendeeProfile::query()->firstOrCreate([
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveVerifiedAt(array $data): ?\Illuminate\Support\Carbon
    {
        return filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOL)
            ? now()
            : null;
    }
}
