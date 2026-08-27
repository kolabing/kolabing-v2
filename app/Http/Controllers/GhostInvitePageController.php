<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Encounter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Where a ghost invite lands for someone who does not have the app (#246).
 *
 * This page exists because **a Universal Link cannot do this job alone.** On a
 * phone that has the app, `https://app.kolabing.com/i/{code}` is handed
 * straight to it and this page is never seen. On a phone that does not, the
 * token is lost the moment the person walks through the App Store — Universal
 * Links carry no state across an install, and Firebase Dynamic Links shut down
 * in 2025.
 *
 * Since the entire point of a ghost invite is a person **without** the app,
 * this page's real job is one thing: show the code big enough to remember, so
 * it can be typed on the other side of an install.
 *
 * Deliberately open to anyone with the code and deliberately thin. It names the
 * inviter and the event, and nothing else — an invite link must not become a
 * way to read a stranger's evening.
 */
class GhostInvitePageController extends Controller
{
    public function show(Request $request): View
    {
        $code = strtoupper((string) $request->route('code'));

        /** @var Encounter|null $invite */
        $invite = Encounter::query()
            ->where('ghost_claim_token', $code)
            ->with(['profile', 'event', 'community', 'challenge'])
            ->first();

        // An expired or already-claimed code still renders, with its own state
        // rather than a 404. Someone tapping a link a fortnight late deserves
        // to be told what happened rather than shown a dead end.
        $status = match (true) {
            $invite === null => 'unknown',
            $invite->claimed_at !== null => 'claimed',
            $invite->expires_at !== null && $invite->expires_at->isPast() => 'expired',
            default => 'open',
        };

        return view('webapp.invite', [
            'code' => $code,
            'invite' => $invite,
            'status' => $status,
            'inviterName' => $invite?->profile?->name,
            'eventName' => $invite?->event?->name,
            'communityName' => $invite?->community?->name,
            'points' => $invite?->pending_points ?? 0,
            'appStoreUrl' => config('webapp.app_store_url'),
            'playStoreUrl' => config('webapp.play_store_url'),
        ]);
    }
}
