<?php

declare(strict_types=1);

use App\Models\Application;
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
