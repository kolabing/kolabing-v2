<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row in the role-based defaults matrix: when a new collaboration forms
 * between business type X and community type Y, every challenge with a
 * matching ChallengeDefault row gets auto-selected into
 * collaboration_challenges. See ChallengeDefaultsService.
 *
 * @property string $id
 * @property string $challenge_id
 * @property string $applies_to 'business_type' | 'community_type'
 * @property string $type_value The matching slug, underscored.
 * @property int $position
 */
class ChallengeDefault extends Model
{
    /** @use HasFactory<\Database\Factories\ChallengeDefaultFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'challenge_id',
        'applies_to',
        'type_value',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
