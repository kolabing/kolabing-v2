<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\KolabStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelKolabCollaborationRequest;
use App\Http\Requests\Admin\UpdateKolabRequest;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Services\Admin\KolabLifecycleService;
use App\Services\CollaborationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KolabController extends Controller
{
    public function __construct(
        private readonly KolabLifecycleService $lifecycle,
        private readonly CollaborationService $collaborations,
    ) {}

    public function index(Request $request): View
    {
        $query = Kolab::query()
            ->select('kolabs.*')
            ->selectSub(
                Application::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('collab_opportunity_id', 'kolabs.id')
                    ->where('status', ApplicationStatus::Pending->value),
                'pending_applications_count'
            )
            ->selectSub(
                Application::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('collab_opportunity_id', 'kolabs.id')
                    ->where('status', ApplicationStatus::Accepted->value),
                'accepted_applications_count'
            )
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile'])
            ->when($request->filled('status'), fn ($q) => $q->where('kolabs.status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('preferred_city', 'like', $term);
                });
            })
            ->when($request->filled('lifecycle'), function ($q) use ($request): void {
                $this->lifecycle->applyFilter($q, (string) $request->string('lifecycle'));
            })
            ->latest();

        $kolabs = $query->paginate(20)->withQueryString();
        $lifecycles = $this->lifecycle->summarizeMany($kolabs->getCollection());

        return view('admin.kolabs.index', [
            'kolabs' => $kolabs,
            'lifecycles' => $lifecycles,
            'statuses' => KolabStatus::cases(),
            'lifecycleOptions' => KolabLifecycleService::all(),
            'filters' => [
                'status' => (string) $request->string('status'),
                'q' => (string) $request->string('q'),
                'lifecycle' => (string) $request->string('lifecycle'),
            ],
        ]);
    }

    public function edit(Kolab $kolab): View
    {
        $kolab->loadMissing(['creatorProfile.businessProfile', 'creatorProfile.communityProfile']);

        return view('admin.kolabs.edit', [
            'kolab' => $kolab,
            'statuses' => KolabStatus::cases(),
            'summary' => $this->lifecycle->summarize($kolab),
        ]);
    }

    public function update(UpdateKolabRequest $request, Kolab $kolab): RedirectResponse
    {
        $data = $request->validated();
        $status = KolabStatus::from($data['status']);

        if ($status === KolabStatus::Published && $kolab->published_at === null) {
            $data['published_at'] = now();
        }

        $kolab->update($data);

        return redirect()
            ->route('admin.kolabs.edit', $kolab)
            ->with('status', __('Kolab updated.'));
    }

    public function destroy(Kolab $kolab): RedirectResponse
    {
        $kolab->delete();

        return redirect()
            ->route('admin.kolabs.index')
            ->with('status', __('Kolab deleted.'));
    }

    public function cancelCollaboration(CancelKolabCollaborationRequest $request, Kolab $kolab): RedirectResponse
    {
        $collaboration = Collaboration::query()
            ->where('collab_opportunity_id', $kolab->id)
            ->first();

        abort_if($collaboration === null, 404, 'No collaboration to cancel for this Kolab.');

        $this->collaborations->cancel($collaboration, $request->validated('reason'));

        return redirect()
            ->route('admin.kolabs.edit', $kolab)
            ->with('status', __('Collaboration cancelled.'));
    }
}
