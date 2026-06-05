<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Profile;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FriendshipService
{
    /**
     * Send (or resurface) a friend request from $requester to the target profile.
     *
     * Idempotent: returns the existing row when one already exists in either
     * direction. Rejects self-friending and any pair involving a block.
     *
     * @throws DomainException 'cannot_friend_self' | 'blocked'
     */
    public function sendRequest(Profile $requester, string $addresseeProfileId): Friendship
    {
        if ($requester->id === $addresseeProfileId) {
            throw new DomainException('cannot_friend_self');
        }

        $existing = Friendship::query()
            ->between($requester->id, $addresseeProfileId)
            ->first();

        if ($existing !== null) {
            if ($existing->status === FriendshipStatus::Blocked) {
                throw new DomainException('blocked');
            }

            return $existing;
        }

        return Friendship::create([
            'requester_profile_id' => $requester->id,
            'addressee_profile_id' => $addresseeProfileId,
            'status' => FriendshipStatus::Pending->value,
        ]);
    }

    /**
     * Accept a pending request. Only the addressee may accept.
     *
     * @throws DomainException 'forbidden' | 'not_pending'
     */
    public function accept(Friendship $friendship, Profile $actor): Friendship
    {
        $this->assertAddressee($friendship, $actor);

        if ($friendship->status !== FriendshipStatus::Pending) {
            throw new DomainException('not_pending');
        }

        $friendship->update([
            'status' => FriendshipStatus::Accepted->value,
            'responded_at' => now(),
        ]);

        return $friendship->refresh();
    }

    /**
     * Decline a pending request. Only the addressee may decline. The row is
     * deleted so the requester can send a fresh request later.
     *
     * @throws DomainException 'forbidden' | 'not_pending'
     */
    public function decline(Friendship $friendship, Profile $actor): void
    {
        $this->assertAddressee($friendship, $actor);

        if ($friendship->status !== FriendshipStatus::Pending) {
            throw new DomainException('not_pending');
        }

        $friendship->delete();
    }

    /**
     * Remove an accepted friendship. Either participant may remove.
     *
     * @throws DomainException 'not_friends'
     */
    public function remove(Profile $actor, string $otherProfileId): void
    {
        $friendship = Friendship::query()
            ->between($actor->id, $otherProfileId)
            ->withStatus(FriendshipStatus::Accepted)
            ->first();

        if ($friendship === null) {
            throw new DomainException('not_friends');
        }

        $friendship->delete();
    }

    /**
     * Block another profile. Any existing pending/accepted row between the two
     * is converted into a block owned by the actor (actor = requester side).
     *
     * @throws DomainException 'cannot_block_self'
     */
    public function block(Profile $actor, string $otherProfileId): Friendship
    {
        if ($actor->id === $otherProfileId) {
            throw new DomainException('cannot_block_self');
        }

        return DB::transaction(function () use ($actor, $otherProfileId): Friendship {
            Friendship::query()
                ->between($actor->id, $otherProfileId)
                ->delete();

            return Friendship::create([
                'requester_profile_id' => $actor->id,
                'addressee_profile_id' => $otherProfileId,
                'status' => FriendshipStatus::Blocked->value,
                'responded_at' => now(),
            ]);
        });
    }

    /**
     * Lift a block the actor created.
     *
     * @throws DomainException 'not_blocked'
     */
    public function unblock(Profile $actor, string $otherProfileId): void
    {
        $friendship = Friendship::query()
            ->where('requester_profile_id', $actor->id)
            ->where('addressee_profile_id', $otherProfileId)
            ->withStatus(FriendshipStatus::Blocked)
            ->first();

        if ($friendship === null) {
            throw new DomainException('not_blocked');
        }

        $friendship->delete();
    }

    /**
     * Paginated list of the actor's accepted friendships (with the other party
     * eager-loaded for the resource).
     */
    public function friends(Profile $actor, int $perPage = 25): LengthAwarePaginator
    {
        return Friendship::query()
            ->withStatus(FriendshipStatus::Accepted)
            ->involving($actor->id)
            ->with(['requester', 'addressee'])
            ->orderByDesc('responded_at')
            ->paginate($perPage);
    }

    /**
     * Incoming and outgoing pending requests for the actor.
     *
     * @return array{incoming: Collection<int, Friendship>, sent: Collection<int, Friendship>}
     */
    public function pendingRequests(Profile $actor): array
    {
        $incoming = Friendship::query()
            ->withStatus(FriendshipStatus::Pending)
            ->where('addressee_profile_id', $actor->id)
            ->with('requester')
            ->orderByDesc('created_at')
            ->get();

        $sent = Friendship::query()
            ->withStatus(FriendshipStatus::Pending)
            ->where('requester_profile_id', $actor->id)
            ->with('addressee')
            ->orderByDesc('created_at')
            ->get();

        return ['incoming' => $incoming, 'sent' => $sent];
    }

    /**
     * Profiles that share at least $minShared attended events with the actor
     * (co-attendance via event_checkins), excluding the actor and anyone the
     * actor already has any friendship/block/pending row with.
     *
     * @return Collection<int, Profile>
     */
    public function suggested(Profile $actor, int $minShared = 3, int $limit = 25): Collection
    {
        $myEventIds = DB::table('event_checkins')
            ->where('profile_id', $actor->id)
            ->pluck('event_id');

        if ($myEventIds->isEmpty()) {
            return Profile::query()->whereRaw('1 = 0')->get();
        }

        $excluded = Friendship::query()
            ->involving($actor->id)
            ->get()
            ->map(fn (Friendship $row): string => $row->otherProfileId($actor->id))
            ->push($actor->id)
            ->unique()
            ->all();

        $candidateIds = DB::table('event_checkins')
            ->whereIn('event_id', $myEventIds)
            ->whereNotIn('profile_id', $excluded)
            ->groupBy('profile_id')
            ->havingRaw('COUNT(DISTINCT event_id) >= ?', [$minShared])
            ->pluck('profile_id');

        if ($candidateIds->isEmpty()) {
            return Profile::query()->whereRaw('1 = 0')->get();
        }

        return Profile::query()
            ->whereIn('id', $candidateIds)
            ->limit($limit)
            ->get();
    }

    /**
     * @throws DomainException 'forbidden'
     */
    private function assertAddressee(Friendship $friendship, Profile $actor): void
    {
        if ($friendship->addressee_profile_id !== $actor->id) {
            throw new DomainException('forbidden');
        }
    }
}
