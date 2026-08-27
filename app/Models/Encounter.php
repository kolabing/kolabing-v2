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
 * @property string|null $ghost_claim_token
 * @property string|null $ghost_contact
 * @property string|null $challenge_id
 * @property int $pending_points
 * @property \Illuminate\Support\Carbon|null $expires_at
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
        'ghost_claim_token',
        'ghost_contact',
        'challenge_id',
        'community_id',
        'event_id',
        'met_at',
        'times_met',
        'pending_points',
        'proof_photo_url',
        'claimed_at',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'met_at' => 'datetime',
            'times_met' => 'integer',
            'pending_points' => 'integer',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
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

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * Nobody is on the other end of this yet.
     */
    public function isGhost(): bool
    {
        return $this->other_profile_id === null;
    }

    /**
     * A ghost nobody has claimed and whose window has not closed — the only
     * kind that counts towards the per-event cap, and the only kind a code can
     * still redeem.
     */
    public function isClaimable(): bool
    {
        return $this->isGhost()
            && $this->claimed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
