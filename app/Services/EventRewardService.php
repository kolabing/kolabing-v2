<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventReward;
use Illuminate\Database\Eloquent\Collection;

class EventRewardService
{
    /**
     * List all rewards for an event, ordered by most recent first.
     *
     * @return Collection<int, EventReward>
     */
    public function listForEvent(Event $event): Collection
    {
        return $event->rewards()
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Create a new reward for an event.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Event $event, array $data): EventReward
    {
        $reward = EventReward::query()->create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_quantity' => $data['total_quantity'],
            'remaining_quantity' => $data['total_quantity'],
            'probability' => $data['probability'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return $reward->load('event');
    }

    /**
     * Update an existing reward.
     *
     * If total_quantity is changed, remaining_quantity is adjusted by the delta,
     * clamped to a minimum of 0.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(EventReward $reward, array $data): EventReward
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['probability'])) {
            $updateData['probability'] = $data['probability'];
        }

        if (array_key_exists('expires_at', $data)) {
            $updateData['expires_at'] = $data['expires_at'];
        }

        if (isset($data['total_quantity'])) {
            $oldTotal = $reward->total_quantity;
            $newTotal = (int) $data['total_quantity'];
            $delta = $newTotal - $oldTotal;

            $updateData['total_quantity'] = $newTotal;
            $updateData['remaining_quantity'] = max(0, $reward->remaining_quantity + $delta);
        }

        if (! empty($updateData)) {
            $reward->update($updateData);
        }

        return $reward->fresh()->load('event');
    }

    /**
     * Delete a reward.
     *
     * @throws \LogicException if the reward has existing claims
     */
    public function delete(EventReward $reward): void
    {
        if ($reward->claims()->exists()) {
            throw new \LogicException('Cannot delete a reward that has existing claims.');
        }

        $reward->delete();
    }
}
