<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrmAccount;
use App\Models\RankingPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the public community-rankings directory from database/data/community_rankings.json.
 *
 *  1. Upserts the editorial copy for each ranking page (ranking_pages), keyed on slug.
 *     Only Wave-1 pages are published; the rest wait for a maintainer / Maria's review.
 *  2. Best-effort: marks the ranked communities `listed` on their CRM lead and writes a
 *     display blurb + rank_override into metrics, so the live page matches this curation.
 *     For Barcelona (not in the PR #157 lead set) the community CRM rows are created here.
 *
 * Idempotent. Run AFTER KolabingCommunityLeadsSeeder (PR #157) so the 7-city leads exist:
 *   php artisan db:seed --class=Database\Seeders\RankingPageSeeder
 */
class RankingPageSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/community_rankings.json');
        if (! File::exists($path)) {
            $this->command?->warn('RankingPageSeeder: data file missing, skipped.');

            return;
        }

        /** @var array{cities: list<array<string, mixed>>} $data */
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $editor = (string) config('rankings.editor_name', 'The Kolabing editorial team');

        foreach ($data['cities'] as $c) {
            $city = $c['city'];

            foreach ($c['pieces'] as $sort => $p) {
                RankingPage::query()->updateOrCreate(
                    ['slug' => $p['slug']],
                    [
                        'city' => $city,
                        'topic' => $p['topic'],
                        'title' => $p['title'],
                        'meta_description' => $p['meta_description'] ?? null,
                        'intro' => $p['intro'] ?? null,
                        'how_ranked' => $p['how_ranked'] ?? null,
                        'verticals' => $p['verticals'] ?? null,
                        'faq' => $p['faq'] ?? [],
                        'editor_name' => $editor,
                        'published' => (int) ($p['wave'] ?? 3) === 1,
                        'sort' => (int) $sort,
                    ],
                );

                $this->wireCommunities($city, $p);
            }
        }
    }

    /**
     * Mark each ranked community `listed` on its CRM lead, with a display blurb + a
     * hub-derived rank_override. Guarded so a CRM hiccup never breaks the copy seed.
     *
     * @param  array<string, mixed>  $piece
     */
    private function wireCommunities(string $city, array $piece): void
    {
        $isHub = $piece['topic'] === null;
        $inferredVertical = $piece['verticals'][0] ?? 'community';

        foreach ($piece['ranked'] as $rank => $r) {
            try {
                $account = CrmAccount::query()
                    ->where('type', 'community')
                    ->whereRaw('lower(name) = ?', [mb_strtolower(trim($r['name']))])
                    ->where('metrics->city', $city)
                    ->first();

                if ($account === null) {
                    // Only mint new leads for Barcelona (the researched, non-PR#157 set).
                    if ($city !== 'Barcelona') {
                        continue;
                    }
                    $account = new CrmAccount([
                        'type' => 'community',
                        'name' => $r['name'],
                        'status' => 'Target',
                        'instagram_handle' => $r['handle'] ?: null,
                        'metrics' => [
                            'city' => $city,
                            'vertical' => $inferredVertical,
                            'handle' => $r['handle'] ?: null,
                        ],
                    ]);
                }

                $metrics = $account->metrics ?? [];
                $metrics['blurb'] = $r['summary'] ?? ($metrics['blurb'] ?? null);
                if ($r['handle'] ?? null) {
                    $metrics['handle'] = $metrics['handle'] ?? $r['handle'];
                }
                // The hub is the canonical citywide order; topic pages only fill gaps.
                if ($isHub || ! isset($metrics['rank_override'])) {
                    $metrics['rank_override'] = (int) $rank;
                }

                $account->metrics = $metrics;
                $account->listed = true;
                $account->save();
            } catch (\Throwable $e) {
                $this->command?->warn("RankingPageSeeder: could not wire '{$r['name']}' ({$e->getMessage()}).");
            }
        }
    }
}
