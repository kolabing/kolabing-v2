<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $badge_id
 * @property string $profile_id
 * @property \Illuminate\Support\Carbon $awarded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Badge $badge
 * @property-read Profile $profile
 */
class BadgeAward extends Model
{
    /** @use HasFactory<\Database\Factories\BadgeAwardFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'badge_id',
        'profile_id',
        'awarded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * Get the badge for this award.
     *
     * @return BelongsTo<Badge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    /**
     * Get the profile that received this award.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
