<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrmAccount;
use App\Models\RankingPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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
 * The pure transforms (pageAttributes / communityAttributes / *Models) are static and
 * side-effect-free so the preview exporter (rankings:export-preview) builds the SAME
 * unsaved models it renders — the preview cannot drift from what db:seed produces.
 *
 * Idempotent. Run AFTER KolabingCommunityLeadsSeeder (PR #157) so the 7-city leads exist:
 *   php artisan db:seed --class=Database\Seeders\RankingPageSeeder
 */
class RankingPageSeeder extends Seeder
{
    public function run(): void
    {
        $data = self::loadData();
        if ($data === null) {
            $this->command?->warn('RankingPageSeeder: data file missing, skipped.');

            return;
        }

        $editor = self::editor();

        foreach ($data['cities'] as $c) {
            foreach ($c['pieces'] as $sort => $piece) {
                RankingPage::query()->updateOrCreate(
                    ['slug' => $piece['slug']],
                    self::pageAttributes($c['city'], (int) $sort, $piece, $editor),
                );
            }
        }

        foreach (self::communityModels($data) as $model) {
            $this->wireCommunity($model);
        }
    }

    /**
     * Mark the community `listed` on its CRM lead (creating the row for Barcelona,
     * which is not in the PR #157 set), copying the display blurb + rank_override.
     * Guarded so a CRM hiccup never breaks the copy seed.
     */
    private function wireCommunity(CrmAccount $model): void
    {
        $city = $model->metrics['city'] ?? null;

        try {
            $existing = CrmAccount::query()
                ->where('type', 'community')
                ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $model->name))])
                ->where('metrics->city', $city)
                ->first();

            if ($existing === null) {
                // Only mint new leads for Barcelona (the researched, non-PR#157 set).
                if ($city === 'Barcelona') {
                    $model->save();
                }

                return;
            }

            $metrics = $existing->metrics ?? [];
            $metrics['blurb'] = $model->metrics['blurb'] ?? ($metrics['blurb'] ?? null);
            $metrics['rank_override'] = $model->metrics['rank_override'] ?? ($metrics['rank_override'] ?? null);
            $metrics['handle'] ??= $model->metrics['handle'] ?? null;

            $existing->metrics = $metrics;
            $existing->listed = true;
            $existing->save();
        } catch (\Throwable $e) {
            $this->command?->warn("RankingPageSeeder: could not wire '{$model->name}' ({$e->getMessage()}).");
        }
    }

    // ---- Pure transforms (shared with the preview exporter) --------------------------

    /** @return array{cities: list<array<string, mixed>>}|null */
    public static function loadData(): ?array
    {
        $path = database_path('data/community_rankings.json');
        if (! File::exists($path)) {
            return null;
        }

        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function editor(): string
    {
        return (string) config('rankings.editor_name', 'The Kolabing editorial team');
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function pageAttributes(string $city, int $sort, array $piece, string $editor): array
    {
        return [
            'city' => $city,
            'topic' => $piece['topic'],
            'title' => $piece['title'],
            'meta_description' => $piece['meta_description'] ?? null,
            'intro' => $piece['intro'] ?? null,
            'how_ranked' => $piece['how_ranked'] ?? null,
            'verticals' => $piece['verticals'] ?? null,
            'faq' => $piece['faq'] ?? [],
            'editor_name' => $editor,
            'published' => (int) ($piece['wave'] ?? 3) === 1,
            'sort' => $sort,
        ];
    }

    /**
     * The CRM-lead attributes synthesized for one ranked entry (name/handle from the
     * entry; vertical from the piece; blurb from the entry summary; rank_override from
     * its position). This is the shape both the seed and the preview render from.
     *
     * @param  array<string, mixed>  $piece
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public static function communityAttributes(string $city, array $piece, int $rank, array $entry): array
    {
        $handle = ($entry['handle'] ?? '') ?: null;
        // Only the hub (topic === null) pins a citywide rank_override; topic-only
        // communities carry none, so they never pollute the curated hub order and
        // fall to score/name ordering on their own topic page.
        $isHub = ($piece['topic'] ?? null) === null;

        return [
            'type' => 'community',
            'name' => $entry['name'],
            'status' => 'Target',
            'instagram_handle' => $handle,
            'listed' => true,
            'metrics' => array_filter([
                'city' => $city,
                'vertical' => $piece['verticals'][0] ?? 'community',
                'handle' => $handle,
                'blurb' => $entry['summary'] ?? null,
                'rank_override' => $isHub ? $rank : null,
            ], fn ($v): bool => $v !== null),
        ];
    }

    /**
     * Unsaved RankingPage models for every piece (the preview's copy source).
     *
     * @param  array{cities: list<array<string, mixed>>}  $data
     * @return Collection<int, RankingPage>
     */
    public static function pageModels(array $data, string $editor, ?string $onlyCity = null): Collection
    {
        $pages = collect();
        foreach ($data['cities'] as $c) {
            if ($onlyCity !== null && $c['city'] !== $onlyCity) {
                continue;
            }
            foreach ($c['pieces'] as $sort => $piece) {
                $attrs = self::pageAttributes($c['city'], (int) $sort, $piece, $editor);
                $attrs['slug'] = $piece['slug'];
                $pages->push(new RankingPage($attrs));
            }
        }

        return $pages;
    }

    /**
     * Unsaved, deduped community CrmAccount models (hub piece is the canonical rank;
     * topic pieces only add communities the hub did not list). One row per (city, name).
     *
     * @param  array{cities: list<array<string, mixed>>}  $data
     * @return Collection<int, CrmAccount>
     */
    public static function communityModels(array $data, ?string $onlyCity = null): Collection
    {
        $byKey = [];

        // Two passes so the hub (topic === null) sets the canonical rank_override
        // before any topic page fills in communities the hub did not carry.
        foreach ([true, false] as $hubPass) {
            foreach ($data['cities'] as $c) {
                $city = $c['city'];
                if ($onlyCity !== null && $city !== $onlyCity) {
                    continue;
                }
                foreach ($c['pieces'] as $piece) {
                    $isHub = ($piece['topic'] ?? null) === null;
                    if ($isHub !== $hubPass) {
                        continue;
                    }
                    foreach ($piece['ranked'] as $rank => $entry) {
                        $key = $city.'|'.mb_strtolower(trim((string) $entry['name']));
                        if (isset($byKey[$key])) {
                            // Already carried (hub is canonical); only backfill blanks.
                            $attrs = self::communityAttributes($city, $piece, (int) $rank, $entry);
                            $byKey[$key]['metrics']['blurb'] ??= $attrs['metrics']['blurb'] ?? null;
                            $byKey[$key]['metrics']['handle'] ??= $attrs['metrics']['handle'] ?? null;

                            continue;
                        }
                        $byKey[$key] = self::communityAttributes($city, $piece, (int) $rank, $entry);
                    }
                }
            }
        }

        return collect(array_values($byKey))->map(fn (array $attrs): CrmAccount => new CrmAccount($attrs));
    }
}
