<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\Event;
use App\Models\Kolab;
use App\Models\Profile;
use App\Support\PublicKolabLink;
use App\Support\PublicProfileLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public landing page for a community's shareable join link.
 *
 * config('communities.invite_base_url') has always pointed at /c/{slug} and
 * Community::inviteUrl() has always emitted it, but the route never existed —
 * so every invite link ever shared 404'd. This is that route.
 */
class CommunityJoinPageController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        // Read by NAME, not by position: this route is registered twice — once at
        // /c/{slug} and once at /{locale}/c/{slug} — so a positional argument
        // picks up the locale on the prefixed one.
        $slug = (string) $request->route('slug');

        $community = Community::query()
            ->where('slug', $slug)
            ->with([
                'communityProfile',
                'tiers' => fn ($query) => $query->orderByDesc('rank'),
            ])
            ->first();

        if ($community === null) {
            $kolab = Str::isUuid($slug) ? Kolab::query()->find($slug) : null;

            if ($kolab !== null) {
                return redirect()->away($this->kolabUrl($request, $kolab));
            }

            $profile = $this->profileFor($slug);

            abort_if($profile === null, 404);

            return redirect()->away(PublicProfileLink::urlFor($profile), 301);
        }

        $memberCount = $community->members()
            ->where('status', CommunityMemberStatus::Active->value)
            ->count();

        // Only public events are listed: this page is unauthenticated, and
        // members-only events must not leak from it.
        $events = Event::query()
            ->where('community_id', $community->id)
            ->where('event_date', '>=', now()->toDateString())
            ->where('visibility', 'public')
            ->orderBy('event_date')
            ->limit(5)
            ->get(['id', 'name', 'event_date']);

        $logo = $community->avatar_url ?: $community->communityProfile?->profile_photo;

        return view('webapp.community-join', [
            'logo' => $logo,
            'metaDescription' => $community->description
                ? \Illuminate\Support\Str::limit(strip_tags($community->description), 155)
                : __('Join :community on Kolabing.', ['community' => $community->name]),
            'community' => $community,
            'memberCount' => $memberCount,
            'events' => $events,
            'isInviteOnly' => $community->join_policy === JoinPolicy::InviteOnly,
            // ?invite= pre-authorises an invite_only join; ?i= carries an
            // email invitation token.
            'inviteToken' => $request->query('invite'),
            'invitationToken' => $request->query('i'),
        ]);
    }

    /**
     * `/c/` is the community join path, but links get shared with a business's slug
     * in it (e.g. `kolabing.com/c/labtwentytwo-barcelona`). A business has no join
     * page, so those used to 301 from the marketing host to a 404 here. Its live
     * page is the public profile at `/p/`, so resolve the slug to a business or
     * community profile and send the visitor there: first the shapes `/p/` itself
     * accepts (`name-1dd66a`, `@handle`, UUID), then a plain name slug, matched
     * exactly against the profile's display name.
     */
    private function profileFor(string $slug): ?Profile
    {
        $profile = PublicProfileLink::resolve($slug);

        if ($profile !== null) {
            return $profile;
        }

        $slug = Str::lower(trim($slug));

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        // Narrow in SQL on the words in order (LIKE is portable across both
        // drivers), then require an exact slug match in PHP.
        $pattern = str_replace('-', '%', $slug).'%';
        $matches = fn (string $table, string $type) => Profile::query()
            ->where('user_type', $type)
            ->whereIn('id', fn ($query) => $query->select('profile_id')->from($table)
                ->whereRaw('LOWER(name) LIKE ?', [$pattern]))
            ->with(['businessProfile', 'communityProfile'])
            ->limit(20)
            ->get();

        return $matches('business_profiles', UserType::Business->value)
            ->concat($matches('community_profiles', UserType::Community->value))
            ->first(fn (Profile $candidate) => Str::slug((string) PublicProfileLink::displayName($candidate)) === $slug);
    }

    /**
     * The mobile app shares a Kolab as `https://kolabing.com/c/{kolabId}` (its
     * `buildOpportunityShareUri()`), so /c/ carries two kinds of id: a community
     * slug and a Kolab UUID. Community slugs are never UUIDs, so a UUID that
     * matches a Kolab is sent to that Kolab — the public page when the open-web
     * surface is on and the Kolab is publishable, otherwise the in-app detail,
     * which asks a signed-out visitor to sign in first. The query string
     * (e.g. `?apply=1`) rides along.
     */
    private function kolabUrl(Request $request, Kolab $kolab): string
    {
        $query = $request->getQueryString();
        $suffix = $query ? '?'.$query : '';

        if ((bool) config('kolabing.public_kolabs.enabled')) {
            $public = PublicKolabLink::resolve($kolab->id);

            if ($public !== null) {
                return PublicKolabLink::urlFor($public).$suffix;
            }
        }

        $locale = (string) $request->route('locale', '');
        $prefix = $locale !== '' ? '/'.$locale : '';

        return rtrim((string) config('webapp.url'), '/').$prefix.'/kolabs/'.$kolab->id.$suffix;
    }
}
