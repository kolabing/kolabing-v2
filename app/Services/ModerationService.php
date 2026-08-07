<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\ModerationAlertMail;
use App\Models\ContentReport;
use App\Models\Profile;
use App\Models\UserBlock;
use Illuminate\Support\Facades\Mail;

/**
 * Backs the UGC moderation feature (App Review Guideline 1.2): blocking abusive
 * users, reporting objectionable content, and notifying the developer by email
 * on both actions.
 */
class ModerationService
{
    /**
     * Block a profile on behalf of the blocker. Idempotent — a repeat block of
     * the same target returns the existing row without erroring. Notifies the
     * moderation address.
     */
    public function block(Profile $blocker, Profile $blocked): UserBlock
    {
        $block = UserBlock::query()->firstOrCreate([
            'blocker_profile_id' => $blocker->id,
            'blocked_profile_id' => $blocked->id,
        ]);

        if ($block->wasRecentlyCreated) {
            $this->notify(
                event: 'block',
                reporterLabel: $this->profileLabel($blocker),
                targetLabel: $this->profileLabel($blocked),
                reason: null,
                contentSummary: null,
                details: [
                    'Blocker profile ID' => $blocker->id,
                    'Blocked profile ID' => $blocked->id,
                ],
            );
        }

        return $block;
    }

    /**
     * Remove a block. No-op if the pair was never blocked.
     */
    public function unblock(Profile $blocker, Profile $blocked): void
    {
        UserBlock::query()
            ->where('blocker_profile_id', $blocker->id)
            ->where('blocked_profile_id', $blocked->id)
            ->delete();
    }

    /**
     * Profile IDs the given viewer should not see: everyone they blocked, plus
     * everyone who blocked them (blocks are enforced symmetrically in feeds).
     *
     * @return array<int, string>
     */
    public function blockedIds(Profile $viewer): array
    {
        $blockedByViewer = UserBlock::query()
            ->where('blocker_profile_id', $viewer->id)
            ->pluck('blocked_profile_id');

        $blockedViewer = UserBlock::query()
            ->where('blocked_profile_id', $viewer->id)
            ->pluck('blocker_profile_id');

        return $blockedByViewer
            ->merge($blockedViewer)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Profile IDs the viewer has themselves blocked (for the GET /me/blocks
     * response — one-directional, only their own outgoing blocks).
     *
     * @return array<int, string>
     */
    public function idsBlockedBy(Profile $viewer): array
    {
        return UserBlock::query()
            ->where('blocker_profile_id', $viewer->id)
            ->pluck('blocked_profile_id')
            ->all();
    }

    /**
     * Persist a content report and notify the moderation address.
     */
    public function report(
        Profile $reporter,
        string $targetType,
        string $targetId,
        ?string $reportedProfileId,
        string $reason,
        ?string $note = null,
    ): ContentReport {
        $report = ContentReport::query()->create([
            'reporter_profile_id' => $reporter->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reported_profile_id' => $reportedProfileId,
            'reason' => $reason,
            'note' => $note,
            'status' => 'open',
        ]);

        $this->notify(
            event: 'report',
            reporterLabel: $this->profileLabel($reporter),
            targetLabel: $targetType.' #'.$targetId,
            reason: $reason,
            contentSummary: $note,
            details: [
                'Report ID' => $report->id,
                'Target type' => $targetType,
                'Target ID' => $targetId,
                'Reported profile ID' => $reportedProfileId,
            ],
        );

        return $report;
    }

    /**
     * @param  'report'|'block'  $event
     * @param  array<string, string|null>  $details
     */
    private function notify(
        string $event,
        string $reporterLabel,
        string $targetLabel,
        ?string $reason,
        ?string $contentSummary,
        array $details,
    ): void {
        Mail::to(config('mail.moderation_address'))->queue(
            new ModerationAlertMail(
                event: $event,
                reporterLabel: $reporterLabel,
                targetLabel: $targetLabel,
                reason: $reason,
                contentSummary: $contentSummary,
                details: $details,
            )
        );
    }

    private function profileLabel(Profile $profile): string
    {
        $name = $profile->name;

        return ($name !== null && $name !== '')
            ? sprintf('%s (%s)', $name, $profile->id)
            : $profile->id;
    }
}
