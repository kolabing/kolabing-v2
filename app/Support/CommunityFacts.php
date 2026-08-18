<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lifts structured, displayable facts for a community out of a ranking-JSON entry.
 *
 * Structured fields provided by research (members/cadence/venue/collabs/event links) are
 * used as-is. Where they are absent, a small, CONSERVATIVE parse of our own `summary`
 * prose recovers only the two facts we can extract reliably (a public member/follower
 * count and a meeting cadence) — always hedged "(from public profile)". Venue and brand
 * collabs are NEVER guessed from prose; they only appear when given as structured data.
 */
class CommunityFacts
{
    /**
     * @param  array<string, mixed>  $entry  a ranking-JSON ranked entry
     * @return array<string, mixed> metric additions to merge into CrmAccount.metrics
     */
    public static function enrich(array $entry): array
    {
        $out = [];

        foreach (['members', 'cadence', 'venue', 'photo_url', 'luma_url', 'meetup_url', 'eventbrite_url', 'app_url'] as $key) {
            if (! empty($entry[$key])) {
                $out[$key] = $entry[$key];
            }
        }
        if (! empty($entry['collabs']) && is_array($entry['collabs'])) {
            $out['collabs'] = array_values($entry['collabs']);
        }
        if (empty($out['app_url']) && ! empty($entry['instagram_url'])) {
            // keep the IG url discoverable even if handle is blank
            $out['instagram_url'] = $entry['instagram_url'];
        }

        $summary = (string) ($entry['summary'] ?? '');

        if (empty($out['members']) && preg_match('/(\d[\d.,]{2,})\s*(?:members|followers)/i', $summary, $m)) {
            $out['members'] = $m[1].' (from public profile)';
        }

        if (empty($out['cadence']) && preg_match(
            '/\b(weekly|monthly|fortnightly|bi-weekly|every\s+\w+day|each\s+\w+day|\w+day\s+(?:mornings?|evenings?|nights?))\b/i',
            $summary,
            $m
        )) {
            $out['cadence'] = ucfirst(mb_strtolower($m[0]));
        }

        return $out;
    }
}
