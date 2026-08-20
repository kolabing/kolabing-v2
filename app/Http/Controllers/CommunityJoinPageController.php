<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public landing page for a community's shareable join link.
 *
 * config('communities.invite_base_url') has always pointed at /c/{slug} and
 * Community::inviteUrl() has always emitted it, but the route never existed —
 * so every invite link ever shared 404'd. This is that route.
 */
class CommunityJoinPageController extends Controller
{
    public function show(Request $request): View
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
            ->firstOrFail();

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
}
