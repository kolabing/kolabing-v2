<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRewardEconomicsRequest;
use App\Services\Admin\RewardEconomicsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RewardEconomicsController extends Controller
{
    public function __construct(private readonly RewardEconomicsService $service) {}

    public function edit(): View
    {
        $current = $this->service->current();

        return view('admin.gamification.economics.edit', [
            'economics' => $current,
            'currentImpact' => $this->service->costImpact(
                $current->euro_cents_per_point,
                $current->withdrawal_threshold_cents,
            ),
        ]);
    }

    public function update(UpdateRewardEconomicsRequest $request): RedirectResponse
    {
        $this->service->update($request->validated());

        return redirect()
            ->route('admin.gamification.economics.edit')
            ->with('status', __('Reward economics updated.'));
    }
}
