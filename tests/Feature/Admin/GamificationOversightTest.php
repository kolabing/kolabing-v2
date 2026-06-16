<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationOversightTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_overview_renders_for_maintainer(): void
    {
        $community = Community::factory()->create(['name' => 'Trailblazers Run Club']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.overview'))
            ->assertOk()
            ->assertSee('Gamification overview')
            ->assertSee('Trailblazers Run Club')
            ->assertSee('Points in circulation');
    }

    public function test_overview_forbidden_for_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->get(route('admin.gamification.overview'))
            ->assertForbidden();
    }

    public function test_community_leaderboard_picker_renders(): void
    {
        Community::factory()->create(['name' => 'Sourdough Society']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.leaderboards.communities'))
            ->assertOk()
            ->assertSee('Sourdough Society')
            ->assertSee('Pick a community');
    }

    public function test_community_leaderboard_shows_member_points(): void
    {
        $community = Community::factory()->create(['name' => 'Chess Collective']);
        $profile = Profile::factory()->attendee()->create(['email' => 'ranked@example.com']);

        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
        ]);
        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'points' => 320,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.leaderboards.communities.show', $community))
            ->assertOk()
            ->assertSee('Chess Collective')
            ->assertSee('ranked@example.com')
            ->assertSee('320');
    }

    public function test_global_leaderboard_renders(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.leaderboards.global'))
            ->assertOk()
            ->assertSee('Global XP leaderboard');
    }

    public function test_global_leaderboard_forbidden_for_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->get(route('admin.gamification.leaderboards.global'))
            ->assertForbidden();
    }
}
