<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
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

    public function test_auto_complete_command_completes_when_one_yes_and_partner_never_answered(): void
    {
        // Safest-MVP decision (2026-06-27): one 'yes' older than the grace
        // window + total partner silence IS auto-completed. This is the one
        // case the feature is designed for.
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        CollaborationCompletion::factory()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->auto_completed_at);
    }

    public function test_auto_complete_command_skips_when_first_completion_confirmation_within_grace(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        // First party just confirmed -> partner still inside the grace window.
        CollaborationCompletion::factory()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_no_completion_confirmation_rows(): void
    {
        ['collab' => $collab] = $this->kolabWithActiveCollaboration();

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_someone_said_no(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        // Old enough to clear the grace window, but it's an explicit 'no' —
        // auto-complete must never paper over that signal.
        CollaborationCompletion::factory()->no()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_one_yes_and_partner_said_not_yet(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->kolabWithActiveCollaboration();

        CollaborationCompletion::factory()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        // Partner explicitly asked to wait — a real signal, not silence.
        CollaborationCompletion::factory()->notYet()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $community->id,
            'role' => 'applicant',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_both_said_not_yet(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->kolabWithActiveCollaboration();

        CollaborationCompletion::factory()->notYet()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);
        CollaborationCompletion::factory()->notYet()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $community->id,
            'role' => 'applicant',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_skips_when_yes_was_only_just_reconfirmed(): void
    {
        // Regression: a 'not_yet' submitted long ago and only just changed to
        // 'yes' (old created_at, recent updated_at) must get a fresh grace
        // window from the 'yes', not auto-complete instantly off created_at.
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        CollaborationCompletion::factory()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
            'created_at' => now()->subDays(10),
            'updated_at' => now(),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_auto_complete_command_dry_run_does_not_write(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->kolabWithActiveCollaboration();

        CollaborationCompletion::factory()->create([
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ]);

        Artisan::call('app:auto-complete-stale-collaborations', ['--dry-run' => true]);

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }
}
