<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Icon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Icons" library — the personalised SVG icons the mobile app renders. Lists the
 * seeded bundled app icons + admin uploads, and lets admins upload new SVGs. Files live
 * on the public disk under `category-icons/` (deploy-safe via `php artisan storage:link`,
 * no external CDN). Each icon's public URL is what taxonomies (offer_options, ...) store
 * and the app's CategoryIcon widget renders.
 */
class IconLibraryController extends Controller
{
    public function index(): View
    {
        $icons = Icon::query()->active()->ordered()->get();

        return view('admin.icons.index', [
            'icons' => $icons,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'icon_svg' => ['required', 'file', 'mimetypes:image/svg+xml', 'mimes:svg', 'max:128'], // ≤128KB
        ]);

        $file = $request->file('icon_svg');

        // Sanitise the filename: slugify the original name, keep it unique, force .svg.
        $original = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = Str::slug($original) ?: 'icon';
        $slug = $this->uniqueSlug($base);
        $filename = 'category-'.$slug.'.svg';

        $file->storeAs(Icon::DIRECTORY, $filename, ['disk' => 'public']);

        Icon::query()->create([
            'label' => $data['label'] ?? Str::headline($slug),
            'slug' => $slug,
            'filename' => $filename,
            'source' => Icon::SOURCE_UPLOADED,
            'sort_order' => (int) (Icon::query()->max('sort_order') ?? 0) + 1,
        ]);

        return redirect()->route('admin.icons.index')
            ->with('status', 'Icon uploaded — it is now pickable.');
    }

    public function destroy(Icon $icon): RedirectResponse
    {
        if ($icon->source === Icon::SOURCE_BUNDLED) {
            throw ValidationException::withMessages([
                'icon' => 'Bundled app icons cannot be deleted.',
            ]);
        }

        Storage::disk('public')->delete(Icon::DIRECTORY.'/'.$icon->filename);
        $icon->delete();

        return redirect()->route('admin.icons.index')
            ->with('status', 'Uploaded icon deleted.');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Icon::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
