<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityPointSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $community_id
 * @property string $profile_id
 * @property int $points
 * @property CommunityPointSource $source
 * @property string|null $reference_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class CommunityPointLedger extends Model
{
    /** @use HasFactory<\Database\Factories\CommunityPointLedgerFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'community_point_ledger';

    /** @var list<string> */
    protected $fillable = [
        'community_id',
        'profile_id',
        'points',
        'source',
        'reference_id',
        'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'source' => CommunityPointSource::class,
        ];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
