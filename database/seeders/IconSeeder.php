<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Icon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds the personalised-icon library from the bundled mobile-app category SVGs.
 *
 * The source SVGs are committed at database/seeders/icons/ (so deploy is self-contained:
 * no manual file copy). This seeder copies any missing ones into the public disk under
 * `category-icons/` (web-served once `php artisan storage:link` has run), then creates one
 * `bundled` row per file. Slug is derived from the filename (`category-running.svg` →
 * `running`), label is the title-cased slug. Idempotent: existing slugs are refreshed,
 * never duplicated, and an admin's is_active toggle is preserved.
 */
class IconSeeder extends Seeder
{
    public function run(): void
    {
        $disk = Storage::disk('public');
        $sourceDir = database_path('seeders/icons');

        // Copy the committed source SVGs into the public disk if missing (deploy-safe).
        if (File::isDirectory($sourceDir)) {
            foreach (File::files($sourceDir) as $file) {
                if (Str::lower($file->getExtension()) !== 'svg') {
                    continue;
                }
                $target = Icon::DIRECTORY.'/'.$file->getFilename();
                if (! $disk->exists($target)) {
                    $disk->put($target, (string) File::get($file->getPathname()));
                }
            }
        }

        $sort = 0;
        foreach ($disk->files(Icon::DIRECTORY) as $path) {
            if (! Str::endsWith(Str::lower($path), '.svg')) {
                continue;
            }

            $filename = basename($path);
            $slug = $this->slugFromFilename($filename);

            Icon::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'label' => Str::headline($slug),
                    'filename' => $filename,
                    'source' => Icon::SOURCE_BUNDLED,
                    'sort_order' => ++$sort,
                ],
            );
        }
    }

    /**
     * `category-running.svg` → `running`; `category-ice-cream.svg` → `ice-cream`.
     */
    private function slugFromFilename(string $filename): string
    {
        $base = Str::of($filename)->beforeLast('.')->lower();

        return $base->replaceFirst('category-', '')->value();
    }
}
