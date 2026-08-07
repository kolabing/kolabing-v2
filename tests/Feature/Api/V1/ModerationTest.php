<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Mail\ModerationAlertMail;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\ContentReport;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_blocks_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/me/blocks')->assertStatus(401);
        $this->postJson('/api/v1/reports')->assertStatus(401);
    }

    public function test_blocking_a_user_adds_them_to_the_block_list(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        $this->actingAs($viewer)
            ->postJson("/api/v1/me/blocks/{$target->id}")
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->actingAs($viewer)
            ->getJson('/api/v1/me/blocks')
            ->assertStatus(200)
            ->assertJsonPath('data', [$target->id]);

        $this->assertDatabaseHas('user_blocks', [
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $target->id,
        ]);
    }

    public function test_unblocking_removes_the_user_from_the_block_list(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        UserBlock::query()->create([
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $target->id,
        ]);

        $this->actingAs($viewer)
            ->deleteJson("/api/v1/me/blocks/{$target->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($viewer)
            ->getJson('/api/v1/me/blocks')
            ->assertStatus(200)
            ->assertJsonPath('data', []);

        $this->assertDatabaseMissing('user_blocks', [
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $target->id,
        ]);
    }

    public function test_duplicate_block_is_idempotent(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        $this->actingAs($viewer)->postJson("/api/v1/me/blocks/{$target->id}")->assertStatus(201);
        $this->actingAs($viewer)->postJson("/api/v1/me/blocks/{$target->id}")->assertStatus(201);

        $this->assertSame(1, UserBlock::query()
            ->where('blocker_profile_id', $viewer->id)
            ->where('blocked_profile_id', $target->id)
            ->count());
    }

    public function test_a_user_cannot_block_themselves(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->community()->create();

        $this->actingAs($viewer)
            ->postJson("/api/v1/me/blocks/{$viewer->id}")
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_blocking_a_user_queues_a_moderation_alert(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        $this->actingAs($viewer)->postJson("/api/v1/me/blocks/{$target->id}")->assertStatus(201);

        Mail::assertQueued(ModerationAlertMail::class, fn (ModerationAlertMail $mail): bool => $mail->event === 'block');
    }

    public function test_reporting_content_persists_a_row_and_queues_an_alert(): void
    {
        Mail::fake();

        $reporter = Profile::factory()->community()->create();
        $reported = Profile::factory()->business()->create();

        $this->actingAs($reporter)
            ->postJson('/api/v1/reports', [
                'target_type' => 'kolab',
                'target_id' => 'abc-123',
                'reported_profile_id' => $reported->id,
                'reason' => 'inappropriate',
                'note' => 'This looks like a scam.',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('content_reports', [
            'reporter_profile_id' => $reporter->id,
            'target_type' => 'kolab',
            'target_id' => 'abc-123',
            'reported_profile_id' => $reported->id,
            'reason' => 'inappropriate',
            'status' => 'open',
        ]);

        Mail::assertQueued(ModerationAlertMail::class, fn (ModerationAlertMail $mail): bool => $mail->event === 'report');
    }

    public function test_report_validation_rejects_unknown_reason_and_target_type(): void
    {
        Mail::fake();

        $reporter = Profile::factory()->community()->create();

        $this->actingAs($reporter)
            ->postJson('/api/v1/reports', [
                'target_type' => 'nonsense',
                'target_id' => 'abc-123',
                'reason' => 'made_up',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_type', 'reason']);

        $this->assertSame(0, ContentReport::query()->count());
        Mail::assertNothingQueued();
    }

    public function test_discovery_excludes_a_blocked_creators_kolab(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
        ]);

        $communityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $communityCreator->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        $kolab = Kolab::factory()->published()->forCreator($communityCreator)->create([
            'intent_type' => 'community_seeking',
            'title' => 'Sunrise beach yoga',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 200,
            'typical_attendance' => 50,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
            'published_at' => now()->subDay(),
        ]);

        // Visible before blocking.
        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id);

        // Block the creator.
        $this->actingAs($viewer)->postJson("/api/v1/me/blocks/{$communityCreator->id}")->assertStatus(201);

        // Excluded after blocking.
        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 0);
    }

    public function test_discovery_excludes_a_kolab_from_a_creator_who_blocked_the_viewer(): void
    {
        Mail::fake();

        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Casa Sol',
            'business_type' => 'restaurant',
            'categories' => ['restaurant'],
            'city_name' => 'Barcelona',
        ]);

        $communityCreator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $communityCreator->id,
            'name' => 'Wellness Collective',
            'community_type' => 'wellness_community',
        ]);

        Kolab::factory()->published()->forCreator($communityCreator)->create([
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'community_types' => ['Wellness'],
            'community_size' => 200,
            'typical_attendance' => 50,
            'offers_in_return' => ['social_media'],
            'venue_preference' => 'business_provides',
            'published_at' => now()->subDay(),
        ]);

        // The creator blocks the viewer.
        UserBlock::query()->create([
            'blocker_profile_id' => $communityCreator->id,
            'blocked_profile_id' => $viewer->id,
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 0);
    }
}
