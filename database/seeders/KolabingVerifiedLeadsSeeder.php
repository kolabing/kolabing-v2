<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CrmAccount;
use Illuminate\Database\Seeder;

/**
 * Replaces the 2026-08-18 first-pass community leads (source 'neil-2026-08-18')
 * with the VERIFIED set produced by Challenge A: every row passed an
 * identity -> status -> plausibility gate and a blind independent recount.
 *
 * Source data: database/data/kolabing_verified_leads.csv (145 verified
 * communities across 7 cities, each classified local-community with a
 * source-attributed audience number, a last-active date, a confidence, and an
 * evidence URL). The prior flawed rows (global brands booked as local, dead
 * clubs, wrong-city, handle-mismatches) are removed. Idempotent + a
 * production-guarded migration runs it on deploy.
 */
class KolabingVerifiedLeadsSeeder extends Seeder
{
    private const OLD_SOURCE = 'neil-2026-08-18';

    private const SOURCE = 'neil-2026-08-18-verified';

    private const OWNER = 'Neil';

    public function run(): void
    {
        $path = database_path('data/kolabing_verified_leads.csv');
        if (! is_file($path)) {
            $this->command?->warn("KolabingVerifiedLeadsSeeder: CSV missing at {$path}, skipping.");

            return;
        }

        // Retire the first-pass community rows that did not survive verification.
        CrmAccount::query()
            ->where('type', 'community')
            ->where('metrics->source', self::OLD_SOURCE)
            ->delete();

        $rows = $this->readCsv($path);
        foreach ($rows as $r) {
            $handle = $this->clean($r['handle'] ?? '');
            $igHandle = str_starts_with($handle, '@') ? substr($handle, 0, 80) : null;
            $profileUrl = str_starts_with($handle, 'http') ? $handle : null;

            CrmAccount::query()->updateOrCreate(
                ['type' => 'community', 'name' => $this->clean($r['community'])],
                [
                    'status' => 'Target',
                    'owner' => self::OWNER,
                    'instagram_handle' => $igHandle,
                    'next_action' => 'Initial outreach (verified 2026-08-18)',
                    'notes' => $this->notes($r),
                    'metrics' => array_filter([
                        'source' => self::SOURCE,
                        'city' => $this->clean($r['city'] ?? ''),
                        'classification' => $this->clean($r['classification'] ?? ''),
                        // All verified rows passed the identity gate as genuine local communities.
                        'locality_confirmed' => true,
                        'is_global_brand' => false,
                        'audience' => $this->clean($r['audience'] ?? ''),
                        'audience_count' => $this->extractNumeric($r['audience'] ?? ''),
                        'audience_source' => $this->clean($r['audience_source'] ?? ''),
                        'last_active' => $this->clean($r['last_active'] ?? ''),
                        'last_active_date' => $this->clean($r['last_active_date'] ?? ''),
                        'confidence' => $this->clean($r['confidence'] ?? ''),
                        'evidence_url' => $this->clean($r['evidence_url'] ?? ''),
                        'profile_url' => $profileUrl,
                        'collab_businesses' => $this->clean($r['collabs'] ?? ''),
                    ], static fn ($v) => $v !== '' && $v !== null),
                ],
            );
        }

        $this->command?->info('KolabingVerifiedLeadsSeeder: '.count($rows).' verified communities loaded.');
    }

    private function notes(array $r): string
    {
        return implode(' | ', array_filter([
            $this->clean($r['reason'] ?? ''),
            ($r['audience'] ?? '') !== '' ? 'Audience: '.$this->clean($r['audience']).' ('.$this->clean($r['audience_source'] ?? '').')' : null,
            ($r['last_active_date'] ?? '') !== '' ? 'Last active: '.$this->clean($r['last_active_date']) : null,
            ($r['confidence'] ?? '') !== '' ? 'Confidence: '.$this->clean($r['confidence']) : null,
        ], static fn ($v) => $v !== '' && $v !== null));
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

    /** First run of digits in a display string ("7,124 IG followers" -> 7124). 0 when none. */
    private function extractNumeric(string $v): int
    {
        return preg_match('/([0-9][0-9,.]{1,})/', $v, $m) ? (int) str_replace([',', '.'], '', $m[1]) : 0;
    }

    private function clean(string $v): string
    {
        return trim(strtr($v, [
            "\u{201C}" => '"', "\u{201D}" => '"', "\u{2018}" => "'", "\u{2019}" => "'",
            "\u{2014}" => '-', "\u{2013}" => '-',
        ]));
    }
}
