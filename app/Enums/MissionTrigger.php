<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The platform action that progresses a self-tracked mission. Stored on
 * `challenges.trigger_action` (nullable; null = a legacy peer-verified event
 * challenge, not a mission).
 *
 * ✓ markers note triggers already emitted today via PointEventType; ⧖ markers
 * note triggers whose source must be wired in a later phase. Both are valid
 * values to seed against now — Phase 1 only stores them, Phase 2/3 fire them.
 */
enum MissionTrigger: string
{
    // --- Attendee ---
    case ProfileCompleted = 'profile_completed';            // ⧖
    case EventCheckin = 'event_checkin';                    // ⧖
    case ChallengeCompleted = 'challenge_completed';        // ⧖
    case ReviewPosted = 'review_posted';                    // ✓
    case SocialShare = 'social_share';                      // ✓ (ugc_posted)
    case FriendInvited = 'friend_invited';                  // ⧖
    case CommunityJoined = 'community_joined';              // ⧖
    case NewEventType = 'new_event_type';                   // ⧖
    case TopAttendeeMonthly = 'top_attendee_monthly';       // ⧖

    // --- Business ---
    case BusinessProfileCompleted = 'business_profile_completed';   // ⧖
    case BusinessPhotoUploaded = 'business_photo_uploaded';         // ⧖
    case KolabPublished = 'kolab_published';                        // ⧖
    case ApplicationReceived = 'application_received';              // ⧖
    case ApplicationAccepted = 'application_accepted';              // ⧖
    case CollaborationComplete = 'collaboration_complete';         // ✓
    case KolabCreatedContent = 'kolab_created_content';            // ⧖
    case KolabCreatedReview = 'kolab_created_review';              // ⧖
    case KolabCreatedRevenue = 'kolab_created_revenue';           // ⧖
    case KolabCreatedProduct = 'kolab_created_product';           // ⧖
    case RecurringKolabCreated = 'recurring_kolab_created';        // ⧖
    case ReviewReceived = 'review_received';                       // ✓ (counterparty review_posted)
    case ContentBriefUploaded = 'content_brief_uploaded';         // ⧖
    case BusinessReferred = 'business_referred';                  // ✓ (referral_conversion)
    case SubscriptionRenewed = 'subscription_renewed';           // ⧖
    case PlanUpgraded = 'plan_upgraded';                         // ⧖
    case GiveawayKolabCreated = 'giveaway_kolab_created';        // ⧖
    case AttendeeCountReached = 'attendee_count_reached';        // ⧖

    // --- Community ---
    case CommunityProfileCompleted = 'community_profile_completed';   // ⧖
    case CommunityPhotoUploaded = 'community_photo_uploaded';         // ⧖
    case ApplicationSubmitted = 'application_submitted';              // ⧖
    case MembersBrought = 'members_brought';                          // ⧖
    case MemberCheckin = 'member_checkin';                            // ⧖
    case UgcCreated = 'ugc_created';                                  // ✓ (ugc_posted)
    case TaggedStoryPosted = 'tagged_story_posted';                  // ⧖
    case BusinessReviewReceived = 'business_review_received';        // ✓
    case RecurringKolabHosted = 'recurring_kolab_hosted';           // ⧖
    case MembersInvited = 'members_invited';                         // ⧖

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
