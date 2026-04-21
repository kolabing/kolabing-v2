<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabCreateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_user_can_create_community_seeking_kolab(): void
    {
        $community = Profile::factory()->community()->create();

        $payload = [
            'intent_type' => 'community_seeking',
            'title' => 'Looking for a venue for yoga meetup',
            'description' => 'We are a yoga community seeking a venue for our weekly meetup in Sevilla.',
            'preferred_city' => 'Sevilla',
            'needs' => ['venue', 'food_drink'],
            'community_types' => ['fitness', 'wellness'],
            'community_size' => 500,
            'typical_attendance' => 50,
            'offers_in_return' => ['social_media', 'event_activation'],
            'venue_preference' => 'business_provides',
        ];

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'community_seeking')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', $payload['title'])
            ->assertJsonPath('data.description', $payload['description'])
            ->assertJsonPath('data.preferred_city', 'Sevilla')
            ->assertJsonPath('data.community_size', 500)
            ->assertJsonPath('data.typical_attendance', 50)
            ->assertJsonPath('data.venue_preference', 'business_provides');

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $community->id,
            'intent_type' => 'community_seeking',
            'status' => 'draft',
            'title' => $payload['title'],
            'preferred_city' => 'Sevilla',
            'community_size' => 500,
            'typical_attendance' => 50,
            'venue_preference' => 'business_provides',
        ]);
    }

    public function test_business_user_can_create_venue_promotion_kolab(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'primary_venue' => [
                'name' => 'Sky Lounge Malaga',
                'venue_type' => 'bar_lounge',
                'capacity' => 150,
                'place_id' => 'sky-lounge-place-id',
                'formatted_address' => 'Calle Larios 12, Malaga',
                'city' => 'Malaga',
                'country' => 'Spain',
                'latitude' => 36.7213,
                'longitude' => -4.4214,
                'photos' => ['https://example.com/sky-lounge.jpg'],
            ],
        ]);

        $payload = [
            'intent_type' => 'venue_promotion',
            'title' => 'Rooftop bar available for community events',
            'description' => 'Beautiful rooftop bar in the heart of Malaga available for community events.',
            'preferred_city' => 'Malaga',
            'media' => [
                [
                    'url' => 'https://example.com/promo-photo.jpg',
                    'type' => 'photo',
                    'sort_order' => 0,
                ],
            ],
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->format('Y-m-d'),
            'offering' => ['venue', 'food_drink', 'discount'],
        ];

        $response = $this->actingAs($business)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'venue_promotion')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.venue_name', 'Sky Lounge Malaga')
            ->assertJsonPath('data.venue_type', 'bar_lounge')
            ->assertJsonPath('data.capacity', 150)
            ->assertJsonPath('data.venue_address', 'Calle Larios 12, Malaga');

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $business->id,
            'intent_type' => 'venue_promotion',
            'status' => 'draft',
            'venue_name' => 'Sky Lounge Malaga',
            'venue_type' => 'bar_lounge',
            'capacity' => 150,
        ]);
    }

    public function test_business_user_can_create_product_promotion_kolab(): void
    {
        $business = Profile::factory()->business()->create();

        $payload = [
            'intent_type' => 'product_promotion',
            'title' => 'Organic cold-pressed juice for fitness communities',
            'description' => 'We produce organic cold-pressed juices and want to partner with fitness communities.',
            'preferred_city' => 'Granada',
            'product_name' => 'VitaPress Organic Juice',
            'product_type' => 'beverage',
            'offering' => ['products', 'discount'],
        ];

        $response = $this->actingAs($business)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'product_promotion')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.product_name', 'VitaPress Organic Juice')
            ->assertJsonPath('data.product_type', 'beverage');

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $business->id,
            'intent_type' => 'product_promotion',
            'status' => 'draft',
            'product_name' => 'VitaPress Organic Juice',
            'product_type' => 'beverage',
        ]);
    }

    public function test_create_kolab_requires_intent_type(): void
    {
        $profile = Profile::factory()->community()->create();

        $payload = [
            'title' => 'Some title',
            'description' => 'Some description',
            'preferred_city' => 'Sevilla',
        ];

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['intent_type']);
    }

    public function test_community_seeking_requires_community_fields(): void
    {
        $profile = Profile::factory()->community()->create();

        $payload = [
            'intent_type' => 'community_seeking',
            'title' => 'Looking for a venue',
            'description' => 'We need a venue for our meetup.',
            'preferred_city' => 'Sevilla',
            // Missing: needs, community_types, community_size, typical_attendance, offers_in_return, venue_preference
        ];

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'needs',
                'community_types',
                'community_size',
                'typical_attendance',
                'offers_in_return',
                'venue_preference',
            ]);
    }

    public function test_venue_promotion_requires_venue_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'primary_venue' => [
                'name' => 'Saved Venue',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Saved Address 1',
                'city' => 'Malaga',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $payload = [
            'intent_type' => 'venue_promotion',
            'title' => 'Venue available',
            'description' => 'Our venue is available for events.',
            'preferred_city' => 'Malaga',
            // Missing: media and offering
        ];

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'media',
                'offering',
            ]);
    }

    public function test_venue_promotion_derives_primary_venue_fields_from_business_profile(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'primary_venue' => [
                'name' => 'Sol Studio Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 120,
                'place_id' => 'google-place-id',
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'latitude' => 41.3874,
                'longitude' => 2.1686,
                'photos' => ['https://example.com/venue-photo.jpg'],
            ],
        ]);

        $payload = [
            'intent_type' => 'venue_promotion',
            'title' => 'Sunset rooftop social for local creators',
            'description' => 'We want to host an evening meetup for lifestyle and food creators.',
            'preferred_city' => 'Barcelona',
            'media' => [
                [
                    'url' => 'https://example.com/kolab-photo.jpg',
                    'type' => 'photo',
                    'sort_order' => 0,
                ],
            ],
            'offering' => ['free_drinks', 'venue_space'],
            'seeking_communities' => ['food', 'lifestyle'],
            'min_community_size' => 50,
            'expects' => ['social_media'],
            'availability_mode' => 'specific_dates',
            'availability_start' => now()->addDays(10)->format('Y-m-d'),
            'availability_end' => now()->addDays(12)->format('Y-m-d'),
        ];

        $response = $this->actingAs($business)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.venue_name', 'Sol Studio Rooftop')
            ->assertJsonPath('data.venue_type', 'cafe')
            ->assertJsonPath('data.capacity', 120)
            ->assertJsonPath('data.venue_address', 'Carrer de Mallorca 1, Barcelona');

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $business->id,
            'venue_name' => 'Sol Studio Rooftop',
            'venue_type' => 'cafe',
            'capacity' => 120,
            'venue_address' => 'Carrer de Mallorca 1, Barcelona',
        ]);
    }

    public function test_venue_promotion_requires_saved_primary_venue(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'primary_venue' => null,
        ]);

        $payload = [
            'intent_type' => 'venue_promotion',
            'title' => 'Venue available',
            'description' => 'Our venue is available for events.',
            'preferred_city' => 'Malaga',
            'media' => [
                [
                    'url' => 'https://example.com/promo-photo.jpg',
                    'type' => 'photo',
                    'sort_order' => 0,
                ],
            ],
            'offering' => ['venue_space'],
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->format('Y-m-d'),
        ];

        $response = $this->actingAs($business)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['primary_venue']);
    }

    public function test_product_promotion_requires_product_fields(): void
    {
        $profile = Profile::factory()->business()->create();

        $payload = [
            'intent_type' => 'product_promotion',
            'title' => 'Product for communities',
            'description' => 'We have a great product for communities.',
            'preferred_city' => 'Cadiz',
            // Missing: product_name, product_type, offering
        ];

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'product_name',
                'product_type',
                'offering',
            ]);
    }

    public function test_unauthenticated_user_cannot_create_kolab(): void
    {
        $payload = [
            'intent_type' => 'community_seeking',
            'title' => 'Some title',
            'description' => 'Some description',
            'preferred_city' => 'Sevilla',
            'needs' => ['venue'],
            'community_types' => ['fitness'],
            'community_size' => 100,
            'typical_attendance' => 20,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
        ];

        $response = $this->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(401);
    }

    public function test_kolab_creates_with_availability_fields(): void
    {
        $community = Profile::factory()->community()->create();

        $startDate = now()->addWeek()->format('Y-m-d');
        $endDate = now()->addMonth()->format('Y-m-d');

        $payload = [
            'intent_type' => 'community_seeking',
            'title' => 'Weekly running meetup needs sponsors',
            'description' => 'Our running community meets every Saturday morning.',
            'preferred_city' => 'Cordoba',
            'needs' => ['sponsor', 'products'],
            'community_types' => ['fitness'],
            'community_size' => 200,
            'typical_attendance' => 40,
            'offers_in_return' => ['social_media', 'community_reach'],
            'venue_preference' => 'community_provides',
            'availability_mode' => 'recurring',
            'availability_start' => $startDate,
            'availability_end' => $endDate,
            'selected_time' => '09:00',
            'recurring_days' => [6, 7],
        ];

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.availability_mode', 'recurring')
            ->assertJsonPath('data.availability_start', $startDate)
            ->assertJsonPath('data.availability_end', $endDate)
            ->assertJsonPath('data.selected_time', '09:00')
            ->assertJsonPath('data.recurring_days', [6, 7]);

        $kolab = Kolab::query()
            ->where('creator_profile_id', $community->id)
            ->first();

        $this->assertNotNull($kolab);
        $this->assertSame('recurring', $kolab->availability_mode);
        $this->assertSame($startDate, $kolab->availability_start->format('Y-m-d'));
        $this->assertSame($endDate, $kolab->availability_end->format('Y-m-d'));
        $this->assertSame('09:00', $kolab->selected_time);
        $this->assertEquals([6, 7], $kolab->recurring_days);
    }
}
