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
     * Locales with (planned) translated Postmark templates. English is the
     * base alias and needs no suffix.
     *
     * @var list<string>
     */
    public const LOCALIZED_LOCALES = ['es', 'ca', 'tr'];

    /**
     * Send a Postmark template email to a profile, respecting preferences.
     *
     * The alias is localized to the recipient's preferred_locale when a
     * translated copy is listed in config('services.postmark.localized_aliases');
     * otherwise the English alias is sent (see localizedAlias()).
     *
     * @param  array<string, mixed>  $model
     * @param  string|null  $replyTo  Reply-To address, for emails that ask the recipient to reply
     * @return bool Whether an email was actually queued (false = suppressed by
     *              the recipient's preferences).
     */
    public function send(Profile $recipient, string $templateAlias, array $model, string $category, ?string $replyTo = null): bool
    {
        if (! $this->shouldSend($recipient, $category)) {
            return false;
        }

        dispatch(SendTransactionalEmail::template(
            to: $recipient->email,
            templateAlias: $this->localizedAlias($templateAlias, $recipient),
            model: $model,
            toName: $this->recipientName($recipient),
            replyTo: $replyTo,
        ));

        return true;
    }

    /**
     * '<alias>-<locale>' when the recipient prefers es/ca/tr and that exact
     * alias is listed as published in config, else the English alias.
     *
     * Bug report 2026-09-28, item 5: every email went out in English although
     * profiles store preferred_locale and push already uses it. The allow-list
     * keeps this safe to deploy before the translations exist: Postmark rejects
     * an unknown alias, so a localized alias is only ever requested once it has
     * been published and listed.
     */
    public function localizedAlias(string $templateAlias, Profile $recipient): string
    {
        $locale = strtolower(substr((string) $recipient->preferred_locale, 0, 2));

        if (! in_array($locale, self::LOCALIZED_LOCALES, true)) {
            return $templateAlias;
        }

        $localized = $templateAlias.'-'.$locale;
        $published = (array) config('services.postmark.localized_aliases', []);

        return in_array($localized, $published, true) ? $localized : $templateAlias;
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
