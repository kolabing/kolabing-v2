<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrmAccount;
use App\Services\CrmScoreService;
use Illuminate\Database\Seeder;

/**
 * Seeds the supply-side community-lead research produced by the Neil agent
 * (2026-08-18): 140 event-publishing communities across 7 EU cities
 * (Madrid, Tallinn, Berlin, Paris, Lisbon, Amsterdam, Warsaw), each with the
 * GTM enrichment used to prioritise Kolabing partner outreach, plus the
 * distinct collaboration BUSINESSES those communities already work with.
 *
 * Source data: database/data/kolabing_community_leads.csv (shipped in-repo,
 * the exact export that also backs the shared Google Sheet). Communities land
 * as type='community' CRM accounts, collab partners as type='business'. Both
 * are idempotent (updateOrCreate keyed on [type, name]) so re-running only
 * tops up / refreshes — safe to run repeatedly on production.
 */
class KolabingCommunityLeadsSeeder extends Seeder
{
    private const SOURCE = 'neil-2026-08-18';

    private const OWNER = 'Neil';

    private const CSV = 'kolabing_community_leads.csv';

    public function run(): void
    {
        $path = database_path('data/'.self::CSV);
        if (! is_file($path)) {
            $this->command?->warn("KolabingCommunityLeadsSeeder: CSV not found at {$path}, skipping.");

            return;
        }

        $rows = $this->readCsv($path);
        $touched = [];

        foreach ($rows as $row) {
            $touched[] = $this->seedCommunity($row)->id;

            foreach ($this->extractBusinesses($row) as $biz) {
                $touched[] = $this->seedBusiness($biz)->id;
            }
        }

        // Best-effort score recompute; never let scoring break the import.
        try {
            $svc = app(CrmScoreService::class);
            CrmAccount::query()->whereIn('id', array_unique($touched))
                ->each(fn (CrmAccount $a) => $svc->recalculate($a));
        } catch (\Throwable $e) {
            $this->command?->warn('KolabingCommunityLeadsSeeder: score recompute skipped ('.$e->getMessage().').');
        }

        $this->command?->info('KolabingCommunityLeadsSeeder: '.count($rows).' communities processed.');
    }

    private function seedCommunity(array $r): CrmAccount
    {
        $igFollowers = $this->firstInt($r['Est Audience'] ?? '');
        $handle = $this->handle($r['Profile/Handle'] ?? '');

        $notes = $this->notes([
            $r['Vertical'] ?? null,
            ($r['Kolabing Fit'] ?? '') !== '' ? 'Fit: '.$r['Kolabing Fit'] : null,
            ($r['Leader'] ?? '') !== '' ? 'Leader: '.$r['Leader'] : null,
            ($r['Last Event'] ?? '') !== '' ? 'Last event: '.$r['Last Event'] : null,
            ($r['Collab Businesses'] ?? '') !== '' ? 'Collabs: '.$r['Collab Businesses'] : null,
        ]);

        return CrmAccount::query()->updateOrCreate(
            ['type' => 'community', 'name' => $this->clean($r['Community'])],
            [
                'status' => 'Target',
                'owner' => self::OWNER,
                'instagram_handle' => $handle,
                'next_action' => 'Initial outreach',
                'notes' => $notes,
                'metrics' => array_filter([
                    'source' => self::SOURCE,
                    'city' => $this->clean($r['City'] ?? ''),
                    'category' => $this->clean($r['Vertical'] ?? ''),
                    'location' => $this->clean($r['City'] ?? ''),
                    'platforms' => $this->clean($r['Platforms'] ?? ''),
                    'ig_followers' => $igFollowers,
                    'audience_raw' => $this->clean($r['Est Audience'] ?? ''),
                    'avg_attendance_raw' => $this->clean($r['Attendee Range'] ?? ''),
                    'cadence' => $this->clean($r['Cadence'] ?? ''),
                    'founder_name' => $this->clean($r['Leader'] ?? ''),
                    'last_event' => $this->clean($r['Last Event'] ?? ''),
                    'monetised' => $this->clean($r['Monetised'] ?? ''),
                    'kolabing_fit' => $this->clean($r['Kolabing Fit'] ?? ''),
                    'collab_businesses' => $this->clean($r['Collab Businesses'] ?? ''),
                    'collab_evidence_links' => $this->clean($r['Collab Evidence Links'] ?? ''),
                    'language' => $this->clean($r['Language'] ?? ''),
                ], static fn ($v) => $v !== '' && $v !== null),
            ],
        );
    }

    private function seedBusiness(array $biz): CrmAccount
    {
        return CrmAccount::query()->updateOrCreate(
            ['type' => 'business', 'name' => $biz['name']],
            [
                'status' => 'Target',
                'owner' => self::OWNER,
                'notes' => 'Collab partner of '.$biz['community'].' ('.$biz['city'].').'
                    .($biz['url'] !== '' ? ' Evidence: '.$biz['url'] : ''),
                'metrics' => array_filter([
                    'source' => self::SOURCE,
                    'category' => 'collab-partner',
                    'source_community' => $biz['community'],
                    'source_city' => $biz['city'],
                    'evidence_url' => $biz['url'],
                ], static fn ($v) => $v !== '' && $v !== null),
            ],
        );
    }

    /**
     * Best-effort extraction of distinct collab BUSINESSES from the evidence
     * column ("Name (…) (https://…) ; Other (https://…)"). Skips junk / n/f /
     * bare-URL fragments so a messy cell never creates a bad account.
     *
     * @return list<array{name:string, url:string, community:string, city:string}>
     */
    private function extractBusinesses(array $r): array
    {
        $raw = trim((string) ($r['Collab Evidence Links'] ?? ''));
        if ($raw === '' || stripos($raw, 'n/f') !== false && strlen($raw) < 6) {
            return [];
        }

        $out = [];
        foreach (preg_split('/\s*;\s*/', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // URL = last parenthesised or bare http(s) token.
            $url = '';
            if (preg_match_all('#https?://[^\s)]+#i', $part, $m) && $m[0]) {
                $url = rtrim(end($m[0]), '.,');
            }
            // Name = text before the first " (" ; strip a leading bare-url fragment.
            $name = trim(preg_replace('/\s*\(.*$/s', '', $part));
            if ($name === '' || preg_match('#^https?://#i', $name)) {
                continue;
            }
            $name = $this->clean($name);
            if ($name === '' || strlen($name) > 120 || stripos($name, 'n/f') !== false) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'url' => $url,
                'community' => $this->clean($r['Community']),
                'city' => $this->clean($r['City'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<array<string,string>> */
    private function readCsv(string $path): array
    {
        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        $rows = [];
        while (($line = fgetcsv($fh)) !== false) {
            if ($line === [null] || $line === false) {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }
        fclose($fh);

        return $rows;
    }

    private function handle(string $v): ?string
    {
        $v = trim($v);

        return ($v !== '' && str_starts_with($v, '@')) ? substr($v, 0, 80) : null;
    }

    private function firstInt(string $v): ?int
    {
        if (preg_match('/([0-9][0-9.,]{2,})/', $v, $m)) {
            $n = (int) str_replace([',', '.'], '', $m[1]);

            return $n > 0 ? $n : null;
        }

        return null;
    }

    /** @param list<?string> $parts */
    private function notes(array $parts): string
    {
        return implode(' · ', array_filter(array_map(
            fn ($p) => $p === null ? null : $this->clean($p),
            $parts,
        ), static fn ($p) => $p !== '' && $p !== null));
    }

    private function clean(string $v): string
    {
        // Normalise smart quotes/dashes the research text carries, trim whitespace.
        return trim(strtr($v, [
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{2018}" => "'", "\u{2019}" => "'",
            "\u{2014}" => '-', "\u{2013}" => '-',
        ]));
    }
}
