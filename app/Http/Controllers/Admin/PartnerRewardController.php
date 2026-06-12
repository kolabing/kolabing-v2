<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRewardRequest;
use App\Http\Requests\Admin\UpdatePartnerRewardRequest;
use App\Models\PartnerReward;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Admin CRUD for the GLOBAL "Redeem your XP" partner-reward catalogue. This is
 * the maintainer counterpart to the app's read-only partner_rewards feed.
 */
class PartnerRewardController extends Controller
{
    public function index(): View
    {
        return view('admin.gamification.partner-rewards.index', [
            'rewards' => PartnerReward::query()
                ->orderByDesc('is_active')
                ->orderBy('cost_xp')
                ->paginate(50),
        ]);
    }

    public function create(): View
    {
        return view('admin.gamification.partner-rewards.create');
    }

    public function store(StorePartnerRewardRequest $request): RedirectResponse
    {
        PartnerReward::query()->create($request->validated());

        return redirect()
            ->route('admin.gamification.partner-rewards.index')
            ->with('status', __('Partner reward created.'));
    }

    public function edit(PartnerReward $partnerReward): View
    {
        return view('admin.gamification.partner-rewards.edit', [
            'reward' => $partnerReward,
        ]);
    }

    public function update(UpdatePartnerRewardRequest $request, PartnerReward $partnerReward): RedirectResponse
    {
        $partnerReward->update($request->validated());

        return redirect()
            ->route('admin.gamification.partner-rewards.index')
            ->with('status', __('Partner reward updated.'));
    }

    public function destroy(PartnerReward $partnerReward): RedirectResponse
    {
        $partnerReward->delete();

        return redirect()
            ->route('admin.gamification.partner-rewards.index')
            ->with('status', __('Partner reward deleted.'));
    }
}
