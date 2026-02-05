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

    /** @var list<string> */
    protected $fillable = [
        'challenge_id',
        'event_id',
        'challenger_profile_id',
        'verifier_profile_id',
        'status',
        'points_earned',
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
