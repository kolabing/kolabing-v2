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

    /**
     * Total XP points available (reputation). NOT cash-convertible.
     */
    public function getAvailablePoints(): int
    {
        return $this->points - $this->redeemed_points;
    }

    /**
     * Points available to withdraw as cash. Backed by REFERRAL rewards ONLY —
     * XP from challenges, reviews, UGC and collaborations is reputation and is
     * never cash-convertible. Computed from the point ledger as referral
     * credits minus amounts already withdrawn, so it stays self-consistent
     * with the withdrawal flow.
     */
    public function getReferralAvailablePoints(): int
    {
        $earned = (int) PointLedger::query()
            ->where('profile_id', $this->profile_id)
            ->where('event_type', PointEventType::ReferralConversion)
            ->sum('points');

        $withdrawn = abs((int) PointLedger::query()
            ->where('profile_id', $this->profile_id)
            ->where('event_type', PointEventType::Withdrawal)
            ->sum('points'));

        return max(0, $earned - $withdrawn);
    }
}
