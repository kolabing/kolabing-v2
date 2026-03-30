<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GamificationBadgeSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property GamificationBadgeSlug $badge_slug
 * @property \Illuminate\Support\Carbon $earned_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class EarnedBadge extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'badge_slug',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'badge_slug' => GamificationBadgeSlug::class,
            'earned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
