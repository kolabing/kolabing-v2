<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrmAccount;
use App\Models\ListingClaim;
use App\Models\RankingPage;
use App\Services\RankingProjection;
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
 *
 * The filter + ordering live in RankingProjection so the static preview exporter
 * renders through the exact same code path (no drift between preview and production).
 */
class DirectoryController extends Controller
{
    public function __construct(private readonly RankingProjection $projection) {}

    public function index(): View
    {
        $cities = RankingPage::query()->published()->whereNull('topic')
            ->orderBy('sort')->orderBy('city')->get();
        $listed = $this->listedCommunities();

        return view('directory.index', [
            'cities' => $cities->map(fn (RankingPage $p) => [
                'page' => $p,
                'count' => $this->projection->forCity($listed, $p->city)->count(),
            ]),
        ]);
    }

    public function show(string $city): View
    {
        $page = RankingPage::query()->published()->where('city', $city)->whereNull('topic')->firstOrFail();

        $communities = $this->projection->forCity($this->listedCommunities(), $city);

        return view('directory.city', [
            'page' => $page,
            'ranked' => $this->projection->hubRanked($communities)->take((int) config('rankings.hub_limit', 20)),
            'total' => $communities->count(),
            'topics' => RankingPage::query()->published()->where('city', $city)->whereNotNull('topic')
                ->orderBy('sort')->get(),
        ]);
    }

    public function topic(string $city, string $slug): View
    {
        $page = RankingPage::query()->published()->where('slug', $slug)->where('city', $city)->firstOrFail();

        $communities = $this->projection->forCity($this->listedCommunities(), $city, (array) $page->verticals);

        return view('directory.topic', [
            'page' => $page,
            'ranked' => $this->projection->rank($communities),
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
     * Every listed community lead, loaded once and filtered/ordered in memory by
     * RankingProjection. Loading the whole listed set (hundreds of rows) and
     * projecting in PHP is what lets the preview exporter reuse the identical code.
     *
     * @return Collection<int, CrmAccount>
     */
    private function listedCommunities(): Collection
    {
        return CrmAccount::query()
            ->where('type', 'community')
            ->where('listed', true)
            ->get();
    }
}
