<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAutoKolabRequest;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use App\Services\AutoKolabService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Admin → Auto-kolabs: set up a Kolab that is already matched between a
 * business and a community ({@see AutoKolabService}). Preview first, then
 * create; the command `kolabing:auto-kolab` is the same thing for Laravel
 * Cloud Commands.
 */
class AutoKolabController extends Controller
{
    public function __construct(
        private readonly AutoKolabService $autoKolabs,
    ) {}

    public function index(): View
    {
        return $this->form();
    }

    public function preview(StoreAutoKolabRequest $request): View|RedirectResponse
    {
        $data = $request->validated();

        try {
            $business = $this->autoKolabs->resolveBusiness((string) $data['business']);
            $community = $this->autoKolabs->resolveCommunity((string) $data['community']);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['business' => $e->getMessage()]);
        }

        return $this->form([
            'plan' => $this->autoKolabs->plan($business, $community, $this->options($data)),
            'planBusiness' => $business,
            'planCommunity' => $community,
        ]);
    }

    public function store(StoreAutoKolabRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $admin = $request->user('admin');

        try {
            $business = $this->autoKolabs->resolveBusiness((string) $data['business']);
            $community = $this->autoKolabs->resolveCommunity((string) $data['community']);
            $result = $this->autoKolabs->create(
                $business,
                $community,
                $this->options($data),
                $admin instanceof User ? $admin : null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['business' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.auto-kolabs.index')
            ->with('status', 'Auto-kolab created: "'.$result['kolab']->title.'". Both sides now see it as a confirmed collaboration.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{date: string|null, title: string|null, notify: bool}
     */
    private function options(array $data): array
    {
        return [
            'date' => $data['date'] ?? null,
            'title' => $data['title'] ?? null,
            'notify' => (bool) ($data['notify'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function form(array $extra = []): View
    {
        return view('admin.auto-kolabs.index', [
            'businesses' => $this->profilesOfType(UserType::Business),
            'communities' => $this->profilesOfType(UserType::Community),
            'recent' => Kolab::query()
                ->autoMatched()
                ->with([
                    'creatorProfile.businessProfile',
                    'recipientCommunity.communityProfile',
                    'collaborations',
                    'createdByAdmin',
                ])
                ->latest()
                ->limit(25)
                ->get(),
            'plan' => null,
            'planBusiness' => null,
            'planCommunity' => null,
            ...$extra,
        ]);
    }

    /**
     * Active, real accounts that finished onboarding, by display name.
     *
     * @return Collection<int, Profile>
     */
    private function profilesOfType(UserType $type): Collection
    {
        $relation = $type === UserType::Business ? 'businessProfile' : 'communityProfile';

        return Profile::query()
            ->where('user_type', $type)
            ->active()
            ->where('is_test_user', false)
            ->whereHas($relation)
            ->with($relation)
            ->get()
            ->sortBy(fn (Profile $p): string => mb_strtolower((string) ($p->{$relation}?->name ?? $p->email)))
            ->values();
    }
}
