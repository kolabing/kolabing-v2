<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RankingProjection;
use Database\Seeders\RankingPageSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

/**
 * Renders the REAL directory Blade views to static HTML for a shareable preview.
 *
 * The whole point: the preview is the production views, rendered through the same
 * RankingProjection + RankingPageSeeder transforms the live site uses — so it cannot
 * drift from what ships. Data comes from unsaved Eloquent models built off the content
 * JSON (no database needed; the box has no pdo_sqlite). For Barcelona the models are
 * byte-identical to what db:seed persists (its leads are created purely from the JSON).
 *
 *   php artisan rankings:export-preview --only=Barcelona
 */
class ExportRankingPreview extends Command
{
    protected $signature = 'rankings:export-preview
        {--only= : Limit to a single city (e.g. Barcelona)}
        {--out= : Output directory (default storage/app/preview)}
        {--site= : Marketing origin for chrome links (default https://kolabing.com)}';

    protected $description = 'Render the community-rankings directory to static HTML for preview.';

    public function handle(RankingProjection $projection): int
    {
        $data = RankingPageSeeder::loadData();
        if ($data === null) {
            $this->error('community_rankings.json not found.');

            return self::FAILURE;
        }

        // Marketing chrome (header/footer nav) points at the real live site; internal
        // directory links are rewritten to root-relative below so they stay in-preview.
        $site = rtrim($this->option('site') ?: 'https://kolabing.com', '/');
        URL::forceRootUrl($site);
        // @csrf in the claim form needs a session token when rendered off-request;
        // the array driver keeps it in-memory (no DB, which the box lacks).
        config(['session.driver' => 'array']);
        if (! app('session')->isStarted()) {
            app('session')->start();
        }

        $editor = RankingPageSeeder::editor();
        $only = $this->option('only') ?: null;
        $pages = RankingPageSeeder::pageModels($data, $editor, $only);
        $communities = RankingPageSeeder::communityModels($data, $only);

        $out = $this->option('out') ?: storage_path('app/preview');
        File::deleteDirectory($out);
        File::ensureDirectoryExists($out);

        $written = 0;

        if (View::exists('directory.index')) {
            $hubs = $pages->filter(fn ($p) => $p->topic === null)->map(fn ($p) => [
                'page' => $p,
                'count' => $projection->forCity($communities, $p->city)->count(),
                'categories' => $pages->filter(fn ($t) => $t->city === $p->city && $t->topic !== null)
                    ->map(fn ($t) => ['slug' => $t->slug, 'label' => \App\Http\Controllers\DirectoryController::topicLabel($t->topic)])
                    ->values(),
            ]);
            $this->emit($out.'/communities/index.html', view('directory.index', ['cities' => $hubs])->render(), $site);
            $written++;
        }

        if (View::exists('directory.how-we-rank')) {
            $this->emit($out.'/communities/how-we-rank/index.html', view('directory.how-we-rank')->render(), $site);
            $written++;
        }

        foreach ($pages as $page) {
            if ($page->topic === null) {
                $comm = $projection->forCity($communities, $page->city);
                $html = view('directory.city', [
                    'page' => $page,
                    'ranked' => $projection->hubRanked($comm)->take((int) config('rankings.hub_limit', 20)),
                    'total' => $comm->count(),
                    'topics' => $pages->filter(fn ($p) => $p->city === $page->city && $p->topic !== null)->values(),
                ])->render();
                $this->emit($out.'/communities/'.$page->city.'/index.html', $html, $site);
                $written++;
            } elseif (View::exists('directory.topic')) {
                $comm = $projection->forCity($communities, $page->city, (array) $page->verticals);
                $html = view('directory.topic', ['page' => $page, 'ranked' => $projection->rank($comm)])->render();
                $this->emit($out.'/communities/'.$page->city.'/'.$page->slug.'/index.html', $html, $site);
                $written++;
            }
        }

        $this->copyAssets($out);
        File::put($out.'/vercel.json', json_encode(['cleanUrls' => true, 'trailingSlash' => true], JSON_PRETTY_PRINT));

        $this->info("Exported {$written} pages to {$out}");

        return self::SUCCESS;
    }

    private function emit(string $path, string $html, string $site): void
    {
        // Keep directory navigation inside the preview (root-relative), whatever origin
        // route() emitted; leave all other links (chrome, app, blog) pointing at the
        // real live site.
        $html = preg_replace('#https?://[^/"]+/communities#', '/communities', $html);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $html);
    }

    private function copyAssets(string $out): void
    {
        if (File::isDirectory(public_path('brand'))) {
            File::copyDirectory(public_path('brand'), $out.'/brand');
        }
        foreach (['favicon.ico', 'favicon-512.png', 'favicon-32x32.png', 'favicon-16x16.png', 'social-preview.svg', 'site.webmanifest'] as $asset) {
            if (File::exists(public_path($asset))) {
                File::copy(public_path($asset), $out.'/'.$asset);
            }
        }
    }
}
