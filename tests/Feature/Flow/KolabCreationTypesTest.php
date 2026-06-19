<?php

declare(strict_types=1);

namespace Tests\Feature\Flow;

use App\Enums\ProductType;
use App\Enums\VenueType;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\CommunityType;
use App\Models\OfferOption;
use App\Support\OfferOptionValues;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CitySeeder;
use Database\Seeders\CommunityTypeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Pipeline coverage for EVERY kolab creation type, driven through the real
 * registration + bearer-token path (not actingAs).
 *
 * The create CONTRACT per intent (incl. validation guards) is already unit-style
 * covered by tests/Feature/Api/V1/KolabCreateTest.php; this file proves each
 * intent is reachable end to end by a freshly-registered real user, and that the
 * community_seeking `past_events` showcase persists when sent on create.
 */
class KolabCreationTypesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([CitySeeder::class, BusinessTypeSeeder::class, CommunityTypeSeeder::class]);
    }

    public function test_registered_community_creates_community_seeking_kolab_with_past_events(): void
    {
        $token = $this->registerCommunity();
        $city = City::query()->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'community_seeking',
            'title' => 'Seeking a cafe to host our weekly book club',
            'description' => 'We are a 200-member book club looking for a cosy venue for weekly meetups.',
            'preferred_city' => $city->name,
            'needs' => [OfferOptionValues::for(OfferOption::KIND_NEED)[0]],
            'offers_in_return' => [OfferOptionValues::for(OfferOption::KIND_DELIVERABLE)[0]],
            'typical_attendance' => 30,
            'venue_preference' => 'business_provides',
            // past_events showcase sent on CREATE (persisted by KolabService::create).
            'past_events' => [
                [
                    'name' => 'Summer Reading Night',
                    'date' => now()->subMonths(2)->toDateString(),
                    'partner_name' => 'Cafe Central',
                    'media' => [
                        ['url' => 'https://example.com/past-event-1.jpg', 'type' => 'image'],
                    ],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'community_seeking')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.past_events.0.name', 'Summer Reading Night')
            ->assertJsonPath('data.past_events.0.partner_name', 'Cafe Central');
    }

    public function test_registered_business_with_venue_creates_venue_promotion_kolab(): void
    {
        $token = $this->registerBusinessWithVenue();

        $response = $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'venue_promotion',
            'title' => 'Host your community event at our rooftop',
            'description' => 'We offer our rooftop venue to communities looking for a memorable event space.',
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'media' => [
                ['url' => 'https://example.com/venue-hero.jpg', 'type' => 'image'],
            ],
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'venue_promotion')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_registered_business_creates_product_promotion_kolab(): void
    {
        $token = $this->registerBusinessProductOnly();
        $city = City::query()->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'product_promotion',
            'title' => 'Feature our cold brew at your events',
            'description' => 'We supply ready-to-serve cold brew kegs for community events across the city.',
            'preferred_city' => $city->name,
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'product_name' => 'Cold brew keg',
            'product_type' => ProductType::values()[0],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'product_promotion')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_registered_community_cannot_create_venue_promotion(): void
    {
        $token = $this->registerCommunity();

        $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'venue_promotion',
            'title' => 'Community trying to promote a venue',
            'description' => 'This must be rejected: communities can only create community-seeking kolabs.',
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'media' => [['url' => 'https://example.com/x.jpg', 'type' => 'image']],
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['intent_type']]);
    }

    public function test_registered_community_cannot_create_product_promotion(): void
    {
        $token = $this->registerCommunity();
        $city = City::query()->firstOrFail();

        $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'product_promotion',
            'title' => 'Community trying to promote a product',
            'description' => 'This must be rejected: communities can only create community-seeking kolabs.',
            'preferred_city' => $city->name,
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'product_name' => 'Some product',
            'product_type' => ProductType::values()[0],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['intent_type']]);
    }

    public function test_registered_business_without_venue_cannot_create_venue_promotion(): void
    {
        $token = $this->registerBusinessProductOnly();

        $this->withToken($token)->postJson('/api/v1/kolabs', [
            'intent_type' => 'venue_promotion',
            'title' => 'Product business trying a venue promotion',
            'description' => 'This must be rejected: the business has no saved primary venue profile.',
            'offering' => [OfferOptionValues::for(OfferOption::KIND_OFFERING)[0]],
            'media' => [['url' => 'https://example.com/x.jpg', 'type' => 'image']],
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['primary_venue']]);
    }

    private function registerCommunity(): string
    {
        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'types.community@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Types Book Club',
            'community_type' => CommunityType::query()->firstOrFail()->slug,
            'city_id' => City::query()->firstOrFail()->id,
        ]);
        $response->assertCreated();

        return $response->json('data.token');
    }

    private function registerBusinessProductOnly(): string
    {
        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'types.product@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Types Cold Brew Co',
            'business_type' => BusinessType::query()->firstOrFail()->slug,
            'has_venue' => false,
            'city_id' => City::query()->firstOrFail()->id,
        ]);
        $response->assertCreated();

        return $response->json('data.token');
    }

    private function registerBusinessWithVenue(): string
    {
        $city = City::query()->firstOrFail();

        $response = $this->postJson('/api/v1/auth/register/business', [
            'email' => 'types.venue@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Types Rooftop Venue',
            'business_type' => BusinessType::query()->firstOrFail()->slug,
            'has_venue' => true,
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'The Rooftop',
                'venue_type' => VenueType::values()[0],
                'capacity' => 120,
                'formatted_address' => '123 Skyline Ave, Madrid',
                'city' => $city->name,
            ],
        ]);
        $response->assertCreated();

        return $response->json('data.token');
    }
}
