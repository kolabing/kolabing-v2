<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_registering_community_with_channels_moves_status_to_pending(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'verifyme@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Verify Me Run Club',
            'community_type' => 'run_club',
            'city_id' => $city->id,
            'verification_channels' => [
                ['type' => 'instagram', 'url' => 'https://instagram.com/verifyme'],
                ['type' => 'strava', 'url' => 'https://strava.com/clubs/verifyme'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.community_profile.verification_status', 'pending')
            ->assertJsonPath('data.user.community_profile.is_verified', false);

        $profile = Profile::where('email', 'verifyme@example.com')->firstOrFail();
        $cp = $profile->communityProfile;

        $this->assertSame(VerificationStatus::Pending->value, $cp->verification_status);
        $this->assertCount(2, $cp->verification_channels);
        $this->assertSame('instagram', $cp->verification_channels[0]['type']);
    }

    public function test_register_community_rejects_invalid_channel_type(): void
    {
        $city = City::factory()->create();

        $response = $this->postJson('/api/v1/auth/register/community', [
            'email' => 'bad@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Bad Channel Club',
            'community_type' => 'run_club',
            'city_id' => $city->id,
            'verification_channels' => [
                ['type' => 'facebook', 'url' => 'https://facebook.com/bad'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['verification_channels.0.type']]);
    }

    public function test_owner_sees_channels_in_resource_but_status_defaults_unverified(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->getJson('/api/v1/me/profile');

        $response->assertOk()
            ->assertJsonPath('data.community_profile.verification_status', 'unverified')
            ->assertJsonPath('data.community_profile.is_verified', false)
            ->assertJsonPath('data.community_profile.verification_channels', []);
    }

    public function test_admin_verify_sets_verified_and_resource_reflects_it(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'verification_status' => VerificationStatus::Pending->value,
            'verification_channels' => [['type' => 'instagram', 'url' => 'https://instagram.com/x']],
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.verification.verify', $profile))
            ->assertRedirect();

        $cp = $profile->communityProfile->fresh();
        $this->assertSame(VerificationStatus::Verified->value, $cp->verification_status);
        $this->assertNotNull($cp->verified_at);
        $this->assertNotNull($cp->verified_by);

        $resource = $this->actingAs($profile->fresh(), 'sanctum')->getJson('/api/v1/me/profile');
        $resource->assertJsonPath('data.community_profile.is_verified', true)
            ->assertJsonPath('data.community_profile.verification_status', 'verified');
    }

    public function test_admin_reject_sets_rejected_with_reason(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'verification_status' => VerificationStatus::Pending->value,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.verification.reject', $profile), [
                'rejection_reason' => 'Channels do not prove ownership.',
            ])
            ->assertRedirect();

        $cp = $profile->communityProfile->fresh();
        $this->assertSame(VerificationStatus::Rejected->value, $cp->verification_status);
        $this->assertSame('Channels do not prove ownership.', $cp->rejection_reason);
    }

    public function test_rejected_community_resubmitting_channels_goes_back_to_pending(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'verification_status' => VerificationStatus::Rejected->value,
            'rejection_reason' => 'Not enough proof.',
        ]);

        $response = $this->actingAs($profile)->putJson('/api/v1/me/profile', [
            'verification_channels' => [
                ['type' => 'website', 'url' => 'https://myclub.example.com'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.community_profile.verification_status', 'pending');

        $cp = $profile->communityProfile->fresh();
        $this->assertNull($cp->rejection_reason);
    }

    public function test_verified_community_editing_channels_stays_verified_and_is_flagged(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'verification_status' => VerificationStatus::Verified->value,
            'verified_at' => now(),
            'verification_channels' => [['type' => 'instagram', 'url' => 'https://instagram.com/old']],
        ]);

        $response = $this->actingAs($profile)->putJson('/api/v1/me/profile', [
            'verification_channels' => [
                ['type' => 'instagram', 'url' => 'https://instagram.com/new'],
                ['type' => 'tiktok', 'url' => 'https://tiktok.com/@new'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.community_profile.verification_status', 'verified')
            ->assertJsonPath('data.community_profile.is_verified', true);

        $cp = $profile->communityProfile->fresh();
        $this->assertSame(VerificationStatus::Verified->value, $cp->verification_status);
        $this->assertNotNull($cp->verification_flagged_at);
        $this->assertCount(2, $cp->verification_channels);
    }

    public function test_admin_reverify_clears_the_flag(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'verification_status' => VerificationStatus::Verified->value,
            'verification_flagged_at' => now(),
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.verification.verify', $profile))
            ->assertRedirect();

        $cp = $profile->communityProfile->fresh();
        $this->assertNull($cp->verification_flagged_at);
    }

    public function test_application_resource_exposes_community_verification_to_business(): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'verification_status' => VerificationStatus::Verified->value,
        ]);

        // ProfileSummaryResource is what represents a community inside an
        // application's applicant_profile; verify it emits the tick directly.
        $community->load('communityProfile');
        $resource = (new \App\Http\Resources\Api\V1\ProfileSummaryResource($community))
            ->toArray(request());

        $this->assertTrue($resource['is_verified']);
        $this->assertSame('verified', $resource['verification_status']);
    }

    public function test_admin_verification_index_pending_filter(): void
    {
        $pending = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $pending->id,
            'verification_status' => VerificationStatus::Pending->value,
        ]);

        $verified = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $verified->id,
            'verification_status' => VerificationStatus::Verified->value,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.community-verification.index', ['filter' => 'pending']))
            ->assertOk()
            ->assertSee($pending->id)
            ->assertDontSee($verified->id);
    }
}
