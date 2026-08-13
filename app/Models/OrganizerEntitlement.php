<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizerCapability;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A maintainer-granted capability on a profile, independent from
 * {@see Profile::hasActiveSubscription()}. MVP scope is the single
 * {@see OrganizerCapability::EventCreator} capability (Task 3 adds the
 * live-read helper + grant/revoke service; this model only carries the
 * identity/relations Task 2 needs).
 *
 * @property string $id
 * @property string $profile_id
 * @property OrganizerCapability $capability
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $granted_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property-read Profile $profile
 */
class OrganizerEntitlement extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'capability',
        'source',
        'granted_at',
        'expires_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability' => OrganizerCapability::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}
