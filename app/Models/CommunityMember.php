<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityMemberStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property string|null $tier_id
 * @property bool $can_manage
 * @property CommunityMemberStatus $status
 * @property \Illuminate\Support\Carbon $joined_at
 * @property \Illuminate\Support\Carbon|null $tier_assigned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Profile $profile
 * @property-read CommunityTier|null $tier
 */
class CommunityMember extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityMemberFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'profile_id',
        'tier_id',
        'can_manage',
        'status',
        'joined_at',
        'last_attended_at',
        'tier_assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_manage' => 'boolean',
            'status' => CommunityMemberStatus::class,
            'joined_at' => 'datetime',
            'last_attended_at' => 'datetime',
            'tier_assigned_at' => 'datetime',
        ];
    }

    /**
     * How recently a member has to have turned up to count as **Active**
     * (kolabing-app#147).
     *
     * One place, because the number appears in a scope, in counts, in the app's
     * copy and in tests, and three of those silently disagreeing is how a
     * definition rots.
     */
    public const ACTIVE_WINDOW_DAYS = 90;

    /**
     * Members who attended within [self::ACTIVE_WINDOW_DAYS].
     *
     * Someone who has not is **still a Member** — they only stop counting as
     * Active. Nothing expires, nothing is deleted, and their next check-in makes
     * them Active again with no other action: `last_attended_at` moves and this
     * scope starts matching them.
     *
     * @param  Builder<CommunityMember>  $query
     * @return Builder<CommunityMember>
     */
    public function scopeActiveMembers(Builder $query): Builder
    {
        return $query->where('status', CommunityMemberStatus::Active->value)
            ->where('last_attended_at', '>=', now()->subDays(self::ACTIVE_WINDOW_DAYS));
    }

    /**
     * Whether this member counts as Active right now.
     */
    public function isActiveMember(): bool
    {
        return $this->status === CommunityMemberStatus::Active
            && $this->last_attended_at !== null
            && $this->last_attended_at->gte(now()->subDays(self::ACTIVE_WINDOW_DAYS));
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

    /**
     * @return BelongsTo<CommunityTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CommunityTier::class, 'tier_id');
    }
}
