<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FriendshipStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $requester_profile_id
 * @property string $addressee_profile_id
 * @property FriendshipStatus $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $requester
 * @property-read Profile $addressee
 */
class Friendship extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var string
     */
    protected $table = 'friendships';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requester_profile_id',
        'addressee_profile_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
        ];
    }

    /**
     * The profile that sent the request.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'requester_profile_id');
    }

    /**
     * The profile that received the request.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'addressee_profile_id');
    }

    /**
     * Whether this friendship is accepted (the two profiles are friends).
     */
    public function isAccepted(): bool
    {
        return $this->status === FriendshipStatus::Accepted;
    }

    /**
     * Whether this friendship is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === FriendshipStatus::Pending;
    }
}
