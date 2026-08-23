<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A challenge a community has chosen to play, and how strictly
 * (kolabing-app#150).
 *
 * The row's existence is the choice — there is no enabled flag. Its two booleans
 * are the community's restrictiveness dial, which is where the product model
 * puts anti-abuse (§9): communities decide, rather than the platform deciding for
 * all of them.
 *
 * @property string $id
 * @property string $community_id
 * @property string $challenge_id
 * @property bool $allow_repeat_with_same_person
 * @property bool $requires_new_person
 * @property-read Community $community
 * @property-read Challenge $challenge
 */
class CommunityChallenge extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'challenge_id',
        'allow_repeat_with_same_person',
        'requires_new_person',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_repeat_with_same_person' => 'boolean',
            'requires_new_person' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<Challenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
