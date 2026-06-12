<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityBadgeCriteriaType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_id
 * @property string $title
 * @property string|null $icon
 * @property CommunityBadgeCriteriaType $criteria_type
 * @property int $criteria_value
 * @property array<int, string>|null $challenge_ids
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 */
class CommunityBadge extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityBadgeFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'title',
        'icon',
        'criteria_type',
        'criteria_value',
        'challenge_ids',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'criteria_type' => CommunityBadgeCriteriaType::class,
            'criteria_value' => 'integer',
            'challenge_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
