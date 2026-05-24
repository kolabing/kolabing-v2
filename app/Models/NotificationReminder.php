<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property NotificationType $type
 * @property string $entity_id
 * @property string $entity_type
 * @property \Illuminate\Support\Carbon|null $anchor_at
 * @property int $next_sequence
 * @property int|null $last_sent_sequence
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class NotificationReminder extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'type',
        'entity_id',
        'entity_type',
        'anchor_at',
        'next_sequence',
        'last_sent_sequence',
        'scheduled_for',
        'sent_at',
        'cancelled_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'anchor_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
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
