<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ChatSenderType;
use App\Enums\ChatThreadType;
use App\Enums\KolabingTeamOfferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreKolabingTeamOfferRequest;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\KolabingTeamOffer;
use App\Models\Profile;
use App\Models\User;
use App\Services\KolabingTeamService;
use App\Services\KolabInviteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Admin → Kolabing team: draft one offer for one profile, read it, send it
 * (one row = one send), and answer the replies that land in the thread
 * ({@see KolabingTeamService}).
 */
class KolabingTeamController extends Controller
{
    public function __construct(
        private readonly KolabingTeamService $team,
        private readonly KolabInviteService $invites,
    ) {}

    public function index(): View
    {
        $offers = KolabingTeamOffer::query()
            ->with(['recipient.businessProfile', 'recipient.communityProfile', 'kolab', 'communityProfile.communityProfile'])
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.kolabing-team.index', [
            'offers' => $offers,
            'problems' => $offers->mapWithKeys(fn (KolabingTeamOffer $o): array => [
                $o->id => $o->status === KolabingTeamOfferStatus::Draft ? $this->team->problems($o) : [],
            ]),
            'whatsapp' => $offers->mapWithKeys(fn (KolabingTeamOffer $o): array => [
                $o->id => $o->channel === 'email' ? $this->team->whatsappLink($o) : null,
            ]),
            'kolabs' => $this->invites->invitableKolabs(),
            'unanswered' => $this->unansweredCount(),
        ]);
    }

    public function store(StoreKolabingTeamOfferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $admin = $request->user('admin');

        try {
            $recipient = $this->resolveProfile((string) $data['recipient']);
            $community = isset($data['community']) && $data['community'] !== ''
                ? $this->resolveProfile((string) $data['community'])
                : null;

            $this->team->draft([
                ...$data,
                'recipient' => $recipient->id,
                'community' => $community?->id,
                'test_send' => (bool) ($data['test_send'] ?? false),
            ], $admin instanceof User ? $admin : null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['recipient' => $e->getMessage()]);
        }

        return redirect()->route('admin.kolabing-team.index')
            ->with('status', 'Draft saved. Read it, then press Send on its row.');
    }

    public function send(Request $request, KolabingTeamOffer $offer): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof User, 403);

        try {
            $sent = $this->team->send($offer, $admin);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['send' => $e->getMessage()]);
        }

        return redirect()->route('admin.kolabing-team.index')
            ->with('status', "Sent \"{$sent->title}\" via the ".($sent->channel === 'thread' ? 'Kolabing team thread' : 'Kolabing team thread and email').'.');
    }

    public function cancel(KolabingTeamOffer $offer): RedirectResponse
    {
        if ($offer->status !== KolabingTeamOfferStatus::Draft) {
            return back()->withErrors(['send' => 'Only a draft can be cancelled.']);
        }

        $offer->forceFill(['status' => KolabingTeamOfferStatus::Cancelled])->save();

        return back()->with('status', 'Draft cancelled.');
    }

    /**
     * Threads where the recipient has written, unanswered first.
     */
    public function inbox(): View
    {
        $threads = ChatThread::query()
            ->where('type', ChatThreadType::KolabingTeam->value)
            ->whereNotNull('last_message_at')
            ->with(['teamProfile.businessProfile', 'teamProfile.communityProfile'])
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get();

        $repliesByThread = ChatMessage::query()
            ->whereIn('thread_id', $threads->pluck('id'))
            ->where('sender_type', ChatSenderType::Profile->value)
            ->selectRaw('thread_id, count(*) as replies, max(created_at) as last_reply_at')
            ->groupBy('thread_id')
            ->get()
            ->keyBy('thread_id');

        return view('admin.kolabing-team.inbox', [
            'threads' => $threads,
            'repliesByThread' => $repliesByThread,
        ]);
    }

    public function show(ChatThread $thread): View
    {
        abort_unless($thread->type === ChatThreadType::KolabingTeam, 404);

        $messages = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->with(['senderProfile', 'teamOffer'])
            ->orderBy('created_at')
            ->get();

        $thread->forceFill(['admin_last_read_at' => now()])->save();

        return view('admin.kolabing-team.thread', [
            'thread' => $thread->load(['teamProfile.businessProfile', 'teamProfile.communityProfile']),
            'messages' => $messages,
        ]);
    }

    public function reply(Request $request, ChatThread $thread): RedirectResponse
    {
        abort_unless($thread->type === ChatThreadType::KolabingTeam && $thread->teamProfile !== null, 404);
        $admin = $request->user('admin');
        abort_unless($admin instanceof User, 403);

        $data = $request->validate(['content' => ['required', 'string', 'max:2000']]);

        try {
            $this->team->reply($thread->teamProfile, $data['content'], $admin);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['content' => $e->getMessage()]);
        }

        return redirect()->route('admin.kolabing-team.thread', $thread)->with('status', 'Sent.');
    }

    /**
     * Threads whose newest message is the recipient's, i.e. waiting for an answer.
     */
    private function unansweredCount(): int
    {
        return ChatThread::query()
            ->where('type', ChatThreadType::KolabingTeam->value)
            ->whereNotNull('last_message_at')
            ->where(fn ($q) => $q->whereNull('admin_last_read_at')->orWhereColumn('last_message_at', '>', 'admin_last_read_at'))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('chat_messages')
                ->whereColumn('chat_messages.thread_id', 'chat_threads.id')
                ->where('chat_messages.sender_type', ChatSenderType::Profile->value))
            ->count();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function resolveProfile(string $reference): Profile
    {
        $reference = trim($reference);

        $profile = Str::isUuid($reference)
            ? Profile::query()->find($reference)
            : Profile::query()->whereRaw('lower(email) = ?', [mb_strtolower($reference)])->first();

        return $profile ?? throw new InvalidArgumentException("No account found for {$reference}.");
    }
}
