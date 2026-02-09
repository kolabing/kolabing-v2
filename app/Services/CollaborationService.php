<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Exceptions\CollaborationException;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CollaborationService
{
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

        return $query
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Find a collaboration by ID or throw exception.
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $id): Collaboration
    {
        return Collaboration::query()
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
            ])
            ->findOrFail($id);
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

        return $collaboration->fresh([
            'collabOpportunity',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);
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
}
