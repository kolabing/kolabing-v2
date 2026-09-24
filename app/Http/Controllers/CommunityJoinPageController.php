<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\Event;
use App\Models\Kolab;
use App\Support\PublicKolabLink;
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

            abort_if($kolab === null, 404);

            return redirect()->away($this->kolabUrl($request, $kolab));
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
