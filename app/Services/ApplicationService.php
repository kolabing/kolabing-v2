<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ApplicationService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Apply to an opportunity.
     *
     * @param  Profile  $applicant  The profile applying to the opportunity
     * @param  CollabOpportunity  $opportunity  The opportunity to apply to
     * @param  array{message?: string|null, availability?: string|null}  $data  Application data
     *
     * @throws InvalidArgumentException When validation fails
     * @throws RuntimeException When subscription requirements are not met
     */
    public function apply(Profile $applicant, CollabOpportunity $opportunity, array $data): Application
    {
        $this->validateCanApply($applicant, $opportunity);

        $application = Application::create([
            'collab_opportunity_id' => $opportunity->id,
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type,
            'message' => $data['message'] ?? null,
            'availability' => $data['availability'] ?? null,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->notificationService->notifyApplicationReceived($application);

        return $application;
    }

    /**
     * Accept an application and create a collaboration.
     *
     * @param  Application  $application  The application to accept
     * @param  array{scheduled_date?: string|null, contact_methods?: array<string, mixed>|null}  $data  Collaboration data
     * @return array{application: Application, collaboration: Collaboration}
     *
     * @throws InvalidArgumentException When application cannot be accepted
     * @throws RuntimeException When subscription requirements are not met
     */
    public function accept(Application $application, array $data = []): array
    {
        $application->loadMissing([
            'collaboration',
            'collabOpportunity.creatorProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        if ($application->isAccepted() && $application->collaboration !== null) {
            return [
                'application' => $application->fresh([
                    'collaboration',
                    'applicantProfile.businessProfile',
                    'applicantProfile.communityProfile',
                    'collabOpportunity.creatorProfile',
                ]),
                'collaboration' => $application->collaboration->fresh(),
            ];
        }

        $this->validateCanAccept($application);

        return DB::transaction(function () use ($application, $data): array {
            $application->update([
                'status' => ApplicationStatus::Accepted,
            ]);

            $collaboration = $this->createCollaboration($application, $data);

            $this->notificationService->notifyApplicationAccepted($application);

            return [
                'application' => $application->fresh(),
                'collaboration' => $collaboration,
            ];
        });
    }

    /**
     * Decline an application.
     *
     * @param  Application  $application  The application to decline
     * @param  string|null  $reason  Optional reason for declining
     *
     * @throws InvalidArgumentException When application cannot be declined
     */
    public function decline(Application $application, ?string $reason = null): Application
    {
        if (! $application->canBeDeclined()) {
            throw new InvalidArgumentException(
                'Application cannot be declined. Current status: '.$application->status->value
            );
        }

        $application->update([
            'status' => ApplicationStatus::Declined,
        ]);

        $this->notificationService->notifyApplicationDeclined($application);

        return $application->fresh();
    }

    /**
     * Withdraw an application.
     *
     * @param  Application  $application  The application to withdraw
     *
     * @throws InvalidArgumentException When application cannot be withdrawn
     */
    public function withdraw(Application $application): Application
    {
        if (! $application->canBeWithdrawn()) {
            throw new InvalidArgumentException(
                'Application cannot be withdrawn. Current status: '.$application->status->value
            );
        }

        $application->update([
            'status' => ApplicationStatus::Withdrawn,
        ]);

        return $application->fresh();
    }

    /**
     * Get applications for a specific opportunity.
     *
     * @param  CollabOpportunity  $opportunity  The opportunity to get applications for
     * @param  array{status?: string|null}  $filters  Filter options
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator<Application>
     */
    public function getForOpportunity(
        CollabOpportunity $opportunity,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Application::query()
            ->where('collab_opportunity_id', $opportunity->id)
            ->with(['applicantProfile.businessProfile', 'applicantProfile.communityProfile'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get applications submitted by a profile.
     *
     * @param  Profile  $profile  The profile to get applications for
     * @param  array{status?: string|null}  $filters  Filter options
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator<Application>
     */
    public function getMyApplications(
        Profile $profile,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Application::query()
            ->where('applicant_profile_id', $profile->id)
            ->with(['collabOpportunity.creatorProfile'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Get applications received on a profile's opportunities.
     *
     * @param  Profile  $profile  The opportunity creator's profile
     * @param  array{status?: string|null, opportunity_id?: string|null}  $filters  Filter options
     * @param  int  $perPage  Number of results per page
     * @return LengthAwarePaginator<Application>
     */
    public function getReceivedApplications(
        Profile $profile,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Application::query()
            ->whereHas('collabOpportunity', function ($q) use ($profile): void {
                $q->where('creator_profile_id', $profile->id);
            })
            ->with([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity',
            ])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['opportunity_id'])) {
            $query->where('collab_opportunity_id', $filters['opportunity_id']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Find an application by ID or throw an exception.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(string $id): Application
    {
        return Application::query()
            ->with([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity.creatorProfile',
                'collaboration',
            ])
            ->findOrFail($id);
    }

    /**
     * Validate that a profile can apply to an opportunity.
     *
     * @throws InvalidArgumentException When validation fails
     * @throws RuntimeException When subscription requirements are not met
     */
    private function validateCanApply(Profile $applicant, CollabOpportunity $opportunity): void
    {
        // Cannot apply to own opportunity
        if ($opportunity->creator_profile_id === $applicant->id) {
            throw new InvalidArgumentException('You cannot apply to your own opportunity.');
        }

        // Opportunity must be published
        if (! $opportunity->isPublished()) {
            throw new InvalidArgumentException(
                'This opportunity is not accepting applications. Status: '.$opportunity->status->value
            );
        }

        // Profile must have completed onboarding
        if (! $applicant->onboarding_completed) {
            throw new InvalidArgumentException(
                'You must complete your profile before applying to opportunities.'
            );
        }

        // Check for existing application (unique constraint will also catch this)
        $existingApplication = Application::query()
            ->where('collab_opportunity_id', $opportunity->id)
            ->where('applicant_profile_id', $applicant->id)
            ->exists();

        if ($existingApplication) {
            throw new InvalidArgumentException(
                'You have already applied to this opportunity.'
            );
        }
    }

    /**
     * Validate that an application can be accepted.
     *
     * @throws InvalidArgumentException When application cannot be accepted
     * @throws RuntimeException When subscription requirements are not met
     */
    private function validateCanAccept(Application $application): void
    {
        if (! $application->canBeAccepted()) {
            throw new InvalidArgumentException(
                'Application cannot be accepted. Current status: '.$application->status->value
            );
        }

        // Load the opportunity creator if not already loaded
        $opportunity = $application->collabOpportunity;
        $opportunity->loadMissing('creatorProfile');

        // Business users must have active subscription to accept applications
        $creator = $opportunity->creatorProfile;
        if ($creator->isBusiness() && ! $creator->hasActiveSubscription()) {
            throw new RuntimeException(
                'An active subscription is required to accept applications.'
            );
        }
    }

    /**
     * Create a collaboration from an accepted application.
     *
     * @param  Application  $application  The accepted application
     * @param  array{scheduled_date?: string|null, contact_methods?: array<string, mixed>|null}  $data  Collaboration data
     */
    private function createCollaboration(Application $application, array $data): Collaboration
    {
        $opportunity = $application->collabOpportunity;
        $creator = $opportunity->creatorProfile;
        $applicant = $application->applicantProfile;

        // Determine which profile is business and which is community
        $businessProfileId = $creator->isBusiness()
            ? $creator->businessProfile?->id
            : $applicant->businessProfile?->id;

        $communityProfileId = $creator->isCommunity()
            ? $creator->communityProfile?->id
            : $applicant->communityProfile?->id;

        return Collaboration::create([
            'application_id' => $application->id,
            'collab_opportunity_id' => $opportunity->id,
            'creator_profile_id' => $creator->id,
            'applicant_profile_id' => $applicant->id,
            'business_profile_id' => $businessProfileId,
            'community_profile_id' => $communityProfileId,
            'status' => CollaborationStatus::Scheduled,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'contact_methods' => $data['contact_methods'] ?? null,
        ]);
    }
}
