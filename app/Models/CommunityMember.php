<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityMemberStatus;
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
            'tier_assigned_at' => 'datetime',
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

    /**
     * @return BelongsTo<CommunityTier, $this>
     */
    public function tier(): BelongsTo
    {
        return $this->belongsTo(CommunityTier::class, 'tier_id');
    }
}
