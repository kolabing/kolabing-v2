<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $owner_profile_id
 * @property string|null $community_profile_id
 * @property string $name
 * @property string $slug
 * @property CommunityType $type
 * @property string|null $description
 * @property string|null $avatar_url
 * @property bool $is_primary
 * @property bool $is_featured
 * @property JoinPolicy $join_policy
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $owner
 * @property-read CommunityProfile|null $communityProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityTier> $tiers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityMember> $members
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
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommunityType::class,
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
}
