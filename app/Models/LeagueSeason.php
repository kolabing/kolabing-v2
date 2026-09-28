<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $city_id
 * @property string $month
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon $ends_at
 * @property bool $divisions_enabled
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property-read City $city
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LeagueStanding> $standings
 */
class LeagueSeason extends Model
{
    use HasUuids;

    protected $fillable = [
        'city_id',
        'month',
        'starts_at',
        'ends_at',
        'divisions_enabled',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'divisions_enabled' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return HasMany<LeagueStanding, $this>
     */
    public function standings(): HasMany
    {
        return $this->hasMany(LeagueStanding::class, 'season_id');
    }
}
