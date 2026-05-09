<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WithdrawalStatus;
use App\Models\CommunityProfile;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class WithdrawalNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.enabled_types.withdrawal_approved' => true,
            'notifications.enabled_types.withdrawal_rejected' => true,
            'notifications.enabled_types.withdrawal_paid' => true,
        ]);
    }

    public function test_approving_withdrawal_creates_notification_and_updates_status(): void
    {
        [$profile, $withdrawalRequest] = $this->seedPendingWithdrawal();

        $service = app(WithdrawalRequestService::class);
        $service->approve($withdrawalRequest, 'Queued for payout');

        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawalRequest->id,
            'status' => WithdrawalStatus::Processing->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $profile->id,
            'type' => 'withdrawal_approved',
            'target_id' => $withdrawalRequest->id,
        ]);
    }

    public function test_rejecting_withdrawal_restores_wallet_and_creates_notification(): void
    {
        [$profile, $withdrawalRequest, $wallet] = $this->seedPendingWithdrawal();

        $service = app(WithdrawalRequestService::class);
        $service->reject($withdrawalRequest, 'Bank details need correction');

        $wallet->refresh();

        $this->assertFalse($wallet->pending_withdrawal);
        $this->assertSame(0, $wallet->redeemed_points);

        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawalRequest->id,
            'status' => WithdrawalStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $profile->id,
            'type' => 'withdrawal_rejected',
            'target_id' => $withdrawalRequest->id,
        ]);
    }

    public function test_marking_withdrawal_paid_is_idempotent(): void
    {
        [$profile, $withdrawalRequest, $wallet] = $this->seedPendingWithdrawal();

        $service = app(WithdrawalRequestService::class);
        $service->approve($withdrawalRequest);
        $service->markPaid($withdrawalRequest->fresh());
        $service->markPaid($withdrawalRequest->fresh());

        $wallet->refresh();

        $this->assertFalse($wallet->pending_withdrawal);
        $this->assertDatabaseHas('withdrawal_requests', [
            'id' => $withdrawalRequest->id,
            'status' => WithdrawalStatus::Completed->value,
        ]);

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $profile->id)
            ->where('type', 'withdrawal_paid')
            ->where('target_id', $withdrawalRequest->id)
            ->count());
    }

    /**
     * @return array{0: Profile, 1: WithdrawalRequest, 2: Wallet}
     */
    private function seedPendingWithdrawal(): array
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Wallet Owner',
        ]);

        $wallet = Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 375,
            'redeemed_points' => 375,
            'pending_withdrawal' => true,
        ]);

        $withdrawalRequest = WithdrawalRequest::factory()->forProfile($profile)->create([
            'status' => WithdrawalStatus::Pending,
        ]);

        return [$profile, $withdrawalRequest, $wallet];
    }
}
