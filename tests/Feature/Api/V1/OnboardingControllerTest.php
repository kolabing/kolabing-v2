<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OnboardingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->city = City::factory()->create(['name' => 'Barcelona']);
    }

    public function test_business_onboarding_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/onboarding/business', [
            'name' => 'Test Business',
            'business_type' => 'cafe',
            'city_id' => $this->city->id,
            'instagram' => 'testbusiness',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_business_onboarding_requires_business_user_type(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_business_onboarding_validates_required_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'business_type', 'city_id'],
            ]);
    }

    public function test_business_onboarding_validates_business_type(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'invalid_type',
                'city_id' => $this->city->id,
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['business_type'],
            ]);
    }

    public function test_business_onboarding_validates_city_exists(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => '00000000-0000-0000-0000-000000000000',
                'instagram' => 'testbusiness',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['city_id'],
            ]);
    }

    public function test_business_onboarding_validates_phone_number_format(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Test Business',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'phone_number' => '123456789', // Invalid format
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['phone_number'],
            ]);
    }

    public function test_business_onboarding_completes_successfully(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/business', [
                'name' => 'Cafe Barcelona',
                'about' => 'A cozy cafe in the heart of Barcelona',
                'business_type' => 'cafe',
                'city_id' => $this->city->id,
                'phone_number' => '+34612345678',
                'instagram' => '@cafebarcelona',
                'website' => 'https://cafebarcelona.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Business profile updated successfully')
            ->assertJsonPath('data.onboarding_completed', true)
            ->assertJsonPath('data.phone_number', '+34612345678')
            ->assertJsonPath('data.business_profile.name', 'Cafe Barcelona')
            ->assertJsonPath('data.business_profile.business_type', 'cafe')
            ->assertJsonPath('data.business_profile.instagram', 'cafebarcelona'); // @ stripped

        // Verify database updates
        $profile->refresh();
        $this->assertEquals('+34612345678', $profile->phone_number);

        $businessProfile = $profile->businessProfile;
        $this->assertEquals('Cafe Barcelona', $businessProfile->name);
        $this->assertEquals('cafe', $businessProfile->business_type);
        $this->assertEquals($this->city->id, $businessProfile->city_id);
        $this->assertEquals('cafebarcelona', $businessProfile->instagram);
    }

    public function test_community_onboarding_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/onboarding/community', [
            'name' => 'Test Community',
            'community_type' => 'run_club',
            'city_id' => $this->city->id,
            'instagram' => 'testcommunity',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_community_onboarding_requires_community_user_type(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Test Community',
                'community_type' => 'run_club',
                'city_id' => $this->city->id,
                'instagram' => 'testcommunity',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Access denied');
    }

    public function test_community_onboarding_validates_required_fields(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['name', 'community_type', 'city_id'],
            ]);
    }

    public function test_community_onboarding_validates_community_type(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Test User',
                'community_type' => 'invalid_type',
                'city_id' => $this->city->id,
                'instagram' => 'testuser',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['community_type'],
            ]);
    }

    public function test_community_onboarding_completes_successfully(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->putJson('/api/v1/onboarding/community', [
                'name' => 'Maria Garcia',
                'about' => 'Food blogger and coffee enthusiast',
                'community_type' => 'run_club',
                'city_id' => $this->city->id,
                'phone_number' => '+34698765432',
                'instagram' => '@maria_food_bcn',
                'tiktok' => '@maria_food',
                'website' => 'https://mariafoodblog.com',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Community profile updated successfully')
            ->assertJsonPath('data.onboarding_completed', true)
            ->assertJsonPath('data.phone_number', '+34698765432')
            ->assertJsonPath('data.community_profile.name', 'Maria Garcia')
            ->assertJsonPath('data.community_profile.community_type', 'run_club')
            ->assertJsonPath('data.community_profile.instagram', 'maria_food_bcn')
            ->assertJsonPath('data.community_profile.tiktok', 'maria_food')
            ->assertJsonPath('data.community_profile.is_featured', false);

        // Verify database updates
        $profile->refresh();
        $this->assertEquals('+34698765432', $profile->phone_number);

        $communityProfile = $profile->communityProfile;
        $this->assertEquals('Maria Garcia', $communityProfile->name);
        $this->assertEquals('run_club', $communityProfile->community_type);
        $this->assertEquals($this->city->id, $communityProfile->city_id);
    }
}
