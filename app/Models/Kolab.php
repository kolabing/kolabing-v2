<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $creator_profile_id
 * @property string|null $recipient_community_id
 * @property IntentType $intent_type
 * @property KolabStatus $status
 * @property string $title
 * @property string $description
 * @property string|null $goal
 * @property string|null $offer_headline
 * @property string|null $base_offer
 * @property array<int, array{condition: string, additional_offer: string}>|null $negotiation_triggers
 * @property string $preferred_city
 * @property string|null $area
 * @property array<string, mixed>|null $media
 * @property string|null $availability_mode
 * @property \Illuminate\Support\Carbon|null $availability_start
 * @property \Illuminate\Support\Carbon|null $availability_end
 * @property string|null $selected_time
 * @property array<int>|null $recurring_days
 * @property array<string, mixed>|null $needs
 * @property array<string>|null $community_types
 * @property int|null $community_size
 * @property int|null $typical_attendance
 * @property array<string, mixed>|null $offers_in_return
 * @property string|null $venue_preference
 * @property string|null $venue_name
 * @property string|null $venue_type
 * @property int|null $capacity
 * @property string|null $venue_address
 * @property string|null $product_name
 * @property string|null $product_type
 * @property array<string, mixed>|null $offering
 * @property array<string, mixed>|null $seeking_communities
 * @property int|null $min_community_size
 * @property array<string, mixed>|null $expects
 * @property array<string, mixed>|null $past_events
 * @property array<int, string>|null $highlights
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $creatorProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Application> $applications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collaboration> $collaborations
 */
class Kolab extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kolabs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'creator_profile_id',
        'recipient_community_id',
        'intent_type',
        'status',
        'title',
        'description',
        'goal',
        'offer_headline',
        'base_offer',
        'negotiation_triggers',
        'preferred_city',
        'area',
        'media',
        'availability_mode',
        'availability_start',
        'availability_end',
        'selected_time',
        'recurring_days',
        'needs',
        'community_types',
        'community_size',
        'typical_attendance',
        'offers_in_return',
        'venue_preference',
        'venue_name',
        'venue_type',
        'capacity',
        'venue_address',
        'product_name',
        'product_type',
        'offering',
        'seeking_communities',
        'min_community_size',
        'expects',
        'past_events',
        'highlights',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'intent_type' => IntentType::class,
            'status' => KolabStatus::class,
            'negotiation_triggers' => 'array',
            'media' => 'array',
            'availability_start' => 'date',
            'availability_end' => 'date',
            'recurring_days' => 'array',
            'needs' => 'array',
            'community_types' => 'array',
            'offers_in_return' => 'array',
            'offering' => 'array',
            'seeking_communities' => 'array',
            'expects' => 'array',
            'past_events' => 'array',
            'highlights' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the profile that created this kolab.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * Get the explicitly targeted recipient community, if any.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function recipientCommunity(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'recipient_community_id');
    }

    /**
     * Get all applications for this Kolab.
     *
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'kolab_id');
    }

    /**
     * Get all collaborations for this Kolab.
     *
     * @return HasMany<Collaboration, $this>
     */
    public function collaborations(): HasMany
    {
        return $this->hasMany(Collaboration::class, 'kolab_id');
    }

    /**
     * Profiles that have saved/bookmarked this kolab.
     *
     * @return BelongsToMany<Profile, $this>
     */
    public function savedByProfiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'saved_kolabs', 'kolab_id', 'profile_id')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include published kolabs.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KolabStatus::Published);
    }

    /**
     * Scope a query to filter kolabs by city.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopeForCity(Builder $query, string $city): Builder
    {
        return $query->where('preferred_city', $city);
    }

    /**
     * Scope a query to filter kolabs by intent type.
     *
     * @param  Builder<Kolab>  $query
     * @return Builder<Kolab>
     */
    public function scopeByIntent(Builder $query, IntentType $intentType): Builder
    {
        return $query->where('intent_type', $intentType);
    }

    /**
     * Limit to kolabs that still have at least one selectable application date
     * from today onward — the SQL-expressible form of {@see hasSelectableDatesFrom()}:
     * an open (null) or not-yet-passed availability_end. The one case this can't
     * express in portable SQL is a recurring kolab whose remaining (< 7-day)
     * window contains no matching weekday; the apply-time guard in
     * ApplicationService still rejects that, so the feed never surfaces a Kolab
     * you cannot actually apply to (dead-end date picker).
     */
    public function scopeWithSelectableDates(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('availability_end')
                ->orWhereDate('availability_end', '>=', now()->toDateString());
        });
    }

    /**
     * Check if the kolab is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === KolabStatus::Draft;
    }

    /**
     * Check if the kolab is published.
     */
    public function isPublished(): bool
    {
        return $this->status === KolabStatus::Published;
    }

    /**
     * Check if the kolab is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === KolabStatus::Closed;
    }

    /**
     * Whether $date falls inside this kolab's availability window and, for a
     * recurring kolab, matches one of its recurring weekdays (ISO 1..7).
     *
     * Single source of truth for date validity — mirrors the app's
     * buildSelectableApplicationDates logic and the accept-time window check.
     */
    public function isDateWithinAvailability(\Carbon\CarbonInterface $date): bool
    {
        $day = $date->copy()->startOfDay();

        $start = $this->availability_start?->copy()->startOfDay();
        if ($start !== null && $day->lt($start)) {
            return false;
        }

        $end = $this->availability_end?->copy()->startOfDay();
        if ($end !== null && $day->gt($end)) {
            return false;
        }

        if ($this->availability_mode !== 'recurring') {
            return true;
        }

        $recurringDays = collect($this->recurring_days ?? [])
            ->filter(fn (mixed $d): bool => is_numeric($d))
            ->map(fn (mixed $d): int => (int) $d)
            ->values()
            ->all();

        if ($recurringDays === []) {
            return true;
        }

        return in_array($day->dayOfWeekIso, $recurringDays, true);
    }

    /**
     * Whether at least one application date is still selectable from $from
     * onward (today, in practice). Used to close applications on
     * date-exhausted kolabs at apply time, matching accept-time validation.
     */
    public function hasSelectableDatesFrom(\Carbon\CarbonInterface $from): bool
    {
        $fromDay = $from->copy()->startOfDay();

        $cursor = $this->availability_start?->copy()->startOfDay() ?? $fromDay;
        if ($cursor->lt($fromDay)) {
            $cursor = $fromDay;
        }

        $end = $this->availability_end?->copy()->startOfDay();
        if ($end !== null && $cursor->gt($end)) {
            return false;
        }

        // Scan day-by-day within the window (bounded by its length). For an
        // open-ended window cap the look-ahead so this can never loop away.
        $scanEnd = $end ?? $cursor->copy()->addDays(90);

        $day = $cursor->copy();
        while ($day->lte($scanEnd)) {
            if ($this->isDateWithinAvailability($day)) {
                return true;
            }
            $day = $day->addDay();
        }

        return false;
    }
}
