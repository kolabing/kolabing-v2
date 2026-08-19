<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\DuplicateRoleApplicationException;
use App\Exceptions\MultiKolabApplicationRejectedException;
use App\Exceptions\MultiKolabNotOrganizerException;
use App\Exceptions\RoleCapacityExceededException;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\Concerns\RunsSideEffects;
use App\Services\PostHog\PostHogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Free role applications on a {@see \App\Models\MultiKolabEvent}. Applying is
 * never gated by {@see Profile::hasActiveSubscription()} or
 * {@see Profile::hasEventCreatorEntitlement()} — only publishing an event
 * requires entitlement; applying to one never does (Global Constraints).
 */
class MultiKolabRoleApplicationService
{
    use RunsSideEffects;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly PostHogService $postHog,
    ) {}

    /**
     * @param  array{pitch?: string|null, availability?: string|null}  $data
     */
    public function apply(MultiKolabRole $role, Profile $applicant, array $data): MultiKolabRoleApplication
    {
        $this->validateCanApply($role, $applicant, $data);

        $application = MultiKolabRoleApplication::query()->create([
            'multi_kolab_role_id' => $role->id,
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type->value,
            'status' => MultiKolabRoleApplicationStatus::Pending,
            'pitch' => $data['pitch'],
            'availability' => $data['availability'] ?? null,
        ]);

        $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabApplicationReceived($application));
        $this->runSideEffect(fn () => $this->postHog->capture($applicant, 'role_application_submitted', [
            'role_id' => $role->id,
            'application_id' => $application->id,
        ]));

        return $application;
    }

    public function shortlist(MultiKolabRoleApplication $application, Profile $actor): MultiKolabRoleApplication
    {
        $this->assertOrganizer($application, $actor);

        if ($application->status !== MultiKolabRoleApplicationStatus::Pending) {
            throw new InvalidArgumentException(
                "Cannot shortlist an application with status \"{$application->status->value}\"."
            );
        }

        $application->update(['status' => MultiKolabRoleApplicationStatus::Shortlisted]);
        $application = $application->fresh();

        $this->runSideEffect(fn () => $this->postHog->capture($actor, 'applicant_shortlisted', [
            'application_id' => $application->id,
        ]));

        return $application;
    }

    public function decline(MultiKolabRoleApplication $application, Profile $actor): MultiKolabRoleApplication
    {
        $this->assertOrganizer($application, $actor);

        if (! in_array($application->status, [
            MultiKolabRoleApplicationStatus::Pending,
            MultiKolabRoleApplicationStatus::Shortlisted,
        ], true)) {
            throw new InvalidArgumentException(
                "Cannot decline an application with status \"{$application->status->value}\"."
            );
        }

        $application->update([
            'status' => MultiKolabRoleApplicationStatus::Declined,
            'declined_at' => now(),
        ]);
        $application = $application->fresh();

        $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabApplicantDeclined($application));

        return $application;
    }

    /**
     * Accept a role application: create exactly one canonical child
     * {@see Kolab}, an accepted canonical {@see Application}, and a
     * {@see Collaboration} — inside one locked transaction that rechecks
     * ownership, status, and role capacity after acquiring the lock, so two
     * concurrent accept attempts on a one-position role can never both
     * succeed (Task 6).
     *
     * Deliberately does NOT call {@see \App\Services\ApplicationService::accept()}
     * — that path re-validates the business-subscription paywall via
     * `validateCanAccept()`, which must never apply to a free Multi-Kolab
     * role application (see the frozen API contract §11). Instead this
     * mirrors the canonical field mapping directly via Eloquent `create()`,
     * matching `ApplicationService::createCollaboration()`'s shape without
     * going through any paywalled entry point.
     *
     * Idempotent: a repeated call on an application that is already accepted
     * and already linked to a Kolab returns that same Kolab without creating
     * duplicates or incrementing capacity again.
     */
    public function accept(MultiKolabRoleApplication $application, Profile $actor): Kolab
    {
        if ($this->isAlreadyAccepted($application)) {
            return $application->kolab()->firstOrFail();
        }

        // The transaction contains ONLY critical, atomic domain/database
        // work, and returns enough information for the caller to determine
        // the resulting entity, whether a real state transition occurred,
        // and which side effects (if any) to attempt. Notifications/analytics
        // are deliberately NOT called in here — see the post-commit block
        // below and RunsSideEffects.
        $outcome = DB::transaction(function () use ($application, $actor): array {
            $lockedApplication = MultiKolabRoleApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($this->isAlreadyAccepted($lockedApplication)) {
                return ['newly_accepted' => false, 'kolab' => $lockedApplication->kolab()->firstOrFail()];
            }

            if (! in_array($lockedApplication->status, [
                MultiKolabRoleApplicationStatus::Pending,
                MultiKolabRoleApplicationStatus::Shortlisted,
            ], true)) {
                throw new InvalidArgumentException(
                    "Cannot accept an application with status \"{$lockedApplication->status->value}\"."
                );
            }

            $role = MultiKolabRole::query()->lockForUpdate()->findOrFail($lockedApplication->multi_kolab_role_id);
            $role->loadMissing('event.creatorProfile');
            $event = $role->event;

            if ($event === null || $actor->id !== $event->creator_profile_id) {
                throw new MultiKolabNotOrganizerException('Only the event organizer may accept this application.');
            }

            if ($event->status !== MultiKolabEventStatus::Recruiting) {
                throw new InvalidArgumentException(
                    "Cannot accept an application while the event is \"{$event->status->value}\" — only a recruiting event may accept new partners."
                );
            }

            if ($role->positions_filled >= $role->positions_needed) {
                throw new RoleCapacityExceededException('This role has no remaining positions.');
            }

            $organizer = $event->creatorProfile;
            $applicant = $lockedApplication->applicantProfile;

            $kolab = Kolab::query()->create([
                'creator_profile_id' => $organizer->id,
                'intent_type' => $organizer->isBusiness() ? IntentType::VenuePromotion : IntentType::CommunitySeeking,
                'status' => KolabStatus::Published,
                'title' => "{$event->title} — {$role->title}",
                'description' => (string) ($role->need ?? $role->details ?? $event->description ?? ''),
                'preferred_city' => (string) ($event->city ?? ''),
                'published_at' => now(),
                'multi_kolab_event_id' => $event->id,
                'multi_kolab_role_id' => $role->id,
            ]);

            $canonicalApplication = Application::query()->create([
                'kolab_id' => $kolab->id,
                'applicant_profile_id' => $applicant->id,
                'applicant_profile_type' => $applicant->user_type,
                'message' => $lockedApplication->pitch,
                'availability' => $lockedApplication->availability,
                'status' => ApplicationStatus::Accepted,
                'accepted_at' => now(),
            ]);

            Collaboration::query()->create([
                'application_id' => $canonicalApplication->id,
                'kolab_id' => $kolab->id,
                'creator_profile_id' => $organizer->id,
                'applicant_profile_id' => $applicant->id,
                'business_profile_id' => $organizer->isBusiness()
                    ? $organizer->businessProfile?->id
                    : $applicant->businessProfile?->id,
                'community_profile_id' => $organizer->isCommunity()
                    ? $organizer->communityProfile?->id
                    : $applicant->communityProfile?->id,
                'status' => CollaborationStatus::Scheduled,
            ]);

            $newPositionsFilled = $role->positions_filled + 1;
            $role->update([
                'positions_filled' => $newPositionsFilled,
                'status' => $newPositionsFilled >= $role->positions_needed
                    ? MultiKolabRoleStatus::Filled
                    : $role->status,
            ]);

            $lockedApplication->update([
                'status' => MultiKolabRoleApplicationStatus::Accepted,
                'accepted_at' => now(),
                'kolab_id' => $kolab->id,
            ]);

            return [
                'newly_accepted' => true,
                'kolab' => $kolab->fresh(),
                'application' => $lockedApplication->fresh(),
                'role' => $role->fresh(),
                'organizer' => $organizer,
                'applicant' => $applicant,
                'role_became_filled' => $newPositionsFilled >= $role->positions_needed,
            ];
        });

        if (! $outcome['newly_accepted']) {
            // Idempotent replay (lost the race to a concurrent accept, or a
            // retry of an already-accepted application) — no new state was
            // committed, so no side effect fires again.
            return $outcome['kolab'];
        }

        // Post-commit, best-effort side effects. Each is isolated in its own
        // try/catch (via runSideEffect) so one failure never prevents the
        // others from being attempted, and none of them can roll back or
        // invalidate the domain state already committed above.
        $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabApplicantAccepted($outcome['application']));
        $this->runSideEffect(fn () => $this->postHog->capture($outcome['applicant'], 'applicant_accepted', [
            'application_id' => $outcome['application']->id,
            'kolab_id' => $outcome['kolab']->id,
        ]));

        if ($outcome['role_became_filled']) {
            $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabRoleFilled($outcome['role']));
            $this->runSideEffect(fn () => $this->postHog->capture($outcome['organizer'], 'role_filled', ['role_id' => $outcome['role']->id]));
        }

        return $outcome['kolab'];
    }

    private function isAlreadyAccepted(MultiKolabRoleApplication $application): bool
    {
        return $application->status === MultiKolabRoleApplicationStatus::Accepted
            && $application->kolab_id !== null;
    }

    /**
     * Withdraw an application. A reason is required only when withdrawing an
     * already-accepted application (the "post-acceptance withdrawal reason"
     * from the plan) — withdrawing a pending/shortlisted application does
     * not require one. Withdrawing an accepted application transactionally
     * decrements the role's `positions_filled` (never below zero) and
     * reopens the role to `open` if it had been marked `filled`.
     */
    /**
     * Withdraw an application. Status determination happens ENTIRELY inside
     * the locked transaction (Review item #10) so a concurrent double-
     * withdraw can never decrement role capacity twice: the application row
     * is reloaded with `lockForUpdate()`, ownership/allowed-status/reason
     * validation is re-run against that locked snapshot, and — when the
     * locked snapshot was Accepted — the canonical child Kolab and
     * Collaboration are cancelled and the role's `positions_filled` is
     * decremented exactly once, all before the lock is released.
     *
     * Withdrawing an already-accepted application additionally (Review
     * item #4) transitions the canonical partnership out of its live state
     * using EXISTING domain statuses (Kolab → `closed`, Collaboration →
     * `cancelled`) rather than deleting any historical record, and (Review
     * item #5) only reopens the role to `open` when it had been
     * auto-`filled` by capacity — never when the organizer had explicitly
     * `closed` it, and never when the parent event is no longer Recruiting.
     */
    public function withdraw(
        MultiKolabRoleApplication $application,
        Profile $actor,
        ?string $reason,
    ): MultiKolabRoleApplication {
        $outcome = DB::transaction(function () use ($application, $actor, $reason): array {
            $lockedApplication = MultiKolabRoleApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($actor->id !== $lockedApplication->applicant_profile_id) {
                throw new InvalidArgumentException('Only the applicant may withdraw this application.');
            }

            if (! in_array($lockedApplication->status, [
                MultiKolabRoleApplicationStatus::Pending,
                MultiKolabRoleApplicationStatus::Shortlisted,
                MultiKolabRoleApplicationStatus::Accepted,
            ], true)) {
                throw new InvalidArgumentException(
                    "Cannot withdraw an application with status \"{$lockedApplication->status->value}\"."
                );
            }

            $wasAccepted = $lockedApplication->status === MultiKolabRoleApplicationStatus::Accepted;

            if ($wasAccepted && ! Str::of((string) ($reason ?? ''))->trim()->isNotEmpty()) {
                throw new InvalidArgumentException(
                    'A reason is required to withdraw an already-accepted application.'
                );
            }

            $lockedApplication->update([
                'status' => MultiKolabRoleApplicationStatus::Withdrawn,
                'withdrawn_at' => now(),
                'withdrawal_reason' => $reason,
            ]);

            if ($wasAccepted) {
                $role = MultiKolabRole::query()->lockForUpdate()->findOrFail($lockedApplication->multi_kolab_role_id);
                $role->loadMissing('event');
                $preWithdrawalRoleStatus = $role->status;
                $event = $role->event;

                $newStatus = ($preWithdrawalRoleStatus === MultiKolabRoleStatus::Filled
                    && $event?->status === MultiKolabEventStatus::Recruiting)
                    ? MultiKolabRoleStatus::Open
                    : $preWithdrawalRoleStatus;

                $role->update([
                    'positions_filled' => max(0, $role->positions_filled - 1),
                    'status' => $newStatus,
                ]);

                // Historical records remain — only the *live* partnership
                // state is cancelled. `kolab_id` still points at the same
                // canonical Kolab/Application/Collaboration.
                if ($lockedApplication->kolab_id !== null) {
                    $kolab = Kolab::query()->find($lockedApplication->kolab_id);
                    $kolab?->update(['status' => KolabStatus::Closed]);

                    Collaboration::query()
                        ->where('kolab_id', $lockedApplication->kolab_id)
                        ->whereIn('status', [CollaborationStatus::Scheduled, CollaborationStatus::Active])
                        ->update(['status' => CollaborationStatus::Cancelled]);
                }
            }

            return ['application' => $lockedApplication->fresh(['applicantProfile']), 'was_accepted' => $wasAccepted];
        });

        if ($outcome['was_accepted']) {
            $freshApplication = $outcome['application'];
            $this->runSideEffect(fn () => $this->notificationService->notifyMultiKolabPartnerWithdrew($freshApplication));
            $this->runSideEffect(fn () => $this->postHog->capture($freshApplication->applicantProfile, 'partner_withdrew', [
                'application_id' => $freshApplication->id,
            ]));
        }

        return $outcome['application'];
    }

    /**
     * @param  array{pitch?: string|null, availability?: string|null}  $data
     *
     * @throws DuplicateRoleApplicationException
     */
    private function validateCanApply(MultiKolabRole $role, Profile $applicant, array $data): void
    {
        $role->loadMissing('event');
        $event = $role->event;

        if ($event === null) {
            throw new InvalidArgumentException('This role is not linked to an event.');
        }

        if ($applicant->id === $event->creator_profile_id) {
            throw new MultiKolabApplicationRejectedException(
                'You cannot apply to your own event.',
                'cannot_apply_to_own_event',
            );
        }

        if ($event->status !== MultiKolabEventStatus::Recruiting) {
            throw new MultiKolabApplicationRejectedException(
                "This event is not accepting applications. Status: {$event->status->value}.",
                'event_not_recruiting',
                'event',
            );
        }

        if ($role->status !== MultiKolabRoleStatus::Open) {
            throw new MultiKolabApplicationRejectedException(
                "This role is not accepting applications. Status: {$role->status->value}.",
                'role_not_open',
                'role',
            );
        }

        if (! $this->isEligible($role, $applicant)) {
            throw new MultiKolabApplicationRejectedException(
                'Your account type is not eligible to apply to this role.',
                'role_ineligible',
                'role',
            );
        }

        if (! Str::of((string) ($data['pitch'] ?? ''))->trim()->isNotEmpty()) {
            throw new InvalidArgumentException('A pitch is required to apply.');
        }

        $alreadyApplied = MultiKolabRoleApplication::query()
            ->where('multi_kolab_role_id', $role->id)
            ->where('applicant_profile_id', $applicant->id)
            ->exists();

        if ($alreadyApplied) {
            throw new DuplicateRoleApplicationException('You have already applied to this role.');
        }
    }

    private function isEligible(MultiKolabRole $role, Profile $applicant): bool
    {
        return match ($role->eligible_account_type) {
            MultiKolabEligibleAccountType::Either => $applicant->isBusiness() || $applicant->isCommunity(),
            MultiKolabEligibleAccountType::Business => $applicant->isBusiness(),
            MultiKolabEligibleAccountType::Community => $applicant->isCommunity(),
        };
    }

    private function assertOrganizer(MultiKolabRoleApplication $application, Profile $actor): void
    {
        $application->loadMissing('role.event');
        $event = $application->role?->event;

        if ($event === null || $actor->id !== $event->creator_profile_id) {
            throw new MultiKolabNotOrganizerException('Only the event organizer may perform this action.');
        }
    }
}
