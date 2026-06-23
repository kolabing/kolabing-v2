<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Exceptions\CollaborationException;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\PostHog\PostHogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CollaborationService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
        private readonly CollaborationFeedbackService $feedbackService,
        private readonly PostHogService $postHog,
        private readonly NotificationService $notificationService,
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
                'kolab',
                'kolab.creatorProfile',
                // The collaboration's own business/community profiles carry the
                // partner logo (profile_photo) the Active/Finished cards show —
                // load them so CollaborationResource surfaces business_profile /
                // community_profile (otherwise whenLoaded omits them -> no photo).
                'businessProfile',
                'communityProfile',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
                'challengeBonuses',
                'feedbacks',
                'reviews',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

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
                'kolab',
                'kolab.creatorProfile',
                // The collaboration's own business/community profiles carry the
                // partner logo (profile_photo) the Active/Finished cards show —
                // load them so CollaborationResource surfaces business_profile /
                // community_profile (otherwise whenLoaded omits them -> no photo).
                'businessProfile',
                'communityProfile',
                'creatorProfile.businessProfile.city',
                'creatorProfile.communityProfile.city',
                'applicantProfile.businessProfile.city',
                'applicantProfile.communityProfile.city',
                'application',
                'challenges',
                'challengeBonuses',
                'feedbacks',
                'reviews',
            ])
            ->findOrFail($id);

        return $collaboration;
    }

    /**
     * Activate a scheduled collaboration.
     *
     * @throws CollaborationException
     */
    public function activate(Collaboration $collaboration, ?Profile $actor = null): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeActivated()) {
            throw CollaborationException::cannotActivate($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Active,
            'activated_at' => Carbon::now(),
        ]);

        $fresh = $collaboration->fresh([
            'kolab',
            'kolab.creatorProfile',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);

        $this->dispatchNotification(fn () => $this->notificationService->notifyCollaborationActivated(
            $fresh,
            $actor ?? $fresh->creatorProfile,
        ));

        return $fresh;
    }

    /**
     * Complete an active collaboration. The caller must have submitted their
     * own feedback row and the partner must have too. XP is NOT awarded here
     * — each party's CollaborationComplete XP fires when they POST /feedback.
     *
     * Gating can be disabled via the `collaborations.complete_requires_feedback`
     * config (true by default) — provides a soft-rollout knob if a mobile
     * cutover regresses.
     *
     * @throws CollaborationException
     */
    public function complete(Collaboration $collaboration, ?Profile $caller = null): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeCompleted()) {
            throw CollaborationException::cannotComplete($collaboration->status->value);
        }

        if (config('collaborations.complete_requires_feedback', true) === true) {
            $this->enforceFeedbackGate($collaboration, $caller);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Completed,
            'completed_at' => Carbon::now(),
        ]);

        $fresh = $collaboration->fresh([
            'kolab',
            'kolab.creatorProfile',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);

        $this->dispatchNotification(fn () => $this->notificationService->notifyCollaborationCompleted(
            $fresh,
            $caller ?? $fresh->creatorProfile,
        ));

        return $fresh;
    }

    /**
     * Maintainer-only escape hatch. Force-completes a collaboration without
     * the feedback gate, recording the reason for the admin audit trail.
     * No XP is awarded — by design, force-complete bypasses the participation
     * reward path.
     */
    public function adminForceComplete(Collaboration $collaboration, string $reason): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Completed,
            'completed_at' => Carbon::now(),
            'completion_reason' => $reason,
            'completed_by_profile_id' => null,
        ]);

        $fresh = $collaboration->fresh([
            'kolab',
            'kolab.creatorProfile',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);

        $this->dispatchNotification(
            fn () => $this->notificationService->notifyCollaborationCompleted($fresh, null),
        );

        return $fresh;
    }

    /**
     * Scheduled-command path. Used by app:auto-complete-stale-collaborations
     * when scheduled_date + threshold has passed AND at least one feedback
     * row exists. Sets auto_completed_at as the audit marker.
     */
    public function autoComplete(Collaboration $collaboration): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        $now = Carbon::now();
        $collaboration->update([
            'status' => CollaborationStatus::Completed,
            'completed_at' => $now,
            'auto_completed_at' => $now,
        ]);

        $fresh = $collaboration->fresh([
            'kolab',
            'kolab.creatorProfile',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);

        $this->dispatchNotification(
            fn () => $this->notificationService->notifyCollaborationCompleted($fresh, null),
        );

        return $fresh;
    }

    /**
     * @throws CollaborationException
     */
    private function enforceFeedbackGate(Collaboration $collaboration, ?Profile $caller): void
    {
        $pending = $this->feedbackService->pendingFeedbackFrom($collaboration);

        if ($pending === []) {
            return;
        }

        if ($caller !== null && in_array($caller->user_type->value, $pending, true)) {
            throw CollaborationException::awaitingOwnFeedback();
        }

        throw CollaborationException::awaitingPartnerFeedback($pending);
    }

    /**
     * Cancel a collaboration that is not yet completed.
     *
     * @throws CollaborationException
     */
    public function cancel(Collaboration $collaboration, string $reason, ?Profile $cancelledBy = null): Collaboration
    {
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        if (! $collaboration->canBeCancelled()) {
            throw CollaborationException::cannotCancel($collaboration->status->value);
        }

        $collaboration->update([
            'status' => CollaborationStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $reason,
            'cancelled_by_profile_id' => $cancelledBy?->id,
        ]);

        $fresh = $collaboration->fresh([
            'kolab',
            'kolab.creatorProfile',
            'creatorProfile',
            'applicantProfile',
            'application',
        ]);

        $distinctProfile = $cancelledBy ?? $fresh->creatorProfile;

        $this->postHog->capture($distinctProfile, 'collaboration_cancelled_server_side', [
            'collaboration_id' => $fresh->id,
            'kolab_id' => $fresh->kolab_id,
            'cancelled_by_profile_id' => $cancelledBy?->id,
            'cancelled_by_role' => $cancelledBy?->user_type->value ?? 'maintainer',
        ]);

        $this->dispatchNotification(
            fn () => $this->notificationService->notifyCollaborationCancelled($fresh, $cancelledBy),
        );

        return $fresh;
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

        $application->loadMissing(['kolab.creatorProfile', 'applicantProfile']);

        $opportunity = $application->kolab;
        if ($opportunity === null) {
            throw CollaborationException::applicationNotAccepted();
        }

        $creatorProfile = $opportunity->creatorProfile;
        $applicantProfile = $application->applicantProfile;

        $businessProfileId = $this->resolveBusinessProfileId($creatorProfile, $applicantProfile);
        $communityProfileId = $this->resolveCommunityProfileId($creatorProfile, $applicantProfile);

        $collaboration = DB::transaction(function () use (
            $application,
            $creatorProfile,
            $applicantProfile,
            $businessProfileId,
            $communityProfileId,
            $data
        ): Collaboration {
            $collaboration = Collaboration::create([
                'application_id' => $application->id,
                'kolab_id' => $application->kolab_id,
                'creator_profile_id' => $creatorProfile->id,
                'applicant_profile_id' => $applicantProfile->id,
                'business_profile_id' => $businessProfileId,
                'community_profile_id' => $communityProfileId,
                'status' => CollaborationStatus::Scheduled,
                'scheduled_date' => $data['scheduled_date'] ?? null,
                'contact_methods' => $data['contact_methods'] ?? null,
            ]);

            // Seed role-based default challenges. Idempotent — skips if any
            // challenges already attached.
            app(\App\Services\Admin\ChallengeDefaultsService::class)
                ->seedForCollaboration($collaboration);

            return $collaboration->load([
                'kolab',
                'kolab.creatorProfile',
                'creatorProfile',
                'applicantProfile',
                'application',
                'challenges',
            ]);
        });

        $this->dispatchNotification(
            fn () => $this->notificationService->notifyCollaborationCreated($collaboration),
        );

        return $collaboration;
    }

    /**
     * Dispatch a collaboration notification, swallowing and reporting any
     * failure so a push/notification error never breaks the state transition.
     */
    private function dispatchNotification(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            report($e);
        }
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
