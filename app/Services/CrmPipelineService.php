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

    /**
     * A personalised first-touch outreach draft built from the lead's own
     * fields. Deterministic (no LLM) so it renders instantly and is copy-ready;
     * the operator edits before sending.
     */
    public function firstTouchMessage(CrmAccount $account): string
    {
        $m = $account->metrics ?? [];
        $city = $m['city'] ?? 'your city';
        $audience = $m['audience'] ?? 'engaged';
        $collabRaw = $m['collab_businesses'] ?? ($m['collabs'] ?? '');
        $collab = trim((string) preg_split('/[(,]/', (string) $collabRaw)[0]);
        $collabLine = $collab !== '' && stripos($collab, 'n/f') === false
            ? " Loved seeing your work with {$collab}."
            : '';

        return implode("\n", [
            "Hola {$account->name} 👋",
            '',
            "I look after community partnerships at Kolabing — we connect {$city} communities with local brands for paid collaborations (venue, product, or a fee), fully handled through the app.".$collabLine,
            '',
            "With your {$audience} following you'd be a strong fit. Would you be open to a quick chat about running paid Kolabs for your members?",
            '',
            'No cost to join — you only ever say yes to collabs you like.',
            '',
            '— Neil, Kolabing',
        ]);
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
