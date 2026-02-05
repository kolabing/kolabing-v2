<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ChallengeService
{
    /**
     * List challenges for an event: system challenges + event-specific custom challenges.
     */
    public function listForEvent(Event $event, int $perPage = 20): LengthAwarePaginator
    {
        return Challenge::query()
            ->where(function ($q) use ($event) {
                $q->where('is_system', true)
                    ->orWhere('event_id', $event->id);
            })
            ->orderBy('is_system', 'desc')
            ->orderBy('difficulty')
            ->paginate($perPage);
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
