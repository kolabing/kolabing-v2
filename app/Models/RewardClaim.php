<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RewardClaimStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_reward_id
 * @property string $profile_id
 * @property string|null $challenge_completion_id
 * @property RewardClaimStatus $status
 * @property Carbon $won_at
 * @property Carbon|null $redeemed_at
 * @property string|null $redeem_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EventReward $eventReward
 * @property-read Profile $profile
 * @property-read ChallengeCompletion|null $challengeCompletion
 */
class RewardClaim extends Model
{
    /** @use HasFactory<\Database\Factories\RewardClaimFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'event_reward_id',
        'profile_id',
        'challenge_completion_id',
        'status',
        'won_at',
        'redeemed_at',
        'redeem_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RewardClaimStatus::class,
            'won_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventReward, $this>
     */
    public function eventReward(): BelongsTo
    {
        return $this->belongsTo(EventReward::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return BelongsTo<ChallengeCompletion, $this>
     */
    public function challengeCompletion(): BelongsTo
    {
        return $this->belongsTo(ChallengeCompletion::class);
    }

    /**
     * Check if the reward claim is available for redemption.
     */
    public function isAvailable(): bool
    {
        return $this->status === RewardClaimStatus::Available;
    }

    /**
     * Check if the reward claim has been redeemed.
     */
    public function isRedeemed(): bool
    {
        return $this->status === RewardClaimStatus::Redeemed;
    }
}
