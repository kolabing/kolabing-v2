<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\EventVisibility;
use App\Models\City;
use App\Models\Community;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use App\Support\PublicEventLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * "What's on" — the attendee's front door, open to anyone.
 *
 * The assertions that matter most are the negative ones. This is an unauthenticated,
 * indexable surface, so a members-only or tier-gated event must be impossible to
 * list *and* impossible to reach by guessing a URL. A private community's calendar
 * leaking here would not break the page; it would just quietly be public.
 */
class PublicEventPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function communityHost(string $name = 'Barcelona Runners'): Profile
    {
        $host = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $host->id, 'name' => $name]);

        return $host->fresh();
    }

    private function event(array $attributes = []): Event
    {
        $host = $attributes['host'] ?? $this->communityHost();
        unset($attributes['host']);

        $community = Community::factory()->create([
            'owner_profile_id' => $host->id,
            'name' => 'Barcelona Runners',
        ]);

        return Event::factory()->create(array_merge([
            'profile_id' => $host->id,
            'community_id' => $community->id,
            'name' => 'Sunday beach run',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'visibility' => EventVisibility::Public,
            'location' => 'Platja de la Barceloneta',
        ], $attributes));
    }

    public function test_the_feed_lists_upcoming_public_events(): void
    {
        $this->event();

        $this->get('http://kolabing.com/events')
            ->assertOk()
            ->assertSee('Sunday beach run')
            ->assertSee('Barcelona Runners')
            // Blade escapes the apostrophe, so match the part that survives.
            ->assertSee('on near you');
    }

    public function test_members_only_and_tier_gated_events_are_never_listed(): void
    {
        $this->event(['name' => 'Public morning run']);
        $this->event(['name' => 'Members only supper', 'visibility' => EventVisibility::Members]);
        $this->event(['name' => 'Gold tier tasting', 'visibility' => EventVisibility::Tier]);

        $this->get('http://kolabing.com/events')
            ->assertOk()
            ->assertSee('Public morning run')
            ->assertDontSee('Members only supper')
            ->assertDontSee('Gold tier tasting');
    }

    public function test_a_private_event_cannot_be_reached_by_guessing_its_url(): void
    {
        // The stronger half of the rule: not listed is not the same as not readable.
        $private = $this->event(['name' => 'Members only supper', 'visibility' => EventVisibility::Members]);

        $this->get('http://kolabing.com/events/'.PublicEventLink::slugFor($private))->assertNotFound();
        $this->get('http://kolabing.com/events/'.$private->id)->assertNotFound();
    }

    public function test_past_events_drop_off_the_feed(): void
    {
        $this->event(['name' => 'Last summer party', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subMonth()->addHours(2)]);

        $this->get('http://kolabing.com/events')->assertOk()->assertDontSee('Last summer party');
    }

    public function test_the_event_page_shows_the_detail_and_walls_only_the_action(): void
    {
        $event = $this->event();

        $response = $this->get('http://kolabing.com/events/'.PublicEventLink::slugFor($event));

        $response->assertOk()
            // Details are open — someone deciding whether to turn up needs them.
            ->assertSee('Sunday beach run')
            ->assertSee('Platja de la Barceloneta')
            ->assertSee('Barcelona Runners')
            ->assertSee('Free')
            // …and the wall is at the action, handed off to the panel with the intent.
            ->assertSee("I'm going", false)
            ->assertSee(rtrim(config('webapp.url'), '/').'/events/'.$event->id.'?rsvp=1', false);
    }

    public function test_the_event_page_emits_valid_event_schema(): void
    {
        $event = $this->event();

        $response = $this->get('http://kolabing.com/events/'.PublicEventLink::slugFor($event))->assertOk();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $matches);
        $blocks = array_map(fn (string $raw): array => json_decode(trim($raw), true), $matches[1]);

        $eventSchema = collect($blocks)->firstWhere('@type', 'Event');

        $this->assertNotNull($eventSchema, 'no Event schema on the page');
        $this->assertSame('https://schema.org', $eventSchema['@context']);
        $this->assertSame('Sunday beach run', $eventSchema['name']);
        // Attendees never pay, so this is a fact rather than a marketing claim.
        $this->assertTrue($eventSchema['isAccessibleForFree']);
        $this->assertSame('Barcelona Runners', $eventSchema['organizer']['name']);
    }

    public function test_an_old_link_still_resolves_after_a_rename(): void
    {
        $event = $this->event();
        $oldSlug = PublicEventLink::slugFor($event);

        $event->update(['name' => 'Sunday sunrise run']);

        $this->get('http://kolabing.com/events/'.$oldSlug)->assertOk()->assertSee('Sunday sunrise run');
    }

    public function test_the_city_filter_only_offers_cities_with_something_on(): void
    {
        $withEvent = City::factory()->create(['name' => 'Barcelona']);
        $empty = City::factory()->create(['name' => 'Nowhere']);

        $this->event(['city_id' => $withEvent->id]);

        $this->get('http://kolabing.com/events')
            ->assertOk()
            ->assertSee('Barcelona')
            ->assertDontSee('Nowhere');
    }

    public function test_the_sitemap_lists_public_events_and_hides_private_ones(): void
    {
        $public = $this->event(['name' => 'Public morning run']);
        $private = $this->event(['name' => 'Members only supper', 'visibility' => EventVisibility::Members]);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee(PublicEventLink::urlFor($public), false)
            ->assertDontSee(PublicEventLink::urlFor($private), false);
    }

    public function test_an_unknown_event_slug_is_a_404(): void
    {
        $this->get('http://kolabing.com/events/nothing-abc123')->assertNotFound();
    }
}
