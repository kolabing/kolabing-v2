<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartnerStatusTier;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property PartnerStatusTier $status
 * @property int $completed_kolabs_count
 * @property int $review_count
 * @property int $repeat_partner_count
 * @property float|null $average_rating
 * @property \Illuminate\Support\Carbon|null $recalculated_at
 * @property-read Profile $profile
 */
class BusinessPartnerStatus extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'status',
        'completed_kolabs_count',
        'review_count',
        'repeat_partner_count',
        'average_rating',
        'recalculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PartnerStatusTier::class,
            'completed_kolabs_count' => 'integer',
            'review_count' => 'integer',
            'repeat_partner_count' => 'integer',
            'average_rating' => 'float',
            'recalculated_at' => 'datetime',
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
