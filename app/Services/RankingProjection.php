<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmAccount;
use Illuminate\Support\Collection;

/**
 * The single source of truth for how a public ranking page projects the CRM.
 *
 * Both the live controller (DirectoryController) and the static preview exporter
 * (rankings:export-preview) filter and order community leads through THIS service,
 * so the preview can never drift from what production renders. It is pure: it
 * operates on an in-memory collection of community CrmAccount models and touches
 * no database, which is also what lets the preview run without one.
 */
class RankingProjection
{
    /**
     * Narrow a set of listed community leads to one city, optionally to a set of
     * vertical keywords (a substring match on metrics.vertical, mirroring the
     * `metrics->vertical LIKE %keyword%` the CRM query used).
     *
     * @param  Collection<int, CrmAccount>  $communities  all type=community, listed=true
     * @param  list<string>  $verticals
     * @return Collection<int, CrmAccount>
     */
    public function forCity(Collection $communities, string $city, array $verticals = []): Collection
    {
        return $communities->filter(function (CrmAccount $a) use ($city, $verticals): bool {
            if (($a->metrics['city'] ?? null) !== $city) {
                return false;
            }
            if ($verticals === []) {
                return true;
            }
            $vertical = (string) ($a->metrics['vertical'] ?? '');
            foreach ($verticals as $keyword) {
                if ($keyword !== '' && str_contains($vertical, $keyword)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Order the ranked list: an explicit metrics.rank_override first, then the
     * admin-managed CRM score (desc), then name. Resolved in PHP so the override
     * works regardless of database driver (and so the preview matches exactly).
     *
     * @param  Collection<int, CrmAccount>  $communities
     * @return Collection<int, CrmAccount>
     */
    public function rank(Collection $communities): Collection
    {
        // A single array-spaceship comparator: rank_override asc, then CRM score desc,
        // then name. (Collection::sortBy with an array of bare closures is silently
        // wrong in this Laravel version, so the ordering is expressed explicitly.)
        return $communities->sort(fn (CrmAccount $a, CrmAccount $b) => [
            $a->metrics['rank_override'] ?? PHP_INT_MAX, -1 * (int) $a->score, $a->name,
        ] <=> [
            $b->metrics['rank_override'] ?? PHP_INT_MAX, -1 * (int) $b->score, $b->name,
        ])->values();
    }

    /**
     * The curated citywide hub list: only communities the hub pinned (rank_override
     * set), ordered by it. Keeps topic-only communities off the hub page.
     *
     * @param  Collection<int, CrmAccount>  $cityCommunities
     * @return Collection<int, CrmAccount>
     */
    public function hubRanked(Collection $cityCommunities): Collection
    {
        return $this->rank($cityCommunities->filter(
            fn (CrmAccount $a) => isset($a->metrics['hub_rank'])
        ));
    }
}
