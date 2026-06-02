<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeBonusType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $collaboration_id
 * @property string $challenge_id
 * @property ChallengeBonusType $bonus_type
 * @property string $bonus_value
 * @property string|null $bonus_description
 * @property string $set_by_profile_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Collaboration $collaboration
 * @property-read Challenge $challenge
 * @property-read Profile $setBy
 */
class CollaborationChallengeBonus extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'collaboration_id',
        'challenge_id',
        'bonus_type',
        'bonus_value',
        'bonus_description',
        'set_by_profile_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'bonus_type' => ChallengeBonusType::class,
        ];
    }

    /**
     * @return BelongsTo<Collaboration, $this>
     */
    public function collaboration(): BelongsTo
    {
        return $this->belongsTo(Collaboration::class);
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
    public function setBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'set_by_profile_id');
    }
}
