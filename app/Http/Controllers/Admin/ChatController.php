<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BanChatMemberRequest;
use App\Models\ChatThread;
use App\Models\Profile;
use App\Services\Admin\AdminCommunityChatService;
use App\Services\ChatService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly AdminCommunityChatService $service,
        private readonly ChatService $chats,
    ) {}

    public function show(ChatThread $thread): View
    {
        $thread->loadMissing(['community', 'application.collabOpportunity.creatorProfile', 'application.applicantProfile']);

        return view('admin.chats.show', [
            'thread' => $thread,
            'messages' => $this->service->transcript($thread),
            'participants' => $this->service->threadParticipants($thread),
        ]);
    }

    /**
     * Soft-delete a (community-custom or event) thread via the shared Wave 1 service.
     */
    public function destroy(ChatThread $thread): RedirectResponse
    {
        try {
            $this->chats->deleteThread($thread);
        } catch (DomainException $e) {
            return redirect()->back()->withErrors([
                'thread' => __('This thread type cannot be deleted.'),
            ]);
        }

        return redirect()->back()->with('status', __('Chat thread deleted.'));
    }

    /**
     * Ban a participant from the thread via the shared Wave 1 service.
     */
    public function banMember(BanChatMemberRequest $request, ChatThread $thread): RedirectResponse
    {
        $target = Profile::query()->findOrFail($request->validated('profile_id'));

        // A maintainer operator has no profile, so banned_by is recorded as null.
        $this->chats->banMember($thread, $target);

        return redirect()->back()->with('status', __('Member banned from this chat.'));
    }
}
