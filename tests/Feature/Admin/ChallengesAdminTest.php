<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Challenge;
use App\Models\ChallengeDefault;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\CommunityType;
use App\Models\Profile;
use App\Models\User;
use App\Services\Admin\ChallengeDefaultsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChallengesAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_challenges_index_renders_for_maintainer(): void
    {
        Challenge::factory()->create([
            'name' => 'Test challenge alpha',
            'is_system' => true,
            'event_id' => null,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.challenges.index'))
            ->assertOk()
            ->assertSee('Test challenge alpha');
    }

    public function test_challenges_index_filters_by_audience(): void
    {
        Challenge::factory()->create([
            'name' => 'Business only challenge',
            'audience' => ChallengeAudience::Business,
            'is_system' => true,
            'event_id' => null,
        ]);
        Challenge::factory()->create([
            'name' => 'Community only challenge',
            'audience' => ChallengeAudience::Community,
            'is_system' => true,
            'event_id' => null,
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.challenges.index', ['audience' => 'business']));

        $response->assertSee('Business only challenge');
        $response->assertDontSee('Community only challenge');
    }

    public function test_challenges_index_forbidden_for_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->get(route('admin.gamification.challenges.index'))
            ->assertForbidden();
    }

    public function test_create_persists_a_new_challenge_with_audience(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'New thing',
                'description' => 'desc',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 15,
                'category' => ChallengeCategory::IceBreaker->value,
                'audience' => ChallengeAudience::Community->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $this->assertDatabaseHas('challenges', [
            'name' => 'New thing',
            'audience' => 'community',
            'is_system' => true,
        ]);
    }

    public function test_update_changes_audience(): void
    {
        $challenge = Challenge::factory()->create([
            'audience' => ChallengeAudience::Both,
            'is_system' => true,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.challenges.update', $challenge), [
                'name' => $challenge->name,
                'description' => $challenge->description,
                'difficulty' => $challenge->difficulty->value,
                'points' => $challenge->points,
                'category' => $challenge->category?->value,
                'audience' => ChallengeAudience::Business->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $this->assertSame('business', $challenge->fresh()->audience->value);
    }

    public function test_defaults_matrix_renders(): void
    {
        BusinessType::query()->create(['slug' => 'cafe', 'name' => 'Café']);
        CommunityType::query()->create(['slug' => 'run_club', 'name' => 'Run Club']);
        Challenge::factory()->create(['name' => 'Take a group photo', 'is_system' => true]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.challenges.defaults.index'))
            ->assertOk()
            ->assertSee('Café')
            ->assertSee('Run Club')
            ->assertSee('Take a group photo');
    }

    public function test_defaults_matrix_save_writes_rows(): void
    {
        BusinessType::query()->create(['slug' => 'coworking', 'name' => 'Coworking']);
        $challenge = Challenge::factory()->create(['is_system' => true]);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.challenges.defaults.update'), [
                'applies_to' => 'business_type',
                'type_value' => 'coworking',
                'challenge_ids' => [$challenge->id],
            ])
            ->assertRedirect(route('admin.gamification.challenges.defaults.index'));

        $this->assertDatabaseHas('challenge_defaults', [
            'challenge_id' => $challenge->id,
            'applies_to' => 'business_type',
            'type_value' => 'coworking',
        ]);
    }

    public function test_seed_for_collaboration_pulls_defaults_for_business_and_community_type(): void
    {
        $businessProfile = BusinessProfile::factory()->create(['business_type' => 'cafe']);
        $communityProfile = CommunityProfile::factory()->create(['community_type' => 'run_club']);

        $business = $businessProfile->profile;
        $community = $communityProfile->profile;

        $challengeForCafe = Challenge::factory()->create(['name' => 'Cafe default']);
        $challengeForRunClub = Challenge::factory()->create(['name' => 'Run-club default']);

        ChallengeDefault::query()->create([
            'challenge_id' => $challengeForCafe->id,
            'applies_to' => 'business_type',
            'type_value' => 'cafe',
            'position' => 0,
        ]);
        ChallengeDefault::query()->create([
            'challenge_id' => $challengeForRunClub->id,
            'applies_to' => 'community_type',
            'type_value' => 'run_club',
            'position' => 0,
        ]);

        $opportunity = CollabOpportunity::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);

        $collab = Collaboration::factory()->scheduled()->create([
            'collab_opportunity_id' => $opportunity->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $businessProfile->id,
            'community_profile_id' => $communityProfile->id,
        ]);

        app(ChallengeDefaultsService::class)->seedForCollaboration($collab);

        $this->assertSame(2, $collab->challenges()->count());
    }

    public function test_seed_for_collaboration_is_idempotent(): void
    {
        $businessProfile = BusinessProfile::factory()->create(['business_type' => 'cafe']);
        $business = $businessProfile->profile;
        $community = Profile::factory()->community()->create();

        $challenge = Challenge::factory()->create();
        ChallengeDefault::query()->create([
            'challenge_id' => $challenge->id,
            'applies_to' => 'business_type',
            'type_value' => 'cafe',
            'position' => 0,
        ]);

        $opportunity = CollabOpportunity::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);
        $collab = Collaboration::factory()->scheduled()->create([
            'collab_opportunity_id' => $opportunity->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $businessProfile->id,
        ]);

        $service = app(ChallengeDefaultsService::class);
        $service->seedForCollaboration($collab);
        $service->seedForCollaboration($collab);

        $this->assertSame(1, $collab->challenges()->count());
    }
}
