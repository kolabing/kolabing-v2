<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CollaborationService
{
    public function __construct(
        private readonly GamificationWalletService $walletService
    ) {}

    /**
     * Get collaborations for a profile with filtering and pagination.
     *
     * @param  array{status?: string, role?: string}  $filters
     * @return LengthAwarePaginator<Collaboration>
     */
    public function getForProfile(Profile $profile, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Collaboration::query()
            ->where(function (Builder $query) use ($profile): void {
                $query->where('creator_profile_id', $profile->id)
                    ->orWhere('applicant_profile_id', $profile->id);
            });

        $this->applyFilters($query, $filters, $profile);

        $paginator = $query
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
                'reviews',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $paginator->getCollection()->each(fn (Collaboration $collaboration) => $this->loadFeedback($collaboration));

        return $paginator;
    }

    /**
     * Find a collaboration by ID or throw exception.
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $id): Collaboration
    {
        $collaboration = Collaboration::query()
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
                'reviews',
            ])
            ->findOrFail($id);

        return $this->loadFeedback($collaboration);
    }

    /**
     * Activate a scheduled collaboration.
     *
     * @throws CollaborationException
     */
    public function activate(Collaboration $collaboration): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeActivated()) {
            throw CollaborationException::cannotActivate($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Active,
        ]);

        return $collaboration->fresh([
            'collabOpportunity',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);
    }

    /**
     * Complete an active collaboration.
     *
     * @throws CollaborationException
     */
    public function complete(Collaboration $collaboration, ?string $feedback = null): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeCompleted()) {
            throw CollaborationException::cannotComplete($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Completed,
            'completed_at' => Carbon::now(),
        ]);

        // Award points to both parties
        $this->awardCollaborationPoints($collaboration);

        return $collaboration->fresh([
            'collabOpportunity',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);
    }

    /**
     * Finish a collaboration on behalf of one participant. Feedback is REQUIRED
     * to finish (ROLES-AND-PERMISSIONS.md §4): the validated, role-specific
     * feedback payload is persisted as exactly one collaboration_feedback row per
     * (collaboration, reviewer) and the public star rating + note are mirrored
     * onto collaboration_reviews. Transitions a scheduled/active collaboration to
     * completed (the date-passed path closes it the same way). Idempotent-safe:
     * if already completed, the caller's feedback is still recorded without
     * re-awarding points. Rejects only when the collaboration has been cancelled.
     *
     * The controller guarantees a non-empty $feedback via FinishCollaborationRequest,
     * so reaching finish() without feedback is impossible through the API.
     *
     * @param  array<string, mixed>  $feedback  Validated payload from FinishCollaborationRequest.
     *
     * @throws CollaborationException
     */
    public function finish(
        Collaboration $collaboration,
        Profile $profile,
        array $feedback
    ): Collaboration {
        if ($collaboration->isCancelled()) {
            throw CollaborationException::cannotComplete($collaboration->status->value);
        }

        $role = $this->getProfileRole($collaboration, $profile);
        $reviewerType = $profile->isBusiness() ? 'business' : 'community';

        return DB::transaction(function () use ($collaboration, $profile, $role, $reviewerType, $feedback): Collaboration {
            // One rich feedback row per side, unique on (collaboration, reviewer).
            CollaborationFeedback::query()->updateOrCreate(
                [
                    'collaboration_id' => $collaboration->id,
                    'reviewer_profile_id' => $profile->id,
                ],
                [
                    'reviewer_type' => $reviewerType,
                    'reviewer_role' => $role,
                    'rating' => (int) $feedback['rating'],
                    'posts_reels' => isset($feedback['posts_reels']) ? (int) $feedback['posts_reels'] : null,
                    'expectation_match' => (bool) $feedback['expectation_match'],
                    'would_recommend' => (bool) $feedback['would_recommend'],
                    'stories_posted' => $reviewerType === 'business' && isset($feedback['stories_posted'])
                        ? (int) $feedback['stories_posted']
                        : null,
                    'revenue' => $reviewerType === 'business' && isset($feedback['revenue'])
                        ? $feedback['revenue']
                        : null,
                    'benefits' => $reviewerType === 'community'
                        ? ($feedback['benefits'] ?? null)
                        : null,
                ]
            );

            // Mirror the public star rating + optional note onto the review row,
            // which feeds profile ratings and positioning.
            $collaboration->reviews()->updateOrCreate(
                ['reviewer_profile_id' => $profile->id],
                [
                    'reviewer_role' => $role,
                    'rating' => (int) $feedback['rating'],
                    'note' => $feedback['note'] ?? null,
                ]
            );

            if (! $collaboration->isCompleted()) {
                $collaboration->update([
                    'status' => CollaborationStatus::Completed,
                    'completed_at' => Carbon::now(),
                ]);

                $this->awardCollaborationPoints($collaboration);
            }

            $fresh = $collaboration->fresh([
                'collabOpportunity',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
                'reviews',
            ]);

            $this->loadFeedback($fresh);

            return $fresh;
        });
    }

    /**
     * Attach the collaboration_feedback rows onto the model as a "feedback"
     * relation. Done here (rather than via a relationship method on the model)
     * to keep the change inside CollaborationService ownership; the resource
     * reads it through whenLoaded('feedback').
     */
    public function loadFeedback(Collaboration $collaboration): Collaboration
    {
        $feedback = CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->orderBy('created_at')
            ->get();

        $collaboration->setRelation('feedback', $feedback);

        return $collaboration;
    }

    /**
     * Cancel a collaboration that is not yet completed.
     *
     * @throws CollaborationException
     */
    public function cancel(Collaboration $collaboration, string $reason): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeCancelled()) {
            throw CollaborationException::cannotCancel($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Cancelled,
        ]);

        return $collaboration->fresh([
            'collabOpportunity',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);
    }

    /**
     * Create a collaboration from an accepted application.
     *
     * @param  array{scheduled_date?: string, contact_methods?: array<string, mixed>}  $data
     *
     * @throws CollaborationException
     */
    public function createFromApplication(Application $application, array $data = []): Collaboration
    {
        if (! $application->isAccepted()) {
            throw CollaborationException::applicationNotAccepted();
        }

        $existingCollaboration = Collaboration::query()
            ->where('application_id', $application->id)
            ->exists();

        if ($existingCollaboration) {
            throw CollaborationException::collaborationAlreadyExists();
        }

        $application->loadMissing(['collabOpportunity.creatorProfile', 'applicantProfile']);

        $opportunity = $application->collabOpportunity;
        $creatorProfile = $opportunity->creatorProfile;
        $applicantProfile = $application->applicantProfile;

        $businessProfileId = $this->resolveBusinessProfileId($creatorProfile, $applicantProfile);
        $communityProfileId = $this->resolveCommunityProfileId($creatorProfile, $applicantProfile);

        return DB::transaction(function () use (
            $application,
            $opportunity,
            $creatorProfile,
            $applicantProfile,
            $businessProfileId,
            $communityProfileId,
            $data
        ): Collaboration {
            $collaboration = Collaboration::create([
                'application_id' => $application->id,
                'collab_opportunity_id' => $opportunity->id,
                'creator_profile_id' => $creatorProfile->id,
                'applicant_profile_id' => $applicantProfile->id,
                'business_profile_id' => $businessProfileId,
                'community_profile_id' => $communityProfileId,
                'status' => CollaborationStatus::Scheduled,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'contact_methods' => $data['contact_methods'] ?? null,
            ]);

            return $collaboration->load([
                'collabOpportunity',
                'creatorProfile',
                'applicantProfile',
                'application',
                'challenges',
            ]);
        });
    }

    /**
     * Check if a profile is a participant in a collaboration.
     */
    public function isParticipant(Collaboration $collaboration, Profile $profile): bool
    {
        return $collaboration->creator_profile_id === $profile->id
            || $collaboration->applicant_profile_id === $profile->id;
    }

    /**
     * Get the role of a profile in a collaboration.
     *
     * @return 'creator'|'applicant'|null
     */
    public function getProfileRole(Collaboration $collaboration, Profile $profile): ?string
    {
        if ($collaboration->creator_profile_id === $profile->id) {
            return 'creator';
        }

        if ($collaboration->applicant_profile_id === $profile->id) {
            return 'applicant';
        }

        return null;
    }

    /**
     * Apply filters to the collaboration query.
     *
     * @param  array{status?: string, role?: string}  $filters
     */
    private function applyFilters(Builder $query, array $filters, Profile $profile): void
    {
        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = CollaborationStatus::tryFrom($filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if (isset($filters['role']) && $filters['role'] !== '') {
            match ($filters['role']) {
                'creator' => $query->where('creator_profile_id', $profile->id),
                'applicant' => $query->where('applicant_profile_id', $profile->id),
                default => null,
            };
        }
    }

    /**
     * Resolve the business profile ID from the participants.
     */
    private function resolveBusinessProfileId(Profile $creatorProfile, Profile $applicantProfile): ?string
    {
        if ($creatorProfile->isBusiness()) {
            return $creatorProfile->businessProfile?->id;
        }

        if ($applicantProfile->isBusiness()) {
            return $applicantProfile->businessProfile?->id;
        }

        return null;
    }

    /**
     * Resolve the community profile ID from the participants.
     */
    private function resolveCommunityProfileId(Profile $creatorProfile, Profile $applicantProfile): ?string
    {
        if ($creatorProfile->isCommunity()) {
            return $creatorProfile->communityProfile?->id;
        }

        if ($applicantProfile->isCommunity()) {
            return $applicantProfile->communityProfile?->id;
        }

        return null;
    }

    /**
     * Award collaboration completion points to both parties.
     */
    private function awardCollaborationPoints(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['collabOpportunity']);
        $title = $collaboration->collabOpportunity?->title ?? 'a collaboration';

        $this->walletService->awardPoints(
            $collaboration->creator_profile_id,
            PointEventType::CollaborationComplete->defaultPoints(),
            PointEventType::CollaborationComplete,
            $collaboration->id,
            "Collaboration completed: {$title}"
        );

        $this->walletService->awardPoints(
            $collaboration->applicant_profile_id,
            PointEventType::CollaborationComplete->defaultPoints(),
            PointEventType::CollaborationComplete,
            $collaboration->id,
            "Collaboration completed: {$title}"
        );
    }
}
