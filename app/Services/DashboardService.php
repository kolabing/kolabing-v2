<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Profile;

class DashboardService
{
    /**
     * Get dashboard stats for a business user.
     *
     * @return array{
     *     opportunities: array{total: int, published: int, draft: int, closed: int},
     *     applications_received: array{total: int, pending: int, accepted: int, declined: int},
     *     collaborations: array{total: int, active: int, upcoming: int, completed: int},
     *     upcoming_collaborations: \Illuminate\Database\Eloquent\Collection
     * }
     */
    public function getBusinessDashboard(Profile $profile): array
    {
        return [
            'opportunities' => $this->getOpportunityStats($profile),
            'applications_received' => $this->getReceivedApplicationStats($profile),
            'collaborations' => $this->getCollaborationStats($profile),
            'upcoming_collaborations' => $this->getUpcomingCollaborations($profile),
        ];
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
        $opportunities = $profile->createdOpportunities()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'total' => (int) $opportunities->sum(),
            'published' => (int) ($opportunities[OfferStatus::Published->value] ?? 0),
            'draft' => (int) ($opportunities[OfferStatus::Draft->value] ?? 0),
            'closed' => (int) ($opportunities[OfferStatus::Closed->value] ?? 0),
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
            ->whereHas('collabOpportunity', function ($q) use ($profile) {
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
            ->with(['collabOpportunity:id,title,categories,availability_start', 'applicantProfile.communityProfile:id,profile_id,name', 'creatorProfile.businessProfile:id,profile_id,name'])
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
