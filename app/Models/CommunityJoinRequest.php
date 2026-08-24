<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JoinRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property JoinRequestStatus $status
 * @property string|null $decided_by
 * @property \Illuminate\Support\Carbon|null $requested_at
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Profile $profile
 * @property-read Profile|null $decidedBy
 */
class CommunityJoinRequest extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityJoinRequestFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'profile_id',
        'status',
        'decided_by',
        'requested_at',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JoinRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    /**
     * What the applicant answered. Eager-load this wherever a leader reviews
     * the queue — the answers are the substance they decide on.
     *
     * @return HasMany<CommunityJoinAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(CommunityJoinAnswer::class, 'join_request_id');
    }

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
     * @return BelongsTo<Profile, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'decided_by');
    }
}
