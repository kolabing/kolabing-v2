<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmAccount;
use App\Models\CrmActivity;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Drives the supply-side pipeline: moving an account between stages and writing
 * the immutable activity trail. Every stage move and note flows through here so
 * the account's history stays reconstructable and last-activity stays fresh.
 */
class CrmPipelineService
{
    /** Move an account to a stage, stamp last activity, and log the change. */
    public function moveStage(CrmAccount $account, string $toStage, ?string $actor = null): CrmActivity
    {
        if (! in_array($toStage, CrmAccount::COMMUNITY_STAGES, true)) {
            throw new InvalidArgumentException("Unknown stage: {$toStage}");
        }

        $from = $account->currentStage();
        $account->status = $toStage;
        $account->last_activity_at = Carbon::now();
        $account->save();

        return $this->log(
            $account,
            'stage_change',
            $from === $toStage ? "Stage set to {$toStage}." : "Stage moved: {$from} → {$toStage}.",
            $actor,
            ['from' => $from, 'to' => $toStage],
        );
    }

    /** Record a free-text note against the account and refresh last activity. */
    public function logNote(CrmAccount $account, string $body, ?string $actor = null): CrmActivity
    {
        $account->forceFill(['last_activity_at' => Carbon::now()])->save();

        return $this->log($account, 'note', $body, $actor);
    }

    /**
     * Append an activity row of any supported type.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(CrmAccount $account, string $type, string $body, ?string $actor = null, array $meta = []): CrmActivity
    {
        if (! in_array($type, CrmActivity::TYPES, true)) {
            throw new InvalidArgumentException("Unknown activity type: {$type}");
        }

        return $account->activities()->create([
            'type' => $type,
            'actor' => $actor,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);
    }
}
