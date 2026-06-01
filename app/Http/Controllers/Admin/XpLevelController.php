<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateXpLevelRequest;
use App\Models\XpLevel;
use App\Services\Admin\XpLevelService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class XpLevelController extends Controller
{
    public function __construct(private readonly XpLevelService $service) {}

    public function index(): View
    {
        return view('admin.gamification.levels.index', [
            'levels' => $this->service->ordered(),
            'ladderErrors' => $this->service->validateLadder(),
        ]);
    }

    public function edit(XpLevel $level): View
    {
        return view('admin.gamification.levels.edit', [
            'level' => $level,
        ]);
    }

    public function update(UpdateXpLevelRequest $request, XpLevel $level): RedirectResponse
    {
        try {
            $this->service->update($level, $request->validated());
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.gamification.levels.edit', $level)
                ->withInput()
                ->withErrors(['ladder' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.gamification.levels.index')
            ->with('status', __('XP level updated.'));
    }
}
