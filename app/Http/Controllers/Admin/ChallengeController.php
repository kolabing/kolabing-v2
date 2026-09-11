<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeProofType;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChallengeRequest;
use App\Http\Requests\Admin\UpdateChallengeRequest;
use App\Models\Challenge;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the global system-challenge catalogue
 * (is_system = true AND event_id IS NULL). Custom event-scoped challenges
 * are organizer-managed elsewhere and intentionally excluded from this list.
 */
class ChallengeController extends Controller
{
    public function index(Request $request): View
    {
        $challenges = Challenge::query()
            ->where('is_system', true)
            ->whereNull('event_id')
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->string('difficulty')))
            ->when($request->filled('audience'), fn ($q) => $q->where('audience', $request->string('audience')))
            ->orderBy('category')
            ->orderBy('difficulty')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.gamification.challenges.index', [
            'challenges' => $challenges,
            'categories' => ChallengeCategory::cases(),
            'difficulties' => ChallengeDifficulty::cases(),
            'audiences' => ChallengeAudience::cases(),
            'filters' => [
                'category' => (string) $request->string('category'),
                'difficulty' => (string) $request->string('difficulty'),
                'audience' => (string) $request->string('audience'),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.gamification.challenges.create', $this->formContext());
    }

    public function store(StoreChallengeRequest $request): RedirectResponse
    {
        Challenge::query()->create(array_merge(
            $request->validated(),
            ['is_system' => true],
        ));

        return redirect()
            ->route('admin.gamification.challenges.index')
            ->with('status', __('Challenge created.'));
    }

    public function edit(Challenge $challenge): View
    {
        return view('admin.gamification.challenges.edit', array_merge(
            $this->formContext(),
            ['challenge' => $challenge],
        ));
    }

    public function update(UpdateChallengeRequest $request, Challenge $challenge): RedirectResponse
    {
        $challenge->update($request->validated());

        return redirect()
            ->route('admin.gamification.challenges.index')
            ->with('status', __('Challenge updated.'));
    }

    public function destroy(Challenge $challenge): RedirectResponse
    {
        $challenge->delete();

        return redirect()
            ->route('admin.gamification.challenges.index')
            ->with('status', __('Challenge deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formContext(): array
    {
        return [
            'categories' => ChallengeCategory::cases(),
            'difficulties' => ChallengeDifficulty::cases(),
            'audiences' => ChallengeAudience::cases(),
            'proofTypes' => ChallengeProofType::cases(),
            'triggers' => MissionTrigger::cases(),
            'repeats' => MissionRepeat::cases(),
        ];
    }
}
