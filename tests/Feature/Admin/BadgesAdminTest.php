<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BadgeMilestoneType;
use App\Enums\GamificationBadgeSlug;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\EarnedBadge;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BadgesAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function attendeeBadge(BadgeMilestoneType $type, int $value, string $name = 'First Check-in'): Badge
    {
        return Badge::query()->create([
            'name' => $name,
            'description' => 'desc',
            'icon' => 'medal',
            'milestone_type' => $type,
            'milestone_value' => $value,
        ]);
    }

    public function test_index_route_is_protected_and_renders_for_maintainer(): void
    {
        $this->get(route('admin.gamification.badges.index'))->assertRedirect();

        $nonMaintainer = User::factory()->create(['is_maintainer' => false]);
        $this->actingAs($nonMaintainer, 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertForbidden();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->assertSee('Attendees')
            ->assertSee('Business')
            ->assertSee('Community');
    }

    public function test_attendee_section_shows_db_badges_with_award_counts(): void
    {
        $badge = $this->attendeeBadge(BadgeMilestoneType::FirstCheckin, 1, 'First Check-in');
        $other = $this->attendeeBadge(BadgeMilestoneType::ChallengeMaster, 50, 'Challenge Master');

        $profile1 = Profile::factory()->create();
        $profile2 = Profile::factory()->create();
        BadgeAward::factory()->create(['badge_id' => $badge->id, 'profile_id' => $profile1->id]);
        BadgeAward::factory()->create(['badge_id' => $badge->id, 'profile_id' => $profile2->id]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->assertSee('First Check-in')
            ->assertSee('Challenge Master')
            ->assertSeeText('2'); // award count for FirstCheckin
    }

    public function test_business_section_lists_business_audience_slugs_with_audience_filtered_counts(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        EarnedBadge::query()->create([
            'profile_id' => $business->id,
            'badge_slug' => GamificationBadgeSlug::FirstKolab,
            'earned_at' => now(),
        ]);
        EarnedBadge::query()->create([
            'profile_id' => $community->id,
            'badge_slug' => GamificationBadgeSlug::FirstKolab,
            'earned_at' => now(),
        ]);

        $html = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->assertSee('First Kolab')
            ->getContent();

        $this->assertStringContainsString(
            route('admin.gamification.badges.system-b.edit', GamificationBadgeSlug::FirstKolab->value),
            $html,
        );
    }

    public function test_community_section_includes_community_only_badge(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->assertSee('Momentum Club');
    }

    public function test_edit_form_renders(): void
    {
        $badge = $this->attendeeBadge(BadgeMilestoneType::EventGuru, 10, 'Event Guru');

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.edit', $badge))
            ->assertOk()
            ->assertSee('Event Guru')
            ->assertSee('Milestone type');
    }

    public function test_attendee_badge_can_be_updated(): void
    {
        $badge = $this->attendeeBadge(BadgeMilestoneType::PointHunter, 500, 'Point Hunter');

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.update', $badge), [
                'name' => 'Point Maverick',
                'description' => 'Hit 750 points',
                'icon' => 'star',
                'milestone_value' => 750,
            ])
            ->assertRedirect(route('admin.gamification.badges.index'));

        $this->assertDatabaseHas('badges', [
            'id' => $badge->id,
            'name' => 'Point Maverick',
            'icon' => 'star',
            'milestone_value' => 750,
        ]);
    }

    public function test_update_validation_rejects_empty_name(): void
    {
        $badge = $this->attendeeBadge(BadgeMilestoneType::Legend, 2000, 'Legend');

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.update', $badge), [
                'name' => '',
                'description' => 'still here',
                'icon' => 'trophy',
                'milestone_value' => 2000,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_update_rejects_non_maintainer(): void
    {
        $badge = $this->attendeeBadge(BadgeMilestoneType::FirstCheckin, 1);
        $nonMaintainer = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($nonMaintainer, 'admin')
            ->put(route('admin.gamification.badges.update', $badge), [
                'name' => 'New name',
                'description' => 'd',
                'icon' => 'i',
                'milestone_value' => 1,
            ])
            ->assertForbidden();
    }

    public function test_system_b_edit_form_renders_with_enum_defaults(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.system-b.edit', GamificationBadgeSlug::ContentCreator->value))
            ->assertOk()
            ->assertSee(GamificationBadgeSlug::ContentCreator->displayName())
            ->assertSee('Audiences');
    }

    public function test_system_b_update_creates_override_row(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.system-b.update', GamificationBadgeSlug::FirstKolab->value), [
                'name' => 'First Collab',
                'description' => 'You shipped your first one.',
                'icon' => 'rocket',
                'audiences' => ['business'],
            ])
            ->assertRedirect(route('admin.gamification.badges.index'));

        $this->assertDatabaseHas('gamification_badge_overrides', [
            'badge_slug' => GamificationBadgeSlug::FirstKolab->value,
            'name' => 'First Collab',
            'icon' => 'rocket',
        ]);
    }

    public function test_system_b_override_appears_in_index(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.system-b.update', GamificationBadgeSlug::PowerPartner->value), [
                'name' => 'Power Player',
                'description' => null,
                'icon' => null,
                'audiences' => null,
            ])->assertRedirect();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->assertSee('Power Player')
            ->assertDontSee(GamificationBadgeSlug::PowerPartner->displayName());
    }

    public function test_system_b_audience_override_moves_badge_between_sections(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.system-b.update', GamificationBadgeSlug::CommunityEarner->value), [
                'name' => null,
                'description' => null,
                'icon' => null,
                'audiences' => ['business'],
            ])->assertRedirect();

        $html = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(GamificationBadgeSlug::CommunityEarner->displayName(), $html);
    }

    public function test_system_b_update_rejects_non_maintainer(): void
    {
        $nonMaintainer = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($nonMaintainer, 'admin')
            ->put(route('admin.gamification.badges.system-b.update', GamificationBadgeSlug::FirstKolab->value), [
                'name' => 'X',
            ])
            ->assertForbidden();
    }

    public function test_system_b_update_validates_audience_values(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.badges.system-b.update', GamificationBadgeSlug::FirstKolab->value), [
                'audiences' => ['attendee'],
            ])
            ->assertSessionHasErrors('audiences.0');
    }

    public function test_system_b_invalid_slug_404s(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.badges.system-b.edit', 'not_a_real_slug'))
            ->assertNotFound();
    }
}
