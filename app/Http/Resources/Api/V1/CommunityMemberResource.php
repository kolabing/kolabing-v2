<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @mixin CommunityMember
 */
class CommunityMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'profile_id' => $this->profile_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier
                ? new CommunityTierResource($this->tier)
                : null),
            'tier_id' => $this->tier_id,
            'can_manage' => $this->can_manage,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'tier_assigned_at' => $this->tier_assigned_at?->toIso8601String(),
            ...$this->engagementFields(),
            'profile' => $this->whenLoaded('profile', fn () => [
                'name' => $this->profileDisplayName(),
                'handle' => $this->profile?->handle,
                'email' => $this->profile?->email,
                'avatar_url' => $this->profileAvatarUrl(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Per-member engagement, present only when the caller resolved it (the web
     * roster does, via CommunityRosterQuery). Callers that did not preload get
     * the original lean payload and pay nothing extra — the same
     * preloaded-attribute fast path CommunityResource uses.
     *
     * @return array<string, mixed>
     */
    private function engagementFields(): array
    {
        $raw = $this->resource->getAttributes();

        if (! array_key_exists('points_value', $raw)) {
            return [];
        }

        return [
            'points' => (int) ($raw['points_value'] ?? 0),
            'events_attended' => (int) ($raw['events_attended_value'] ?? 0),
            'last_active_at' => $this->lastActiveAt($raw['last_active_value'] ?? null),
            'tenure_days' => $this->joined_at ? (int) $this->joined_at->diffInDays(now()) : null,
        ];
    }

    private function lastActiveAt(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return $raw instanceof \DateTimeInterface
            ? Carbon::instance($raw)->toIso8601String()
            : Carbon::parse((string) $raw)->toIso8601String();
    }

    /**
     * profiles.name is the canonical display name (set at onboarding for every
     * user type). attendee_profiles carries no name column at all, so the old
     * extended-profile-first order rendered every community member as their
     * email prefix. Extended names stay as a fallback for business/community
     * profiles created before profiles.name existed.
     */
    private function profileDisplayName(): ?string
    {
        if (filled($this->profile?->name)) {
            return $this->profile->name;
        }

        $extended = $this->profile?->getExtendedProfile();

        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return $this->profile ? Str::before($this->profile->email, '@') : null;
    }

    private function profileAvatarUrl(): ?string
    {
        $extended = $this->profile?->getExtendedProfile();

        return $this->profile?->avatar_url
            ?? ($extended->profile_photo ?? null);
    }
}
