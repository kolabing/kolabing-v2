<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBusinessVisibilityBoostRequest;
use App\Services\Admin\BusinessVisibilityBoostService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BusinessVisibilityBoostController extends Controller
{
    public function __construct(private readonly BusinessVisibilityBoostService $service) {}

    public function edit(): View
    {
        return view('admin.gamification.business-visibility-boost.edit', [
            'settings' => $this->service->current(),
        ]);
    }

    public function update(UpdateBusinessVisibilityBoostRequest $request): RedirectResponse
    {
        $this->service->update($request->validated());

        return redirect()
            ->route('admin.gamification.business-visibility-boost.edit')
            ->with('status', __('Business visibility boost updated.'));
    }
}
