<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $referral_code_id
 * @property string $referred_profile_id
 * @property string $business_subscription_id
 * @property \Illuminate\Support\Carbon $rewarded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read ReferralCode $referralCode
 * @property-read Profile $referredProfile
 * @property-read BusinessSubscription $businessSubscription
 */
class ReferralRedemption extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'referral_code_id',
        'referred_profile_id',
        'business_subscription_id',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'rewarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReferralCode, $this>
     */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function referredProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'referred_profile_id');
    }

    /**
     * @return BelongsTo<BusinessSubscription, $this>
     */
    public function businessSubscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class);
    }
}
