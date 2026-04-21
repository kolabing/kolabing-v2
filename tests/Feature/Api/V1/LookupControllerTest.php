<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\City;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LookupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ─── Cities ──────────────────────────────────────────────────

    public function test_cities_endpoint_returns_active_cities_only(): void
    {
        City::query()->delete();
        City::factory()->create(['name' => 'Barcelona', 'is_active' => true, 'sort_order' => 1]);
        City::factory()->create(['name' => 'Madrid', 'is_active' => true, 'sort_order' => 2]);
        City::factory()->create(['name' => 'Lugo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        // 2 active cities + 1 "Other" entry
        $this->assertCount(3, $data);
        $this->assertEquals('Barcelona', $data[0]['name']);
        $this->assertEquals('Madrid', $data[1]['name']);
        $this->assertEquals('Other / Suggest a city', $data[2]['name']);
        $this->assertEquals('other', $data[2]['id']);
    }

    public function test_cities_endpoint_returns_all_cities_with_all_param(): void
    {
        City::query()->delete();
        City::factory()->create(['name' => 'Barcelona', 'is_active' => true, 'sort_order' => 1]);
        City::factory()->create(['name' => 'Madrid', 'is_active' => true, 'sort_order' => 2]);
        City::factory()->create(['name' => 'Lugo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/cities?all=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        // 3 real cities + 1 "Other" entry
        $this->assertCount(4, $data);
    }

    public function test_cities_sorted_by_sort_order_then_name(): void
    {
        City::query()->delete();
        City::factory()->create(['name' => 'Zaragoza', 'is_active' => true, 'sort_order' => 1]);
        City::factory()->create(['name' => 'Barcelona', 'is_active' => true, 'sort_order' => 2]);

        $response = $this->getJson('/api/v1/cities');

        $data = $response->json('data');
        $this->assertEquals('Zaragoza', $data[0]['name']);
        $this->assertEquals('Barcelona', $data[1]['name']);
    }

    public function test_cities_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_cities_response_includes_other_entry(): void
    {
        City::query()->delete();

        $response = $this->getJson('/api/v1/cities');

        $data = $response->json('data');
        $lastEntry = end($data);
        $this->assertEquals('other', $lastEntry['id']);
        $this->assertEquals('Other / Suggest a city', $lastEntry['name']);
    }

    // ─── City Suggestions ────────────────────────────────────────

    public function test_suggest_city_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/cities/suggest', [
            'city_name' => 'Salamanca',
        ]);

        $response->assertStatus(401);
    }

    public function test_suggest_city_creates_suggestion(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/cities/suggest', [
                'city_name' => 'Salamanca',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('city_suggestions', [
            'suggested_by' => $profile->id,
            'city_name' => 'Salamanca',
        ]);
    }

    public function test_suggest_city_validates_city_name(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/cities/suggest', []);

        $response->assertStatus(422);
    }

    // ─── Business Types ──────────────────────────────────────────

    public function test_business_types_endpoint_returns_all_types(): void
    {
        $response = $this->getJson('/api/v1/lookup/business-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 10)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'value',
                        'label',
                        'description',
                    ],
                ],
                'meta' => [
                    'total',
                ],
            ]);

        $values = collect($response->json('data'))->pluck('value')->toArray();
        $this->assertContains('cafe', $values);
        $this->assertContains('restaurant', $values);
        $this->assertContains('bar', $values);
        $this->assertContains('gym', $values);
        $this->assertContains('other', $values);
    }

    public function test_business_types_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/lookup/business-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_places_autocomplete_returns_place_predictions(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'suggestions' => [
                        [
                            'placePrediction' => [
                                'placeId' => 'google-place-id',
                                'text' => ['text' => 'Sol Studio'],
                                'structuredFormat' => [
                                    'mainText' => ['text' => 'Sol Studio'],
                                    'secondaryText' => ['text' => 'Carrer de Mallorca 1, Barcelona, Spain'],
                                ],
                            ],
                        ],
                    ],
                ])
                ->push([
                    'location' => [
                        'latitude' => 41.3874,
                        'longitude' => 2.1686,
                    ],
                    'formattedAddress' => 'Carrer de Mallorca 1, Barcelona',
                    'addressComponents' => [
                        ['types' => ['locality'], 'longText' => 'Barcelona'],
                        ['types' => ['country'], 'longText' => 'Spain'],
                    ],
                ]),
        ]);

        $response = $this->getJson('/api/v1/places/autocomplete?query=sol');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.place_id', 'google-place-id')
            ->assertJsonPath('data.0.title', 'Sol Studio')
            ->assertJsonPath('data.0.subtitle', 'Carrer de Mallorca 1, Barcelona, Spain');
    }

    public function test_places_autocomplete_matches_existing_city_id(): void
    {
        $city = City::factory()->create([
            'name' => 'Barcelona',
            'country' => 'Spain',
        ]);

        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'suggestions' => [
                        [
                            'placePrediction' => [
                                'placeId' => 'google-place-id',
                                'text' => ['text' => 'Sol Studio'],
                                'structuredFormat' => [
                                    'mainText' => ['text' => 'Sol Studio'],
                                    'secondaryText' => ['text' => 'Carrer de Mallorca 1, Barcelona, Spain'],
                                ],
                            ],
                        ],
                    ],
                ])
                ->push([
                    'location' => [
                        'latitude' => 41.3874,
                        'longitude' => 2.1686,
                    ],
                    'formattedAddress' => 'Carrer de Mallorca 1, Barcelona',
                    'addressComponents' => [
                        ['types' => ['locality'], 'longText' => 'Barcelona'],
                        ['types' => ['country'], 'longText' => 'Spain'],
                    ],
                ]),
        ]);

        $response = $this->getJson('/api/v1/places/autocomplete?query=sol');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.city', 'Barcelona')
            ->assertJsonPath('data.0.country', 'Spain')
            ->assertJsonPath('data.0.city_id', $city->id)
            ->assertJsonPath('data.0.formatted_address', 'Carrer de Mallorca 1, Barcelona')
            ->assertJsonPath('data.0.latitude', 41.3874)
            ->assertJsonPath('data.0.longitude', 2.1686);
    }

    // ─── Community Types ─────────────────────────────────────────

    public function test_community_types_endpoint_returns_all_types(): void
    {
        $response = $this->getJson('/api/v1/lookup/community-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'value',
                        'label',
                        'description',
                    ],
                ],
                'meta' => [
                    'total',
                ],
            ]);

        $values = collect($response->json('data'))->pluck('value')->toArray();
        $this->assertContains('run_club', $values);
        $this->assertContains('fitness_community', $values);
        $this->assertContains('photography_community', $values);
        $this->assertContains('student_community', $values);
        $this->assertContains('other', $values);
    }

    public function test_community_types_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/lookup/community-types');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
