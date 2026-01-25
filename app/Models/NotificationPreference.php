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
 * @property bool $email_notifications
 * @property bool $whatsapp_notifications
 * @property bool $new_application_alerts
 * @property bool $collaboration_updates
 * @property bool $marketing_tips
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class NotificationPreference extends Model
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
        'email_notifications',
        'whatsapp_notifications',
        'new_application_alerts',
        'collaboration_updates',
        'marketing_tips',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_notifications' => 'boolean',
            'whatsapp_notifications' => 'boolean',
            'new_application_alerts' => 'boolean',
            'collaboration_updates' => 'boolean',
            'marketing_tips' => 'boolean',
        ];
    }

    /**
     * Get the profile that owns this notification preference.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
