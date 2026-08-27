<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeCompletionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $challenge_id
 * @property string $event_id
 * @property string $challenger_profile_id
 * @property string $verifier_profile_id
 * @property ChallengeCompletionStatus $status
 * @property int $points_earned
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Challenge $challenge
 * @property-read Event $event
 * @property-read Profile $challenger
 * @property-read Profile $verifier
 */
class ChallengeCompletion extends Model
{
    /** @use HasFactory<\Database\Factories\ChallengeCompletionFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Where this pair now stands on the ladder, set by EncounterService right
     * after a verification (#244).
     *
     * A REAL property, not an Eloquent attribute: assigning an undeclared name
     * on a model puts it in $attributes, and the next save() would try to write
     * a `pair_level` column that does not exist. Declared here, PHP sets the
     * property directly and Eloquent never sees it.
     *
     * @var array{times_met:int,key:string,next_at:int|null,just_levelled_up:bool,bonus_awarded:int}|null
     */
    public ?array $pairLevel = null;

    /** @var list<string> */
    protected $fillable = [
        'challenge_id',
        'event_id',
        'challenger_profile_id',
        'verifier_profile_id',
        'status',
        'points_earned',
        'proof_photo_url',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ChallengeCompletionStatus::class,
            'points_earned' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Challenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'challenger_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'verifier_profile_id');
    }

    public function isPending(): bool
    {
        return $this->status === ChallengeCompletionStatus::Pending;
    }

    public function isVerified(): bool
    {
        return $this->status === ChallengeCompletionStatus::Verified;
    }
}
