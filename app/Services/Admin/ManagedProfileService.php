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
use Laravel\Sanctum\PersonalAccessToken;

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

    public function delete(Profile $profile): void
    {
        $profile->delete();
    }

    /**
     * Grant a maintainer-issued subscription that unblocks publish.
     * Defaults to 12 months from today.
     */
    /**
     * Switch an account off (#254).
     *
     * Reversible and lossless — the opposite of delete(). Revoking the tokens is
     * the half that makes it immediate: without it a signed-in phone keeps working
     * until its token happens to expire.
     */
    public function deactivate(Profile $profile): Profile
    {
        return DB::transaction(function () use ($profile): Profile {
            $profile->forceFill(['is_active' => false])->save();

            $profile->tokens()->delete();

            return $profile->refresh();
        });
    }

    /**
     * Switch an account back on (#254). The user signs in again as normal;
     * nothing else needs restoring, because nothing was destroyed.
     */
    public function activate(Profile $profile): Profile
    {
        return DB::transaction(function () use ($profile): Profile {
            $profile->forceFill(['is_active' => true])->save();

            return $profile->refresh();
        });
    }

    /**
     * Switch a batch off in two statements (#256).
     *
     * Not a loop over deactivate(): that would issue one UPDATE and one DELETE
     * per account, which is the whole reason bulk exists. Atomic, so an admin
     * who selects twenty either changes twenty or changes none.
     *
     * @param  list<string>  $profileIds
     * @return int how many rows actually changed
     */
    public function deactivateMany(array $profileIds): int
    {
        if ($profileIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($profileIds): int {
            $changed = Profile::query()
                ->whereIn('id', $profileIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // One statement for the whole batch, whatever its size. Without this
            // a signed-in phone keeps working until its token happens to expire.
            PersonalAccessToken::query()
                ->where('tokenable_type', Profile::class)
                ->whereIn('tokenable_id', $profileIds)
                ->delete();

            return $changed;
        });
    }

    /**
     * Switch a batch back on (#256). Nothing to restore — nothing was destroyed.
     *
     * @param  list<string>  $profileIds
     * @return int how many rows actually changed
     */
    public function activateMany(array $profileIds): int
    {
        if ($profileIds === []) {
            return 0;
        }

        return DB::transaction(fn (): int => Profile::query()
            ->whereIn('id', $profileIds)
            ->where('is_active', false)
            ->update(['is_active' => true]));
    }

    public function grantSubscription(Profile $profile, int $months = 12): BusinessSubscription
    {
        return DB::transaction(function () use ($profile, $months): BusinessSubscription {
            $subscription = BusinessSubscription::query()->firstOrNew(
                ['profile_id' => $profile->id],
            );

            $subscription->source = SubscriptionSource::Maintainer;
            $subscription->status = SubscriptionStatus::Active;
            $subscription->current_period_start = now();
            $subscription->current_period_end = now()->addMonths($months);
            $subscription->cancel_at_period_end = false;
            $subscription->save();

            return $subscription;
        });
    }

    public function revokeSubscription(Profile $profile): ?BusinessSubscription
    {
        return DB::transaction(function () use ($profile): ?BusinessSubscription {
            $subscription = BusinessSubscription::query()
                ->where('profile_id', $profile->id)
                ->first();

            if ($subscription === null) {
                return null;
            }

            $subscription->status = SubscriptionStatus::Inactive;
            $subscription->cancel_at_period_end = true;
            $subscription->save();

            return $subscription;
        });
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
