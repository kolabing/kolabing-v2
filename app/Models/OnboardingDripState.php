<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-recipient state for the T+0/T+2/T+5/T+10 onboarding email drip.
 *
 * One row per profile (unique on profile_id). Mirrors the shape of
 * {@see NotificationReminder}: an anchor timestamp, a cadence position
 * (next_sequence), and a scheduled_for the drip's sender polls. Cancelling
 * (cancelled_at) stops the drip cleanly when the underlying condition the
 * step exists for resolves (e.g. profile completed, first action taken).
 *
 * @property string $id
 * @property string $profile_id
 * @property \Illuminate\Support\Carbon $anchor_at
 * @property int $next_sequence
 * @property int|null $last_sent_sequence
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class OnboardingDripState extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'anchor_at',
        'next_sequence',
        'last_sent_sequence',
        'scheduled_for',
        'sent_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anchor_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
