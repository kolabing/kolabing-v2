<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Community;
use App\Models\CommunityType;
use App\Models\Icon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin CRUD for the community/business type taxonomies — the single source of
 * truth the app reads via /lookup. Supports a personalised icon picked from the
 * admin icon library (icon_url, the same library offer_options use), an optional
 * Lucide icon-name fallback, an uploaded SVG override, drag-drop reorder, and
 * deactivate (retire without deleting). Business types also carry `applies_to`
 * (venue|product|both), which the business-onboarding category picker filters
 * by. See docs/plans/2026-06-10-type-source-of-truth-DECISION.md.
 */
class TypeController extends Controller
{
    /** Allowed onboarding-path values for a business type. */
    private const APPLIES_TO = ['venue', 'product', 'both'];

    /** @return array{model: class-string, label: string} */
    private function kindConfig(string $kind): array
    {
        return $kind === 'business'
            ? ['model' => BusinessType::class, 'label' => 'Business']
            : ['model' => CommunityType::class, 'label' => 'Community'];
    }

    private function resolveKind(?string $kind): string
    {
        return $kind === 'business' ? 'business' : 'community';
    }

    /** How many live entities reference this type slug (so deletes are safe). */
    private function inUseCount(string $kind, string $slug): int
    {
        return $kind === 'business'
            ? BusinessProfile::query()->where('business_type', $slug)->count()
            : Community::query()->where('type', $slug)->count();
    }

    /**
     * In-use counts for every type slug of a kind, in ONE grouped query (the
     * per-row inUseCount() call inside index()'s loop was an N+1 — see
     * PHP-LARAVEL-5). Slugs with no referencing rows are simply absent.
     *
     * @return array<string, int> slug => count
     */
    private function inUseCounts(string $kind): array
    {
        $column = $kind === 'business' ? 'business_type' : 'type';

        $query = $kind === 'business'
            ? BusinessProfile::query()
            : Community::query();

        return $query
            ->select($column)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    public function index(Request $request): View
    {
        $kind = $this->resolveKind($request->query('kind'));
        $model = $this->kindConfig($kind)['model'];

        $inUse = $this->inUseCounts($kind);

        $types = $model::query()->orderBy('sort_order')->orderBy('name')->get()
            ->map(function ($t) use ($inUse) {
                $t->in_use = $inUse[$t->slug] ?? 0;

                return $t;
            });

        return view('admin.types.index', ['kind' => $kind, 'types' => $types]);
    }

    public function create(Request $request): View
    {
        $kind = $this->resolveKind($request->query('kind'));

        return view('admin.types.edit', [
            'kind' => $kind,
            'type' => new ($this->kindConfig($kind)['model']),
            'iconLibrary' => $this->iconLibrary(),
        ]);
    }

    public function edit(Request $request, string $kind, string $id): View
    {
        $kind = $this->resolveKind($kind);
        $type = $this->kindConfig($kind)['model']::query()->findOrFail($id);

        return view('admin.types.edit', [
            'kind' => $kind,
            'type' => $type,
            'iconLibrary' => $this->iconLibrary(),
        ]);
    }

    /**
     * The active personalised-icon library, for the type edit picker.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Icon>
     */
    private function iconLibrary()
    {
        return Icon::query()->active()->ordered()->get();
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = $this->resolveKind($request->input('kind'));
        $model = $this->kindConfig($kind)['model'];
        $data = $this->validated($request, $kind, null);

        $model::query()->create($data);

        return redirect()->route('admin.types.index', ['kind' => $kind])->with('status', 'Type created.');
    }

    public function update(Request $request, string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $type = $this->kindConfig($kind)['model']::query()->findOrFail($id);
        $type->update($this->validated($request, $kind, $type->id));

        return redirect()->route('admin.types.index', ['kind' => $kind])->with('status', 'Type updated.');
    }

    /** "Remove" = deactivate (retire, never delete) when in use; hard-delete only when unused. */
    public function destroy(string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $type = $this->kindConfig($kind)['model']::query()->findOrFail($id);

        if ($this->inUseCount($kind, $type->slug) > 0) {
            $type->update(['is_active' => false]);

            return redirect()->route('admin.types.index', ['kind' => $kind])
                ->with('status', 'Type is in use — deactivated (hidden from the app) instead of deleted.');
        }

        $type->delete();

        return redirect()->route('admin.types.index', ['kind' => $kind])->with('status', 'Unused type deleted.');
    }

    public function toggle(string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $type = $this->kindConfig($kind)['model']::query()->findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        return redirect()->route('admin.types.index', ['kind' => $kind])
            ->with('status', $type->is_active ? 'Type activated.' : 'Type deactivated.');
    }

    /** Persist drag-drop order: ordered list of ids → sort_order. */
    public function reorder(Request $request, string $kind): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $model = $this->kindConfig($kind)['model'];
        foreach ((array) $request->input('order', []) as $i => $id) {
            $model::query()->whereKey($id)->update(['sort_order' => $i + 1]);
        }

        return redirect()->route('admin.types.index', ['kind' => $kind])->with('status', 'Order saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $kind, ?string $ignoreId): array
    {
        $table = $kind === 'business' ? 'business_types' : 'community_types';
        $isBusiness = $kind === 'business';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                "unique:{$table},slug".($ignoreId ? ",{$ignoreId}" : '')],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_url' => ['nullable', 'string', 'max:2048'], // public URL of a library icon
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon_svg' => ['nullable', 'file', 'mimetypes:image/svg+xml', 'max:128'], // ≤128KB (inline upload override)
            // Onboarding path is a business-only concept; communities have no venue/product split.
            'applies_to' => [$isBusiness ? 'required' : 'nullable', 'in:'.implode(',', self::APPLIES_TO)],
        ]);

        $out = [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::of($data['name'])->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value(),
            'icon' => $data['icon'] ?? null,
            // Primary path: the chosen library icon's public URL (the app renders this).
            'icon_url' => ($data['icon_url'] ?? null) ?: null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($isBusiness) {
            $out['applies_to'] = $data['applies_to'] ?? 'both';
        }

        // Inline SVG upload still supported; it overrides the picked library URL.
        if ($request->hasFile('icon_svg')) {
            $path = $request->file('icon_svg')->store('type-icons', ['disk' => config('filesystems.default')]);
            $out['icon_url'] = Storage::disk(config('filesystems.default'))->url($path);
        }

        return $out;
    }
}
