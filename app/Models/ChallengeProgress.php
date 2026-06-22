<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $challenge_id
 * @property string $profile_id
 * @property int $progress_count
 * @property int $target_value
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $period_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Challenge $challenge
 * @property-read Profile $profile
 */
class ChallengeProgress extends Model
{
    /** @use HasFactory<\Database\Factories\ChallengeProgressFactory> */
    use HasFactory;

    use HasUuids;

    /** @var string */
    protected $table = 'challenge_progress';

    /** @var list<string> */
    protected $fillable = [
        'challenge_id',
        'profile_id',
        'progress_count',
        'target_value',
        'completed_at',
        'period_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'progress_count' => 'integer',
            'target_value' => 'integer',
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
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
