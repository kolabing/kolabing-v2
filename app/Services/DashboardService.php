<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;

class DashboardService
{
    public function __construct(
        private readonly BusinessPartnerStatusService $businessPartnerStatusService,
    ) {}

    /**
     * Get dashboard stats for a business user.
     *
     * @return array{
     *     opportunities: array{total: int, published: int, draft: int, closed: int},
     *     applications_received: array{total: int, pending: int, accepted: int, declined: int},
     *     collaborations: array{total: int, active: int, upcoming: int, completed: int},
     *     upcoming_collaborations: \Illuminate\Database\Eloquent\Collection,
     *     partner_status: array{status: string, label: string, icon: string, breakdown: array<string, mixed>},
     *     next_action: array{key: string, title: string, body: string}|null
     * }
     */
    public function getBusinessDashboard(Profile $profile): array
    {
        $opportunities = $this->getOpportunityStats($profile);
        $applicationsReceived = $this->getReceivedApplicationStats($profile);
        $collaborations = $this->getCollaborationStats($profile);

        return [
            'opportunities' => $opportunities,
            'applications_received' => $applicationsReceived,
            'collaborations' => $collaborations,
            'upcoming_collaborations' => $this->getUpcomingCollaborations($profile),
            'partner_status' => $this->getPartnerStatus($profile),
            'next_action' => $this->getNextAction($profile, $opportunities, $applicationsReceived, $collaborations),
        ];
    }

    /**
     * Build the business's partner status block, including the component
     * breakdown the audit calls for showing transparently to the business itself.
     *
     * @return array{status: string, label: string, icon: string, breakdown: array<string, mixed>}
     */
    private function getPartnerStatus(Profile $profile): array
    {
        $status = $this->businessPartnerStatusService->statusFor($profile);
        $record = $profile->businessPartnerStatus;

        return [
            'status' => $status->value,
            'label' => $status->label(),
            'icon' => $status->icon(),
            'breakdown' => [
                'completed_kolabs' => $record?->completed_kolabs_count ?? 0,
                'review_count' => $record?->review_count ?? 0,
                'average_rating' => $record?->average_rating,
                'repeat_partner_count' => $record?->repeat_partner_count ?? 0,
            ],
        ];
    }

    /**
     * Single next-best-action for the business dashboard, evaluated as a
     * priority-ordered rule chain — first match wins.
     *
     * @param  array{total: int, published: int, draft: int, closed: int}  $opportunities
     * @param  array{total: int, pending: int, accepted: int, declined: int}  $applicationsReceived
     * @param  array{total: int, active: int, upcoming: int, completed: int}  $collaborations
     * @return array{key: string, title: string, body: string}|null
     */
    private function getNextAction(
        Profile $profile,
        array $opportunities,
        array $applicationsReceived,
        array $collaborations,
    ): ?array {
        if (! $this->isProfileComplete($profile)) {
            return [
                'key' => 'complete_profile',
                'title' => 'Complete your profile',
                'body' => 'A complete profile helps communities trust you and apply with confidence.',
            ];
        }

        if ($opportunities['published'] === 0) {
            return [
                'key' => 'create_first_offer',
                'title' => 'Create your first Kolab',
                'body' => 'Publish an offer so communities can discover and apply to it.',
            ];
        }

        if ($applicationsReceived['pending'] > 0) {
            $count = $applicationsReceived['pending'];

            return [
                'key' => 'review_pending_applications',
                'title' => $count === 1 ? 'Review 1 pending application' : "Review {$count} pending applications",
                'body' => 'A new application is waiting for your review.',
            ];
        }

        if ($collaborations['completed'] === 0) {
            return null;
        }

        if ($this->hasUnreviewedCompletedCollaboration($profile)) {
            return [
                'key' => 'leave_review',
                'title' => 'Leave your review',
                'body' => 'Your review helps future partners collaborate with confidence.',
            ];
        }

        if ($collaborations['completed'] === 1 && $opportunities['published'] < 2) {
            return [
                'key' => 'create_second_offer',
                'title' => 'Ready for your next Kolab?',
                'body' => 'Build on the momentum and create your next offer.',
            ];
        }

        return null;
    }

    private function isProfileComplete(Profile $profile): bool
    {
        $businessProfile = $profile->businessProfile;

        if ($businessProfile === null) {
            return false;
        }

        return filled($businessProfile->name)
            && filled($businessProfile->about)
            && filled($businessProfile->business_type)
            && filled($businessProfile->city_id);
    }

    private function hasUnreviewedCompletedCollaboration(Profile $profile): bool
    {
        return $this->getAllCollaborationsQuery($profile)
            ->where('status', CollaborationStatus::Completed)
            ->whereDoesntHave('reviews', function ($query) use ($profile): void {
                $query->where('reviewer_profile_id', $profile->id);
            })
            ->exists();
    }

    /**
     * Get dashboard stats for a community user.
     *
     * @return array{
     *     applications_sent: array{total: int, pending: int, accepted: int, declined: int, withdrawn: int},
     *     collaborations: array{total: int, active: int, upcoming: int, completed: int},
     *     upcoming_collaborations: \Illuminate\Database\Eloquent\Collection
     * }
     */
    public function getCommunityDashboard(Profile $profile): array
    {
        return [
            'applications_sent' => $this->getSentApplicationStats($profile),
            'collaborations' => $this->getCollaborationStats($profile),
            'upcoming_collaborations' => $this->getUpcomingCollaborations($profile),
        ];
    }

    /**
     * Get opportunity stats for the creator.
     *
     * @return array{total: int, published: int, draft: int, closed: int}
     */
    private function getOpportunityStats(Profile $profile): array
    {
        $kolabs = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => (int) $kolabs->sum(),
            'published' => (int) ($kolabs[KolabStatus::Published->value] ?? 0),
            'draft' => (int) ($kolabs[KolabStatus::Draft->value] ?? 0),
            'closed' => (int) ($kolabs[KolabStatus::Closed->value] ?? 0),
        ];
    }

    /**
     * Get received application stats for the opportunity creator.
     *
     * @return array{total: int, pending: int, accepted: int, declined: int}
     */
    private function getReceivedApplicationStats(Profile $profile): array
    {
        $applications = Application::query()
            ->whereHas('kolab', function ($q) use ($profile) {
                $q->where('creator_profile_id', $profile->id);
            })
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => (int) $applications->sum(),
            'pending' => (int) ($applications[ApplicationStatus::Pending->value] ?? 0),
            'accepted' => (int) ($applications[ApplicationStatus::Accepted->value] ?? 0),
            'declined' => (int) ($applications[ApplicationStatus::Declined->value] ?? 0),
        ];
    }

    /**
     * Get sent application stats for the applicant.
     *
     * @return array{total: int, pending: int, accepted: int, declined: int, withdrawn: int}
     */
    private function getSentApplicationStats(Profile $profile): array
    {
        $applications = $profile->applications()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => (int) $applications->sum(),
            'pending' => (int) ($applications[ApplicationStatus::Pending->value] ?? 0),
            'accepted' => (int) ($applications[ApplicationStatus::Accepted->value] ?? 0),
            'declined' => (int) ($applications[ApplicationStatus::Declined->value] ?? 0),
            'withdrawn' => (int) ($applications[ApplicationStatus::Withdrawn->value] ?? 0),
        ];
    }

    /**
     * Get collaboration stats for a profile (works for both creator and applicant).
     *
     * @return array{total: int, active: int, upcoming: int, completed: int}
     */
    private function getCollaborationStats(Profile $profile): array
    {
        $collaborations = $this->getAllCollaborationsQuery($profile)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $upcoming = $this->getAllCollaborationsQuery($profile)
            ->where('status', CollaborationStatus::Scheduled)
            ->where('scheduled_date', '>=', now()->toDateString())
            ->count();

        return [
            'total' => (int) $collaborations->sum(),
            'active' => (int) ($collaborations[CollaborationStatus::Active->value] ?? 0),
            'upcoming' => $upcoming,
            'completed' => (int) ($collaborations[CollaborationStatus::Completed->value] ?? 0),
        ];
    }

    /**
     * Get upcoming collaborations for a profile, ordered by scheduled date.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Collaboration>
     */
    private function getUpcomingCollaborations(Profile $profile): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getAllCollaborationsQuery($profile)
            ->whereIn('status', [CollaborationStatus::Scheduled, CollaborationStatus::Active])
            ->where(function ($q) {
                $q->whereNull('scheduled_date')
                    ->orWhere('scheduled_date', '>=', now()->toDateString());
            })
            ->with([
                'kolab:id,creator_profile_id,title,description,status,intent_type,community_types,seeking_communities,offering,needs,expects,offers_in_return,venue_preference,venue_address,offer_headline,base_offer,negotiation_triggers,availability_mode,availability_start,availability_end,selected_time,recurring_days,preferred_city,media,past_events,recipient_community_id,published_at',
                'kolab.creatorProfile:id,user_type,avatar_url',
                'applicantProfile.communityProfile:id,profile_id,name',
                'creatorProfile.businessProfile:id,profile_id,name',
            ])
            ->orderBy('scheduled_date')
            ->limit(5)
            ->get();
    }

    /**
     * Build a query for all collaborations where the profile is either creator or applicant.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Collaboration>
     */
    private function getAllCollaborationsQuery(Profile $profile): \Illuminate\Database\Eloquent\Builder
    {
        return Collaboration::query()
            ->where(function ($q) use ($profile) {
                $q->where('creator_profile_id', $profile->id)
                    ->orWhere('applicant_profile_id', $profile->id);
            });
    }
}
