<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RewardRedemptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property string $reward_id
 * @property int $points_spent
 * @property RewardRedemptionStatus $status
 * @property \Illuminate\Support\Carbon $redeemed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read CommunityReward $reward
 * @property-read Profile $profile
 */
class RewardRedemption extends Model
{
    /** @use HasFactory<\Database\Factories\RewardRedemptionFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'profile_id',
        'reward_id',
        'points_spent',
        'status',
        'redeemed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'status' => RewardRedemptionStatus::class,
            'redeemed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CommunityReward, $this> */
    public function reward(): BelongsTo
    {
        return $this->belongsTo(CommunityReward::class, 'reward_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
