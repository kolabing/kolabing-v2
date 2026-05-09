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
 * @property bool $email_notifications
 * @property bool $whatsapp_notifications
 * @property bool $new_application_alerts
 * @property bool $collaboration_updates
 * @property bool $marketing_tips
 * @property bool $messages_enabled
 * @property bool $applications_enabled
 * @property bool $collaborations_enabled
 * @property bool $rewards_enabled
 * @property bool $marketing_enabled
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string|null $timezone
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
        'messages_enabled',
        'applications_enabled',
        'collaborations_enabled',
        'rewards_enabled',
        'marketing_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
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
            'messages_enabled' => 'boolean',
            'applications_enabled' => 'boolean',
            'collaborations_enabled' => 'boolean',
            'rewards_enabled' => 'boolean',
            'marketing_enabled' => 'boolean',
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

    public function allowsPushFor(NotificationType $type): bool
    {
        $field = $type->preferenceField();

        return (bool) $this->{$field};
    }

    public function isQuietHoursActive(?\Illuminate\Support\Carbon $at = null): bool
    {
        if ($this->quiet_hours_start === null || $this->quiet_hours_end === null) {
            return false;
        }

        $timezone = $this->timezone ?: config('app.timezone');
        $now = ($at ?? now())->copy()->timezone($timezone);
        $current = $now->format('H:i:s');

        if ($this->quiet_hours_start <= $this->quiet_hours_end) {
            return $current >= $this->quiet_hours_start && $current <= $this->quiet_hours_end;
        }

        return $current >= $this->quiet_hours_start || $current <= $this->quiet_hours_end;
    }
}
