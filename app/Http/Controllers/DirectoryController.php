<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CrmAccount;
use App\Models\ListingClaim;
use App\Models\ListingTestimonial;
use App\Models\ListingVouch;
use App\Models\RankingPage;
use App\Services\RankingProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
        $topics = RankingPage::query()->published()->whereNotNull('topic')
            ->orderBy('sort')->get()->groupBy('city');

        return view('directory.index', [
            'cities' => $cities->map(fn (RankingPage $p) => [
                'page' => $p,
                'count' => $this->projection->forCity($listed, $p->city)->count(),
                'categories' => ($topics->get($p->city) ?? collect())
                    ->map(fn (RankingPage $t) => ['slug' => $t->slug, 'label' => self::topicLabel($t->topic)]),
            ]),
        ]);
    }

    /** Human label for a topic slug (shared by the index + category views). */
    public static function topicLabel(?string $topic): string
    {
        return $topic
            ? (string) Str::of($topic)->replace('-', ' ')->title()->replace(' And ', ' & ')
            : 'Community';
    }

    public function show(string $city): View
    {
        $page = RankingPage::query()->published()->where('city', $city)->whereNull('topic')->firstOrFail();

        $communities = $this->projection->forCity($this->listedCommunities(), $city);
        $ranked = $this->projection->hubRanked($communities)->take((int) config('rankings.hub_limit', 20));

        return view('directory.city', [
            'page' => $page,
            'ranked' => $ranked,
            'counts' => $this->socialCounts($ranked),
            'total' => $communities->count(),
            'topics' => RankingPage::query()->published()->where('city', $city)->whereNotNull('topic')
                ->orderBy('sort')->get(),
        ]);
    }

    public function topic(string $city, string $slug): View
    {
        $page = RankingPage::query()->published()->where('slug', $slug)->where('city', $city)->firstOrFail();

        $communities = $this->projection->forCity($this->listedCommunities(), $city, (array) $page->verticals);
        $ranked = $this->projection->rank($communities);

        return view('directory.topic', [
            'page' => $page,
            'ranked' => $ranked,
            'counts' => $this->socialCounts($ranked),
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
            'verify_token' => Str::random(40),
        ]);

        return back()->with('claimed', true);
    }

    /**
     * One public endorsement of a listed community. Deduped by a PII-free hash so the
     * count is real taps only; never reorders the ranking.
     */
    public function vouch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:24'],
            'website' => ['nullable', 'size:0'], // honeypot
        ]);

        if (RateLimiter::tooManyAttempts('vouch:'.$request->ip(), 30)) {
            return response()->json(['error' => 'rate_limited'], 429);
        }
        RateLimiter::hit('vouch:'.$request->ip(), 3600);

        $hash = hash('sha256', implode('|', [
            $request->cookie('kb_vid') ?: $request->ip(),
            $this->subnet($request->ip()),
            $data['listing_id'],
            config('app.key'),
        ]));

        if (! ListingVouch::query()->where('dedupe_hash', $hash)->exists()) {
            ListingVouch::query()->create([
                'listing_id' => $data['listing_id'],
                'dedupe_hash' => $hash,
                'reason' => $data['reason'] ?? null,
            ]);
        }

        return response()->json([
            'count' => ListingVouch::query()->where('listing_id', $data['listing_id'])->count(),
        ]);
    }

    /**
     * A member testimonial, captured moderation-queued (never shown until approved).
     */
    public function testimonial(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'listing_id' => ['required', 'uuid'],
            'body' => ['required', 'string', 'min:20', 'max:280'],
            'author_label' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255'],
            'website' => ['nullable', 'size:0'], // honeypot
        ]);

        if (preg_match('#https?://|www\.#i', $data['body'])) {
            return back()->withErrors(['body' => 'Links are not allowed in testimonials.']);
        }

        $verifiedMember = ListingClaim::query()
            ->where('crm_account_id', $data['listing_id'])
            ->whereNotNull('verified_at')
            ->where('email', $data['email'])
            ->exists();

        ListingTestimonial::query()->create([
            'listing_id' => $data['listing_id'],
            'body' => $data['body'],
            'author_label' => $data['author_label'] ?? null,
            'email_hash' => hash('sha256', mb_strtolower($data['email']).config('app.key')),
            'verified_member' => $verifiedMember,
            'status' => 'pending',
        ]);

        return back()->with('testimonial_submitted', true);
    }

    /**
     * Double-opt-in: a claim's verification link marks it verified (backs the
     * "Verified by N members" proof and the verified badge).
     */
    public function verifyClaim(string $token): RedirectResponse
    {
        $claim = ListingClaim::query()->where('verify_token', $token)->firstOrFail();
        $claim->update(['verified_at' => now(), 'verify_token' => null]);

        $city = $claim->city ?: 'communities';

        return redirect()->to($claim->city ? route('directory.city', $city) : route('directory.index'))
            ->with('verified', true);
    }

    /**
     * A stateless, embeddable "#N in {city}" badge — pure render of the projection,
     * so it is identical everywhere and carries zero fabrication risk.
     */
    public function badge(string $city, string $id): View
    {
        $account = $this->projection->forCity($this->listedCommunities(), $city)->firstWhere('id', $id);
        abort_if($account === null, 404);

        // A community's rank is its position in its own list: hub position for hub
        // members, otherwise its category position. (The hub-only search left every
        // topic-only community's badge with no number.)
        $pos = $account->metrics['hub_rank'] ?? $account->metrics['rank_override'] ?? null;

        return view('directory.badge', [
            'name' => $account->name,
            'city' => $city,
            'rank' => $pos === null ? null : (int) $pos + 1,
            'url' => route('directory.city', $city),
        ]);
    }

    /**
     * Real vouch + verified-member counts for a set of listings (0 is honest).
     *
     * @param  Collection<int, CrmAccount>  $listings
     * @return array<string, array{vouches: int, verified_members: int, verified: bool}>
     */
    private function socialCounts(Collection $listings): array
    {
        $ids = $listings->pluck('id')->filter()->values()->all();
        if ($ids === []) {
            return [];
        }

        $vouches = ListingVouch::query()->whereIn('listing_id', $ids)->get()->groupBy('listing_id');
        $verifiedClaims = ListingClaim::query()->whereIn('crm_account_id', $ids)
            ->whereNotNull('verified_at')->get()->groupBy('crm_account_id');

        $out = [];
        foreach ($ids as $id) {
            $v = $vouches->get($id) ?? collect();
            $vc = ($verifiedClaims->get($id) ?? collect())->count();
            $out[$id] = [
                'vouches' => $v->count(),
                'verified_members' => $v->where('verified', true)->count() + $vc,
                'verified' => $vc > 0,
            ];
        }

        return $out;
    }

    private function subnet(?string $ip): string
    {
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3));
        }

        return (string) $ip;
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
