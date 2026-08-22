<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MultiKolabEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only lifecycle audit row for a {@see MultiKolabEvent} (publish,
 * confirm, complete, cancel). A null {@see actor_profile_id} means a
 * system/maintainer action, mirroring the "null = maintainer" convention on
 * `collaborations.cancelled_by_profile_id` (ROLES-BACKEND-DB-MAP.md §10).
 *
 * @property string $id
 * @property string $multi_kolab_event_id
 * @property MultiKolabEventStatus $status
 * @property string|null $actor_profile_id
 * @property string|null $reason
 * @property-read MultiKolabEvent $event
 * @property-read Profile|null $actorProfile
 */
class MultiKolabEventStatusEvent extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'multi_kolab_event_id',
        'status',
        'actor_profile_id',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MultiKolabEventStatus::class,
        ];
    }

    /**
     * @return BelongsTo<MultiKolabEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(MultiKolabEvent::class, 'multi_kolab_event_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function actorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'actor_profile_id');
    }
}
