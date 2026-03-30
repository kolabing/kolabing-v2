<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $points
 * @property int $redeemed_points
 * @property bool $pending_withdrawal
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class Wallet extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'points',
        'redeemed_points',
        'pending_withdrawal',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'redeemed_points' => 'integer',
            'pending_withdrawal' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function getAvailablePoints(): int
    {
        return $this->points - $this->redeemed_points;
    }

    public function getEurValue(): float
    {
        return round($this->getAvailablePoints() * 0.20, 2);
    }

    public function getProgress(): float
    {
        $available = $this->getAvailablePoints();
        if ($available <= 0) {
            return 0.0;
        }

        return round(min($available / 375, 1.0), 4);
    }

    public function canWithdraw(): bool
    {
        return $this->getAvailablePoints() >= 375 && ! $this->pending_withdrawal;
    }
}
