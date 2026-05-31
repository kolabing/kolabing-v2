<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\KolabStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateKolabRequest;
use App\Models\Kolab;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KolabController extends Controller
{
    public function index(Request $request): View
    {
        $kolabs = Kolab::query()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('preferred_city', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.kolabs.index', [
            'kolabs' => $kolabs,
            'statuses' => KolabStatus::cases(),
            'filters' => [
                'status' => (string) $request->string('status'),
                'q' => (string) $request->string('q'),
            ],
        ]);
    }

    public function edit(Kolab $kolab): View
    {
        $kolab->loadMissing(['creatorProfile.businessProfile', 'creatorProfile.communityProfile']);

        return view('admin.kolabs.edit', [
            'kolab' => $kolab,
            'statuses' => KolabStatus::cases(),
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
}
