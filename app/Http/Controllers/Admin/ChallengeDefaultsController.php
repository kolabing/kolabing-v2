<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessType;
use App\Models\Challenge;
use App\Models\CommunityType;
use App\Services\Admin\ChallengeDefaultsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "which challenges should auto-seed for this business / community
 * type?" matrix. One screen, one save endpoint per type row.
 */
class ChallengeDefaultsController extends Controller
{
    public function __construct(private readonly ChallengeDefaultsService $service) {}

    public function index(): View
    {
        return view('admin.gamification.challenges.defaults.index', [
            'businessTypes' => BusinessType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'communityTypes' => CommunityType::query()->orderBy('sort_order')->orderBy('name')->get(),
            'systemChallenges' => Challenge::query()
                ->where('is_system', true)
                ->whereNull('event_id')
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'matrix' => $this->service->matrix(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'applies_to' => ['required', Rule::in(['business_type', 'community_type'])],
            'type_value' => ['required', 'string', 'max:100'],
            'challenge_ids' => ['nullable', 'array'],
            'challenge_ids.*' => ['uuid', 'exists:challenges,id'],
        ]);

        $this->service->syncForType(
            $data['applies_to'],
            $data['type_value'],
            $data['challenge_ids'] ?? [],
        );

        return redirect()
            ->route('admin.gamification.challenges.defaults.index')
            ->with('status', __('Defaults updated for :type.', ['type' => $data['type_value']]));
    }
}
