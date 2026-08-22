<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $owner_profile_id
 * @property string|null $community_profile_id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property string|null $avatar_url
 * @property bool $is_primary
 * @property bool $is_featured
 * @property JoinPolicy $join_policy
 * @property string|null $invite_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $owner
 * @property-read CommunityProfile|null $communityProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityTier> $tiers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityMember> $members
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityJoinRequest> $joinRequests
 * @property-read CommunityTier|null $defaultTier
 */
class Community extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_profile_id',
        'community_profile_id',
        'name',
        'slug',
        'type',
        'description',
        'avatar_url',
        'is_primary',
        'is_featured',
        'join_policy',
        'invite_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // `type` carries the real 17-slug community-type vocabulary
            // (CommunityOnboardingRequest::COMMUNITY_TYPES) as a raw string.
            // It is intentionally NOT cast to App\Enums\CommunityType (a 5-value
            // placeholder); validation/matching happen against the 17-slug list.
            'join_policy' => JoinPolicy::class,
            'is_primary' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'owner_profile_id');
    }

    /**
     * @return BelongsTo<CommunityProfile, $this>
     */
    public function communityProfile(): BelongsTo
    {
        return $this->belongsTo(CommunityProfile::class);
    }

    /**
     * Communities the profile may administer: the ones it OWNS plus the ones it
     * co-runs as an active member with `can_manage = true`.
     *
     * This is the query form of `CommunityPolicy::manage()` / `ChatService::
     * canManageCommunity()`, and the set `GET /me/communities` lists (BE-FX-15) —
     * a manager who cannot see the community id cannot reach any of the
     * management actions it is otherwise authorised for.
     *
     * @param  Builder<Community>  $query
     * @return Builder<Community>
     */
    public function scopeManageableBy(Builder $query, Profile $profile): Builder
    {
        return $query->where(function (Builder $scoped) use ($profile): void {
            $scoped->where('owner_profile_id', $profile->id)
                ->orWhereHas('members', function (Builder $member) use ($profile): void {
                    $member->where('profile_id', $profile->id)
                        ->where('can_manage', true)
                        ->where('status', CommunityMemberStatus::Active->value);
                });
        });
    }

    /**
     * @return HasMany<CommunityTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(CommunityTier::class)->orderByDesc('rank');
    }

    /**
     * @return HasMany<CommunityMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }

    /**
     * @return HasMany<CommunityJoinRequest, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(CommunityJoinRequest::class);
    }

    /**
     * @return HasMany<CommunityGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(CommunityGoal::class);
    }

    /**
     * @return HasMany<CommunityReward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(CommunityReward::class);
    }

    /**
     * @return HasMany<CommunityBadge, $this>
     */
    public function badges(): HasMany
    {
        return $this->hasMany(CommunityBadge::class);
    }

    /**
     * @return HasOne<CommunityTier, $this>
     */
    public function defaultTier(): HasOne
    {
        return $this->hasOne(CommunityTier::class)->where('is_default', true);
    }

    /**
     * Count of active members.
     */
    public function memberCount(): int
    {
        return $this->members()
            ->where('status', \App\Enums\CommunityMemberStatus::Active->value)
            ->count();
    }

    /**
     * Canonical shareable join link, "<base>/<slug>". Always safe to share;
     * open communities join straight from it, invite_only ones still require a
     * token (use inviteUrlWithToken()).
     */
    public function inviteUrl(): string
    {
        $base = rtrim((string) config('communities.invite_base_url'), '/');

        return $base.'/'.$this->slug;
    }

    /**
     * Token-bearing invite link for invite_only communities. Returns the plain
     * slug link for open communities (no token needed).
     */
    public function inviteUrlWithToken(): string
    {
        if ($this->join_policy !== JoinPolicy::InviteOnly) {
            return $this->inviteUrl();
        }

        return $this->inviteUrl().'?invite='.$this->ensureInviteToken();
    }

    /**
     * Lazily mint (and persist) the invite token. Idempotent: returns the
     * existing token when one is already set.
     */
    public function ensureInviteToken(): string
    {
        if ($this->invite_token === null || $this->invite_token === '') {
            do {
                $token = Str::lower(Str::random(32));
            } while (self::query()->where('invite_token', $token)->exists());

            $this->forceFill(['invite_token' => $token])->save();
        }

        return (string) $this->invite_token;
    }
}
