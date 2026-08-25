<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Enums\PartnerStatusTier;
use App\Jobs\SendPushNotification;
use App\Models\Application;
use App\Models\ChallengeCompletion;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Notification types that also send a transactional email, mapped to their
     * Postmark template alias + preference category. Every notification flows
     * through {@see createNotification()}; a type present here gets an email
     * side-effect there, gated by the recipient's preferences. Types absent
     * from this map are push/in-app only.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const EMAIL_MAP = [
        'application_received' => ['application-received', EmailService::CATEGORY_APPLICATION],
        'application_accepted' => ['application-accepted', EmailService::CATEGORY_COLLABORATION],
        'application_declined' => ['application-declined', EmailService::CATEGORY_COLLABORATION],
        'collaboration_created' => ['collab-confirmed', EmailService::CATEGORY_COLLABORATION],
        'collab_followup_reminder' => ['feedback-request', EmailService::CATEGORY_COLLABORATION],
        'badge_awarded' => ['badge-earned', EmailService::CATEGORY_GAMIFICATION],
        'reward_won' => ['reward-won', EmailService::CATEGORY_GAMIFICATION],
        'tier_promoted' => ['tier-promotion', EmailService::CATEGORY_GAMIFICATION],
    ];

    public function __construct(
        private readonly EmailService $emailService,
    ) {}

    /**
     * Get paginated notifications for a profile.
     *
     * @return LengthAwarePaginator<Notification>
     */
    public function getNotifications(Profile $profile, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->with(['actorProfile.businessProfile', 'actorProfile.communityProfile', 'actorProfile.attendeeProfile'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get the count of unread notifications for a profile.
     */
    public function getUnreadCount(Profile $profile): int
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): Notification
    {
        $notification->update(['read_at' => now()]);

        return $notification;
    }

    /**
     * Mark all unread notifications as read for a profile.
     */
    public function markAllAsRead(Profile $profile): int
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Create a notification record and dispatch a push notification for the recipient.
     * If the type is in {@see EMAIL_MAP}, also queues a transactional email
     * (gated + isolated) using the merge vars in $emailModel.
     *
     * @param  array<string, mixed>  $pushOptions
     * @param  array<string, mixed>  $emailModel  template-specific merge vars ({{first_name}} is added automatically)
     */
    public function createNotification(
        Profile $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
        array $pushOptions = [],
        array $emailModel = [],
    ): Notification {
        $notification = Notification::create([
            'profile_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actor?->id,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ]);

        SendPushNotification::dispatch($recipient, $title, $body, $type, $targetId, $pushOptions);

        $this->sendEmailSideEffect($recipient, $type, $emailModel);

        return $notification;
    }

    /**
     * Optional transactional-email side-effect of a notification, isolated from
     * the push path so an email failure never affects the in-app notification.
     *
     * @param  array<string, mixed>  $emailModel
     */
    private function sendEmailSideEffect(Profile $recipient, NotificationType $type, array $emailModel): void
    {
        $mapping = self::EMAIL_MAP[$type->value] ?? null;

        if ($mapping === null) {
            return;
        }

        [$alias, $category] = $mapping;

        try {
            $this->emailService->send(
                $recipient,
                $alias,
                ['first_name' => $this->recipientFirstName($recipient)] + $emailModel,
                $category,
            );
        } catch (\Throwable $e) {
            Log::warning('Notification email side-effect failed', [
                'profile_id' => $recipient->id,
                'type' => $type->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Greeting name for a recipient: the business/community name for those
     * types, the person's name for attendees (mirrors the drip's convention).
     */
    private function recipientFirstName(Profile $recipient): ?string
    {
        return $recipient->getExtendedProfile()?->name ?? $recipient->name;
    }

    /**
     * Create a notification whose title/body are resolved in the recipient's
     * preferred locale (falling back to the app fallback locale). Translation
     * keys + replacements are resolved per-recipient via the 4th `__()` arg so
     * no global locale state is mutated.
     *
     * @param  array<string, string|int>  $replace
     * @param  array<string, mixed>  $pushOptions
     * @param  array<string, mixed>  $emailModel  template-specific merge vars ({{first_name}} is added automatically)
     */
    public function createLocalizedNotification(
        Profile $recipient,
        NotificationType $type,
        string $titleKey,
        string $bodyKey,
        array $replace = [],
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
        array $pushOptions = [],
        array $emailModel = [],
    ): Notification {
        $locale = $recipient->preferred_locale ?? config('app.fallback_locale');

        $title = __($titleKey, $replace, $locale);
        $body = __($bodyKey, $replace, $locale);

        return $this->createNotification(
            recipient: $recipient,
            type: $type,
            title: $title,
            body: $body,
            actor: $actor,
            targetId: $targetId,
            targetType: $targetType,
            pushOptions: $pushOptions,
            emailModel: $emailModel,
        );
    }

    /**
     * Bulk-record a notification for many recipients in one (chunked) insert —
     * scale-friendly fan-out (the push is sent separately, in one multi-recipient
     * call by the caller). Does NOT dispatch per-recipient push jobs.
     *
     * @param  array<int, string>  $recipientIds
     */
    public function recordNotifications(
        array $recipientIds,
        NotificationType $type,
        string $title,
        string $body,
        ?string $actorProfileId = null,
        ?string $targetId = null,
        ?string $targetType = null,
    ): void {
        if ($recipientIds === []) {
            return;
        }

        $now = now();
        $rows = array_map(static fn (string $profileId): array => [
            'id' => (string) Str::uuid(),
            'profile_id' => $profileId,
            'type' => $type->value,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actorProfileId,
            'target_id' => $targetId,
            'target_type' => $targetType,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($recipientIds));

        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::query()->insert($chunk);
        }
    }

    /**
     * Of the given profile ids, those who allow chat-message notifications.
     * Missing preference row = default ON.
     *
     * @param  array<int, string>  $profileIds
     * @return array<int, string>
     */
    public function recipientsAllowingMessages(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        $optedOut = \App\Models\NotificationPreference::query()
            ->whereIn('profile_id', $profileIds)
            ->where('message_notifications', false)
            ->pluck('profile_id')
            ->all();

        return array_values(array_diff($profileIds, $optedOut));
    }

    /**
     * Create a notification for a new chat message.
     * Notifies the other party in the application conversation.
     */
    public function notifyNewMessage(ChatMessage $message, Application $application): void
    {
        $application->loadMissing([
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $senderProfileId = $message->sender_profile_id;

        // Determine recipient: if sender is applicant, notify creator; otherwise notify applicant
        $recipient = $senderProfileId === $application->applicant_profile_id
            ? $opportunity->creatorProfile
            : $application->applicantProfile;

        $senderProfile = $senderProfileId === $application->applicant_profile_id
            ? $application->applicantProfile
            : $opportunity->creatorProfile;

        // Respect the recipient's message-notification preference (default ON).
        if ($this->recipientsAllowingMessages([$recipient->id]) === []) {
            return;
        }

        $body = Str::limit($message->content, 100, '...');

        $locale = $recipient->preferred_locale ?? config('app.fallback_locale');

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::NewMessage,
            title: __('notifications.new_message.title', [], $locale),
            body: $body,
            actor: $senderProfile,
            targetId: $application->id,
            targetType: 'application',
        );
    }

    /**
     * Create a notification when an application is received.
     * Notifies the opportunity owner.
     */
    public function notifyApplicationReceived(Application $application): void
    {
        $application->loadMissing([
            'kolab.creatorProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $opportunity->creatorProfile;
        $actor = $application->applicantProfile;
        $actorName = $actor->getExtendedProfile()?->name ?? 'Someone';
        $opportunityTitle = $opportunity->title;

        $this->createLocalizedNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            titleKey: 'notifications.application.received.title',
            bodyKey: 'notifications.application.received.body',
            replace: ['name' => $actorName, 'kolab' => $opportunityTitle],
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            emailModel: ['applicant_name' => $actorName, 'opportunity_title' => $opportunityTitle],
        );
    }

    /**
     * Create a notification when an application is accepted.
     * Notifies the applicant.
     */
    public function notifyApplicationAccepted(Application $application): void
    {
        $application->loadMissing([
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $application->applicantProfile;
        $actor = $opportunity->creatorProfile;
        $opportunityTitle = $opportunity->title;
        $partnerName = $actor?->getExtendedProfile()?->name ?? 'your partner';

        $this->createLocalizedNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationAccepted,
            titleKey: 'notifications.application.accepted.title',
            bodyKey: 'notifications.application.accepted.body',
            replace: ['kolab' => $opportunityTitle],
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            emailModel: ['partner_name' => $partnerName, 'opportunity_title' => $opportunityTitle],
        );
    }

    /**
     * Create a notification when an application is declined.
     * Notifies the applicant.
     */
    public function notifyApplicationDeclined(Application $application): void
    {
        $application->loadMissing([
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $application->applicantProfile;
        $actor = $opportunity->creatorProfile;
        $opportunityTitle = $opportunity->title;
        $partnerName = $actor?->getExtendedProfile()?->name ?? 'your partner';

        $this->createLocalizedNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationDeclined,
            titleKey: 'notifications.application.declined.title',
            bodyKey: 'notifications.application.declined.body',
            replace: ['kolab' => $opportunityTitle],
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            emailModel: ['partner_name' => $partnerName, 'opportunity_title' => $opportunityTitle],
        );
    }

    /**
     * Create a notification when an application is withdrawn by the applicant.
     * Primary: notify the kolab creator/business that the applicant withdrew.
     * Secondary: confirm to the withdrawing applicant.
     */
    public function notifyApplicationWithdrawn(Application $application): void
    {
        $application->loadMissing([
            'kolab.creatorProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $creator = $opportunity->creatorProfile;
        $applicant = $application->applicantProfile;
        $applicantName = $applicant?->getExtendedProfile()?->name ?? 'Someone';
        $opportunityTitle = $opportunity->title;

        if ($creator !== null) {
            $this->createLocalizedNotification(
                recipient: $creator,
                type: NotificationType::ApplicationWithdrawn,
                titleKey: 'notifications.application.withdrawn.creator.title',
                bodyKey: 'notifications.application.withdrawn.creator.body',
                replace: ['name' => $applicantName, 'kolab' => $opportunityTitle],
                actor: $applicant,
                targetId: $application->id,
                targetType: 'application',
            );
        }

        if ($applicant !== null) {
            $this->createLocalizedNotification(
                recipient: $applicant,
                type: NotificationType::ApplicationWithdrawn,
                titleKey: 'notifications.application.withdrawn.applicant.title',
                bodyKey: 'notifications.application.withdrawn.applicant.body',
                replace: ['kolab' => $opportunityTitle],
                targetId: $application->id,
                targetType: 'application',
            );
        }
    }

    /**
     * Notify when a challenge is verified (awarded to the challenger).
     */
    public function notifyChallengeVerified(ChallengeCompletion $completion): void
    {
        $completion->loadMissing(['challenge', 'challenger', 'verifier']);

        $this->createLocalizedNotification(
            recipient: $completion->challenger,
            type: NotificationType::ChallengeVerified,
            titleKey: 'notifications.challenge.verified.title',
            bodyKey: 'notifications.challenge.verified.body',
            replace: [
                'challenge' => $completion->challenge->name,
                'points' => $completion->points_earned,
            ],
            actor: $completion->verifier,
            targetId: $completion->id,
            targetType: 'challenge_completion',
        );
    }

    /**
     * Send "Today's your Kolab 🎉" reminder to both parties.
     * Idempotent: skips a party if a reminder of this type was already sent
     * for this collaboration (checked via existing notifications rows).
     */
    public function notifyCollabDayReminder(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($this->reminderAlreadySent($profile->id, NotificationType::CollabDayReminder, $collaboration->id)) {
                continue;
            }
            $this->createLocalizedNotification(
                recipient: $profile,
                type: NotificationType::CollabDayReminder,
                titleKey: 'notifications.collab.day_reminder.title',
                bodyKey: 'notifications.collab.day_reminder.body',
                targetId: $collaboration->id,
                targetType: 'collaboration',
            );
        }
    }

    /**
     * Send "Did your Kolab happen? Mark it complete." follow-up to both parties.
     * Idempotent: skips a party if a follow-up of this type was already sent.
     */
    public function notifyCollabFollowUpReminder(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($this->reminderAlreadySent($profile->id, NotificationType::CollabFollowUpReminder, $collaboration->id)) {
                continue;
            }

            $counterpart = $profile->id === $collaboration->creatorProfile?->id
                ? $collaboration->applicantProfile
                : $collaboration->creatorProfile;

            $this->createLocalizedNotification(
                recipient: $profile,
                type: NotificationType::CollabFollowUpReminder,
                titleKey: 'notifications.collab.follow_up_reminder.title',
                bodyKey: 'notifications.collab.follow_up_reminder.body',
                targetId: $collaboration->id,
                targetType: 'collaboration',
                emailModel: ['partner_name' => $counterpart?->getExtendedProfile()?->name ?? 'your partner'],
            );
        }
    }

    /**
     * Notify when a collaboration is created from an accepted application.
     * System event — no actor; both parties get the same copy.
     */
    public function notifyCollaborationCreated(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCreated,
            actor: null,
            replace: ['kolab' => $kolab],
            titleKey: 'notifications.collaboration.created.title',
            sharedBodyKey: 'notifications.collaboration.created.body',
        );
    }

    /**
     * Notify when a collaboration is activated. The actor (who activated it)
     * sees actor-aware copy; the counterpart sees "{name} marked…".
     */
    public function notifyCollaborationActivated(Collaboration $collaboration, Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationActivated,
            actor: $actor,
            replace: ['kolab' => $kolab, 'name' => $name],
            titleKey: 'notifications.collaboration.activated.title',
            actorBodyKey: 'notifications.collaboration.activated.actor_body',
            counterpartBodyKey: 'notifications.collaboration.activated.counterpart_body',
        );
    }

    /**
     * Notify when one party submits feedback. The reviewer (actor) sees
     * "Feedback submitted"; the counterpart sees "New feedback".
     */
    public function notifyCollaborationFeedbackReceived(Collaboration $collaboration, Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationFeedbackReceived,
            actor: $actor,
            replace: ['kolab' => $kolab, 'name' => $name],
            actorTitleKey: 'notifications.collaboration.feedback_received.actor_title',
            actorBodyKey: 'notifications.collaboration.feedback_received.actor_body',
            counterpartTitleKey: 'notifications.collaboration.feedback_received.counterpart_title',
            counterpartBodyKey: 'notifications.collaboration.feedback_received.counterpart_body',
        );
    }

    /**
     * Notify when a collaboration is completed. With an actor (manual / admin
     * force) both parties get "Collaboration completed" with actor-aware copy;
     * with a null actor (auto-complete) both get the shared auto copy.
     */
    public function notifyCollaborationCompleted(Collaboration $collaboration, ?Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);

        if ($actor === null) {
            $this->notifyBothParties(
                collaboration: $collaboration,
                type: NotificationType::CollaborationCompleted,
                actor: null,
                replace: ['kolab' => $kolab],
                titleKey: 'notifications.collaboration.completed.title',
                sharedBodyKey: 'notifications.collaboration.completed.auto_body',
            );

            return;
        }

        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCompleted,
            actor: $actor,
            replace: ['kolab' => $kolab, 'name' => $name],
            titleKey: 'notifications.collaboration.completed.title',
            actorBodyKey: 'notifications.collaboration.completed.actor_body',
            counterpartBodyKey: 'notifications.collaboration.completed.counterpart_body',
        );
    }

    /**
     * Notify when a collaboration is cancelled. The actor sees "You cancelled…";
     * the counterpart sees "{name} cancelled…". When the actor is null (e.g. a
     * maintainer cancel without a profile) both parties get the counterpart copy
     * with a "Someone" fallback name.
     */
    public function notifyCollaborationCancelled(Collaboration $collaboration, ?Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCancelled,
            actor: $actor,
            replace: ['kolab' => $kolab, 'name' => $name],
            titleKey: 'notifications.collaboration.cancelled.title',
            actorBodyKey: 'notifications.collaboration.cancelled.actor_body',
            counterpartBodyKey: 'notifications.collaboration.cancelled.counterpart_body',
        );
    }

    /**
     * Notify both parties of a collaboration, resolving copy per-recipient in
     * each recipient's locale. The actor (if a participant) receives actor-aware
     * copy; everyone else receives the counterpart copy. Pass either a single
     * $sharedBodyKey (system / no-actor events, optionally with a single
     * $titleKey) or distinct $actorBodyKey / $counterpartBodyKey (and optional
     * per-side title keys). Each side defaults to $titleKey when a side-specific
     * title key is not given.
     *
     * @param  array<string, string|int>  $replace
     */
    private function notifyBothParties(
        Collaboration $collaboration,
        NotificationType $type,
        ?Profile $actor,
        array $replace = [],
        ?string $titleKey = null,
        ?string $sharedBodyKey = null,
        ?string $actorTitleKey = null,
        ?string $actorBodyKey = null,
        ?string $counterpartTitleKey = null,
        ?string $counterpartBodyKey = null,
    ): void {
        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($profile === null) {
                continue;
            }

            $isActor = $actor !== null && $profile->id === $actor->id;

            $resolvedTitleKey = $isActor
                ? ($actorTitleKey ?? $titleKey)
                : ($counterpartTitleKey ?? $titleKey);

            $resolvedBodyKey = $sharedBodyKey
                ?? ($isActor ? $actorBodyKey : $counterpartBodyKey)
                ?? $counterpartBodyKey
                ?? $actorBodyKey;

            $counterpart = $profile->id === $collaboration->creatorProfile?->id
                ? $collaboration->applicantProfile
                : $collaboration->creatorProfile;

            $this->createLocalizedNotification(
                recipient: $profile,
                type: $type,
                titleKey: (string) $resolvedTitleKey,
                bodyKey: (string) $resolvedBodyKey,
                replace: $replace,
                actor: $actor,
                targetId: $collaboration->id,
                targetType: 'collaboration',
                emailModel: [
                    'partner_name' => $counterpart?->getExtendedProfile()?->name ?? 'your partner',
                    'scheduled_date' => $collaboration->scheduled_date?->format('l, j M Y') ?? 'soon',
                ],
            );
        }
    }

    /**
     * The collaboration's kolab title, with a safe fallback.
     */
    private function collaborationKolabTitle(Collaboration $collaboration): string
    {
        return $collaboration->kolab?->title ?? 'your Kolab';
    }

    /**
     * The actor's display name, mirroring the notifyApplicationReceived
     * convention (extended-profile name, "Someone" fallback).
     */
    private function actorDisplayName(?Profile $actor): string
    {
        return $actor?->getExtendedProfile()?->name ?? 'Someone';
    }

    /**
     * Check whether a reminder of the given type has already been sent
     * to this profile for this collaboration.
     */
    private function reminderAlreadySent(string $profileId, NotificationType $type, string $targetId): bool
    {
        return Notification::query()
            ->where('profile_id', $profileId)
            ->where('type', $type)
            ->where('target_id', $targetId)
            ->exists();
    }

    private function applicationOpportunity(Application $application): mixed
    {
        return $application->kolab;
    }

    /**
     * Notify a business that their Kolab is now live and discoverable.
     */
    public function notifyKolabPublished(Kolab $kolab): void
    {
        $this->createNotification(
            recipient: $kolab->creatorProfile,
            type: NotificationType::KolabPublished,
            title: 'Your offer is live',
            body: 'Communities can now discover it and apply. We\'ll let you know when there\'s a match.',
            targetId: $kolab->id,
            targetType: 'kolab',
        );
    }

    /**
     * Notify a business their partner status has been upgraded.
     */
    public function notifyPartnerStatusUpgraded(Profile $business, PartnerStatusTier $status): void
    {
        $body = match ($status) {
            PartnerStatusTier::ActivePartner => 'You\'re building your reputation as a Kolabing partner.',
            PartnerStatusTier::TrustedPartner => 'Communities can now see you as a Trusted Partner on Kolabing.',
            PartnerStatusTier::CommunityFavourite => 'You\'ve reached Community Favourite — the top partner status on Kolabing.',
            PartnerStatusTier::NewPartner => 'Welcome to Kolabing.',
        };

        $this->createNotification(
            recipient: $business,
            type: NotificationType::PartnerStatusUpgraded,
            title: "You're now a {$status->label()}",
            body: $body,
        );
    }

    /**
     * Nudge a subscribed but inactive business back to the platform.
     */
    public function notifyReactivation(Profile $business): void
    {
        $this->createNotification(
            recipient: $business,
            type: NotificationType::ReactivationPrompt,
            title: 'Ready for your next Kolab?',
            body: 'Create a new offer or reuse one of your previous ideas.',
        );
    }

    /**
     * Notify when a reward is won from spin-the-wheel.
     */
    public function notifyRewardWon(RewardClaim $claim): void
    {
        $claim->loadMissing(['eventReward', 'profile']);

        $this->createLocalizedNotification(
            recipient: $claim->profile,
            type: NotificationType::RewardWon,
            titleKey: 'notifications.reward.won.title',
            bodyKey: 'notifications.reward.won.body',
            replace: ['reward' => $claim->eventReward->name],
            targetId: $claim->id,
            targetType: 'reward_claim',
            emailModel: ['reward_name' => $claim->eventReward->name],
        );
    }

    /**
     * Notify a community member they were promoted to a new tier. System event
     * (no actor). Also queues the `tier-promotion` email via the funnel.
     */
    public function notifyTierPromoted(CommunityMember $member, CommunityTier $tier): void
    {
        $member->loadMissing('profile');

        $recipient = $member->profile;

        if ($recipient === null) {
            return;
        }

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::TierPromoted,
            title: "You've reached {$tier->name}",
            body: "Your activity in the community earned you the {$tier->name} tier.",
            targetId: $member->id,
            targetType: 'community_member',
            emailModel: ['tier_name' => $tier->name],
        );
    }

    // --- Multi-Kolab Event MVP ------------------------------------------------
    // Callers are responsible for only invoking these on the actual
    // state-changing path (never on an idempotent early-return), so retrying
    // a request never sends a duplicate notification — see
    // MultiKolabRoleApplicationService::accept()/withdraw() and
    // MultiKolabEventService::confirm()/cancel().

    /**
     * Notify the event organizer that a new application landed on one of
     * their roles.
     */
    public function notifyMultiKolabApplicationReceived(MultiKolabRoleApplication $application): void
    {
        $application->loadMissing(['role.event.creatorProfile', 'applicantProfile']);
        $role = $application->role;
        $event = $role?->event;
        $organizer = $event?->creatorProfile;

        if ($organizer === null || $event === null || $role === null) {
            return;
        }

        $applicant = $application->applicantProfile;
        $applicantName = $applicant?->getExtendedProfile()?->name ?? 'Someone';

        $this->createLocalizedNotification(
            recipient: $organizer,
            type: NotificationType::MultiKolabApplicationReceived,
            titleKey: 'notifications.multi_kolab.application.received.title',
            bodyKey: 'notifications.multi_kolab.application.received.body',
            replace: ['name' => $applicantName, 'role' => $role->title, 'event' => $event->title],
            actor: $applicant,
            targetId: $application->id,
            targetType: 'multi_kolab_role_application',
        );
    }

    /**
     * Notify the applicant their application was accepted.
     */
    public function notifyMultiKolabApplicantAccepted(MultiKolabRoleApplication $application): void
    {
        $application->loadMissing(['role.event.creatorProfile', 'applicantProfile']);
        $role = $application->role;
        $event = $role?->event;
        $applicant = $application->applicantProfile;

        if ($applicant === null || $event === null || $role === null) {
            return;
        }

        $this->createLocalizedNotification(
            recipient: $applicant,
            type: NotificationType::MultiKolabApplicantAccepted,
            titleKey: 'notifications.multi_kolab.application.accepted.title',
            bodyKey: 'notifications.multi_kolab.application.accepted.body',
            replace: ['role' => $role->title, 'event' => $event->title],
            actor: $event->creatorProfile,
            targetId: $application->id,
            targetType: 'multi_kolab_role_application',
        );
    }

    /**
     * Notify the applicant their application was declined.
     */
    public function notifyMultiKolabApplicantDeclined(MultiKolabRoleApplication $application): void
    {
        $application->loadMissing(['role.event.creatorProfile', 'applicantProfile']);
        $role = $application->role;
        $event = $role?->event;
        $applicant = $application->applicantProfile;

        if ($applicant === null || $event === null || $role === null) {
            return;
        }

        $this->createLocalizedNotification(
            recipient: $applicant,
            type: NotificationType::MultiKolabApplicantDeclined,
            titleKey: 'notifications.multi_kolab.application.declined.title',
            bodyKey: 'notifications.multi_kolab.application.declined.body',
            replace: ['role' => $role->title, 'event' => $event->title],
            actor: $event->creatorProfile,
            targetId: $application->id,
            targetType: 'multi_kolab_role_application',
        );
    }

    /**
     * Notify the organizer that an already-accepted partner withdrew.
     * Deliberately not called for a pending/shortlisted withdrawal — only a
     * "partner withdrawal" (post-acceptance) needs organizer attention.
     */
    public function notifyMultiKolabPartnerWithdrew(MultiKolabRoleApplication $application): void
    {
        $application->loadMissing(['role.event.creatorProfile', 'applicantProfile']);
        $role = $application->role;
        $event = $role?->event;
        $organizer = $event?->creatorProfile;

        if ($organizer === null || $event === null || $role === null) {
            return;
        }

        $applicant = $application->applicantProfile;
        $applicantName = $applicant?->getExtendedProfile()?->name ?? 'Your partner';

        $this->createLocalizedNotification(
            recipient: $organizer,
            type: NotificationType::MultiKolabPartnerWithdrew,
            titleKey: 'notifications.multi_kolab.application.withdrawn.title',
            bodyKey: 'notifications.multi_kolab.application.withdrawn.body',
            replace: ['name' => $applicantName, 'role' => $role->title, 'event' => $event->title],
            actor: $applicant,
            targetId: $application->id,
            targetType: 'multi_kolab_role_application',
        );
    }

    /**
     * Notify the organizer that a role is now fully filled.
     */
    public function notifyMultiKolabRoleFilled(MultiKolabRole $role): void
    {
        $role->loadMissing('event.creatorProfile');
        $event = $role->event;
        $organizer = $event?->creatorProfile;

        if ($organizer === null || $event === null) {
            return;
        }

        $this->createLocalizedNotification(
            recipient: $organizer,
            type: NotificationType::MultiKolabRoleFilled,
            titleKey: 'notifications.multi_kolab.role.filled.title',
            bodyKey: 'notifications.multi_kolab.role.filled.body',
            replace: ['role' => $role->title, 'event' => $event->title],
            targetId: $role->id,
            targetType: 'multi_kolab_role',
        );
    }

    /**
     * Notify every accepted partner that the event is now confirmed.
     */
    public function notifyMultiKolabEventConfirmed(MultiKolabEvent $event): void
    {
        $applicants = $this->acceptedApplicants($event);

        foreach ($applicants as $applicant) {
            $this->createLocalizedNotification(
                recipient: $applicant,
                type: NotificationType::MultiKolabEventConfirmed,
                titleKey: 'notifications.multi_kolab.event.confirmed.title',
                bodyKey: 'notifications.multi_kolab.event.confirmed.body',
                replace: ['event' => $event->title],
                targetId: $event->id,
                targetType: 'multi_kolab_event',
            );
        }
    }

    /**
     * Notify every accepted partner that the event was cancelled.
     */
    public function notifyMultiKolabEventCancelled(MultiKolabEvent $event, string $reason): void
    {
        $applicants = $this->acceptedApplicants($event);

        foreach ($applicants as $applicant) {
            $this->createLocalizedNotification(
                recipient: $applicant,
                type: NotificationType::MultiKolabEventCancelled,
                titleKey: 'notifications.multi_kolab.event.cancelled.title',
                bodyKey: 'notifications.multi_kolab.event.cancelled.body',
                replace: ['event' => $event->title, 'reason' => $reason],
                targetId: $event->id,
                targetType: 'multi_kolab_event',
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Profile>
     */
    private function acceptedApplicants(MultiKolabEvent $event): \Illuminate\Support\Collection
    {
        return Profile::query()
            ->whereIn('id', MultiKolabRoleApplication::query()
                ->whereIn('multi_kolab_role_id', $event->roles()->pluck('id'))
                ->where('status', \App\Enums\MultiKolabRoleApplicationStatus::Accepted)
                ->pluck('applicant_profile_id'))
            ->get();
    }
}
