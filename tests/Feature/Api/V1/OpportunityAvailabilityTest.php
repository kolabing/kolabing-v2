<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Profile $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Profile::factory()->business()->create();
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Opportunity',
            'description' => 'A test opportunity for collaboration.',
            'business_offer' => ['venue' => true, 'food_drink' => false],
            'community_deliverables' => ['instagram_post' => true],
            'categories' => ['Food & Drink'],
            'venue_mode' => 'business_venue',
            'address' => 'Calle Test 123, Sevilla',
            'preferred_city' => 'Sevilla',
        ], $overrides);
    }

    // ─── One Time Mode ───────────────────────────────────────────

    public function test_create_one_time_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'one_time')
            ->assertJsonPath('data.selected_time', '10:00')
            ->assertJsonPath('data.recurring_days', null);
    }

    public function test_create_one_time_requires_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_one_time_rejects_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => [1, 3],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_one_time_requires_date_range(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'selected_time' => '10:00',
            'availability_start' => null,
            'availability_end' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability_start', 'availability_end']);
    }

    // ─── Recurring Mode ──────────────────────────────────────────

    public function test_create_recurring_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => [4, 6],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'recurring')
            ->assertJsonPath('data.selected_time', '20:00')
            ->assertJsonPath('data.recurring_days', [4, 6]);
    }

    public function test_create_recurring_requires_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => null,
            'recurring_days' => [1, 3],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_recurring_requires_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_recurring_allows_null_dates(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '18:00',
            'recurring_days' => [1],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_start', null)
            ->assertJsonPath('data.availability_end', null);
    }

    public function test_create_recurring_rejects_invalid_day_number(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => [0, 8],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days.0');
    }

    // ─── Flexible Mode ───────────────────────────────────────────

    public function test_create_flexible_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'flexible')
            ->assertJsonPath('data.selected_time', null)
            ->assertJsonPath('data.recurring_days', null);
    }

    public function test_create_flexible_rejects_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_flexible_rejects_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
            'recurring_days' => [1, 2],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_flexible_requires_date_range(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => null,
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability_start', 'availability_end']);
    }

    // ─── Time Format Validation ──────────────────────────────────

    public function test_selected_time_rejects_invalid_format(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '25:00',
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    // ─── GET Response ────────────────────────────────────────────

    public function test_show_returns_new_availability_fields(): void
    {
        $opportunity = CollabOpportunity::factory()
            ->forCreator($this->business)
            ->recurring()
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'selected_time',
                    'recurring_days',
                ],
            ]);
    }

    public function test_index_returns_new_availability_fields(): void
    {
        CollabOpportunity::factory()
            ->forCreator($this->business)
            ->oneTime()
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $firstItem = $response->json('data.data.0');
        $this->assertArrayHasKey('selected_time', $firstItem);
        $this->assertArrayHasKey('recurring_days', $firstItem);
    }
}
