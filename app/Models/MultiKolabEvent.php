<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Multi-Kolab Event: one organizer-authored brief that recruits multiple
 * role-specific partners. Distinct from the attendee {@see \App\Models\Event}
 * (per Global Constraints — never reuse the bare "Event" name/model).
 *
 * @property string $id
 * @property string $creator_profile_id
 * @property MultiKolabEventStatus $status
 * @property string $title
 * @property string|null $description
 * @property string|null $value_summary
 * @property bool $venue_needed
 * @property string|null $date_mode
 * @property \Illuminate\Support\Carbon|null $event_date
 * @property \Illuminate\Support\Carbon|null $date_range_start
 * @property \Illuminate\Support\Carbon|null $date_range_end
 * @property string|null $city
 * @property string|null $category
 * @property string|null $rsvp_url
 * @property MultiKolabEligibleAccountType $eligible_account_type
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property-read Profile $creatorProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MultiKolabRole> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MultiKolabEventStatusEvent> $statusEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Kolab> $kolabs
 */
class MultiKolabEvent extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'creator_profile_id',
        'status',
        'title',
        'description',
        'value_summary',
        'venue_needed',
        'date_mode',
        'event_date',
        'date_range_start',
        'date_range_end',
        'city',
        'category',
        'rsvp_url',
        'eligible_account_type',
        'published_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MultiKolabEventStatus::class,
            'eligible_account_type' => MultiKolabEligibleAccountType::class,
            'venue_needed' => 'boolean',
            'event_date' => 'date',
            'date_range_start' => 'date',
            'date_range_end' => 'date',
            'published_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * @return HasMany<MultiKolabRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(MultiKolabRole::class, 'multi_kolab_event_id');
    }

    /**
     * Ordered oldest-first by `id` — UUIDv7 primary keys (see
     * {@see \Illuminate\Database\Eloquent\Concerns\HasUuids}) are time-ordered,
     * so this is a reliable, portable chronological order without depending
     * on `created_at` precision (which can collide within the same second).
     *
     * @return HasMany<MultiKolabEventStatusEvent, $this>
     */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(MultiKolabEventStatusEvent::class, 'multi_kolab_event_id')
            ->orderBy('id');
    }

    /**
     * Child Kolabs created when a role application on this event is accepted.
     *
     * @return HasMany<Kolab, $this>
     */
    public function kolabs(): HasMany
    {
        return $this->hasMany(Kolab::class, 'multi_kolab_event_id');
    }
}
