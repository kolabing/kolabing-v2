<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Create a business profile with its extended profile record.
     */
    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * Create an attendee profile with its extended profile record.
     */
    private function createAttendeeProfile(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | Generate QR Token (POST /api/v1/events/{event}/generate-qr)
    |--------------------------------------------------------------------------
    */

    public function test_organizer_can_generate_qr_token(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/generate-qr");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'checkin_token',
                ],
            ]);

        $token = $response->json('data.checkin_token');
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'checkin_token' => $token,
            'is_active' => true,
        ]);
    }

    public function test_non_owner_cannot_generate_qr_token(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($nonOwner)
            ->postJson("/api/v1/events/{$event->id}/generate-qr");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Check In (POST /api/v1/checkin)
    |--------------------------------------------------------------------------
    */

    public function test_attendee_can_checkin_with_valid_token(): void
    {
        $owner = $this->createBusinessProfile();
        $attendee = $this->createAttendeeProfile();

        $event = Event::factory()->forProfile($owner)->create([
            'checkin_token' => Str::random(64),
            'is_active' => true,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', [
                'token' => $event->checkin_token,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Checked in successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'event_id',
                    'profile_id',
                    'checked_in_at',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('event_checkins', [
            'event_id' => $event->id,
            'profile_id' => $attendee->id,
        ]);

        // Verify total_events_attended was incremented
        $attendee->refresh();
        $this->assertEquals(1, $attendee->attendeeProfile->total_events_attended);
    }

    public function test_attendee_cannot_checkin_twice(): void
    {
        $owner = $this->createBusinessProfile();
        $attendee = $this->createAttendeeProfile();

        $event = Event::factory()->forProfile($owner)->create([
            'checkin_token' => Str::random(64),
            'is_active' => true,
        ]);

        // First check-in
        EventCheckin::factory()->forEvent($event)->forProfile($attendee)->create();

        // Second check-in attempt
        $response = $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', [
                'token' => $event->checkin_token,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You have already checked in to this event.');
    }

    public function test_checkin_fails_with_invalid_token(): void
    {
        $attendee = $this->createAttendeeProfile();

        $response = $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', [
                'token' => 'invalid-token-that-does-not-exist',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid check-in token.');
    }

    public function test_checkin_fails_when_event_not_active(): void
    {
        $owner = $this->createBusinessProfile();
        $attendee = $this->createAttendeeProfile();

        $event = Event::factory()->forProfile($owner)->create([
            'checkin_token' => Str::random(64),
            'is_active' => false,
        ]);

        $response = $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', [
                'token' => $event->checkin_token,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This event is not currently accepting check-ins.');
    }

    /*
    |--------------------------------------------------------------------------
    | List Checkins (GET /api/v1/events/{event}/checkins)
    |--------------------------------------------------------------------------
    */

    public function test_list_checkins_returns_paginated_results(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        // Create 3 checkins for this event
        $attendees = [];
        for ($i = 0; $i < 3; $i++) {
            $attendee = $this->createAttendeeProfile();
            $attendees[] = $attendee;
            EventCheckin::factory()->forEvent($event)->forProfile($attendee)->create();
        }

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/events/{$event->id}/checkins");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.checkins')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'checkins' => [
                        '*' => [
                            'id',
                            'event_id',
                            'profile_id',
                            'checked_in_at',
                            'created_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'total_pages',
                        'total_count',
                        'per_page',
                    ],
                ],
            ])
            ->assertJsonPath('data.pagination.total_count', 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_user_gets_401_for_generate_qr(): void
    {
        $event = Event::factory()->create();

        $response = $this->postJson("/api/v1/events/{$event->id}/generate-qr");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_for_checkin(): void
    {
        $response = $this->postJson('/api/v1/checkin', [
            'token' => 'some-token',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_gets_401_for_list_checkins(): void
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}/checkins");

        $response->assertStatus(401);
    }
}
