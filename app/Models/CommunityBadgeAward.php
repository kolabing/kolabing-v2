<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_badge_id
 * @property string $profile_id
 * @property string $community_id
 * @property \Illuminate\Support\Carbon $awarded_at
 * @property-read CommunityBadge $badge
 */
class CommunityBadgeAward extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityBadgeAwardFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'community_badge_id',
        'profile_id',
        'community_id',
        'awarded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CommunityBadge, $this> */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(CommunityBadge::class, 'community_badge_id');
    }
}
