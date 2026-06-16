<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\CommunityService;
use App\Services\OnboardingService;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A community account's profile photo and its community logo are ONE image
 * (Daniel 2026-06-05): editing either side mirrors to the other.
 */
class CommunityPhotoSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function leaderWithCommunity(): array
    {
        $leader = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $leader->id, 'profile_photo' => null]);
        $community = app(CommunityService::class)->create($leader, [
            'name' => 'Run Club', 'type' => 'greek',
        ]);

        return [$leader, $community];
    }

    public function test_profile_photo_update_mirrors_to_community_avatar(): void
    {
        [$leader, $community] = $this->leaderWithCommunity();

        app(ProfileService::class)->updateProfile($leader, [], [
            'profile_photo' => 'https://cdn.example/logo.jpg',
        ]);

        $this->assertSame('https://cdn.example/logo.jpg', $community->fresh()->avatar_url);
    }

    public function test_community_avatar_update_mirrors_back_to_profile_photo(): void
    {
        [$leader, $community] = $this->leaderWithCommunity();

        app(CommunityService::class)->update($community, [
            'avatar_url' => 'https://cdn.example/new-logo.jpg',
        ]);

        $this->assertSame(
            'https://cdn.example/new-logo.jpg',
            $leader->communityProfile->fresh()->profile_photo
        );
    }

    /**
     * FX-13 / issue 2: the photo set during community onboarding must reach the
     * already-created community group's avatar (onboarding bypasses
     * ProfileService::updateProfile, so it must mirror the photo itself).
     */
    public function test_onboarding_photo_propagates_to_existing_community_group(): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');

        [$leader, $community] = $this->leaderWithCommunity();
        // Group starts with no avatar.
        $this->assertNull($community->fresh()->avatar_url);

        // A same-origin storage URL is returned as-is by handleProfilePhoto
        // (no external download needed in the test).
        $photo = rtrim((string) config('app.url'), '/').'/storage/onboarding-logo.jpg';

        app(OnboardingService::class)->completeCommunityOnboarding($leader, [
            'name' => 'Run Club',
            'community_type' => 'run_club',
            'city_id' => $leader->communityProfile->city_id,
            'profile_photo' => $photo,
        ]);

        $stored = $leader->communityProfile->fresh()->profile_photo;
        $this->assertSame($photo, $stored);
        // The community group's avatar now matches the onboarding photo.
        $this->assertSame($photo, $community->fresh()->avatar_url);
    }
}
