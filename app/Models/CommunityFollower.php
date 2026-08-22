<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone following a community — interest, not membership.
 *
 * A follower can see the community and turn up to its public events (and so
 * play the QR check-in / challenge loop), but gets none of what membership
 * unlocks: the community chat, member- or tier-gated events, community points,
 * badges, the leaderboard, or a tier.
 *
 * Deliberately its own model rather than a flag on [CommunityMember]: every
 * member-gated query in the app reads `community_members`, and keeping
 * followers out of that table means none of them can start matching a follower
 * by accident. See kolabing-app#138.
 *
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property \Illuminate\Support\Carbon $followed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Profile $profile
 */
class CommunityFollower extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'profile_id',
        'followed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'followed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
