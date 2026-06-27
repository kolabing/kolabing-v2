<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Models\Challenge;
use Illuminate\Database\Seeder;

/**
 * Seeds the system MISSION set (self-tracked onboarding/growth missions for
 * attendee / business / community). Idempotent: keyed on `slug` via
 * updateOrCreate. This replaces the old peer-verified event icebreakers; the
 * accompanying data migration wipes those before (re)running this seeder.
 *
 * Field mapping follows docs/plans/2026-06-22-gamification-mission-system.md:
 * target_value from the number in the title, repeat from "this month"/recurring
 * cues, category/points/difficulty from the mapping rules.
 */
class SystemChallengeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->missions() as $m) {
            Challenge::query()->updateOrCreate(
                ['slug' => $m['slug']],
                [
                    'name' => $m['name'],
                    'description' => $m['description'],
                    'audience' => $m['audience'],
                    'category' => $m['category'],
                    'difficulty' => $m['difficulty'],
                    'points' => $m['points'],
                    'trigger_action' => $m['trigger_action'],
                    'target_value' => $m['target_value'],
                    'repeat_interval' => $m['repeat_interval'],
                    'is_system' => true,
                    'app_visible' => $m['app_visible'],
                    'event_id' => null,
                ]
            );
        }
    }

    /**
     * @return array<int, array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     audience: ChallengeAudience,
     *     category: ChallengeCategory,
     *     difficulty: ChallengeDifficulty,
     *     points: int,
     *     trigger_action: MissionTrigger,
     *     target_value: int,
     *     repeat_interval: MissionRepeat,
     *     app_visible: bool
     * }>
     */
    private function missions(): array
    {
        return array_merge(
            $this->attendeeMissions(),
            $this->businessMissions(),
            $this->communityMissions(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attendeeMissions(): array
    {
        $a = ChallengeAudience::Attendee;

        return [
            $this->row($a, 'attendee-complete-profile', 'Complete your attendee profile', 'Fill in your attendee profile to get started.', ChallengeCategory::Onboarding, MissionTrigger::ProfileCompleted, 1, ChallengeDifficulty::Easy, 10, MissionRepeat::Once, true),
            $this->row($a, 'attendee-first-checkin', 'Check in to your first Kolab', 'Check in at your first Kolab event.', ChallengeCategory::Milestone, MissionTrigger::EventCheckin, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($a, 'attendee-attend-3-events-monthly', 'Attend 3 events this month', 'Check in to 3 events within a single month.', ChallengeCategory::Attendance, MissionTrigger::EventCheckin, 3, ChallengeDifficulty::Medium, 30, MissionRepeat::Monthly, true),
            $this->row($a, 'attendee-attend-5-kolabs', 'Attend 5 Kolabs', 'Check in to 5 Kolabs.', ChallengeCategory::Attendance, MissionTrigger::EventCheckin, 5, ChallengeDifficulty::Medium, 30, MissionRepeat::Once),
            $this->row($a, 'attendee-attend-10-kolabs', 'Attend 10 Kolabs', 'Check in to 10 Kolabs.', ChallengeCategory::Attendance, MissionTrigger::EventCheckin, 10, ChallengeDifficulty::Hard, 50, MissionRepeat::Once),
            $this->row($a, 'attendee-first-challenge', 'Complete your first challenge', 'Complete your first verified event challenge.', ChallengeCategory::Milestone, MissionTrigger::ChallengeCompleted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($a, 'attendee-bring-a-friend', 'Bring a friend to an event', 'Invite a friend who attends an event with you.', ChallengeCategory::Social, MissionTrigger::FriendInvited, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($a, 'attendee-first-review', 'Leave your first review', 'Post your first review after an event.', ChallengeCategory::Content, MissionTrigger::ReviewPosted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($a, 'attendee-share-on-instagram', 'Share a Kolab on Instagram', 'Share a Kolab on Instagram.', ChallengeCategory::Content, MissionTrigger::SocialShare, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($a, 'attendee-join-2-communities', 'Join 2 different communities', 'Join 2 different communities on the platform.', ChallengeCategory::Social, MissionTrigger::CommunityJoined, 2, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($a, 'attendee-try-new-event-type', 'Try a new type of event', 'Attend an event of a type you have not been to before.', ChallengeCategory::Attendance, MissionTrigger::NewEventType, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($a, 'attendee-complete-5-verified-challenges', 'Complete 5 verified challenges', 'Complete 5 peer-verified event challenges.', ChallengeCategory::Engagement, MissionTrigger::ChallengeCompleted, 5, ChallengeDifficulty::Medium, 30, MissionRepeat::Once),
            $this->row($a, 'attendee-complete-10-verified-challenges', 'Complete 10 verified challenges', 'Complete 10 peer-verified event challenges.', ChallengeCategory::Engagement, MissionTrigger::ChallengeCompleted, 10, ChallengeDifficulty::Hard, 50, MissionRepeat::Once),
            $this->row($a, 'attendee-top-attendee-monthly', 'Become a top attendee this month', 'Rank among the top attendees for the month.', ChallengeCategory::Milestone, MissionTrigger::TopAttendeeMonthly, 1, ChallengeDifficulty::Easy, 50, MissionRepeat::Monthly),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function businessMissions(): array
    {
        $b = ChallengeAudience::Business;

        return [
            $this->row($b, 'business-complete-profile', 'Complete your business profile', 'Fill in your business profile to get started.', ChallengeCategory::Onboarding, MissionTrigger::BusinessProfileCompleted, 1, ChallengeDifficulty::Easy, 10, MissionRepeat::Once, true),
            $this->row($b, 'business-upload-profile-photo', 'Upload a business profile photo', 'Add a profile photo for your business.', ChallengeCategory::Onboarding, MissionTrigger::BusinessPhotoUploaded, 1, ChallengeDifficulty::Easy, 10, MissionRepeat::Once, true),
            $this->row($b, 'business-publish-first-kolab', 'Launch your first Kolab', 'Publish your first Kolab opportunity.', ChallengeCategory::Milestone, MissionTrigger::KolabPublished, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($b, 'business-first-application-received', 'Receive your first community application', 'Receive your first application from a community.', ChallengeCategory::Milestone, MissionTrigger::ApplicationReceived, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($b, 'business-first-application-accepted', 'Accept your first community application', 'Accept your first application from a community.', ChallengeCategory::Milestone, MissionTrigger::ApplicationAccepted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($b, 'business-first-kolab-completed', 'Complete your first Kolab', 'Complete your first collaboration end to end.', ChallengeCategory::Milestone, MissionTrigger::CollaborationComplete, 1, ChallengeDifficulty::Easy, 50, MissionRepeat::Once, true),
            $this->row($b, 'business-create-content-kolab', 'Create a Content Kolab', 'Create a Kolab focused on content.', ChallengeCategory::Milestone, MissionTrigger::KolabCreatedContent, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-create-review-kolab', 'Create a Review Kolab', 'Create a Kolab focused on reviews.', ChallengeCategory::Milestone, MissionTrigger::KolabCreatedReview, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-create-revenue-kolab', 'Create a Revenue Kolab', 'Create a Kolab focused on revenue.', ChallengeCategory::Milestone, MissionTrigger::KolabCreatedRevenue, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-create-product-testing-kolab', 'Create a Product Testing Kolab', 'Create a Kolab focused on product testing.', ChallengeCategory::Milestone, MissionTrigger::KolabCreatedProduct, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-create-recurring-kolab', 'Create a recurring Kolab', 'Set up a recurring Kolab.', ChallengeCategory::Growth, MissionTrigger::RecurringKolabCreated, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-receive-5-reviews', 'Receive 5 reviews from a Kolab', 'Collect 5 reviews from a Kolab.', ChallengeCategory::Content, MissionTrigger::ReviewReceived, 5, ChallengeDifficulty::Medium, 30, MissionRepeat::Once, true),
            $this->row($b, 'business-get-10-attendees', 'Get 10 attendees from a Kolab', 'Draw 10 attendees from a single Kolab.', ChallengeCategory::Growth, MissionTrigger::AttendeeCountReached, 10, ChallengeDifficulty::Hard, 50, MissionRepeat::Once),
            $this->row($b, 'business-upload-content-brief', 'Upload a content brief', 'Upload a content brief for a Kolab.', ChallengeCategory::Content, MissionTrigger::ContentBriefUploaded, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-refer-another-business', 'Refer another business', 'Refer another business that joins the platform.', ChallengeCategory::Referral, MissionTrigger::BusinessReferred, 1, ChallengeDifficulty::Easy, 50, MissionRepeat::Once),
            $this->row($b, 'business-renew-subscription', 'Renew your subscription', 'Renew your business subscription.', ChallengeCategory::Growth, MissionTrigger::SubscriptionRenewed, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-upgrade-plan', 'Upgrade your plan', 'Upgrade to a higher subscription plan.', ChallengeCategory::Growth, MissionTrigger::PlanUpgraded, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-launch-giveaway-kolab', 'Launch a giveaway Kolab', 'Launch a Kolab built around a giveaway.', ChallengeCategory::Milestone, MissionTrigger::GiveawayKolabCreated, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($b, 'business-collaborate-with-3-communities', 'Collaborate with 3 communities', 'Complete collaborations with 3 different communities.', ChallengeCategory::Growth, MissionTrigger::CollaborationComplete, 3, ChallengeDifficulty::Medium, 30, MissionRepeat::Once),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function communityMissions(): array
    {
        $c = ChallengeAudience::Community;

        return [
            $this->row($c, 'community-complete-profile', 'Complete your community profile', 'Fill in your community profile to get started.', ChallengeCategory::Onboarding, MissionTrigger::CommunityProfileCompleted, 1, ChallengeDifficulty::Easy, 10, MissionRepeat::Once, true),
            $this->row($c, 'community-upload-profile-photo', 'Upload community profile photo', 'Add a profile photo for your community.', ChallengeCategory::Onboarding, MissionTrigger::CommunityPhotoUploaded, 1, ChallengeDifficulty::Easy, 10, MissionRepeat::Once, true),
            $this->row($c, 'community-apply-first-kolab', 'Apply to your first Kolab', 'Submit your first application to a Kolab.', ChallengeCategory::Milestone, MissionTrigger::ApplicationSubmitted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($c, 'community-get-accepted-first-kolab', 'Get accepted to your first Kolab', 'Have your first Kolab application accepted.', ChallengeCategory::Milestone, MissionTrigger::ApplicationAccepted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once, true),
            $this->row($c, 'community-first-kolab-completed', 'Complete your first Kolab', 'Complete your first collaboration end to end.', ChallengeCategory::Milestone, MissionTrigger::CollaborationComplete, 1, ChallengeDifficulty::Easy, 50, MissionRepeat::Once, true),
            $this->row($c, 'community-bring-10-members', 'Bring 10 members to a Kolab', 'Bring 10 of your members to a Kolab.', ChallengeCategory::Social, MissionTrigger::MembersBrought, 10, ChallengeDifficulty::Hard, 50, MissionRepeat::Once),
            $this->row($c, 'community-generate-20-checkins', 'Generate 20 member check-ins', 'Generate 20 member check-ins.', ChallengeCategory::Engagement, MissionTrigger::MemberCheckin, 20, ChallengeDifficulty::Hard, 50, MissionRepeat::Monthly),
            $this->row($c, 'community-create-ugc', 'Create UGC for a business', 'Create user-generated content for a business.', ChallengeCategory::Content, MissionTrigger::UgcCreated, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($c, 'community-post-tagged-story', 'Post a tagged story after a Kolab', 'Post a tagged story after a Kolab.', ChallengeCategory::Content, MissionTrigger::TaggedStoryPosted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($c, 'community-refer-first-business', 'Refer your first business', 'Refer a business that joins the platform.', ChallengeCategory::Referral, MissionTrigger::BusinessReferred, 1, ChallengeDifficulty::Easy, 50, MissionRepeat::Once, true),
            $this->row($c, 'community-complete-3-kolabs', 'Complete 3 Kolabs', 'Complete 3 collaborations.', ChallengeCategory::Growth, MissionTrigger::CollaborationComplete, 3, ChallengeDifficulty::Medium, 30, MissionRepeat::Once),
            $this->row($c, 'community-complete-5-kolabs', 'Complete 5 Kolabs', 'Complete 5 collaborations.', ChallengeCategory::Growth, MissionTrigger::CollaborationComplete, 5, ChallengeDifficulty::Medium, 30, MissionRepeat::Once),
            $this->row($c, 'community-first-business-review', 'Get your first business review', 'Receive your first review from a business.', ChallengeCategory::Milestone, MissionTrigger::BusinessReviewReceived, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($c, 'community-host-recurring-kolab', 'Host a recurring Kolab', 'Host a recurring Kolab.', ChallengeCategory::Growth, MissionTrigger::RecurringKolabHosted, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($c, 'community-invite-members', 'Invite members to join the platform', 'Invite your members to join the platform.', ChallengeCategory::Social, MissionTrigger::MembersInvited, 1, ChallengeDifficulty::Easy, 20, MissionRepeat::Once),
            $this->row($c, 'community-reach-100-checkins', 'Reach 100 total check-ins', 'Reach 100 total member check-ins.', ChallengeCategory::Engagement, MissionTrigger::MemberCheckin, 100, ChallengeDifficulty::Hard, 50, MissionRepeat::Once),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        ChallengeAudience $audience,
        string $slug,
        string $name,
        string $description,
        ChallengeCategory $category,
        MissionTrigger $trigger,
        int $targetValue,
        ChallengeDifficulty $difficulty,
        int $points,
        MissionRepeat $repeat,
        bool $appVisible = false,
    ): array {
        return [
            'audience' => $audience,
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'trigger_action' => $trigger,
            'target_value' => $targetValue,
            'difficulty' => $difficulty,
            'points' => $points,
            'repeat_interval' => $repeat,
            'app_visible' => $appVisible,
        ];
    }
}
