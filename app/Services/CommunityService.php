<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatThreadType;
use App\Enums\FileUploadType;
use App\Enums\JoinPolicy;
use App\Enums\TierAssignmentRule;
use App\Exceptions\CommunityLimitReachedException;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommunityService
{
    public function __construct(
        private readonly FileUploadService $fileUploadService,
    ) {}

    /**
     * Create a community for a leader, enforcing the free cap and auto-creating
     * the default tier. The cap is a NEW gate (config-driven), never the
     * business paywall.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws CommunityLimitReachedException
     */
    public function create(Profile $owner, array $data): Community
    {
        $existing = Community::query()->where('owner_profile_id', $owner->id)->count();

        if ($existing >= (int) config('communities.max_free_communities', 1)) {
            throw new CommunityLimitReachedException;
        }

        return DB::transaction(function () use ($owner, $data, $existing): Community {
            // Inherit the owner's existing community-profile photo when the client
            // doesn't supply one, so creating a community never blanks the picture
            // the leader already has (the create path mirrors update()'s same-image
            // sync — it must never null an existing image).
            $avatarUrl = $this->resolveAvatarUrl(
                $data['avatar_url'] ?? $owner->communityProfile?->profile_photo,
                $owner->id
            );

            // The group carries the real 17-slug community-type vocabulary. Prefer
            // the type the leader picked at sign-up (community_profiles
            // .community_type) so the group inherits it; else the provided 17-slug;
            // else 'other'. App\Enums\CommunityType (5-value) is NOT used here.
            $type = $owner->communityProfile?->community_type
                ?? $data['type']
                ?? 'other';

            $community = Community::query()->create([
                'owner_profile_id' => $owner->id,
                'community_profile_id' => $data['community_profile_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'type' => $type,
                'description' => $data['description'] ?? null,
                'avatar_url' => $avatarUrl,
                'is_primary' => $existing === 0,
                'join_policy' => $data['join_policy'] ?? JoinPolicy::Open->value,
            ]);

            $this->createDefaultTier($community);

            // Every community has exactly one main chat (NF-CHAT Phase 2).
            ChatThread::query()->create([
                'type' => ChatThreadType::CommunityMain->value,
                'community_id' => $community->id,
                'name' => $community->name,
            ]);

            return $community->load('tiers');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Community $community, array $data): Community
    {
        $avatarUrl = array_key_exists('avatar_url', $data)
            ? $this->resolveAvatarUrl($data['avatar_url'], $community->owner_profile_id)
            : null;

        $community->fill(array_filter([
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? null,
            'description' => $data['description'] ?? null,
            'avatar_url' => $avatarUrl,
            'join_policy' => $data['join_policy'] ?? null,
            'community_profile_id' => $data['community_profile_id'] ?? null,
        ], static fn ($value) => $value !== null));

        $community->save();

        // Same-image sync (reverse of ProfileService): editing the community logo
        // mirrors back to the owner's profile photo, so the two never drift.
        if ($avatarUrl !== null) {
            $owner = Profile::query()->with('communityProfile')->find($community->owner_profile_id);
            $owner?->communityProfile?->update(['profile_photo' => $avatarUrl]);
        }

        return $community->refresh();
    }

    /**
     * Normalize a community avatar payload into a stored public URL.
     *
     * Accepts already-hosted URLs, data URIs, and raw base64. Invalid payloads
     * are ignored so a bad image never breaks community creation/update.
     */
    private function resolveAvatarUrl(?string $avatarUrl, string $entityId): ?string
    {
        if ($avatarUrl === null || $avatarUrl === '') {
            return null;
        }

        try {
            if (filter_var($avatarUrl, FILTER_VALIDATE_URL)) {
                return $avatarUrl;
            }

            if (preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $avatarUrl) === 1
                || base64_decode($avatarUrl, true) !== false) {
                return $this->fileUploadService->uploadFromBase64(
                    $avatarUrl,
                    FileUploadType::ProfilePhoto,
                    $entityId
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to normalize community avatar', [
                'owner_profile_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function createDefaultTier(Community $community): CommunityTier
    {
        return $community->tiers()->create([
            'name' => (string) config('communities.default_tier.name', 'Member'),
            'rank' => (int) config('communities.default_tier.rank', 1),
            'assignment_rule' => TierAssignmentRule::Manual->value,
            'threshold' => null,
            'permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
            'is_default' => true,
        ]);
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source);

        if ($base === '') {
            $base = 'community';
        }

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Community::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
