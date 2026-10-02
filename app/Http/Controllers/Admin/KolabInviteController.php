<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendKolabInvitesRequest;
use App\Models\CommunityType;
use App\Models\Kolab;
use App\Models\KolabInvite;
use App\Models\User;
use App\Services\KolabInviteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Admin → Invite communities: send communities an in-app + push invite to
 * apply to an open business offer ({@see KolabInviteService}). Preview first,
 * then send; `kolabing:invite-communities` is the same for Laravel Cloud.
 */
class KolabInviteController extends Controller
{
    public function __construct(
        private readonly KolabInviteService $invites,
    ) {}

    public function index(Request $request): View
    {
        return $this->form(['selectedKolabId' => (string) $request->query('kolab', '')]);
    }

    public function preview(SendKolabInvitesRequest $request): View
    {
        $data = $request->validated();
        $kolab = Kolab::query()->findOrFail($data['kolab']);

        return $this->form([
            'selectedKolabId' => $kolab->id,
            'preview' => $this->invites->preview($kolab, $this->filters($data)),
            'previewKolab' => $kolab,
        ]);
    }

    public function store(SendKolabInvitesRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $kolab = Kolab::query()->findOrFail($data['kolab']);
        $admin = $request->user('admin');

        try {
            $result = $this->invites->send($kolab, $this->filters($data), $admin instanceof User ? $admin : null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['kolab' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.kolab-invites.index', ['kolab' => $kolab->id])
            ->with('status', "Invited {$result['invited']} communit".($result['invited'] === 1 ? 'y' : 'ies')." to \"{$kolab->title}\"."
                .($result['skipped'] > 0 ? " {$result['skipped']} were already invited." : '')
                .($result['failed'] > 0 ? " {$result['failed']} failed (see logs)." : ''));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{city: string|null, category: string|null, limit: int, weekly_cap: int}
     */
    private function filters(array $data): array
    {
        return [
            'city' => $data['city'] ?? null,
            'category' => $data['category'] ?? null,
            'limit' => (int) ($data['limit'] ?? KolabInviteService::DEFAULT_LIMIT),
            'weekly_cap' => (int) ($data['weekly_cap'] ?? KolabInviteService::DEFAULT_WEEKLY_CAP),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function form(array $extra = []): View
    {
        $recent = KolabInvite::query()
            ->selectRaw('kolab_id, count(*) as invites, max(created_at) as last_sent_at')
            ->groupBy('kolab_id')
            ->orderByDesc('last_sent_at')
            ->limit(25)
            ->get();
        $recentKolabs = Kolab::query()
            ->with('creatorProfile.businessProfile')
            ->whereIn('id', $recent->pluck('kolab_id'))
            ->get()
            ->keyBy('id');

        return view('admin.kolab-invites.index', [
            'kolabs' => $this->invites->invitableKolabs(),
            'categories' => CommunityType::query()->orderBy('name')->get(['name', 'slug']),
            'defaults' => [
                'limit' => KolabInviteService::DEFAULT_LIMIT,
                'weekly_cap' => KolabInviteService::DEFAULT_WEEKLY_CAP,
            ],
            'recent' => $recent,
            'recentKolabs' => $recentKolabs,
            'preview' => null,
            'previewKolab' => null,
            'selectedKolabId' => '',
            ...$extra,
        ]);
    }
}
