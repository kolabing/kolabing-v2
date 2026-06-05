<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FriendshipStatus;
use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $requester_profile_id
 * @property string $addressee_profile_id
 * @property FriendshipStatus $status
 * @property \Illuminate\Support\Carbon|null $responded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $requester
 * @property-read Profile $addressee
 */
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requester_profile_id',
        'addressee_profile_id',
        'status',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'requester_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'addressee_profile_id');
    }

    /**
     * Rows in a given status.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeWithStatus(Builder $query, FriendshipStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Rows that involve the given profile on either side.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeInvolving(Builder $query, string $profileId): Builder
    {
        return $query->where(function (Builder $inner) use ($profileId): void {
            $inner->where('requester_profile_id', $profileId)
                ->orWhere('addressee_profile_id', $profileId);
        });
    }

    /**
     * Rows between exactly two profiles, in either direction.
     *
     * @param  Builder<Friendship>  $query
     * @return Builder<Friendship>
     */
    public function scopeBetween(Builder $query, string $profileA, string $profileB): Builder
    {
        return $query->where(function (Builder $inner) use ($profileA, $profileB): void {
            $inner->where(function (Builder $forward) use ($profileA, $profileB): void {
                $forward->where('requester_profile_id', $profileA)
                    ->where('addressee_profile_id', $profileB);
            })->orWhere(function (Builder $reverse) use ($profileA, $profileB): void {
                $reverse->where('requester_profile_id', $profileB)
                    ->where('addressee_profile_id', $profileA);
            });
        });
    }

    /**
     * Resolve the profile id on the other side of this friendship.
     */
    public function otherProfileId(string $profileId): string
    {
        return $this->requester_profile_id === $profileId
            ? $this->addressee_profile_id
            : $this->requester_profile_id;
    }
}
