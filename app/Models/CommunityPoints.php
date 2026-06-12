<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denormalised per-community points balance for one member.
 *
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property int $points
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Community $community
 * @property-read Profile $profile
 */
class CommunityPoints extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityPointsFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'community_points';

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'profile_id',
        'points',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
