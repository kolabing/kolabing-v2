<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\ChatThread;
use App\Models\Profile;
use App\Services\ChatService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('chat.application.{applicationId}', function (Profile $profile, string $applicationId) {
    $application = Application::find($applicationId);

    if (! $application) {
        return false;
    }

    $chatService = app(ChatService::class);

    return $chatService->canParticipate($profile, $application);
});

/*
 * Per-thread channel for real-time chat (NF-CHAT Phase 3). NewChatMessage
 * broadcasts on `chat.thread.{id}` for every thread type (collaboration,
 * community, event). Authorization is the SAME derived access as the REST layer
 * (ChatService::canAccessThread) — this is the security boundary: never authorize
 * on community membership alone for tier-gated or event (sign-up-gated) threads.
 */
Broadcast::channel('chat.thread.{threadId}', function (Profile $profile, string $threadId) {
    $thread = ChatThread::find($threadId);

    if (! $thread) {
        return false;
    }

    return app(ChatService::class)->canAccessThread($profile, $thread);
});
