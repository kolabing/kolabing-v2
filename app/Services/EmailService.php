<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendTransactionalEmail;
use App\Models\Profile;

/**
 * Preference-gated dispatch of transactional emails.
 *
 * All sends are queued via {@see SendTransactionalEmail}. Gating reads the
 * recipient's {@see \App\Models\NotificationPreference}; a missing preference
 * row is treated as "all defaults on".
 *
 * Categories map to preference flags:
 *  - account / security      → always send (cannot be opted out)
 *  - application             → new_application_alerts
 *  - collaboration           → collaboration_updates
 *  - gamification / nudge     → email_notifications master only
 *  - marketing               → marketing_tips
 */
class EmailService
{
    public const CATEGORY_ACCOUNT = 'account';

    public const CATEGORY_SECURITY = 'security';

    public const CATEGORY_APPLICATION = 'application';

    public const CATEGORY_COLLABORATION = 'collaboration';

    public const CATEGORY_GAMIFICATION = 'gamification';

    public const CATEGORY_NUDGE = 'nudge';

    public const CATEGORY_MARKETING = 'marketing';

    /**
     * Send a Postmark template email to a profile, respecting preferences.
     *
     * @param  array<string, mixed>  $model
     */
    public function send(Profile $recipient, string $templateAlias, array $model, string $category): void
    {
        if (! $this->shouldSend($recipient, $category)) {
            return;
        }

        dispatch(SendTransactionalEmail::template(
            to: $recipient->email,
            templateAlias: $templateAlias,
            model: $model,
            toName: $this->recipientName($recipient),
        ));
    }

    /**
     * Send a Postmark template email to a bare address (system / no profile gate).
     *
     * @param  array<string, mixed>  $model
     */
    public function sendToAddress(string $email, string $templateAlias, array $model, ?string $toName = null): void
    {
        dispatch(SendTransactionalEmail::template(
            to: $email,
            templateAlias: $templateAlias,
            model: $model,
            toName: $toName,
        ));
    }

    /**
     * Send a raw (non-template) email to a bare address. Connectivity tests only.
     */
    public function sendRawToAddress(string $email, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        dispatch(SendTransactionalEmail::raw(
            to: $email,
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
        ));
    }

    private function shouldSend(Profile $recipient, string $category): bool
    {
        // Account + security email is transactional and never opt-outable.
        if (in_array($category, [self::CATEGORY_ACCOUNT, self::CATEGORY_SECURITY], true)) {
            return true;
        }

        $prefs = $recipient->notificationPreferences;

        // No preference row yet → defaults are all-on.
        if ($prefs === null) {
            return true;
        }

        // Master switch.
        if (! $prefs->email_notifications) {
            return false;
        }

        return match ($category) {
            self::CATEGORY_APPLICATION => (bool) $prefs->new_application_alerts,
            self::CATEGORY_COLLABORATION => (bool) $prefs->collaboration_updates,
            self::CATEGORY_MARKETING => (bool) $prefs->marketing_tips,
            // gamification + activation nudges ride the master switch only.
            self::CATEGORY_GAMIFICATION, self::CATEGORY_NUDGE => true,
            default => true,
        };
    }

    private function recipientName(Profile $recipient): ?string
    {
        return $recipient->businessProfile?->name
            ?? $recipient->communityProfile?->name
            ?? null;
    }
}
