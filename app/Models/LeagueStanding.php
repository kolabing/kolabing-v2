<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $season_id
 * @property string $profile_id
 * @property string $division
 * @property int $rank
 * @property int $points
 * @property array<string, int> $score_breakdown
 * @property string|null $movement
 * @property string|null $badge
 * @property \Illuminate\Support\Carbon|null $intro_done_at
 * @property-read LeagueSeason $season
 * @property-read Profile $profile
 */
class LeagueStanding extends Model
{
    use HasUuids;

    public const BADGE_CHAMPION = 'champion';

    public const BADGE_TOP3 = 'top3';

    protected $fillable = [
        'season_id',
        'profile_id',
        'division',
        'rank',
        'points',
        'score_breakdown',
        'movement',
        'badge',
        'intro_done_at',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'points' => 'integer',
            'score_breakdown' => 'array',
            'intro_done_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LeagueSeason, $this>
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(LeagueSeason::class, 'season_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isIntroDue(): bool
    {
        return $this->badge === self::BADGE_CHAMPION && $this->intro_done_at === null;
    }
}
