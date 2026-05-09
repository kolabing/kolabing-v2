<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $notification_id
 * @property string $device_token_id
 * @property string $provider
 * @property string|null $provider_message_id
 * @property string $status
 * @property int $attempt_count
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property-read Notification $notification
 * @property-read DeviceToken $deviceToken
 */
class NotificationDelivery extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'notification_id',
        'device_token_id',
        'provider',
        'provider_message_id',
        'status',
        'attempt_count',
        'last_error_code',
        'last_error_message',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * @return BelongsTo<DeviceToken, $this>
     */
    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }
}
