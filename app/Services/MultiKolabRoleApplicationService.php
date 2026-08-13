<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\DuplicateRoleApplicationException;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
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
    /**
     * @param  array{pitch?: string|null, availability?: string|null}  $data
     */
    public function apply(MultiKolabRole $role, Profile $applicant, array $data): MultiKolabRoleApplication
    {
        $this->validateCanApply($role, $applicant, $data);

        return MultiKolabRoleApplication::query()->create([
            'multi_kolab_role_id' => $role->id,
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type->value,
            'status' => MultiKolabRoleApplicationStatus::Pending,
            'pitch' => $data['pitch'],
            'availability' => $data['availability'] ?? null,
        ]);
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

        return $application->fresh();
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

        return $application->fresh();
    }

    /**
     * Withdraw an application. A reason is required only when withdrawing an
     * already-accepted application (the "post-acceptance withdrawal reason"
     * from the plan) — withdrawing a pending/shortlisted application does
     * not require one. Withdrawing an accepted application transactionally
     * decrements the role's `positions_filled` (never below zero) and
     * reopens the role to `open` if it had been marked `filled`.
     */
    public function withdraw(
        MultiKolabRoleApplication $application,
        Profile $actor,
        ?string $reason,
    ): MultiKolabRoleApplication {
        if ($actor->id !== $application->applicant_profile_id) {
            throw new InvalidArgumentException('Only the applicant may withdraw this application.');
        }

        if (! in_array($application->status, [
            MultiKolabRoleApplicationStatus::Pending,
            MultiKolabRoleApplicationStatus::Shortlisted,
            MultiKolabRoleApplicationStatus::Accepted,
        ], true)) {
            throw new InvalidArgumentException(
                "Cannot withdraw an application with status \"{$application->status->value}\"."
            );
        }

        $wasAccepted = $application->status === MultiKolabRoleApplicationStatus::Accepted;

        if ($wasAccepted && ! Str::of((string) ($reason ?? ''))->trim()->isNotEmpty()) {
            throw new InvalidArgumentException(
                'A reason is required to withdraw an already-accepted application.'
            );
        }

        return DB::transaction(function () use ($application, $reason, $wasAccepted): MultiKolabRoleApplication {
            $application->update([
                'status' => MultiKolabRoleApplicationStatus::Withdrawn,
                'withdrawn_at' => now(),
                'withdrawal_reason' => $reason,
            ]);

            if ($wasAccepted) {
                $role = MultiKolabRole::query()->lockForUpdate()->findOrFail($application->multi_kolab_role_id);
                $role->update([
                    'positions_filled' => max(0, $role->positions_filled - 1),
                    'status' => MultiKolabRoleStatus::Open,
                ]);
            }

            return $application->fresh();
        });
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
            throw new InvalidArgumentException('You cannot apply to your own event.');
        }

        if ($event->status !== MultiKolabEventStatus::Recruiting) {
            throw new InvalidArgumentException(
                "This event is not accepting applications. Status: {$event->status->value}."
            );
        }

        if ($role->status !== MultiKolabRoleStatus::Open) {
            throw new InvalidArgumentException(
                "This role is not accepting applications. Status: {$role->status->value}."
            );
        }

        if (! $this->isEligible($role, $applicant)) {
            throw new InvalidArgumentException(
                'Your account type is not eligible to apply to this role.'
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
            throw new InvalidArgumentException('Only the event organizer may perform this action.');
        }
    }
}
