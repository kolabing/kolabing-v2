<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending email invitation to a community.
 *
 * @property string $id
 * @property string $community_id
 * @property string $email
 * @property string|null $tier_id
 * @property string $token
 * @property CommunityInvitationStatus $status
 */
class CommunityInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityInvitationFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'email',
        'tier_id',
        'token',
        'invited_by_profile_id',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_profile_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommunityInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CommunityTier::class, 'tier_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'invited_by_profile_id');
    }

    /** Still redeemable: pending and inside its window. */
    public function isClaimable(): bool
    {
        return $this->status === CommunityInvitationStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
