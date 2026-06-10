<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\City;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeeProfileUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendee_profile_update_persists_name_and_city(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona']);
        $profile = Profile::factory()->attendee()->create(['name' => 'Old Name']);
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->putJson('/api/v1/me/profile', [
            'name' => 'New Attendee Name',
            'city_id' => $city->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Attendee Name')
            ->assertJsonPath('data.city_id', $city->id)
            ->assertJsonPath('data.city_name', 'Barcelona');

        // Persisted to the base profiles table (not a no-op).
        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'name' => 'New Attendee Name',
            'city_id' => $city->id,
        ]);

        // GET /me/profile reflects the change.
        $this->actingAs($profile)->getJson('/api/v1/me/profile')
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Attendee Name')
            ->assertJsonPath('data.city_id', $city->id)
            ->assertJsonPath('data.city_name', 'Barcelona');
    }

    public function test_attendee_profile_update_rejects_unknown_city(): void
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)->putJson('/api/v1/me/profile', [
            'name' => 'Someone',
            'city_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertStatus(422);
    }
}
