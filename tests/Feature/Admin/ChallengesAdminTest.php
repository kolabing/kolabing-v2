<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeProofType;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Challenge;
use App\Models\ChallengeDefault;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\CommunityType;
use App\Models\Kolab;
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

    // #248: the camera setting existed everywhere except the one screen a human
    // uses. `proof_type` was on the model, cast, in the API resource and in the
    // API requests — but not in the admin requests, so `validated()` dropped it
    // and the panel could never author a camera challenge.
    public function test_create_persists_the_camera_setting(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Selfie together',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 15,
                'audience' => ChallengeAudience::Both->value,
                'proof_type' => ChallengeProofType::Photo->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $this->assertDatabaseHas('challenges', [
            'name' => 'Selfie together',
            'proof_type' => 'photo',
        ]);
    }

    public function test_update_can_turn_the_camera_on_and_off_again(): void
    {
        $challenge = Challenge::factory()->create([
            'proof_type' => ChallengeProofType::Text,
            'audience' => ChallengeAudience::Both,
            'is_system' => true,
        ]);

        // Spelled out rather than read back off the model: the factory leaves
        // `audience` to its own default and the form always posts every field.
        $payload = fn (string $proofType): array => [
            'name' => $challenge->name,
            'difficulty' => ChallengeDifficulty::Easy->value,
            'points' => 10,
            'audience' => ChallengeAudience::Both->value,
            'proof_type' => $proofType,
        ];

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.challenges.update', $challenge), $payload('photo'))
            ->assertRedirect(route('admin.gamification.challenges.index'));
        $this->assertSame('photo', $challenge->fresh()->proof_type->value);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.challenges.update', $challenge), $payload('text'))
            ->assertRedirect(route('admin.gamification.challenges.index'));
        $this->assertSame('text', $challenge->fresh()->proof_type->value);
    }

    public function test_create_rejects_an_unknown_camera_setting(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Nonsense',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 15,
                'audience' => ChallengeAudience::Both->value,
                'proof_type' => 'video',
            ])
            ->assertSessionHasErrors('proof_type');

        $this->assertDatabaseMissing('challenges', ['name' => 'Nonsense']);
    }

    public function test_a_challenge_created_without_the_field_stays_text(): void
    {
        // Every challenge authored before #216 reports `text`, and the form
        // always posts a value — but a script or an older form must not end up
        // with a null the app has to guess about.
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Quiet one',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 5,
                'audience' => ChallengeAudience::Both->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $challenge = Challenge::query()->where('name', 'Quiet one')->sole();
        $this->assertNotSame('photo', $challenge->proof_type?->value);
    }

    public function test_the_index_marks_which_challenges_open_the_camera(): void
    {
        Challenge::factory()->create([
            'name' => 'Camera challenge',
            'proof_type' => ChallengeProofType::Photo,
            'is_system' => true,
            'event_id' => null,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.challenges.index'))
            ->assertOk()
            ->assertSee('Camera challenge')
            ->assertSee('photo');
    }

    public function test_index_filters_by_attendee_audience(): void
    {
        Challenge::factory()->mission(audience: ChallengeAudience::Attendee)->create([
            'name' => 'Attendee only mission',
        ]);
        Challenge::factory()->create([
            'name' => 'Business only challenge',
            'audience' => ChallengeAudience::Business,
            'is_system' => true,
            'event_id' => null,
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.challenges.index', ['audience' => 'attendee']));

        $response->assertSee('Attendee only mission');
        $response->assertDontSee('Business only challenge');
    }

    public function test_create_persists_a_mission_with_trigger_target_and_repeat(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Attend 5 Kolabs',
                'description' => 'Check in to 5 Kolabs.',
                'difficulty' => ChallengeDifficulty::Medium->value,
                'points' => 30,
                'category' => ChallengeCategory::Attendance->value,
                'audience' => ChallengeAudience::Attendee->value,
                'trigger_action' => MissionTrigger::EventCheckin->value,
                'target_value' => 5,
                'repeat_interval' => MissionRepeat::Monthly->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $this->assertDatabaseHas('challenges', [
            'name' => 'Attend 5 Kolabs',
            'audience' => 'attendee',
            'trigger_action' => 'event_checkin',
            'target_value' => 5,
            'repeat_interval' => 'monthly',
            'is_system' => true,
        ]);
    }

    public function test_create_rejects_an_invalid_trigger(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Bad mission',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 10,
                'audience' => ChallengeAudience::Attendee->value,
                'trigger_action' => 'not_a_real_trigger',
                'target_value' => 1,
                'repeat_interval' => MissionRepeat::Once->value,
            ])
            ->assertSessionHasErrors('trigger_action');
    }

    public function test_create_without_trigger_defaults_target_value_to_one(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.challenges.store'), [
                'name' => 'Plain challenge',
                'difficulty' => ChallengeDifficulty::Easy->value,
                'points' => 10,
                'audience' => ChallengeAudience::Both->value,
            ])
            ->assertRedirect(route('admin.gamification.challenges.index'));

        $this->assertDatabaseHas('challenges', [
            'name' => 'Plain challenge',
            'target_value' => 1,
            'trigger_action' => null,
        ]);
    }

    public function test_defaults_matrix_renders(): void
    {
        // updateOrCreate: these types are now pre-seeded by the type-tables
        // source-of-truth migration, so we must not collide on the unique slug.
        BusinessType::query()->updateOrCreate(['slug' => 'cafe'], ['name' => 'Café']);
        CommunityType::query()->updateOrCreate(['slug' => 'run_club'], ['name' => 'Run Club']);
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
        BusinessType::query()->updateOrCreate(['slug' => 'coworking'], ['name' => 'Coworking']);
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

        $opportunity = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);

        $collab = Collaboration::factory()->scheduled()->create([
            'kolab_id' => $opportunity->id,
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

        $opportunity = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);
        $collab = Collaboration::factory()->scheduled()->create([
            'kolab_id' => $opportunity->id,
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
