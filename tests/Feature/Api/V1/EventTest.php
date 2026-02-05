<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

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
     * Create a community profile with its extended profile record.
     */
    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | List Events (GET /api/v1/events)
    |--------------------------------------------------------------------------
    */

    public function test_list_events_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(401);
    }

    public function test_list_own_events(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        Event::factory()->count(3)->forProfile($profile)->withPartner($partner)->create();

        // Another user's events should not appear
        $other = $this->createBusinessProfile();
        Event::factory()->count(2)->forProfile($other)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.events')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events' => [
                        '*' => [
                            'id',
                            'name',
                            'partner',
                            'date',
                            'attendee_count',
                            'photos',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'total_pages',
                        'total_count',
                        'per_page',
                    ],
                ],
            ]);
    }

    public function test_list_events_for_another_profile(): void
    {
        $viewer = $this->createBusinessProfile();
        $target = $this->createCommunityProfile();
        $partner = $this->createBusinessProfile();

        Event::factory()->count(2)->forProfile($target)->withPartner($partner)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/events?profile_id={$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.events');
    }

    public function test_list_events_respects_pagination_limit(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        Event::factory()->count(5)->forProfile($profile)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.pagination.total_count', 5)
            ->assertJsonPath('data.pagination.per_page', 2);
    }

    public function test_list_events_max_limit_is_50(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events?limit=100');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    public function test_list_events_returns_empty_when_no_events(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_list_events_ordered_by_date_descending(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $oldest = Event::factory()->forProfile($profile)->withPartner($partner)->create([
            'event_date' => '2023-01-01',
        ]);
        $middle = Event::factory()->forProfile($profile)->withPartner($partner)->create([
            'event_date' => '2024-06-15',
        ]);
        $newest = Event::factory()->forProfile($profile)->withPartner($partner)->create([
            'event_date' => '2025-12-25',
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200);

        $events = $response->json('data.events');
        $this->assertEquals($newest->id, $events[0]['id']);
        $this->assertEquals($middle->id, $events[1]['id']);
        $this->assertEquals($oldest->id, $events[2]['id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Show Event (GET /api/v1/events/{event})
    |--------------------------------------------------------------------------
    */

    public function test_show_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_show_event_returns_full_details(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create();
        EventPhoto::factory()->count(2)->forEvent($event)->create();

        $response = $this->actingAs($profile)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'partner' => [
                        'id',
                        'name',
                        'profile_photo',
                        'type',
                    ],
                    'date',
                    'attendee_count',
                    'photos' => [
                        '*' => [
                            'id',
                            'url',
                            'thumbnail_url',
                        ],
                    ],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.name', $event->name);
    }

    public function test_any_authenticated_user_can_view_event(): void
    {
        $owner = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();
        $viewer = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_show_event_returns_404_for_invalid_id(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Event (POST /api/v1/events)
    |--------------------------------------------------------------------------
    */

    public function test_create_event_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/events');

        $response->assertStatus(401);
    }

    public function test_create_event_with_valid_data(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Summer Festival 2025',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 150,
                'photos' => [
                    UploadedFile::fake()->image('photo1.jpg', 800, 600),
                    UploadedFile::fake()->image('photo2.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Summer Festival 2025')
            ->assertJsonPath('data.attendee_count', 150);

        $this->assertDatabaseHas('events', [
            'profile_id' => $profile->id,
            'name' => 'Summer Festival 2025',
            'partner_id' => $partner->id,
            'partner_type' => 'community',
            'attendee_count' => 150,
        ]);

        $eventId = $response->json('data.id');
        $this->assertDatabaseCount('event_photos', 2);
        $this->assertDatabaseHas('event_photos', [
            'event_id' => $eventId,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('event_photos', [
            'event_id' => $eventId,
            'sort_order' => 1,
        ]);
    }

    public function test_create_event_validates_required_fields(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'partner_id', 'partner_type', 'date', 'attendee_count', 'photos']);
    }

    public function test_create_event_validates_name_min_length(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'AB',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 50,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_event_rejects_future_date(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Future Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2099-01-01',
                'attendee_count' => 50,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_create_event_rejects_invalid_partner_id(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => '00000000-0000-0000-0000-000000000000',
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 50,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    }

    public function test_create_event_rejects_invalid_partner_type(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => $partner->id,
                'partner_type' => 'invalid',
                'date' => '2025-06-15',
                'attendee_count' => 50,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_type']);
    }

    public function test_create_event_rejects_more_than_5_photos(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg", 800, 600);
        }

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Too Many Photos Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 50,
                'photos' => $photos,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_create_event_rejects_non_image_photos(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Invalid Photo Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 50,
                'photos' => [
                    UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_create_event_rejects_zero_attendee_count(): void
    {
        $profile = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Zero Attendees Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-06-15',
                'attendee_count' => 0,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_count']);
    }

    public function test_community_user_can_create_event(): void
    {
        $community = $this->createCommunityProfile();
        $partner = $this->createBusinessProfile();

        $response = $this->actingAs($community)
            ->postJson('/api/v1/events', [
                'name' => 'Community Created Event',
                'partner_id' => $partner->id,
                'partner_type' => 'business',
                'date' => '2025-03-10',
                'attendee_count' => 75,
                'photos' => [
                    UploadedFile::fake()->image('photo.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Community Created Event');

        $this->assertDatabaseHas('events', [
            'profile_id' => $community->id,
            'name' => 'Community Created Event',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Event (PUT /api/v1/events/{event})
    |--------------------------------------------------------------------------
    */

    public function test_update_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->putJson("/api/v1/events/{$event->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(401);
    }

    public function test_owner_can_update_event(): void
    {
        $owner = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create([
            'name' => 'Original Name',
            'attendee_count' => 100,
        ]);

        $response = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'Updated Event Name',
                'attendee_count' => 200,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Event Name')
            ->assertJsonPath('data.attendee_count', 200);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Updated Event Name',
            'attendee_count' => 200,
        ]);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $partner = $this->createBusinessProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $response = $this->actingAs($nonOwner)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_update_event_validates_fields(): void
    {
        $owner = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $response = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'AB',
                'attendee_count' => 0,
                'partner_type' => 'invalid',
                'date' => '2099-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'attendee_count', 'partner_type', 'date']);
    }

    public function test_update_event_partial_update(): void
    {
        $owner = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create([
            'name' => 'Original Name',
            'attendee_count' => 100,
            'event_date' => '2025-01-01',
        ]);

        // Only update name, other fields should remain unchanged
        $response = $this->actingAs($owner)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'Only Name Changed',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Only Name Changed')
            ->assertJsonPath('data.attendee_count', 100)
            ->assertJsonPath('data.date', '2025-01-01');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Only Name Changed',
            'attendee_count' => 100,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Event (DELETE /api/v1/events/{event})
    |--------------------------------------------------------------------------
    */

    public function test_delete_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_owner_can_delete_event(): void
    {
        $owner = $this->createBusinessProfile();
        $partner = $this->createCommunityProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();
        $photo = EventPhoto::factory()->forEvent($event)->create();

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseMissing('event_photos', ['id' => $photo->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $partner = $this->createBusinessProfile();

        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $response = $this->actingAs($nonOwner)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_delete_event_returns_404_for_invalid_id(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)
            ->deleteJson('/api/v1/events/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
