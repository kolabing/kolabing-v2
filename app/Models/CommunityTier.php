<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TierAssignmentRule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $community_id
 * @property string $name
 * @property int $rank
 * @property string|null $color
 * @property TierAssignmentRule $assignment_rule
 * @property int|null $threshold
 * @property array<string, mixed>|null $permissions
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityMember> $members
 */
class CommunityTier extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityTierFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'name',
        'rank',
        'color',
        'assignment_rule',
        'threshold',
        'permissions',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'assignment_rule' => TierAssignmentRule::class,
            'threshold' => 'integer',
            'permissions' => 'array',
            'is_default' => 'boolean',
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
     * @return HasMany<CommunityMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class, 'tier_id');
    }
}
