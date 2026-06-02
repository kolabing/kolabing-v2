<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeBonusType;
use App\Models\Challenge;
use App\Models\Collaboration;
use App\Models\CollaborationChallengeBonus;
use App\Models\Profile;
use InvalidArgumentException;

class CollaborationChallengeBonusService
{
    /**
     * Set or update a bonus the business is offering on top of a challenge
     * attached to this collaboration. Idempotent — re-calling with the same
     * (collaboration, challenge) pair updates the existing row.
     *
     * @param  array{bonus_type: string, bonus_value: string, bonus_description?: string|null}  $data
     */
    public function upsert(
        Collaboration $collaboration,
        Challenge $challenge,
        Profile $setBy,
        array $data,
    ): CollaborationChallengeBonus {
        $this->assertChallengeAttached($collaboration, $challenge);

        $type = ChallengeBonusType::from($data['bonus_type']);
        $value = $this->normaliseValue($type, $data['bonus_value']);

        return CollaborationChallengeBonus::query()->updateOrCreate(
            [
                'collaboration_id' => $collaboration->id,
                'challenge_id' => $challenge->id,
            ],
            [
                'bonus_type' => $type,
                'bonus_value' => $value,
                'bonus_description' => $data['bonus_description'] ?? null,
                'set_by_profile_id' => $setBy->id,
            ],
        );
    }

    public function remove(Collaboration $collaboration, Challenge $challenge): bool
    {
        $bonus = CollaborationChallengeBonus::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('challenge_id', $challenge->id)
            ->first();

        if ($bonus === null) {
            return false;
        }

        return (bool) $bonus->delete();
    }

    /**
     * @return array<string, CollaborationChallengeBonus> Keyed by challenge_id.
     */
    public function forCollaboration(Collaboration $collaboration): array
    {
        return CollaborationChallengeBonus::query()
            ->where('collaboration_id', $collaboration->id)
            ->get()
            ->keyBy('challenge_id')
            ->all();
    }

    private function assertChallengeAttached(Collaboration $collaboration, Challenge $challenge): void
    {
        $isAttached = $collaboration->challenges()
            ->where('challenges.id', $challenge->id)
            ->exists();

        if (! $isAttached) {
            throw new InvalidArgumentException(
                'Challenge is not selected for this collaboration.'
            );
        }
    }

    /**
     * Light validation by type. Heavier rules belong in the FormRequest;
     * this is the safety net the service still enforces if a caller
     * bypasses the request.
     */
    private function normaliseValue(ChallengeBonusType $type, string $raw): string
    {
        $value = trim($raw);

        if ($value === '') {
            throw new InvalidArgumentException('Bonus value cannot be empty.');
        }

        if ($type === ChallengeBonusType::DiscountPercent) {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException('Discount percent must be numeric.');
            }
            $percent = (int) $value;
            if ($percent < 1 || $percent > 100) {
                throw new InvalidArgumentException('Discount percent must be between 1 and 100.');
            }

            return (string) $percent;
        }

        return $value;
    }
}
