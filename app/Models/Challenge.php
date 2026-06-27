<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property ChallengeDifficulty $difficulty
 * @property int $points
 * @property bool $is_system
 * @property ChallengeCategory|null $category
 * @property string|null $event_id
 * @property ChallengeAudience|null $audience
 * @property string|null $slug
 * @property MissionTrigger|null $trigger_action
 * @property int $target_value
 * @property MissionRepeat|null $repeat_interval
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChallengeCompletion> $completions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChallengeProgress> $progress
 */
class Challenge extends Model
{
    /** @use HasFactory<\Database\Factories\ChallengeFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'difficulty',
        'points',
        'is_system',
        'category',
        'event_id',
        'audience',
        'slug',
        'trigger_action',
        'target_value',
        'repeat_interval',
        'starts_at',
        'ends_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'difficulty' => ChallengeDifficulty::class,
            'points' => 'integer',
            'is_system' => 'boolean',
            'category' => ChallengeCategory::class,
            'audience' => ChallengeAudience::class,
            'trigger_action' => MissionTrigger::class,
            'target_value' => 'integer',
            'repeat_interval' => MissionRepeat::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<ChallengeCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(ChallengeCompletion::class);
    }

    /**
     * @return HasMany<ChallengeProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(ChallengeProgress::class);
    }

    public function isSystemChallenge(): bool
    {
        return $this->is_system;
    }

    public function isMission(): bool
    {
        return $this->trigger_action !== null;
    }
}
