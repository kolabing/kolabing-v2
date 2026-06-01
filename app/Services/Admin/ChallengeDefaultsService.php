<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Challenge;
use App\Models\ChallengeDefault;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Manages the role-based defaults matrix:
 *  - Admin reads via matrix() / forType() for the matrix UI.
 *  - Admin writes via syncForType() to commit a row of the matrix.
 *  - CollaborationService::createFromApplication() calls
 *    seedForCollaboration() on the freshly-created row so the default
 *    challenges land in collaboration_challenges. Idempotent on collab.
 */
class ChallengeDefaultsService
{
    /**
     * Replace the set of default challenges for a single (applies_to,
     * type_value) row. Used by the admin matrix save.
     *
     * @param  array<int, string>  $challengeIds  In display order.
     */
    public function syncForType(string $appliesTo, string $typeValue, array $challengeIds): void
    {
        DB::transaction(function () use ($appliesTo, $typeValue, $challengeIds): void {
            ChallengeDefault::query()
                ->where('applies_to', $appliesTo)
                ->where('type_value', $typeValue)
                ->delete();

            foreach (array_values(array_unique($challengeIds)) as $position => $challengeId) {
                ChallengeDefault::query()->create([
                    'challenge_id' => $challengeId,
                    'applies_to' => $appliesTo,
                    'type_value' => $typeValue,
                    'position' => $position,
                ]);
            }
        });
    }

    /**
     * Read the challenge ids currently set as defaults for a single
     * (applies_to, type_value) row.
     *
     * @return array<int, string>
     */
    public function forType(string $appliesTo, string $typeValue): array
    {
        return ChallengeDefault::query()
            ->where('applies_to', $appliesTo)
            ->where('type_value', $typeValue)
            ->orderBy('position')
            ->pluck('challenge_id')
            ->all();
    }

    /**
     * Seed a freshly-created collaboration with default challenges based on
     * the participant business + community types. Idempotent: skips if
     * collaboration_challenges already has any rows for this collaboration.
     */
    public function seedForCollaboration(Collaboration $collaboration): void
    {
        if ($collaboration->challenges()->exists()) {
            return;
        }

        $businessType = $this->resolveBusinessType($collaboration);
        $communityType = $this->resolveCommunityType($collaboration);

        $challengeIds = collect();

        if ($businessType !== null) {
            $challengeIds = $challengeIds->merge(
                $this->forType('business_type', $businessType),
            );
        }
        if ($communityType !== null) {
            $challengeIds = $challengeIds->merge(
                $this->forType('community_type', $communityType),
            );
        }

        $unique = $challengeIds->unique()->values();

        if ($unique->isEmpty()) {
            return;
        }

        $collaboration->challenges()->sync($unique->all());
    }

    /**
     * Compact representation of the matrix for the admin view:
     * keyed by "applies_to:type_value", value is the ordered list of
     * challenge IDs.
     *
     * @return array<string, array<int, string>>
     */
    public function matrix(): array
    {
        return ChallengeDefault::query()
            ->orderBy('applies_to')
            ->orderBy('type_value')
            ->orderBy('position')
            ->get()
            ->groupBy(fn (ChallengeDefault $row): string => $row->applies_to.':'.$row->type_value)
            ->map(fn ($rows) => $rows->pluck('challenge_id')->all())
            ->all();
    }

    private function resolveBusinessType(Collaboration $collaboration): ?string
    {
        $collaboration->loadMissing(['creatorProfile.businessProfile', 'applicantProfile.businessProfile']);

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($profile instanceof Profile && $profile->isBusiness()) {
                return $profile->businessProfile?->business_type;
            }
        }

        return null;
    }

    private function resolveCommunityType(Collaboration $collaboration): ?string
    {
        $collaboration->loadMissing(['creatorProfile.communityProfile', 'applicantProfile.communityProfile']);

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($profile instanceof Profile && $profile->isCommunity()) {
                return $profile->communityProfile?->community_type;
            }
        }

        return null;
    }
}
