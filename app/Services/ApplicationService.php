<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Exceptions\SubscriptionRequiredException;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\PostHog\PostHogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ApplicationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationReminderService $notificationReminderService,
        private readonly PostHogService $postHog,
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

        // Phase 1 dual-write: also persist kolab_id. Because the legacy bridge
        // materializes collab_opportunities with id = kolab.id, the opportunity id
        // is already the kolab id for every kolab-originated row. We confirm a kolab
        // exists before setting it so true-legacy opportunities stay NULL.
        $kolabId = $this->resolveKolabId($opportunity->id);

        $application = Application::create([
            'collab_opportunity_id' => $opportunity->id,
            'kolab_id' => $kolabId,
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type,
            'message' => $data['message'] ?? null,
            'availability' => $data['availability'] ?? null,
            'status' => ApplicationStatus::Pending,
        ]);

        $this->notificationService->notifyApplicationReceived($application);
        $this->notificationReminderService->syncApplicationPendingReminder($application->fresh(['collabOpportunity']));

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

        $result = DB::transaction(function () use ($application, $data): array {
            $application->update([
                'status' => ApplicationStatus::Accepted,
                'accepted_at' => now(),
            ]);
            $this->notificationReminderService->syncApplicationPendingReminder($application->fresh(['collabOpportunity']));

            $collaboration = $this->createCollaboration($application, $data);

            $this->notificationService->notifyApplicationAccepted($application);

            return [
                'application' => $application->fresh(),
                'collaboration' => $collaboration,
            ];
        });

        $acceptedApplication = $result['application']->loadMissing('collabOpportunity.creatorProfile');
        $collaboration = $result['collaboration'];

        $this->postHog->capture($acceptedApplication->collabOpportunity->creatorProfile, 'application_accepted_server_side', [
            'application_id' => $acceptedApplication->id,
            'kolab_id' => $acceptedApplication->collab_opportunity_id,
            'collaboration_id' => $collaboration->id,
            'applicant_profile_type' => $acceptedApplication->applicant_profile_type->value,
        ]);

        return $result;
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
            'declined_at' => now(),
        ]);
        $this->notificationReminderService->syncApplicationPendingReminder($application->fresh(['collabOpportunity']));

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
            'withdrawn_at' => now(),
        ]);
        $this->notificationReminderService->syncApplicationPendingReminder($application->fresh(['collabOpportunity']));

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
     * @throws SubscriptionRequiredException When a business applicant lacks an active subscription
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

        if (is_string($opportunity->recipient_community_id)
            && $opportunity->recipient_community_id !== ''
            && $opportunity->recipient_community_id !== $applicant->id) {
            throw new InvalidArgumentException(
                'This direct proposal is only available to the selected community.'
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

        // Paywall is Business-only. Communities are NEVER gated when applying.
        // A free (non-subscribed) business must subscribe to apply to a Kolab.
        if ($applicant->isBusiness() && ! $applicant->hasActiveSubscription()) {
            throw new SubscriptionRequiredException(
                'An active subscription is required to apply to this opportunity.'
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
     * Resolve the kolab id for an opportunity id (Phase 1 dual-write helper).
     *
     * The legacy bridge persists collab_opportunities with id = kolab.id, so the
     * opportunity id IS the kolab id whenever a kolab exists. Returns null for
     * true-legacy opportunities that have no backing kolab.
     */
    private function resolveKolabId(string $opportunityId): ?string
    {
        return Kolab::query()->whereKey($opportunityId)->exists()
            ? $opportunityId
            : null;
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

        // Phase 1 dual-write: prefer the application's kolab_id (set on apply); fall
        // back to resolving from the opportunity id for older pending applications
        // created before the dual-write was in place.
        $kolabId = $application->kolab_id ?? $this->resolveKolabId($opportunity->id);

        return Collaboration::create([
            'application_id' => $application->id,
            'collab_opportunity_id' => $opportunity->id,
            'kolab_id' => $kolabId,
            'creator_profile_id' => $creator->id,
            'applicant_profile_id' => $applicant->id,
            'business_profile_id' => $businessProfileId,
            'community_profile_id' => $communityProfileId,
            'status' => CollaborationStatus::Scheduled,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'contact_methods' => ! empty($data['contact_methods']) ? $data['contact_methods'] : null,
        ]);
    }
}
