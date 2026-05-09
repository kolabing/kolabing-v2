<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $profile_id
 * @property string $token
 * @property string $platform
 * @property string|null $app_version
 * @property string|null $locale
 * @property string|null $timezone
 * @property float|null $last_location_lat
 * @property float|null $last_location_lng
 * @property \Illuminate\Support\Carbon|null $location_permission_granted_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property \Illuminate\Support\Carbon|null $last_delivered_at
 * @property \Illuminate\Support\Carbon|null $invalidated_at
 * @property string|null $invalid_reason
 * @property-read Profile $profile
 */
class DeviceToken extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'profile_id',
        'token',
        'platform',
        'app_version',
        'locale',
        'timezone',
        'last_location_lat',
        'last_location_lng',
        'location_permission_granted_at',
        'is_active',
        'last_seen_at',
        'last_delivered_at',
        'invalidated_at',
        'invalid_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_location_lat' => 'float',
            'last_location_lng' => 'float',
            'location_permission_granted_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_delivered_at' => 'datetime',
            'invalidated_at' => 'datetime',
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
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
