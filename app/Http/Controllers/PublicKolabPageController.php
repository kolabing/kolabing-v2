<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\IntentType;
use App\Models\Kolab;
use App\Services\PublicKolabFeedService;
use App\Support\PublicKolabLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The marketplace, seen from the open web — `kolabing.com/kolabs`.
 *
 * Deliberately a separate surface from `/events`. An event is something you turn up to
 * (audience: attendees); a Kolab is a partnership offer (audience: businesses and
 * communities). Merging them into one feed would put two unrelated intents in one list
 * and leave each page unable to answer a single search query well.
 *
 * What is shown and what is withheld: the Kolab's substance is public — ROLES §2.5 is
 * explicit that a free business "sees every Kolab's details", so the terms were never
 * the paywalled part. The *poster's* identity is the part that is conditional, and
 * {@see \App\Support\PublicKolabPoster} owns that rule.
 *
 * Applying needs an account, and that hand-off goes to the panel: the token that proves
 * who you are lives in the app host's storage and this origin cannot read it.
 */
class PublicKolabPageController extends Controller
{
    public function __construct(private readonly PublicKolabFeedService $feed) {}

    public function index(Request $request): View
    {
        $city = trim((string) $request->query('city', ''));
        $intent = trim((string) $request->query('intent', ''));

        // An unknown ?intent= is dropped rather than 404'd: a stale or hand-edited link
        // should show the whole marketplace, not an error.
        if (! in_array($intent, IntentType::values(), true)) {
            $intent = '';
        }

        return view('pages.kolabs', [
            'kolabs' => $this->feed
                ->paginate(['city' => $city, 'intent' => $intent])
                ->withQueryString(),
            'cities' => $this->feed->cities(),
            'selectedCity' => $city,
            'selectedIntent' => $intent,
            'appUrl' => rtrim((string) config('webapp.url'), '/'),
        ]);
    }

    public function show(string $slug): View
    {
        $kolab = PublicKolabLink::resolve($slug);

        abort_if($kolab === null, 404);

        return view('pages.kolab', [
            'kolab' => $kolab,
            'canonicalUrl' => PublicKolabLink::urlFor($kolab),
            'appUrl' => rtrim((string) config('webapp.url'), '/'),
            'alsoOpen' => $this->feed
                ->paginate(['city' => $kolab->preferred_city], 4)
                ->getCollection()
                ->reject(fn (Kolab $other): bool => $other->id === $kolab->id)
                ->take(3)
                ->values(),
        ]);
    }
}
