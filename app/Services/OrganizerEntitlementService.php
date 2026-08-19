<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrganizerCapability;
use App\Models\OrganizerEntitlement;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Maintainer grant/revoke for the Multi-Kolab Event Creator capability.
 * Mirrors {@see \App\Services\Admin\ManagedProfileService::grantSubscription()}
 * / revokeSubscription() but is deliberately a separate table and a separate
 * service — this must never touch `business_subscriptions` or
 * {@see Profile::hasActiveSubscription()} (Global Constraints).
 */
class OrganizerEntitlementService
{
    /**
     * Grant (or reactivate) the Event Creator capability for a profile.
     * Idempotent: re-granting reuses the existing row for this
     * (profile, capability) pair rather than creating a duplicate, and clears
     * any prior revocation.
     */
    public function grant(Profile $profile, int $months = 12): OrganizerEntitlement
    {
        return DB::transaction(function () use ($profile, $months): OrganizerEntitlement {
            $entitlement = OrganizerEntitlement::query()->firstOrNew([
                'profile_id' => $profile->id,
                'capability' => OrganizerCapability::EventCreator,
            ]);

            $entitlement->source = 'maintainer';
            $entitlement->granted_at = now();
            $entitlement->expires_at = now()->addMonths($months);
            $entitlement->revoked_at = null;
            $entitlement->save();

            return $entitlement;
        });
    }

    /**
     * Revoke the Event Creator capability. No-op if the profile never held
     * one (mirrors revokeSubscription's null-safe behaviour).
     */
    public function revoke(Profile $profile): void
    {
        OrganizerEntitlement::query()
            ->where('profile_id', $profile->id)
            ->where('capability', OrganizerCapability::EventCreator)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
