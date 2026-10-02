<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A community Kolabing invited to apply to a Kolab ({@see \App\Services\KolabInviteService}).
 *
 * @property string $id
 * @property string $kolab_id
 * @property string $profile_id
 * @property int|null $sent_by_admin_id
 * @property string|null $notification_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Kolab $kolab
 * @property-read Profile $profile
 * @property-read User|null $sentByAdmin
 */
class KolabInvite extends Model
{
    use HasUuids;

    protected $table = 'kolab_invites';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kolab_id',
        'profile_id',
        'sent_by_admin_id',
        'notification_id',
    ];

    /**
     * @return BelongsTo<Kolab, $this>
     */
    public function kolab(): BelongsTo
    {
        return $this->belongsTo(Kolab::class);
    }

    /**
     * The invited community.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sentByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_admin_id');
    }
}
