<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use App\Enums\TierAssignmentRule;
use App\Exceptions\CommunityLimitReachedException;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityService
{
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
            $community = Community::query()->create([
                'owner_profile_id' => $owner->id,
                'community_profile_id' => $data['community_profile_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
                'type' => $data['type'] ?? CommunityType::Other->value,
                'description' => $data['description'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'is_primary' => $existing === 0,
                'join_policy' => $data['join_policy'] ?? JoinPolicy::Open->value,
            ]);

            $this->createDefaultTier($community);

            return $community->load('tiers');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Community $community, array $data): Community
    {
        $community->fill(array_filter([
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? null,
            'description' => $data['description'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'join_policy' => $data['join_policy'] ?? null,
            'community_profile_id' => $data['community_profile_id'] ?? null,
        ], static fn ($value) => $value !== null));

        $community->save();

        return $community->refresh();
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
