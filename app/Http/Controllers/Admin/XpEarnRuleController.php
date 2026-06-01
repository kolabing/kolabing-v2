<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateXpEarnRuleRequest;
use App\Models\XpEarnRule;
use App\Services\Admin\XpEarnRuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class XpEarnRuleController extends Controller
{
    public function __construct(private readonly XpEarnRuleService $service) {}

    public function index(): View
    {
        return view('admin.gamification.earn-rules.index', [
            'rules' => $this->service->ordered(),
        ]);
    }

    public function edit(XpEarnRule $earnRule): View
    {
        return view('admin.gamification.earn-rules.edit', [
            'rule' => $earnRule,
        ]);
    }

    public function update(UpdateXpEarnRuleRequest $request, XpEarnRule $earnRule): RedirectResponse
    {
        $this->service->update($earnRule, $request->validated());

        return redirect()
            ->route('admin.gamification.earn-rules.index')
            ->with('status', __('XP earn rule updated.'));
    }
}
