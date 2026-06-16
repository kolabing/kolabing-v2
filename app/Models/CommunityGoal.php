<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityGoalEarnType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_id
 * @property string $title
 * @property CommunityGoalEarnType $earn_type
 * @property int $target
 * @property int $reward_points
 * @property string|null $challenge_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Challenge|null $challenge
 */
class CommunityGoal extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityGoalFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'title',
        'earn_type',
        'target',
        'reward_points',
        'challenge_id',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'earn_type' => CommunityGoalEarnType::class,
            'target' => 'integer',
            'reward_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
