<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Leaderboard rows are visible to other people, so they must never carry a
 * member's email address, and the per-community roster is for insiders only.
 */
class LeaderboardPrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(?string $name, string $email, int $points = 100): Profile
    {
        $profile = Profile::factory()->attendee()->create(['name' => $name, 'email' => $email]);
        AttendeeProfile::factory()->create(['profile_id' => $profile->id, 'total_points' => $points]);

        return $profile;
    }

    private function member(Community $community, Profile $profile, int $points, array $attributes = []): void
    {
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $profile->id] + $attributes);
        CommunityPoints::query()->create([
            'community_id' => $community->id, 'profile_id' => $profile->id, 'points' => $points,
        ]);
    }

    // ------------------------------------------------------------ no emails

    public function test_community_points_leaderboard_shows_name_or_email_prefix_never_the_email(): void
    {
        $community = Community::factory()->create();
        $named = $this->attendee('Marta Runner', 'marta.private@example.com');
        $unnamed = $this->attendee(null, 'jordi.secret@example.com');
        $this->member($community, $named, 200);
        $this->member($community, $unnamed, 100);

        $response = $this->actingAs($named)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.display_name', 'Marta Runner')
            ->assertJsonPath('data.leaderboard.1.display_name', 'jordi.secret');

        $this->assertStringNotContainsString('@example.com', $response->getContent() ?: '');
    }

    public function test_global_and_chapter_leaderboards_never_show_emails(): void
    {
        $community = Community::factory()->create();
        $named = $this->attendee('Ana Pace', 'ana.hidden@example.com', 300);
        $unnamed = $this->attendee(null, 'pol.hidden@example.com', 200);
        foreach ([$named, $unnamed] as $p) {
            CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $p->id]);
        }

        foreach (['/api/v1/leaderboard/global', "/api/v1/leaderboard/global?community_id={$community->id}"] as $path) {
            $response = $this->actingAs($named)->getJson($path)
                ->assertOk()
                ->assertJsonPath('data.leaderboard.0.display_name', 'Ana Pace')
                ->assertJsonPath('data.leaderboard.1.display_name', 'pol.hidden');

            $this->assertStringNotContainsString('@example.com', $response->getContent() ?: '', $path);
        }
    }

    public function test_event_leaderboard_never_shows_emails(): void
    {
        $event = Event::factory()->create();
        $unnamed = $this->attendee(null, 'eva.hidden@example.com');

        ChallengeCompletion::factory()->create([
            'event_id' => $event->id,
            'challenger_profile_id' => $unnamed->id,
            'status' => ChallengeCompletionStatus::Verified,
            'points_earned' => 40,
        ]);

        $response = $this->actingAs($unnamed)
            ->getJson("/api/v1/events/{$event->id}/leaderboard")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.display_name', 'eva.hidden');

        $this->assertStringNotContainsString('@example.com', $response->getContent() ?: '');
    }

    // ------------------------------------------------- who may read the roster

    public function test_an_outsider_gets_403_on_a_community_leaderboard(): void
    {
        $community = Community::factory()->create();
        $this->member($community, $this->attendee('Insider', 'insider@example.com'), 50);

        $outsider = $this->attendee('Outsider', 'outsider@example.com');

        $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data');
    }

    public function test_a_non_active_member_gets_403(): void
    {
        $community = Community::factory()->create();
        $left = $this->attendee('Left', 'left@example.com');
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $left->id,
            'status' => 'removed',
        ]);

        $this->actingAs($left)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertStatus(403);
    }

    public function test_an_active_member_and_the_organiser_can_read_it(): void
    {
        $community = Community::factory()->create();
        $member = $this->attendee('Member', 'member@example.com');
        $this->member($community, $member, 10);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk();

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.profile_id', $member->id);
    }
}
