<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EventVisibility;
use App\Models\City;
use App\Models\Event;
use App\Services\EventDiscoveryService;
use App\Support\PublicEventLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * What's on, for anyone — no account, no app.
 *
 * This is the attendee's front door. Everything an attendee can join is an event,
 * and `EventVisibility::Public` is the flag the schema already carried for exactly
 * this ("anyone; surfaces in city discover"). Members-only and tier-gated events are
 * never listed or resolvable here: a URL must not become a way to read a private
 * community's calendar.
 *
 * Details are open and the wall sits at the action. Signing up needs an account, and
 * that hand-off goes to the panel, because the token that proves who you are lives
 * in the app host's storage and this page cannot see it.
 */
class PublicEventPageController extends Controller
{
    /** Events per page. Enough to fill a screen without a wall of cards. */
    private const PER_PAGE = 24;

    public function __construct(private readonly EventDiscoveryService $discovery) {}

    public function index(Request $request): View
    {
        $cityId = (string) $request->query('city', '');

        /*
         * Discovery is not reimplemented here. EventDiscoveryService already owns the
         * public-visibility filter, the ordering, and — the part that would have gone
         * wrong — the "effective city" rule: an event's city is events.city_id when
         * set, else the host community's city. Filtering on events.city_id alone
         * would silently drop every event that inherits its city.
         */
        $events = $this->discovery
            ->discover(null, null, 50.0, self::PER_PAGE, array_filter([
                'city_id' => $cityId !== '' ? $cityId : null,
                'date' => 'upcoming',
            ]))
            ->withQueryString();

        return view('pages.events', [
            'events' => $events,
            'cities' => $this->citiesWithPublicEvents(),
            'selectedCity' => $cityId,
            'appUrl' => rtrim((string) config('webapp.url'), '/'),
        ]);
    }

    public function show(string $slug): View
    {
        $event = PublicEventLink::resolve($slug);

        abort_if($event === null, 404);

        return view('pages.event', [
            'event' => $event,
            'canonicalUrl' => PublicEventLink::urlFor($event),
            'appUrl' => rtrim((string) config('webapp.url'), '/'),
            // Nearby in time rather than nearby in space: someone reading about a run
            // on Sunday is usually deciding between weekends, not cities.
            'alsoOn' => $this->discovery
                ->discover(null, null, 50.0, 4, array_filter([
                    'city_id' => $event->city_id,
                    'date' => 'upcoming',
                ]))
                ->getCollection()
                ->reject(fn (Event $other): bool => $other->id === $event->id)
                ->take(3)
                ->values(),
        ]);
    }

    /**
     * Only cities that actually have something on. A filter listing empty cities
     * sends people to empty pages.
     *
     * @return \Illuminate\Support\Collection<int, City>
     */
    private function citiesWithPublicEvents(): \Illuminate\Support\Collection
    {
        return City::query()
            ->whereIn('id', Event::query()
                ->where('visibility', EventVisibility::Public)
                ->whereNotNull('city_id')
                ->where(fn ($query) => $query
                    ->where('starts_at', '>=', now())
                    ->orWhere('event_date', '>=', now()->toDateString()))
                ->select('city_id'))
            ->orderBy('name')
            ->get();
    }
}
