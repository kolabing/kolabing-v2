<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganiserLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property OrganiserLevel $level
 * @property OrganiserLevel|null $previous_level
 * @property array<string, mixed> $criteria
 * @property \Illuminate\Support\Carbon $evaluated_at
 * @property int $discovery_score
 * @property \Illuminate\Support\Carbon|null $intro_done_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class OrganiserLevelSnapshot extends Model
{
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'level',
        'previous_level',
        'criteria',
        'evaluated_at',
        'discovery_score',
        'intro_done_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => OrganiserLevel::class,
            'previous_level' => OrganiserLevel::class,
            'criteria' => 'array',
            'evaluated_at' => 'datetime',
            'discovery_score' => 'integer',
            'intro_done_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Only the newest snapshot of each profile.
     *
     * @param  Builder<OrganiserLevelSnapshot>  $query
     */
    public function scopeLatestPerProfile(Builder $query): void
    {
        $query->whereRaw(
            'organiser_level_snapshots.evaluated_at = (select max(s2.evaluated_at) from organiser_level_snapshots s2 where s2.profile_id = organiser_level_snapshots.profile_id)'
        );
    }

    public static function latestFor(string $profileId): ?self
    {
        return self::query()
            ->where('profile_id', $profileId)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    public function isIntroDue(): bool
    {
        return $this->level === OrganiserLevel::Top
            && (bool) config('incentives.organiser_levels.levels.top.admin_intro_flag', false)
            && $this->intro_done_at === null;
    }
}
