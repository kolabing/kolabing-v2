<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Profile;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FriendshipService
{
    /**
     * Send a friend request from $requester to $addressee.
     *
     * If a reverse pending request already exists (the addressee had already
     * asked the requester), the relationship is auto-accepted (mutual).
     *
     * @throws DomainException 'cannot_friend_self' | 'already_friends' | 'request_exists'
     */
    public function request(Profile $requester, Profile $addressee): Friendship
    {
        if ($requester->id === $addressee->id) {
            throw new DomainException('cannot_friend_self');
        }

        return DB::transaction(function () use ($requester, $addressee): Friendship {
            $existing = $this->existingBetween($requester->id, $addressee->id);

            if ($existing !== null) {
                if ($existing->status === FriendshipStatus::Accepted) {
                    throw new DomainException('already_friends');
                }

                // A reverse pending request exists → mutual interest, auto-accept.
                if ($existing->status === FriendshipStatus::Pending
                    && $existing->addressee_profile_id === $requester->id) {
                    $existing->update(['status' => FriendshipStatus::Accepted]);

                    return $existing->refresh();
                }

                // An outgoing pending request from me already exists, or the pair
                // is blocked.
                throw new DomainException('request_exists');
            }

            return Friendship::query()->create([
                'requester_profile_id' => $requester->id,
                'addressee_profile_id' => $addressee->id,
                'status' => FriendshipStatus::Pending,
            ]);
        });
    }

    /**
     * Accept an incoming pending request where $viewer is the addressee.
     *
     * @throws DomainException 'no_pending_request'
     */
    public function accept(Profile $viewer, Profile $other): Friendship
    {
        $friendship = Friendship::query()
            ->where('status', FriendshipStatus::Pending)
            ->where('requester_profile_id', $other->id)
            ->where('addressee_profile_id', $viewer->id)
            ->first();

        if ($friendship === null) {
            throw new DomainException('no_pending_request');
        }

        $friendship->update(['status' => FriendshipStatus::Accepted]);

        return $friendship->refresh();
    }

    /**
     * Decline an incoming pending request where $viewer is the addressee.
     * Deletes the row. Returns whether a row was removed.
     */
    public function decline(Profile $viewer, Profile $other): bool
    {
        return Friendship::query()
            ->where('status', FriendshipStatus::Pending)
            ->where('requester_profile_id', $other->id)
            ->where('addressee_profile_id', $viewer->id)
            ->delete() > 0;
    }

    /**
     * Remove an accepted friend OR cancel an outgoing pending request.
     * Deletes whichever row connects the two profiles (any direction).
     * Returns whether a row was removed.
     */
    public function remove(Profile $viewer, Profile $other): bool
    {
        return $this->pairQuery($viewer->id, $other->id)->delete() > 0;
    }

    /**
     * Resolve the relationship status of $profile from $viewer's perspective.
     *
     * @return 'self'|'none'|'pending_outgoing'|'pending_incoming'|'friends'
     */
    public function statusFor(Profile $viewer, Profile $profile): string
    {
        if ($viewer->id === $profile->id) {
            return 'self';
        }

        $friendship = $this->existingBetween($viewer->id, $profile->id);

        if ($friendship === null) {
            return 'none';
        }

        if ($friendship->status === FriendshipStatus::Accepted) {
            return 'friends';
        }

        if ($friendship->status === FriendshipStatus::Pending) {
            return $friendship->requester_profile_id === $viewer->id
                ? 'pending_outgoing'
                : 'pending_incoming';
        }

        // blocked (or any other state) presents as no actionable relationship.
        return 'none';
    }

    /**
     * Count accepted friends for a profile.
     */
    public function friendsCountFor(Profile $profile): int
    {
        return $this->acceptedQuery($profile->id)->count();
    }

    /**
     * Paginated accepted friends of a profile (as Profile models).
     *
     * @return LengthAwarePaginator<Profile>
     */
    public function friendsOf(Profile $profile, int $perPage = 20): LengthAwarePaginator
    {
        $friendIds = $this->acceptedQuery($profile->id)
            ->get()
            ->map(fn (Friendship $f): string => $f->requester_profile_id === $profile->id
                ? $f->addressee_profile_id
                : $f->requester_profile_id);

        return Profile::query()
            ->whereIn('id', $friendIds)
            ->with(['attendeeProfile', 'businessProfile', 'communityProfile'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Incoming pending requests for a profile (the requesters' Profile models).
     *
     * @return Collection<int, Profile>
     */
    public function incomingRequests(Profile $profile): Collection
    {
        $requesterIds = Friendship::query()
            ->where('status', FriendshipStatus::Pending)
            ->where('addressee_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->pluck('requester_profile_id');

        return Profile::query()
            ->whereIn('id', $requesterIds)
            ->with(['attendeeProfile', 'businessProfile', 'communityProfile'])
            ->get();
    }

    /**
     * The single friendship row connecting two profiles in either direction.
     */
    private function existingBetween(string $a, string $b): ?Friendship
    {
        return $this->pairQuery($a, $b)->first();
    }

    /**
     * Query matching the row that connects two profiles in either direction.
     *
     * @return Builder<Friendship>
     */
    private function pairQuery(string $a, string $b): Builder
    {
        return Friendship::query()->where(function (Builder $q) use ($a, $b): void {
            $q->where(function (Builder $inner) use ($a, $b): void {
                $inner->where('requester_profile_id', $a)
                    ->where('addressee_profile_id', $b);
            })->orWhere(function (Builder $inner) use ($a, $b): void {
                $inner->where('requester_profile_id', $b)
                    ->where('addressee_profile_id', $a);
            });
        });
    }

    /**
     * Query for accepted friendships involving a profile (either direction).
     *
     * @return Builder<Friendship>
     */
    private function acceptedQuery(string $profileId): Builder
    {
        return Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(function (Builder $q) use ($profileId): void {
                $q->where('requester_profile_id', $profileId)
                    ->orWhere('addressee_profile_id', $profileId);
            });
    }
}
