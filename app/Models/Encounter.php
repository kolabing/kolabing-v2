<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person you have met, from one viewer's side (#244).
 *
 * Rows are written one per direction, so a meeting between two real profiles is
 * two rows. That is what makes "who have I met" an index scan on `profile_id`
 * rather than an OR across two columns, and it is why a ghost — someone met at
 * an event who does not have the app — can exist as a single row with nobody on
 * the other end of it.
 *
 * @property string $id
 * @property string $profile_id
 * @property string|null $other_profile_id
 * @property string|null $ghost_name
 * @property string|null $community_id
 * @property string $event_id
 * @property \Illuminate\Support\Carbon $met_at
 * @property int $times_met
 * @property string|null $proof_photo_url
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property-read Profile $profile
 * @property-read Profile|null $other
 * @property-read Event $event
 * @property-read Community|null $community
 */
class Encounter extends Model
{
    /** @use HasFactory<\Database\Factories\EncounterFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'profile_id',
        'other_profile_id',
        'ghost_name',
        'community_id',
        'event_id',
        'met_at',
        'times_met',
        'proof_photo_url',
        'claimed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'met_at' => 'datetime',
            'times_met' => 'integer',
            'claimed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /** @return BelongsTo<Profile, $this> */
    public function other(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'other_profile_id');
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Nobody is on the other end of this yet.
     */
    public function isGhost(): bool
    {
        return $this->other_profile_id === null;
    }
}
