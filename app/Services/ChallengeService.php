<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use App\Models\Community;
use App\Models\CommunityChallenge;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChallengeService
{
    /**
     * The challenges playable at [$event] (kolabing-app#150).
     *
     * Resolution, in order:
     *   1. challenges authored for THIS event — the leader's own, always in;
     *   2. **plus** the community's enabled set, if it has curated one;
     *   3. **else** the whole library.
     *
     * Step 3 is the important one. A community that has curated nothing gets
     * exactly what it got before this existed, because nobody has curated
     * anything yet — until now there was nothing to curate. Making an empty set
     * mean "no challenges" would blank every existing community's events on
     * deploy day. Curating is the act that changes behaviour.
     *
     * The library is `is_system` with a null `trigger_action`: trigger-driven
     * challenges are missions the system progresses, and putting them on an
     * event surface would offer people something they cannot do by choosing it.
     *
     * @return LengthAwarePaginator<Challenge>
     */
    public function listForEvent(Event $event, int $perPage = 20): LengthAwarePaginator
    {
        $enabledIds = $this->enabledChallengeIds($event);

        return Challenge::query()
            ->where(function (Builder $q) use ($event, $enabledIds): void {
                // The event's own, always.
                $q->where('event_id', $event->id);

                if ($enabledIds === null) {
                    // No curation: the whole library, as before.
                    $q->orWhere(function (Builder $library): void {
                        $library->where('is_system', true)->whereNull('trigger_action');
                    });

                    return;
                }

                if ($enabledIds !== []) {
                    $q->orWhere(function (Builder $chosen) use ($enabledIds): void {
                        $chosen->whereIn('id', $enabledIds)->whereNull('trigger_action');
                    });
                }
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('difficulty')
            ->paginate($perPage);
    }

    /**
     * The challenge ids this event's community has chosen, or **null** when it
     * has not curated at all.
     *
     * Null and `[]` mean different things on purpose: null is "no opinion, give
     * them everything", `[]` is "curated down to nothing but their own event
     * challenges". Collapsing them would make it impossible for a community to
     * deliberately play only what its leader wrote.
     *
     * @return array<int, string>|null
     */
    private function enabledChallengeIds(Event $event): ?array
    {
        if ($event->community_id === null) {
            return null;
        }

        $rows = CommunityChallenge::query()
            ->where('community_id', $event->community_id)
            ->pluck('challenge_id')
            ->all();

        return $rows === [] ? null : $rows;
    }

    /**
     * The library a leader picks from.
     *
     * @return LengthAwarePaginator<Challenge>
     */
    public function library(int $perPage = 50): LengthAwarePaginator
    {
        return Challenge::query()
            ->where('is_system', true)
            ->whereNull('trigger_action')
            ->orderBy('difficulty')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Replace a community's whole enabled set.
     *
     * A sync rather than add/remove: the screen is a checklist, so the request
     * is a checklist. An empty array is allowed and meaningful — it returns the
     * community to the whole-library default, and is the only way back.
     *
     * @param  array<int, array{challenge_id: string, allow_repeat_with_same_person?: bool, requires_new_person?: bool}>  $selections
     * @return Collection<int, CommunityChallenge>
     */
    public function syncForCommunity(Community $community, array $selections): Collection
    {
        return DB::transaction(function () use ($community, $selections): Collection {
            $keep = [];

            foreach ($selections as $selection) {
                $row = CommunityChallenge::query()->updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'challenge_id' => $selection['challenge_id'],
                    ],
                    [
                        'allow_repeat_with_same_person' => (bool) ($selection['allow_repeat_with_same_person'] ?? false),
                        'requires_new_person' => (bool) ($selection['requires_new_person'] ?? false),
                    ],
                );

                $keep[] = $row->id;
            }

            // Anything not in the checklist is off. Delete rather than flag:
            // presence is the enablement.
            CommunityChallenge::query()
                ->where('community_id', $community->id)
                ->whereNotIn('id', $keep === [] ? ['-'] : $keep)
                ->delete();

            return CommunityChallenge::query()
                ->where('community_id', $community->id)
                ->with('challenge')
                ->get();
        });
    }

    /**
     * Create a custom challenge for an event.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): Challenge
    {
        $difficulty = ChallengeDifficulty::from($data['difficulty']);

        return Challenge::query()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'difficulty' => $difficulty,
            'points' => $data['points'] ?? $difficulty->points(),
            'is_system' => false,
            'event_id' => $event->id,
        ]);
    }

    /**
     * Update a custom challenge.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Challenge $challenge, array $data): Challenge
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['difficulty'])) {
            $difficulty = ChallengeDifficulty::from($data['difficulty']);
            $updateData['difficulty'] = $difficulty;

            if (! isset($data['points'])) {
                $updateData['points'] = $difficulty->points();
            }
        }

        if (isset($data['points'])) {
            $updateData['points'] = $data['points'];
        }

        if (! empty($updateData)) {
            $challenge->update($updateData);
        }

        return $challenge->fresh();
    }

    /**
     * Delete a custom challenge.
     */
    public function delete(Challenge $challenge): void
    {
        $challenge->delete();
    }

    /**
     * Get all system challenges.
     */
    public function getSystemChallenges(): Collection
    {
        return Challenge::query()->where('is_system', true)->get();
    }
}
