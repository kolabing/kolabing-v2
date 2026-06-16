<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $community_id
 * @property string $title
 * @property string|null $description
 * @property int $cost_points
 * @property int|null $stock
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardRedemption> $redemptions
 */
class CommunityReward extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityRewardFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'title',
        'description',
        'cost_points',
        'stock',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cost_points' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'reward_id');
    }
}
