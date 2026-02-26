<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\City;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LookupControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cities_endpoint_returns_all_cities(): void
    {
        // Clear existing cities and create test data
        City::query()->delete();
        City::factory()->create(['name' => 'Barcelona']);
        City::factory()->create(['name' => 'Madrid']);
        City::factory()->create(['name' => 'Valencia']);

        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'country',
                    ],
                ],
                'meta' => [
                    'total',
                ],
            ]);

        // Verify cities are sorted alphabetically
        $data = $response->json('data');
        $this->assertEquals('Barcelona', $data[0]['name']);
        $this->assertEquals('Madrid', $data[1]['name']);
        $this->assertEquals('Valencia', $data[2]['name']);
    }

    public function test_cities_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/cities');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

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

        // Verify specific types exist
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

        // Verify specific types exist
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
