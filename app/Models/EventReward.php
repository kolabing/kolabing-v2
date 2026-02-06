<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $event_id
 * @property string $name
 * @property string|null $description
 * @property int $total_quantity
 * @property int $remaining_quantity
 * @property float $probability
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardClaim> $claims
 */
class EventReward extends Model
{
    /** @use HasFactory<\Database\Factories\EventRewardFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'total_quantity',
        'remaining_quantity',
        'probability',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'probability' => 'decimal:4',
            'expires_at' => 'datetime',
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
     * @return HasMany<RewardClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(RewardClaim::class);
    }

    /**
     * Check if the reward still has stock available.
     */
    public function hasStock(): bool
    {
        return $this->remaining_quantity > 0;
    }

    /**
     * Check if the reward has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
