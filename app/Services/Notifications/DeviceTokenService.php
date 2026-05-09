<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class DeviceTokenService
{
    /**
     * @param  array{
     *     token: string,
     *     platform: string,
     *     app_version?: string|null,
     *     locale?: string|null,
     *     timezone?: string|null,
     *     last_location_lat?: float|null,
     *     last_location_lng?: float|null,
     *     location_permission_granted_at?: string|null
     * }  $data
     */
    public function register(Profile $profile, array $data): DeviceToken
    {
        return DB::transaction(function () use ($profile, $data): DeviceToken {
            $existing = DeviceToken::query()
                ->where('token', $data['token'])
                ->first();

            if ($existing !== null && $existing->profile_id !== $profile->id) {
                Profile::query()
                    ->where('id', $existing->profile_id)
                    ->where('device_token', $existing->token)
                    ->update([
                        'device_token' => null,
                        'device_platform' => null,
                    ]);
            }

            $token = DeviceToken::query()->updateOrCreate(
                ['token' => $data['token']],
                [
                    'profile_id' => $profile->id,
                    'platform' => $data['platform'],
                    'app_version' => $data['app_version'] ?? null,
                    'locale' => $data['locale'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'last_location_lat' => $data['last_location_lat'] ?? null,
                    'last_location_lng' => $data['last_location_lng'] ?? null,
                    'location_permission_granted_at' => $data['location_permission_granted_at'] ?? null,
                    'is_active' => true,
                    'last_seen_at' => now(),
                    'invalidated_at' => null,
                    'invalid_reason' => null,
                ]
            );

            $profile->update([
                'device_token' => $token->token,
                'device_platform' => $token->platform,
            ]);

            return $token->fresh();
        });
    }

    public function unregister(Profile $profile, string $token): void
    {
        $deviceToken = DeviceToken::query()
            ->where('profile_id', $profile->id)
            ->where('token', $token)
            ->first();

        if ($deviceToken === null) {
            return;
        }

        $deviceToken->update([
            'is_active' => false,
            'invalidated_at' => now(),
            'invalid_reason' => 'user_deleted',
        ]);

        if ($profile->device_token === $token) {
            $profile->update([
                'device_token' => null,
                'device_platform' => null,
            ]);
        }
    }

    public function markInvalid(DeviceToken $deviceToken, string $reason): void
    {
        $deviceToken->update([
            'is_active' => false,
            'invalidated_at' => now(),
            'invalid_reason' => $reason,
        ]);

        if ($deviceToken->profile->device_token === $deviceToken->token) {
            $deviceToken->profile->update([
                'device_token' => null,
                'device_platform' => null,
            ]);
        }
    }
}
