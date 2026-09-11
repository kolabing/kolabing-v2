<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CrmAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * The shared owner/status/city/search/work-now filter set for a CrmAccount query, used by
 * both the session-authenticated admin panel (Admin\CrmController) and the token-authenticated
 * read API (Api\V1\Admin\CrmController) — one filter definition, never two copies to drift.
 */
class CrmQueryFilters
{
    /**
     * @param  Builder<CrmAccount>  $query
     * @param  array{owner?: ?string, status?: ?string, city?: ?string, q?: ?string, work_now?: bool}  $filters
     * @return Builder<CrmAccount>
     */
    public static function apply(Builder $query, string $type, array $filters): Builder
    {
        // City lives in the metrics JSON: communities key it as `city`, businesses as `source_city`.
        $cityKey = $type === 'business' ? 'source_city' : 'city';

        if ($owner = $filters['owner'] ?? null) {
            $query->where('owner', $owner);
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($city = $filters['city'] ?? null) {
            $query->where("metrics->{$cityKey}", $city);
        }
        if ($q = $filters['q'] ?? null) {
            $query->where('name', 'like', "%{$q}%");
        }
        // "Work now": the operator's daily queue — high/med confidence, locality-confirmed,
        // real fit, not yet contacted. The single most useful supply-side surface.
        if (($filters['work_now'] ?? false) && $type === 'community') {
            $query->where('metrics->locality_confirmed', true)
                ->where('score', '>=', 40)
                ->where('status', 'Target')
                ->where(function (Builder $q2): void {
                    $q2->whereRaw("lower(metrics->>'confidence') like 'high%'")
                        ->orWhereRaw("lower(metrics->>'confidence') like 'med%'");
                });
        }

        return $query;
    }
}
