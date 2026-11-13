<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Web side of a Kolabing team message link (SERR-1344).
 *
 * `/o/{offer}` and `/claim/{token}` are published as app links
 * (config/webapp.php `app_links.paths`), so a phone with the app opens the card
 * in the app and never gets here. A phone without the app lands on this page,
 * which sends it to the store for its platform. After install and sign-in the
 * app finds the offer in the "Kolabing team" thread (or asks to claim), so the
 * user ends up on the card.
 *
 * The page names nothing about the offer: the id or token in the URL is not a
 * login, and the page only holds store links.
 */
class TeamOfferLinkController extends Controller
{
    public function offer(Request $request, string $offer): Response
    {
        return $this->page($request, 'offer');
    }

    public function claim(Request $request, string $token): Response
    {
        return $this->page($request, 'claim');
    }

    private function page(Request $request, string $kind): Response
    {
        $agent = strtolower((string) $request->userAgent());
        $ios = str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ipod');
        $android = str_contains($agent, 'android');

        $iosUrl = config('webapp.app_store_url') ?: null;
        $androidUrl = config('webapp.play_store_url') ?: null;

        // A phone gets its own store; anything else sees both.
        $stores = match (true) {
            $ios && $iosUrl !== null => ['App Store' => $iosUrl],
            $android && $androidUrl !== null => ['Google Play' => $androidUrl],
            default => array_filter(['App Store' => $iosUrl, 'Google Play' => $androidUrl]),
        };

        return response()->view('webapp.team-offer-link', ['kind' => $kind, 'stores' => $stores])
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
