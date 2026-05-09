<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\ApplicationStatus;
use App\Enums\NotificationType;
use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class GrowthAudienceService
{
    /**
     * @return list<array{
     *     recipient: Profile,
     *     type: NotificationType,
     *     title: string,
     *     body: string,
     *     targetId: string|null,
     *     targetType: string|null,
     *     deeplink: string|null,
     *     dedupeKey: string,
     *     data: array<string, mixed>
     * }>
     */
    public function pendingApplicationNudges(?Carbon $now = null): array
    {
        $now ??= now();

        $applications = Application::query()
            ->where('status', ApplicationStatus::Pending)
            ->where('created_at', '<=', $now->copy()->subDay())
            ->with(['collabOpportunity.creatorProfile'])
            ->get();

        $grouped = [];

        foreach ($applications as $application) {
            $owner = $application->collabOpportunity?->creatorProfile;

            if ($owner === null) {
                continue;
            }

            $grouped[$owner->id]['recipient'] = $owner;
            $grouped[$owner->id]['applications'][] = $application;
        }

        return collect($grouped)->map(function (array $payload) use ($now): array {
            /** @var Profile $recipient */
            $recipient = $payload['recipient'];
            $applications = $payload['applications'];
            /** @var Application $firstApplication */
            $firstApplication = $applications[0];
            $count = count($applications);

            return [
                'recipient' => $recipient,
                'type' => NotificationType::PendingApplicationNudge,
                'title' => 'Pending applications waiting',
                'body' => "You have {$count} pending applications older than 24 hours.",
                'targetId' => $firstApplication->id,
                'targetType' => 'application',
                'deeplink' => $recipient->isBusiness() ? '/business' : '/community',
                'dedupeKey' => "pending_application_nudge:{$recipient->id}:{$now->toDateString()}",
                'data' => ['pending_count' => $count],
            ];
        })->values()->all();
    }

    /**
     * @return list<array{
     *     recipient: Profile,
     *     type: NotificationType,
     *     title: string,
     *     body: string,
     *     targetId: string|null,
     *     targetType: string|null,
     *     deeplink: string|null,
     *     dedupeKey: string,
     *     data: array<string, mixed>
     * }>
     */
    public function opportunityMatches(?Carbon $now = null): array
    {
        $now ??= now();

        $opportunities = CollabOpportunity::query()
            ->where('status', OfferStatus::Published)
            ->where('published_at', '>=', $now->copy()->subDay())
            ->with(['creatorProfile', 'creatorProfile.businessProfile.city', 'creatorProfile.communityProfile.city'])
            ->get();

        $payloads = [];

        foreach ($opportunities as $opportunity) {
            $recipientType = $opportunity->creator_profile_type === UserType::Business
                ? UserType::Community
                : UserType::Business;

            $recipients = Profile::query()
                ->where('user_type', $recipientType)
                ->whereKeyNot($opportunity->creator_profile_id)
                ->with(['businessProfile.city', 'communityProfile.city'])
                ->get();

            foreach ($recipients as $recipient) {
                if (! $this->matchesOpportunity($recipient, $opportunity)) {
                    continue;
                }

                $payloads[] = [
                    'recipient' => $recipient,
                    'type' => NotificationType::OpportunityMatch,
                    'title' => 'New opportunity match',
                    'body' => "{$opportunity->title} matches your city and category.",
                    'targetId' => $opportunity->id,
                    'targetType' => 'opportunity',
                    'deeplink' => $recipient->isBusiness() ? '/business/browse' : '/community/offers',
                    'dedupeKey' => "opportunity_match:{$opportunity->id}:{$recipient->id}",
                    'data' => ['opportunity_id' => $opportunity->id],
                ];
            }
        }

        return $payloads;
    }

    /**
     * @return list<array{
     *     recipient: Profile,
     *     type: NotificationType,
     *     title: string,
     *     body: string,
     *     targetId: string|null,
     *     targetType: string|null,
     *     deeplink: string|null,
     *     dedupeKey: string,
     *     data: array<string, mixed>
     * }>
     */
    public function nearbyEventMatches(?Carbon $now = null): array
    {
        $now ??= now();

        $radiusKm = (float) config('notifications.growth.nearby_radius_km', 25.0);
        $consentDays = (int) config('notifications.growth.location_consent_days', 30);

        $tokens = DeviceToken::query()
            ->with('profile.attendeeProfile')
            ->where('is_active', true)
            ->whereNotNull('last_location_lat')
            ->whereNotNull('last_location_lng')
            ->whereNotNull('location_permission_granted_at')
            ->where('location_permission_granted_at', '>=', $now->copy()->subDays($consentDays))
            ->orderByDesc('last_seen_at')
            ->get()
            ->unique('profile_id');

        $payloads = [];

        foreach ($tokens as $token) {
            $recipient = $token->profile;

            if (! $recipient->isAttendee()) {
                continue;
            }

            $event = Event::query()
                ->where('is_active', true)
                ->whereDate('event_date', '>=', $now->toDateString())
                ->whereNotNull('location_lat')
                ->whereNotNull('location_lng')
                ->get()
                ->first(function (Event $event) use ($token, $radiusKm): bool {
                    return $this->distanceKm(
                        (float) $token->last_location_lat,
                        (float) $token->last_location_lng,
                        (float) $event->location_lat,
                        (float) $event->location_lng,
                    ) <= $radiusKm;
                });

            if ($event === null) {
                continue;
            }

            $payloads[] = [
                'recipient' => $recipient,
                'type' => NotificationType::NearbyEventMatch,
                'title' => 'Nearby event match',
                'body' => "{$event->name} is happening near you.",
                'targetId' => $event->id,
                'targetType' => 'event',
                'deeplink' => '/attendee',
                'dedupeKey' => "nearby_event_match:{$recipient->id}:{$event->id}:{$event->event_date->toDateString()}",
                'data' => ['event_id' => $event->id],
            ];
        }

        return $payloads;
    }

    /**
     * @return list<array{
     *     recipient: Profile,
     *     type: NotificationType,
     *     title: string,
     *     body: string,
     *     targetId: string|null,
     *     targetType: string|null,
     *     deeplink: string|null,
     *     dedupeKey: string,
     *     data: array<string, mixed>
     * }>
     */
    public function walletThresholdReached(?Carbon $now = null): array
    {
        $now ??= now();

        return Wallet::query()
            ->with('profile')
            ->get()
            ->filter(static fn (Wallet $wallet): bool => $wallet->canWithdraw())
            ->map(function (Wallet $wallet) use ($now): array {
                return [
                    'recipient' => $wallet->profile,
                    'type' => NotificationType::WalletThresholdReached,
                    'title' => 'Wallet threshold reached',
                    'body' => 'Your wallet is ready for withdrawal.',
                    'targetId' => $wallet->profile_id,
                    'targetType' => 'wallet',
                    'deeplink' => '/community/wallet',
                    'dedupeKey' => "wallet_threshold_reached:{$wallet->profile_id}:{$wallet->redeemed_points}",
                    'data' => ['available_points' => $wallet->getAvailablePoints(), 'captured_at' => $now->toIso8601String()],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     recipient: Profile,
     *     type: NotificationType,
     *     title: string,
     *     body: string,
     *     targetId: string|null,
     *     targetType: string|null,
     *     deeplink: string|null,
     *     dedupeKey: string,
     *     data: array<string, mixed>
     * }>
     */
    public function dormantUserReactivation(?Carbon $now = null): array
    {
        $now ??= now();
        $daysThresholds = array_map('intval', config('notifications.growth.dormant_days', [7, 14]));
        sort($daysThresholds);

        $latestUsage = PersonalAccessToken::query()
            ->selectRaw('tokenable_id, MAX(COALESCE(last_used_at, created_at)) as last_activity_at')
            ->where('tokenable_type', Profile::class)
            ->groupBy('tokenable_id')
            ->get()
            ->keyBy('tokenable_id');

        $payloads = [];

        foreach (Profile::query()->with(['businessProfile', 'communityProfile', 'attendeeProfile'])->get() as $profile) {
            $activity = $latestUsage->get($profile->id)?->last_activity_at;
            $lastActivityAt = $activity !== null ? Carbon::parse($activity) : $profile->created_at;

            foreach ($daysThresholds as $days) {
                if ($lastActivityAt !== null && $lastActivityAt->lte($now->copy()->subDays($days))) {
                    $payloads[] = [
                        'recipient' => $profile,
                        'type' => NotificationType::DormantUserReactivation,
                        'title' => 'Come back to Kolabing',
                        'body' => 'You have fresh opportunities and rewards waiting.',
                        'targetId' => $profile->id,
                        'targetType' => 'profile',
                        'deeplink' => '/notifications',
                        'dedupeKey' => "dormant_user_reactivation:{$profile->id}:{$days}",
                        'data' => ['days_inactive' => $days],
                    ];

                    break;
                }
            }
        }

        return $payloads;
    }

    private function matchesOpportunity(Profile $recipient, CollabOpportunity $opportunity): bool
    {
        $recipientCity = $this->normalizeString($recipient->businessProfile?->city?->name
            ?? $recipient->businessProfile?->city_name
            ?? $recipient->communityProfile?->city?->name);
        $opportunityCity = $this->normalizeString($opportunity->preferred_city);

        if ($recipientCity === null || $opportunityCity === null || $recipientCity !== $opportunityCity) {
            return false;
        }

        $opportunityCategories = collect($opportunity->categories ?? [])
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => $this->normalizeString($value) ?? '')
            ->filter()
            ->values();

        if ($recipient->isBusiness()) {
            $recipientCategories = collect($recipient->businessProfile?->normalizedCategories() ?? [])
                ->map(fn (string $value): string => $this->normalizeString($value) ?? '')
                ->filter()
                ->values();

            return $recipientCategories->intersect($opportunityCategories)->isNotEmpty();
        }

        if ($recipient->isCommunity()) {
            $communityType = $this->normalizeString($recipient->communityProfile?->community_type);

            return $communityType !== null && $opportunityCategories->contains($communityType);
        }

        return false;
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized !== '' ? $normalized : null;
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
