<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CollaborationCompletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function kolabWithActiveCollaboration(): array
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id]);

        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = Collaboration::factory()->active()->create([
            'kolab_id' => $kolab->id,
            'application_id' => $application->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $business->businessProfile?->id,
            'community_profile_id' => $community->communityProfile?->id,
            'scheduled_date' => now()->subDays(30),
        ]);

        return ['kolab' => $kolab, 'collab' => $collab, 'business' => $business, 'community' => $community];
    }

    public function test_admin_can_force_complete_a_collaboration_with_reason(): void
    {
        ['kolab' => $kolab, 'collab' => $collab] = $this->kolabWithActiveCollaboration();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.complete', $kolab), [
                'reason' => 'Ghosted by both parties.',
            ])
            ->assertRedirect(route('admin.kolabs.edit', $kolab));

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
        $this->assertSame('Ghosted by both parties.', $fresh->completion_reason);
        $this->assertNull($fresh->completed_by_profile_id);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_admin_force_complete_requires_reason(): void
    {
        ['kolab' => $kolab] = $this->kolabWithActiveCollaboration();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.complete', $kolab), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_admin_force_complete_404s_when_no_collaboration_exists(): void
    {
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.complete', $kolab), ['reason' => 'noop'])
            ->assertNotFound();
    }

    public function test_auto_complete_command_promotes_collab_when_first_feedback_past_grace(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        // First party confirmed more than the grace window (3 days) ago and the
        // partner never confirmed -> the grace timer has elapsed.
        CollaborationFeedback::factory()->create([
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'rating' => 5,
            'mirrored_from_review' => false,
            'created_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->auto_completed_at);
    }

    public function test_auto_complete_command_skips_when_first_feedback_within_grace(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        // First party just confirmed -> partner still inside the grace window.
        CollaborationFeedback::factory()->create([
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'rating' => 5,
            'mirrored_from_review' => false,
            'created_at' => now()->subDay(),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_no_feedback_rows(): void
    {
        ['collab' => $collab] = $this->kolabWithActiveCollaboration();

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_dry_run_does_not_write(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        CollaborationFeedback::factory()->create([
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'rating' => 5,
            'mirrored_from_review' => false,
            'created_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations', ['--dry-run' => true]);

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }
}
