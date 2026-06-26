<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icon;
use App\Models\Kolab;
use App\Models\OfferOption;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD for the kolab offer taxonomies (offering / deliverable / need /
 * product_type / venue_type) — the single source of truth the app reads via
 * /lookup/{offerings,deliverables,needs,product-types,venue-types}. Mirrors
 * TypeController: icon name (bundled) + uploaded SVG (icon_url), drag-drop reorder,
 * deactivate (retire without deleting). `kind` discriminates.
 */
class OfferOptionController extends Controller
{
    /** Human label per kind, for the Blade tabs/headings. */
    private const LABELS = [
        OfferOption::KIND_OFFERING => 'Offerings',
        OfferOption::KIND_DELIVERABLE => 'Deliverables',
        OfferOption::KIND_NEED => 'Needs',
        OfferOption::KIND_PRODUCT_TYPE => 'Product types',
        OfferOption::KIND_VENUE_TYPE => 'Venue types',
        OfferOption::KIND_GOAL => 'Goals',
        OfferOption::KIND_PRODUCT_INTERACTION => 'Product interactions',
        OfferOption::KIND_VENUE_FIT => 'Venue fits',
        OfferOption::KIND_KOLAB_HIGHLIGHT => 'Kolab highlights',
    ];

    /** Kinds stored as a scalar string column on kolabs (not a JSON array). */
    private const SCALAR_KINDS = [
        OfferOption::KIND_PRODUCT_TYPE,
        OfferOption::KIND_VENUE_TYPE,
        OfferOption::KIND_GOAL,
    ];

    private function resolveKind(?string $kind): string
    {
        return in_array($kind, OfferOption::KINDS, true) ? $kind : OfferOption::KIND_OFFERING;
    }

    /** How many kolabs reference this slug in the kind's column(s) — so deletes are safe. */
    private function inUseCount(string $kind, string $slug): int
    {
        $columns = match ($kind) {
            OfferOption::KIND_OFFERING => ['offering'],
            OfferOption::KIND_DELIVERABLE => ['offers_in_return', 'expects'],
            OfferOption::KIND_NEED => ['needs'],
            OfferOption::KIND_PRODUCT_TYPE => ['product_type'],
            OfferOption::KIND_VENUE_TYPE => ['venue_type'],
            OfferOption::KIND_GOAL => ['goal'],
            OfferOption::KIND_PRODUCT_INTERACTION => [],
            OfferOption::KIND_VENUE_FIT => [],
            OfferOption::KIND_KOLAB_HIGHLIGHT => ['highlights'],
            default => [],
        };

        if ($columns === []) {
            return 0;
        }

        $isScalar = in_array($kind, self::SCALAR_KINDS, true);

        $query = Kolab::query();
        $query->where(function ($q) use ($columns, $slug, $isScalar): void {
            foreach ($columns as $column) {
                if ($isScalar) {
                    $q->orWhere($column, $slug);
                } else {
                    $q->orWhereJsonContains($column, $slug);
                }
            }
        });

        return $query->count();
    }

    public function index(Request $request): View
    {
        $kind = $this->resolveKind($request->query('kind'));

        $options = OfferOption::query()->where('kind', $kind)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(function (OfferOption $o) use ($kind): OfferOption {
                $o->in_use = $this->inUseCount($kind, $o->slug);

                return $o;
            });

        return view('admin.offer-options.index', [
            'kind' => $kind,
            'labels' => self::LABELS,
            'options' => $options,
        ]);
    }

    public function create(Request $request): View
    {
        $kind = $this->resolveKind($request->query('kind'));

        return view('admin.offer-options.edit', [
            'kind' => $kind,
            'labels' => self::LABELS,
            'option' => new OfferOption(['kind' => $kind]),
            'iconLibrary' => $this->iconLibrary(),
        ]);
    }

    public function edit(string $kind, string $id): View
    {
        $kind = $this->resolveKind($kind);
        $option = OfferOption::query()->where('kind', $kind)->findOrFail($id);

        return view('admin.offer-options.edit', [
            'kind' => $kind,
            'labels' => self::LABELS,
            'option' => $option,
            'iconLibrary' => $this->iconLibrary(),
        ]);
    }

    /**
     * The active personalised-icon library, for the offer-options edit picker.
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
        $data = $this->validated($request, $kind, null);
        $data['kind'] = $kind;

        OfferOption::query()->create($data);

        return redirect()->route('admin.offer-options.index', ['kind' => $kind])
            ->with('status', 'Option created.');
    }

    public function update(Request $request, string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $option = OfferOption::query()->where('kind', $kind)->findOrFail($id);
        $option->update($this->validated($request, $kind, $option->id));

        return redirect()->route('admin.offer-options.index', ['kind' => $kind])
            ->with('status', 'Option updated.');
    }

    /** "Remove" = deactivate (retire, never delete) when in use; hard-delete only when unused. */
    public function destroy(string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $option = OfferOption::query()->where('kind', $kind)->findOrFail($id);

        if ($this->inUseCount($kind, $option->slug) > 0) {
            $option->update(['is_active' => false]);

            return redirect()->route('admin.offer-options.index', ['kind' => $kind])
                ->with('status', 'Option is in use — deactivated (hidden from the app) instead of deleted.');
        }

        $option->delete();

        return redirect()->route('admin.offer-options.index', ['kind' => $kind])
            ->with('status', 'Unused option deleted.');
    }

    public function toggle(string $kind, string $id): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        $option = OfferOption::query()->where('kind', $kind)->findOrFail($id);
        $option->update(['is_active' => ! $option->is_active]);

        return redirect()->route('admin.offer-options.index', ['kind' => $kind])
            ->with('status', $option->is_active ? 'Option activated.' : 'Option deactivated.');
    }

    /** Persist drag-drop order: ordered list of ids → sort_order. */
    public function reorder(Request $request, string $kind): RedirectResponse
    {
        $kind = $this->resolveKind($kind);
        foreach ((array) $request->input('order', []) as $i => $id) {
            OfferOption::query()->where('kind', $kind)->whereKey($id)->update(['sort_order' => $i + 1]);
        }

        return redirect()->route('admin.offer-options.index', ['kind' => $kind])
            ->with('status', 'Order saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $kind, ?string $ignoreId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'icon' => ['nullable', 'string', 'max:50'],
            'icon_url' => ['nullable', 'string', 'max:2048'], // public URL of a library icon
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon_svg' => ['nullable', 'file', 'mimetypes:image/svg+xml', 'max:128'], // ≤128KB (legacy inline upload)
        ]);

        $slug = $data['slug']
            ?? Str::of($data['name'])->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();

        // Slug is unique PER kind. Validate manually so "venue" can exist as both an
        // offering and a need (different kind), but never twice in the same kind.
        $clash = OfferOption::query()
            ->where('kind', $kind)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'slug' => "Slug \"{$slug}\" already exists for {$kind}.",
            ]);
        }

        $out = [
            'name' => $data['name'],
            'slug' => $slug,
            'icon' => $data['icon'] ?? null,
            // Primary path: the chosen library icon's public URL (the app renders this).
            'icon_url' => ($data['icon_url'] ?? null) ?: null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
        ];

        // Legacy inline upload still supported; it overrides the picked URL.
        if ($request->hasFile('icon_svg')) {
            $path = $request->file('icon_svg')->store('offer-option-icons', ['disk' => 'public']);
            $out['icon_url'] = Storage::disk('public')->url($path);
        }

        return $out;
    }
}
