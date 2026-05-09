<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Applications\ApplicationAccepted;
use App\Events\Applications\ApplicationCreated;
use App\Events\Applications\ApplicationDeclined;
use App\Events\Collaborations\CollaborationCancelled;
use App\Events\Collaborations\CollaborationRescheduled;
use App\Events\Collaborations\CollaborationScheduled;
use App\Events\Gamification\BadgeAwarded;
use App\Events\Gamification\ChallengeRejected;
use App\Events\Gamification\ChallengeVerificationRequested;
use App\Events\Gamification\ChallengeVerified;
use App\Events\Gamification\RewardWon;
use App\Events\Messages\MessageCreated;
use App\Events\Referrals\ReferralRewardEarned;
use App\Events\Withdrawals\WithdrawalApproved;
use App\Events\Withdrawals\WithdrawalPaid;
use App\Events\Withdrawals\WithdrawalRejected;
use App\Listeners\Applications\CreateApplicationNotifications;
use App\Listeners\Collaborations\CreateCollaborationNotifications;
use App\Listeners\Gamification\CreateGamificationNotifications;
use App\Listeners\Messages\CreateNewMessageNotification;
use App\Listeners\Referrals\CreateReferralNotifications;
use App\Listeners\Withdrawals\CreateWithdrawalNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        MessageCreated::class => [
            CreateNewMessageNotification::class,
        ],
        ApplicationCreated::class => [
            CreateApplicationNotifications::class,
        ],
        ApplicationAccepted::class => [
            CreateApplicationNotifications::class,
        ],
        ApplicationDeclined::class => [
            CreateApplicationNotifications::class,
        ],
        CollaborationScheduled::class => [
            CreateCollaborationNotifications::class,
        ],
        CollaborationRescheduled::class => [
            CreateCollaborationNotifications::class,
        ],
        CollaborationCancelled::class => [
            CreateCollaborationNotifications::class,
        ],
        ChallengeVerificationRequested::class => [
            CreateGamificationNotifications::class,
        ],
        ChallengeVerified::class => [
            CreateGamificationNotifications::class,
        ],
        ChallengeRejected::class => [
            CreateGamificationNotifications::class,
        ],
        RewardWon::class => [
            CreateGamificationNotifications::class,
        ],
        BadgeAwarded::class => [
            CreateGamificationNotifications::class,
        ],
        WithdrawalApproved::class => [
            CreateWithdrawalNotifications::class,
        ],
        WithdrawalRejected::class => [
            CreateWithdrawalNotifications::class,
        ],
        WithdrawalPaid::class => [
            CreateWithdrawalNotifications::class,
        ],
        ReferralRewardEarned::class => [
            CreateReferralNotifications::class,
        ],
    ];
}
