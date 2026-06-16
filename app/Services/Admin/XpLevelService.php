<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\XpLevel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Admin CRUD for the XP ladder. Enforces the invariant that bands are
 * contiguous, non-overlapping, and exactly one open-ended top.
 */
class XpLevelService
{
    /**
     * All levels ordered by `position` (and number as a fallback).
     *
     * @return Collection<int, XpLevel>
     */
    public function ordered(): Collection
    {
        return XpLevel::query()
            ->orderBy('position')
            ->orderBy('number')
            ->get();
    }

    /**
     * Update a single level, then re-validate the whole ladder so a
     * mis-edit can't silently corrupt the contiguous-bands invariant.
     * Throws InvalidArgumentException with a human-readable message if
     * the resulting set is invalid; the controller maps that to a 422.
     *
     * @param  array{number?: int, title?: string, min_xp?: int, max_xp?: int|null, color?: string, position?: int}  $data
     */
    public function update(XpLevel $level, array $data): XpLevel
    {
        // Save and validate inside a transaction: validateLadder() reads the
        // persisted rows, so the edit must be committed to be checked, but a
        // failing check must roll the save back rather than leave an invalid
        // ladder in the DB. Throwing inside the closure aborts the transaction.
        return DB::transaction(function () use ($level, $data): XpLevel {
            $level->fill($data);
            $level->save();

            $errors = $this->validateLadder();

            if ($errors !== []) {
                throw new InvalidArgumentException(implode(' ', $errors));
            }

            Cache::forget(XpEarnRuleService::CACHE_KEY);

            return $level->fresh();
        });
    }

    /**
     * Validate the ladder is contiguous, non-overlapping, and has exactly one
     * open-ended top level. Returns a list of human-readable error messages.
     *
     * @return array<int, string>
     */
    public function validateLadder(): array
    {
        $levels = XpLevel::query()
            ->orderBy('min_xp')
            ->get();

        $errors = [];

        if ($levels->isEmpty()) {
            return ['No XP levels defined.'];
        }

        if ((int) $levels->first()->min_xp !== 0) {
            $errors[] = 'The lowest level must start at min_xp = 0.';
        }

        $openTop = $levels->whereNull('max_xp');
        if ($openTop->count() !== 1) {
            $errors[] = 'Exactly one level must have max_xp = null (the open-ended top).';
        }

        $previousMax = null;
        foreach ($levels as $level) {
            if ($level->max_xp !== null && $level->max_xp < $level->min_xp) {
                $errors[] = "Level {$level->number}: max_xp ({$level->max_xp}) is less than min_xp ({$level->min_xp}).";
            }

            if ($previousMax !== null) {
                if ((int) $level->min_xp !== $previousMax + 1) {
                    $errors[] = "Level {$level->number} starts at {$level->min_xp}; expected ".($previousMax + 1).' to keep bands contiguous.';
                }
            }

            $previousMax = $level->max_xp;
        }

        return $errors;
    }
}
