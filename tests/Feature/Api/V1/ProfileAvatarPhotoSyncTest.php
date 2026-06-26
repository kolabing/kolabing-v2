<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * profiles.avatar_url is the fallback the discovery feed's cover-photo
 * resolver reads when a kolab has no media of its own (see
 * DiscoveryOpportunityResource::resolveCoverPhotoUrl). It must always mirror
 * whatever photo the user set on their extended profile, regardless of which
 * call site wrote it (ProfileService, onboarding, etc.) — these tests cover
 * the model-level sync directly so every call site is covered for free.
 */
class ProfileAvatarPhotoSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_business_profile_photo_update_mirrors_to_profile_avatar_url(): void
    {
        $profile = Profile::factory()->business()->create(['avatar_url' => null]);
        $businessProfile = BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'profile_photo' => null,
        ]);

        $businessProfile->update(['profile_photo' => 'https://cdn.example/business-logo.jpg']);

        $this->assertSame('https://cdn.example/business-logo.jpg', $profile->fresh()->avatar_url);
    }

    public function test_community_profile_photo_update_mirrors_to_profile_avatar_url(): void
    {
        $profile = Profile::factory()->community()->create(['avatar_url' => null]);
        $communityProfile = CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'profile_photo' => null,
        ]);

        $communityProfile->update(['profile_photo' => 'https://cdn.example/community-logo.jpg']);

        $this->assertSame('https://cdn.example/community-logo.jpg', $profile->fresh()->avatar_url);
    }

    public function test_unrelated_field_update_does_not_touch_avatar_url(): void
    {
        $profile = Profile::factory()->community()->create(['avatar_url' => 'https://cdn.example/unchanged.jpg']);
        $communityProfile = CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'profile_photo' => 'https://cdn.example/unchanged.jpg',
        ]);

        $communityProfile->update(['about' => 'New description']);

        $this->assertSame('https://cdn.example/unchanged.jpg', $profile->fresh()->avatar_url);
    }
}
