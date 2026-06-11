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
 * @property string $profile_id
 * @property string|null $community_id
 * @property string|null $city_id
 * @property string $name
 * @property string $partner_name
 * @property string $partner_type
 * @property \Illuminate\Support\Carbon $event_date
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property int $attendee_count
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property string|null $address
 * @property string|null $location
 * @property int|null $capacity
 * @property array<int, mixed>|null $tier_gate
 * @property string $visibility
 * @property string|null $collaboration_id
 * @property int $max_challenges_per_attendee
 * @property bool $is_active
 * @property string|null $checkin_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventPhoto> $photos
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventCheckin> $checkins
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Challenge> $challenges
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventReward> $rewards
 */
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'community_id',
        'city_id',
        'series_id',
        'occurrence_index',
        'collaboration_id',
        'name',
        'partner_name',
        'partner_type',
        'event_date',
        'starts_at',
        'ends_at',
        'attendee_count',
        'location_lat',
        'location_lng',
        'address',
        'location',
        'capacity',
        'tier_gate',
        'visibility',
        'max_challenges_per_attendee',
        'is_active',
        'checkin_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'attendee_count' => 'integer',
            'occurrence_index' => 'integer',
            'location_lat' => 'decimal:7',
            'location_lng' => 'decimal:7',
            'capacity' => 'integer',
            'tier_gate' => 'array',
            'visibility' => \App\Enums\EventVisibility::class,
            'max_challenges_per_attendee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Effective end of the event for the upcoming/past split — prefers the new
     * timestamps, falling back to the legacy date.
     */
    public function effectiveEnd(): \Illuminate\Support\Carbon
    {
        return $this->ends_at
            ?? $this->starts_at
            ?? \Illuminate\Support\Carbon::parse($this->event_date)->endOfDay();
    }

    /**
     * Upcoming = sign-up/RSVP + event chat apply. Past = showcase only.
     */
    public function isUpcoming(): bool
    {
        return $this->effectiveEnd()->isFuture();
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The community this event belongs to (nullable; NF-6 §0.6 linkage).
     *
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The event's own city (where it happens). Nullable; discover falls back to
     * the host community's profile city when this is null.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The recurring series this event is an occurrence of (nullable).
     *
     * @return BelongsTo<EventSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(EventSeries::class);
    }

    /**
     * @return HasMany<EventPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<EventCheckin, $this>
     */
    public function checkins(): HasMany
    {
        return $this->hasMany(EventCheckin::class);
    }

    /**
     * @return HasMany<Challenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /**
     * @return HasMany<EventReward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(EventReward::class);
    }

    /**
     * The collaboration (kolab) this event is linked to, if any.
     *
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
    }

    /**
     * Sign-ups (going / waitlisted / cancelled) for an upcoming event.
     *
     * @return HasMany<EventSignup, $this>
     */
    public function signups(): HasMany
    {
        return $this->hasMany(EventSignup::class);
    }
}
