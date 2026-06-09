<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\CommunityService;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        CommunityProfile::factory()->create(['profile_id' => $leader->id]);
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
}
