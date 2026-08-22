<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question a leader asks before admitting a member (kolabing-app#138).
 *
 * Retired, never deleted: [$is_active] false takes the question out of the form
 * while its past answers stay readable, so a leader reviewing an older
 * application still sees what was asked. Deleting would cascade the answers away
 * and leave that application meaningless.
 *
 * @property string $id
 * @property string $community_id
 * @property int $position
 * @property string $prompt
 * @property bool $required
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CommunityJoinAnswer> $answers
 */
class CommunityJoinQuestion extends Model
{
    use HasUuids;

    /** How many questions a community may have live at once. */
    public const MAX_ACTIVE = 5;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'position',
        'prompt',
        'required',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The set an applicant is asked, in display order.
     *
     * @param  Builder<CommunityJoinQuestion>  $query
     * @return Builder<CommunityJoinQuestion>
     */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return HasMany<CommunityJoinAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(CommunityJoinAnswer::class, 'question_id');
    }
}
