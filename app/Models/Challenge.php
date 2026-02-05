<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeDifficulty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property ChallengeDifficulty $difficulty
 * @property int $points
 * @property bool $is_system
 * @property string|null $event_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event|null $event
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
        'event_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'difficulty' => ChallengeDifficulty::class,
            'points' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isSystemChallenge(): bool
    {
        return $this->is_system;
    }
}
