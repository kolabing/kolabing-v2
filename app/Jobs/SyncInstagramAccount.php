<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\InstagramException;
use App\Models\InstagramAccount;
use App\Services\InstagramService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/me/instagram/sync (BE-NF-75): refresh the connected account's
 * username / type / picture and warm the first page of its media list.
 *
 * Carries the id: an account disconnected or deleted between dispatch and run
 * is a no-op. An expired token disconnects the account (InstagramService) and is
 * not retried; other Instagram errors are retried with backoff.
 */
class SyncInstagramAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $accountId) {}

    public function handle(InstagramService $instagram): void
    {
        $account = InstagramAccount::query()->find($this->accountId);

        if ($account === null || ! $account->isConnected()) {
            return;
        }

        try {
            $instagram->sync($account);
        } catch (InstagramException $e) {
            if (in_array($e->errorCode, [InstagramException::TOKEN_EXPIRED, InstagramException::PERMISSION_DENIED, InstagramException::PERSONAL_ACCOUNT], true)) {
                Log::info('Instagram sync stopped', ['account_id' => $account->id, 'code' => $e->errorCode]);

                return;
            }

            throw $e;
        }
    }
}
