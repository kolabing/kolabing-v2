<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasActiveOwnerScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recurring-event rule. Concrete occurrences live in `events` (linked via
 * `events.series_id`); see {@see \App\Services\EventSeriesService} for the
 * generation + rolling-window logic.
 */
class EventSeries extends Model
{
    use HasActiveOwnerScope;

    /** The column that names this row's owner (#258). */
    protected static string $activeOwnerKey = 'profile_id';

    /** @use HasFactory<\Database\Factories\EventSeriesFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'event_series';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'city_id',
        'profile_id',
        'name',
        'frequency',
        'byweekday',
        'time_of_day',
        'duration_minutes',
        'location',
        'capacity',
        'tier_gate',
        'visibility',
        'chat_mode',
        'ends_mode',
        'ends_count',
        'ends_on',
        'starts_on',
        'materialized_through',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byweekday' => 'array',
            'tier_gate' => 'array',
            'visibility' => \App\Enums\EventVisibility::class,
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'ends_count' => 'integer',
            'ends_on' => 'date',
            'starts_on' => 'date',
            'materialized_through' => 'date',
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
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'series_id')->orderBy('starts_at');
    }
}
