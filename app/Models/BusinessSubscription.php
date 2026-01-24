<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property SubscriptionStatus $status
 * @property \Illuminate\Support\Carbon|null $current_period_start
 * @property \Illuminate\Support\Carbon|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class BusinessSubscription extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    /**
     * Get the profile that owns this subscription.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Check if the subscription is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }
}
