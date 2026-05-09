<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PointEventType;
use App\Enums\WithdrawalStatus;
use App\Events\Withdrawals\WithdrawalApproved;
use App\Events\Withdrawals\WithdrawalPaid;
use App\Events\Withdrawals\WithdrawalRejected;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WithdrawalRequestService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
    ) {}

    /**
     * @param  array{iban: string, account_holder: string}  $data
     */
    public function submit(Profile $profile, array $data): WithdrawalRequest
    {
        $wallet = $this->walletService->getOrCreateWallet($profile->id);

        if ($wallet->pending_withdrawal) {
            throw new InvalidArgumentException('A withdrawal is already pending.');
        }

        $availablePoints = $wallet->getAvailablePoints();
        if ($availablePoints < 375) {
            throw new InvalidArgumentException("Insufficient points. Need 375, have {$availablePoints}.");
        }

        return DB::transaction(function () use ($profile, $wallet, $data): WithdrawalRequest {
            $eurAmount = round(375 * 0.20, 2);

            $withdrawalRequest = WithdrawalRequest::create([
                'profile_id' => $profile->id,
                'points' => 375,
                'eur_amount' => $eurAmount,
                'iban' => $data['iban'],
                'account_holder' => $data['account_holder'],
                'status' => WithdrawalStatus::Pending,
            ]);

            PointLedger::create([
                'profile_id' => $profile->id,
                'points' => -375,
                'event_type' => PointEventType::Withdrawal,
                'reference_id' => $withdrawalRequest->id,
                'description' => "Withdrawal of \u{20AC}".number_format($eurAmount, 2),
            ]);

            $wallet->increment('redeemed_points', 375);
            $wallet->update(['pending_withdrawal' => true]);

            return $withdrawalRequest->fresh();
        });
    }

    public function approve(WithdrawalRequest $withdrawalRequest, ?string $notes = null): WithdrawalRequest
    {
        if ($withdrawalRequest->status === WithdrawalStatus::Processing) {
            return $withdrawalRequest->fresh();
        }

        if (! in_array($withdrawalRequest->status, [WithdrawalStatus::Pending], true)) {
            throw new InvalidArgumentException('Only pending withdrawals can be approved.');
        }

        return DB::transaction(function () use ($withdrawalRequest, $notes): WithdrawalRequest {
            $withdrawalRequest->update([
                'status' => WithdrawalStatus::Processing,
                'notes' => $notes,
            ]);

            event(new WithdrawalApproved($withdrawalRequest->id));

            return $withdrawalRequest->fresh('profile');
        });
    }

    public function reject(WithdrawalRequest $withdrawalRequest, ?string $reason = null): WithdrawalRequest
    {
        if ($withdrawalRequest->status === WithdrawalStatus::Rejected) {
            return $withdrawalRequest->fresh();
        }

        if (in_array($withdrawalRequest->status, [WithdrawalStatus::Completed], true)) {
            throw new InvalidArgumentException('Completed withdrawals cannot be rejected.');
        }

        return DB::transaction(function () use ($withdrawalRequest, $reason): WithdrawalRequest {
            $withdrawalRequest->update([
                'status' => WithdrawalStatus::Rejected,
                'notes' => $reason,
            ]);

            $wallet = Wallet::query()->firstOrCreate(
                ['profile_id' => $withdrawalRequest->profile_id],
                ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false]
            );

            $wallet->update([
                'redeemed_points' => max(0, $wallet->redeemed_points - $withdrawalRequest->points),
                'pending_withdrawal' => false,
            ]);

            event(new WithdrawalRejected($withdrawalRequest->id, $reason));

            return $withdrawalRequest->fresh('profile');
        });
    }

    public function markPaid(WithdrawalRequest $withdrawalRequest, ?string $notes = null): WithdrawalRequest
    {
        if ($withdrawalRequest->status === WithdrawalStatus::Completed) {
            return $withdrawalRequest->fresh();
        }

        if ($withdrawalRequest->status === WithdrawalStatus::Rejected) {
            throw new InvalidArgumentException('Rejected withdrawals cannot be marked as paid.');
        }

        return DB::transaction(function () use ($withdrawalRequest, $notes): WithdrawalRequest {
            $withdrawalRequest->update([
                'status' => WithdrawalStatus::Completed,
                'notes' => $notes,
            ]);

            Wallet::query()
                ->where('profile_id', $withdrawalRequest->profile_id)
                ->update(['pending_withdrawal' => false]);

            event(new WithdrawalPaid($withdrawalRequest->id));

            return $withdrawalRequest->fresh('profile');
        });
    }
}
