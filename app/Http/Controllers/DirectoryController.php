<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrmAccount;
use App\Models\ListingClaim;
use App\Models\RankingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Public community-rankings directory (the GTM lead-magnet pages).
 *
 * Editorial copy comes from ranking_pages; the ranked LIST is a live projection of
 * crm_accounts (type=community, listed=true) filtered by the page's city + verticals
 * and ordered by the admin-managed CRM score (with an optional metrics.rank_override).
 * So re-rank / edit / add / remove all happen in /admin/crm, never in code.
 */
class DirectoryController extends Controller
{
    public function index(): View
    {
        $cities = RankingPage::query()->published()->whereNull('topic')
            ->orderBy('sort')->orderBy('city')->get();

        return view('directory.index', [
            'cities' => $cities->map(fn (RankingPage $p) => [
                'page' => $p,
                'count' => $this->communities($p->city)->count(),
            ]),
        ]);
    }

    public function show(string $city): View
    {
        $page = RankingPage::query()->published()->where('city', $city)->whereNull('topic')->firstOrFail();

        $communities = $this->communities($city);

        return view('directory.city', [
            'page' => $page,
            'ranked' => $this->rank($communities)->take((int) config('rankings.hub_limit', 12)),
            'total' => $communities->count(),
            'topics' => RankingPage::query()->published()->where('city', $city)->whereNotNull('topic')
                ->orderBy('sort')->get(),
        ]);
    }

    public function topic(string $city, string $slug): View
    {
        $page = RankingPage::query()->published()->where('slug', $slug)->where('city', $city)->firstOrFail();

        $communities = $this->communities($city, (array) $page->verticals);

        return view('directory.topic', [
            'page' => $page,
            'ranked' => $this->rank($communities),
        ]);
    }

    public function howWeRank(): View
    {
        return view('directory.how-we-rank');
    }

    public function claim(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'community_name' => ['required', 'string', 'max:160'],
            'handle' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:60'],
            'source' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'size:0'], // honeypot
        ]);

        // Best-effort link to the existing CRM lead by name.
        $account = CrmAccount::query()->where('type', 'community')
            ->where('name', $data['community_name'])->first();

        ListingClaim::query()->create([
            'community_name' => $data['community_name'],
            'handle' => $data['handle'] ?? null,
            'email' => $data['email'],
            'city' => $data['city'] ?? null,
            'source' => $data['source'] ?? null,
            'crm_account_id' => $account?->id,
        ]);

        return back()->with('claimed', true);
    }

    /**
     * Listed community leads for a city, optionally narrowed to a set of vertical keywords.
     *
     * @param  list<string>  $verticals
     * @return Collection<int, CrmAccount>
     */
    private function communities(string $city, array $verticals = []): Collection
    {
        $query = CrmAccount::query()
            ->where('type', 'community')
            ->where('listed', true)
            ->where('metrics->city', $city);

        if ($verticals !== []) {
            $query->where(function ($q) use ($verticals): void {
                foreach ($verticals as $keyword) {
                    $q->orWhere('metrics->vertical', 'like', '%'.$keyword.'%');
                }
            });
        }

        return $query->get();
    }

    /**
     * Order the ranked list: an explicit metrics.rank_override first, then the
     * admin-managed CRM score (desc), then name — all resolved in PHP so the JSON
     * override works regardless of the database driver.
     *
     * @param  Collection<int, CrmAccount>  $communities
     * @return Collection<int, CrmAccount>
     */
    private function rank(Collection $communities): Collection
    {
        return $communities->sortBy([
            fn (CrmAccount $a) => $a->metrics['rank_override'] ?? PHP_INT_MAX,
            fn (CrmAccount $a) => -1 * (int) $a->score,
            fn (CrmAccount $a) => $a->name,
        ])->values();
    }
}
