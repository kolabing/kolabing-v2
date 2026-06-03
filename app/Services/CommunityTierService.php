<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TierAssignmentRule;
use App\Models\Community;
use App\Models\CommunityTier;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommunityTierService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Community $community, array $data): CommunityTier
    {
        $rule = TierAssignmentRule::from($data['assignment_rule'] ?? TierAssignmentRule::Manual->value);
        $threshold = $data['threshold'] ?? null;
        $this->assertThreshold($rule, $threshold);

        return DB::transaction(function () use ($community, $data, $rule, $threshold): CommunityTier {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->clearDefault($community);
            }

            return $community->tiers()->create([
                'name' => $data['name'],
                'rank' => (int) $data['rank'],
                'color' => $data['color'] ?? null,
                'assignment_rule' => $rule->value,
                'threshold' => $threshold,
                'permissions' => $data['permissions'] ?? ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
                'is_default' => $isDefault,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CommunityTier $tier, array $data): CommunityTier
    {
        $rule = isset($data['assignment_rule'])
            ? TierAssignmentRule::from($data['assignment_rule'])
            : $tier->assignment_rule;

        $threshold = array_key_exists('threshold', $data) ? $data['threshold'] : $tier->threshold;
        $this->assertThreshold($rule, $threshold);

        return DB::transaction(function () use ($tier, $data, $rule, $threshold): CommunityTier {
            if (($data['is_default'] ?? false) === true && ! $tier->is_default) {
                $this->clearDefault($tier->community);
            }

            $tier->fill([
                'name' => $data['name'] ?? $tier->name,
                'rank' => isset($data['rank']) ? (int) $data['rank'] : $tier->rank,
                'color' => array_key_exists('color', $data) ? $data['color'] : $tier->color,
                'assignment_rule' => $rule->value,
                'threshold' => $threshold,
                'permissions' => $data['permissions'] ?? $tier->permissions,
                'is_default' => array_key_exists('is_default', $data) ? (bool) $data['is_default'] : $tier->is_default,
            ]);

            $tier->save();

            return $tier->refresh();
        });
    }

    /**
     * @throws DomainException when attempting to delete the sole default tier.
     */
    public function delete(CommunityTier $tier): void
    {
        // Check live DB state: a sibling promotion may have demoted this tier
        // since the model was loaded.
        if ((bool) $tier->fresh()?->is_default) {
            throw new DomainException('cannot_delete_default_tier');
        }

        DB::transaction(function () use ($tier): void {
            // Members on this tier fall back to null (re-evaluated by the job / on next hook).
            $tier->members()->update(['tier_id' => null]);
            $tier->delete();
        });
    }

    private function clearDefault(Community $community): void
    {
        $community->tiers()->where('is_default', true)->update(['is_default' => false]);
    }

    private function assertThreshold(TierAssignmentRule $rule, mixed $threshold): void
    {
        if ($rule->requiresThreshold() && ($threshold === null || (int) $threshold < 0)) {
            throw new InvalidArgumentException('threshold_required_for_rule');
        }
    }
}
