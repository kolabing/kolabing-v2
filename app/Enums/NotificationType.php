<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case NewMessage = 'new_message';
    case ApplicationReceived = 'application_received';
    case ApplicationAccepted = 'application_accepted';
    case ApplicationDeclined = 'application_declined';
    case ApplicationWithdrawn = 'application_withdrawn';
    case BadgeAwarded = 'badge_awarded';
    case ChallengeVerified = 'challenge_verified';
    case RewardWon = 'reward_won';
    case PointsEarned = 'points_earned';
    case GamificationBadgeEarned = 'gamification_badge_earned';
    case WithdrawalProcessed = 'withdrawal_processed';
    case KolabCreateIncomplete = 'kolab_create_incomplete';
    case ApplicationPending = 'application_pending';
    case UnreadMessage = 'unread_message';
    case CollabDayReminder = 'collab_day_reminder';
    case CollabFollowUpReminder = 'collab_followup_reminder';
    case WaitlistPromoted = 'waitlist_promoted';
    case EventCancelled = 'event_cancelled';

    // Attendee event reminders (kolabing-app#191). Two types rather than one
    // two-step chain, because the app ships them as two distinct wire values and
    // each must be cancellable on its own.
    case EventReminder24h = 'event_reminder_24h';
    case EventReminder1h = 'event_reminder_1h';
    case CommunityJoinRequested = 'community_join_requested';
    case CommunityJoinApproved = 'community_join_approved';
    case CommunityJoinDeclined = 'community_join_declined';
    case CommunityVerified = 'community_verified';
    case CommunityVerificationRejected = 'community_verification_rejected';
    case CollaborationCreated = 'collaboration_created';
    case CollaborationActivated = 'collaboration_activated';
    case CollaborationFeedbackReceived = 'collaboration_feedback_received';
    case CollaborationCompleted = 'collaboration_completed';
    case CollaborationCancelled = 'collaboration_cancelled';
    case KolabPublished = 'kolab_published';
    case ReviewReminder = 'review_reminder';
    case SecondOfferPrompt = 'second_offer_prompt';
    case PartnerStatusUpgraded = 'partner_status_upgraded';
    case ReactivationPrompt = 'reactivation_prompt';
    case TierPromoted = 'tier_promoted';

    // Multi-Kolab Event MVP — deliberately new cases, never overloading the
    // attendee EventCancelled case above (different domain entirely).
    case MultiKolabApplicationReceived = 'multi_kolab_application_received';
    case MultiKolabApplicantAccepted = 'multi_kolab_applicant_accepted';
    case MultiKolabApplicantDeclined = 'multi_kolab_applicant_declined';
    case MultiKolabPartnerWithdrew = 'multi_kolab_partner_withdrew';
    case MultiKolabRoleFilled = 'multi_kolab_role_filled';
    case MultiKolabEventConfirmed = 'multi_kolab_event_confirmed';
    case MultiKolabEventCancelled = 'multi_kolab_event_cancelled';
    case MultiKolabEventDraftIncomplete = 'multi_kolab_event_draft_incomplete';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
