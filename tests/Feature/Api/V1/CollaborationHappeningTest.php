<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\EventVisibility;
use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Event;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\CollaborationHappeningService;
use App\Services\EventSignupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A confirmed Kolab is the thing people attend.
 *
 * There is no separate events product: the Kolab is agreed, that agreement is a
 * `collaborations` row, and the happening is how the public meets it. Before this,
 * the chain broke in two silent places — nothing created a happening until someone
 * pressed "generate QR", and what it created was `members`-visible with no
 * `community_id`, which is exactly the combination sign-ups refuse. Production had
 * 16 collaborations and not one attendable happening.
 */
class CollaborationHappeningTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function kolab(Profile $creator, array $overrides = []): Kolab
    {
        return Kolab::factory()->create(array_merge([
            'creator_profile_id' => $creator->id,
            'status' => KolabStatus::Published,
            'published_at' => now(),
            'title' => 'Sunday Run and Brunch',
            'preferred_city' => 'Barcelona',
            'venue_address' => 'Carrer de la Marina 1',
            'selected_time' => '10:15 – 12:00',
            'capacity' => 40,
        ], $overrides));
    }

    private function collaboration(array $overrides = []): Collaboration
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $kolab = $this->kolab($business);

        return Collaboration::factory()->create(array_merge([
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'status' => CollaborationStatus::Scheduled,
            'scheduled_date' => now()->addDays(10)->toDateString(),
        ], $overrides));
    }

    public function test_a_scheduled_collaboration_becomes_a_public_happening(): void
    {
        $collaboration = $this->collaboration();

        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration);

        $this->assertNotNull($event);
        $this->assertSame('Sunday Run and Brunch', $event->name);
        $this->assertSame(EventVisibility::Public, $event->visibility);
        $this->assertSame($collaboration->id, $event->collaboration_id);
        $this->assertSame(40, $event->capacity);
        $this->assertSame('Carrer de la Marina 1', $event->address);
        // Both directions of the link are read, so both are written.
        $this->assertSame($event->id, $collaboration->refresh()->event_id);
    }

    /**
     * The time of day lives on the Kolab as the free text the two sides agreed
     * ("10:15 – 12:00"); `scheduled_date` is only a date. Midnight would read as
     * missing data, so the agreed hour is used when it can be found.
     */
    public function test_the_agreed_time_of_day_is_used_not_midnight(): void
    {
        $collaboration = $this->collaboration();

        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration);

        $this->assertSame('10:15', $event->starts_at->format('H:i'));
    }

    public function test_a_kolab_with_no_agreed_time_still_gets_a_civilised_hour(): void
    {
        $collaboration = $this->collaboration();
        $collaboration->kolab->update(['selected_time' => null]);

        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration->refresh());

        $this->assertSame('19:00', $event->starts_at->format('H:i'));
    }

    /** Nobody can be told when to turn up, so it must not appear in "what's on". */
    public function test_a_collaboration_with_no_date_is_not_made_public(): void
    {
        $collaboration = $this->collaboration(['scheduled_date' => null]);

        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration);

        $this->assertNotNull($event, 'The door still needs an event.');
        $this->assertSame(EventVisibility::Members, $event->visibility);
    }

    public function test_a_cancelled_collaboration_has_no_happening(): void
    {
        $collaboration = $this->collaboration(['status' => CollaborationStatus::Cancelled]);

        $this->assertNull(app(CollaborationHappeningService::class)->ensureFor($collaboration));
    }

    /**
     * Re-running must never mint a second happening: the door token and everyone's
     * tickets hang off the first one.
     */
    public function test_running_it_again_updates_in_place(): void
    {
        $collaboration = $this->collaboration();
        $service = app(CollaborationHappeningService::class);

        $first = $service->ensureFor($collaboration);
        $collaboration->update(['scheduled_date' => now()->addDays(20)->toDateString()]);
        $second = $service->ensureFor($collaboration->refresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Event::query()->where('collaboration_id', $collaboration->id)->count());
        $this->assertSame(
            now()->addDays(20)->toDateString(),
            $second->starts_at->toDateString()
        );
    }

    /**
     * The partner's name comes off the extended profile. The inline version this
     * replaced read `$profile->display_name`, which does not exist on Profile, so
     * every generated event was hosted with the literal word "Partner".
     */
    public function test_the_partner_is_named_properly(): void
    {
        $collaboration = $this->collaboration();
        \App\Models\CommunityProfile::factory()->create([
            'profile_id' => $collaboration->applicant_profile_id,
            'name' => 'Barcelona Nomad Run',
        ]);

        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration->refresh());

        $this->assertSame('Barcelona Nomad Run', $event->partner_name);
    }

    /** The point of all of it: an attendee can take a seat at a confirmed Kolab. */
    public function test_an_attendee_can_join_a_confirmed_kolabs_happening(): void
    {
        Mail::fake();
        $collaboration = $this->collaboration();
        $event = app(CollaborationHappeningService::class)->ensureFor($collaboration);
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertOk();

        $signup = app(EventSignupService::class)->signupFor($event, $attendee);
        $this->assertNotNull($signup);
        $this->assertNotNull($signup->ticket_code, 'Taking a seat should hand over a ticket.');
    }

    /** Accepting is the moment the Kolab becomes a thing that happens. */
    public function test_accepting_an_application_creates_the_happening(): void
    {
        $business = Profile::factory()->business()->create();
        // Accepting is subscription-gated for a business (ROLES §2.7); that gate is
        // not what this test is about, so it is satisfied rather than bypassed.
        \App\Models\BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        $community = Profile::factory()->community()->create();
        $kolab = $this->kolab($business);

        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'status' => ApplicationStatus::Pending,
        ]);

        app(\App\Services\ApplicationService::class)->accept($application, [
            'scheduled_date' => now()->addDays(14)->toDateString(),
        ]);

        $collaboration = Collaboration::query()->where('application_id', $application->id)->firstOrFail();

        $this->assertNotNull($collaboration->event_id, 'Acceptance should create the happening.');
        $this->assertSame(
            EventVisibility::Public,
            Event::query()->findOrFail($collaboration->event_id)->visibility
        );
    }
}
