<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PointEventType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property PointEventType $event_type
 * @property string|null $reference_id
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class PointLedger extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'point_ledger';

    protected $fillable = [
        'profile_id',
        'points',
        'event_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'event_type' => PointEventType::class,
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
