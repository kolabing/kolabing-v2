<?php

declare(strict_types=1);

namespace Tests\Feature\Incentives;

use App\Enums\CollaborationStatus;
use App\Models\Application;
use App\Models\City;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Support\Carbon;

trait BuildsIncentiveFixtures
{
    /** @var array<int, Profile> */
    private array $attendeePool = [];

    private function city(string $name = 'Barcelona'): City
    {
        return City::query()->firstOrCreate(['name' => $name], ['country' => 'Spain']);
    }

    private function organiser(?City $city = null, ?string $name = null, int $members = 0): Profile
    {
        $name ??= 'Club '.fake()->unique()->numberBetween(1000, 9999);
        $profile = Profile::factory()->community()->create(['name' => $name]);
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'city_id' => ($city ?? $this->city())->id,
        ]);

        if ($members > 0) {
            $community = Community::factory()->forOwner($profile)->create();
            foreach (range(1, $members) as $i) {
                CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $this->attendee($i)->id]);
            }
        }

        return $profile;
    }

    private function venue(): Profile
    {
        return Profile::factory()->business()->create();
    }

    /** The i-th attendee of a shared pool (check-ins are unique per event, not globally). */
    private function attendee(int $i): Profile
    {
        return $this->attendeePool[$i] ??= Profile::factory()->attendee()->create();
    }

    private function checkIns(Event $event, int $count, Carbon|string $at, int $offset = 0): void
    {
        foreach (range(1, $count) as $i) {
            EventCheckin::factory()->create([
                'event_id' => $event->id,
                'profile_id' => $this->attendee($i + $offset)->id,
                'checked_in_at' => Carbon::parse($at),
            ]);
        }
    }

    /**
     * A kolab completed in the app at $completedAt with $checkins verified
     * check-ins at its event.
     */
    private function kolab(Profile $organiser, Carbon|string $completedAt, int $checkins = 0, ?Profile $venue = null): Collaboration
    {
        $venue ??= $this->venue();
        $kolab = Kolab::factory()->published()->create([
            'creator_profile_id' => $venue->id,
            'offer_headline' => 'Run with us',
        ]);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $organiser->id,
        ]);
        $collaboration = Collaboration::factory()->create([
            'application_id' => $application->id,
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $venue->id,
            'applicant_profile_id' => $organiser->id,
            'status' => CollaborationStatus::Completed,
            'completed_at' => Carbon::parse($completedAt),
        ]);

        if ($checkins > 0) {
            $event = Event::factory()->create([
                'profile_id' => $organiser->id,
                'collaboration_id' => $collaboration->id,
                'event_date' => Carbon::parse($completedAt)->toDateString(),
            ]);
            $this->checkIns($event, $checkins, $completedAt);
        }

        return $collaboration;
    }

    /** League points only: $n check-ins (× 2) at an organiser's own event. */
    private function eventWithCheckins(Profile $organiser, int $n, Carbon|string $at): Event
    {
        $event = Event::factory()->create([
            'profile_id' => $organiser->id,
            'event_date' => Carbon::parse($at)->toDateString(),
        ]);
        $this->checkIns($event, $n, $at);

        return $event;
    }

    private function venueReview(Profile $organiser, int $rating, Carbon|string $at): void
    {
        $venue = $this->venue();
        $collaboration = $this->kolab($organiser, Carbon::parse($at)->subYear(), 0, $venue);
        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $venue->id,
            'reviewed_profile_id' => $organiser->id,
            'reviewer_role' => 'creator',
            'rating' => $rating,
            'created_at' => Carbon::parse($at),
        ]);
    }
}
