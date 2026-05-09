<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $profile_id
 * @property NotificationType $type
 * @property string $title
 * @property string $body
 * @property string|null $actor_profile_id
 * @property string|null $target_id
 * @property string|null $target_type
 * @property string|null $deeplink
 * @property string|null $image_url
 * @property array<string, mixed>|null $data
 * @property NotificationPriority $priority
 * @property bool $is_in_app
 * @property bool $is_push
 * @property string|null $dedupe_key
 * @property \Illuminate\Support\Carbon|null $queued_at
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read Profile|null $actorProfile
 * @property-read \Illuminate\Database\Eloquent\Collection<int, NotificationDelivery> $deliveries
 */
class Notification extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'type',
        'title',
        'body',
        'actor_profile_id',
        'target_id',
        'target_type',
        'deeplink',
        'image_url',
        'data',
        'priority',
        'is_in_app',
        'is_push',
        'dedupe_key',
        'queued_at',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'priority' => NotificationPriority::class,
            'is_in_app' => 'boolean',
            'is_push' => 'boolean',
            'queued_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the profile that owns this notification.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the actor profile that triggered this notification.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function actorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'actor_profile_id');
    }

    /**
     * @return HasMany<NotificationDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /**
     * Check if the notification has been read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    /**
     * Scope a query to only include unread notifications.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
