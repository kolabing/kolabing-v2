<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    /**
     * Build a Kolab + its paired CollabOpportunity (shared id) so applications
     * and collaborations can reference the same UUID without FK violations.
     */
    private function kolabWithOpportunity(array $kolabOverrides = []): Kolab
    {
        $kolab = Kolab::factory()->published()->create($kolabOverrides);

        CollabOpportunity::factory()->create([
            'id' => $kolab->id,
            'creator_profile_id' => $kolab->creator_profile_id,
        ]);

        return $kolab;
    }

    public function test_maintainer_sees_kolab_list(): void
    {
        Kolab::factory()->create(['title' => 'Beach club takeover']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index'))
            ->assertOk()
            ->assertSee('Beach club takeover');
    }

    public function test_index_filters_by_creator_status(): void
    {
        Kolab::factory()->create(['status' => KolabStatus::Draft, 'title' => 'Draft Kolab Alpha']);
        Kolab::factory()->published()->create(['title' => 'Published Kolab Beta']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index', ['status' => 'published']));

        $response->assertSee('Published Kolab Beta');
        $response->assertDontSee('Draft Kolab Alpha');
    }

    public function test_index_shows_lifecycle_badge_and_application_counts(): void
    {
        $kolab = $this->kolabWithOpportunity(['title' => 'Receiving Kolab']);

        Application::factory()->pending()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
        ]);
        Application::factory()->pending()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index'));

        $response->assertOk();
        $response->assertSee('Receiving applicants', false);
        $response->assertSee('2 · 0', false);
    }

    public function test_lifecycle_filter_active_returns_only_kolabs_with_active_collaboration(): void
    {
        $active = $this->kolabWithOpportunity(['title' => 'Has Active Collab']);
        Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $active->id,
            'application_id' => Application::factory()->accepted()->create([
                'collab_opportunity_id' => $active->id,
                'applicant_profile_id' => Profile::factory()->community(),
            ])->id,
            'creator_profile_id' => $active->creator_profile_id,
        ]);

        Kolab::factory()->published()->create(['title' => 'No Collab Kolab']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index', ['lifecycle' => 'active']));

        $response->assertSee('Has Active Collab');
        $response->assertDontSee('No Collab Kolab');
    }

    public function test_edit_page_shows_collaboration_details_and_reviews(): void
    {
        $kolab = $this->kolabWithOpportunity();
        $application = Application::factory()->accepted()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
        ]);
        $collaboration = Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $kolab->id,
            'application_id' => $application->id,
            'creator_profile_id' => $kolab->creator_profile_id,
            'scheduled_date' => now()->addDays(7),
        ]);

        $collaboration->reviews()->create([
            'reviewer_profile_id' => $kolab->creator_profile_id,
            'reviewed_profile_id' => $application->applicant_profile_id,
            'reviewer_role' => 'business',
            'rating' => 5,
            'body' => 'Smooth, brought 30 people.',
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.edit', $kolab));

        $response->assertOk();
        $response->assertSee('Lifecycle');
        $response->assertSee('Collaboration');
        $response->assertSee('Smooth, brought 30 people.', false);
        $response->assertSee('Force-cancel', false);
    }

    public function test_edit_page_with_no_collaboration_shows_empty_state(): void
    {
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.edit', $kolab))
            ->assertOk()
            ->assertSee('No collaboration yet', false);
    }

    public function test_maintainer_can_force_cancel_an_active_collaboration(): void
    {
        $kolab = $this->kolabWithOpportunity();
        $application = Application::factory()->accepted()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
        ]);
        $collaboration = Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $kolab->id,
            'application_id' => $application->id,
            'creator_profile_id' => $kolab->creator_profile_id,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.cancel', $kolab), ['reason' => 'Spam Kolab.'])
            ->assertRedirect(route('admin.kolabs.edit', $kolab));

        $this->assertSame(
            CollaborationStatus::Cancelled,
            $collaboration->fresh()->status,
        );
    }

    public function test_force_cancel_requires_a_reason(): void
    {
        $kolab = $this->kolabWithOpportunity();
        Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $kolab->id,
            'application_id' => Application::factory()->accepted()->create([
                'collab_opportunity_id' => $kolab->id,
                'applicant_profile_id' => Profile::factory()->community(),
            ])->id,
            'creator_profile_id' => $kolab->creator_profile_id,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.cancel', $kolab), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_force_cancel_404s_when_no_collaboration_exists(): void
    {
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.kolabs.collaboration.cancel', $kolab), ['reason' => 'noop'])
            ->assertNotFound();
    }

    public function test_maintainer_can_edit_a_kolab(): void
    {
        $kolab = Kolab::factory()->create([
            'title' => 'Old title',
            'status' => KolabStatus::Draft,
        ]);

        $payload = [
            'title' => 'New title',
            'description' => $kolab->description,
            'preferred_city' => 'Barcelona',
            'area' => 'El Born',
            'status' => 'published',
            'offer_headline' => null,
            'base_offer' => null,
        ];

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.kolabs.update', $kolab), $payload)
            ->assertRedirect(route('admin.kolabs.edit', $kolab));

        $kolab->refresh();
        $this->assertSame('New title', $kolab->title);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertNotNull($kolab->published_at);
    }

    public function test_update_validates_required_fields(): void
    {
        $kolab = Kolab::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.kolabs.update', $kolab), [])
            ->assertSessionHasErrors(['title', 'description', 'preferred_city', 'status']);
    }

    public function test_maintainer_can_delete_a_kolab(): void
    {
        $kolab = Kolab::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.kolabs.destroy', $kolab))
            ->assertRedirect(route('admin.kolabs.index'));

        $this->assertDatabaseMissing('kolabs', ['id' => $kolab->id]);
    }

    public function test_non_maintainer_cannot_touch_kolabs(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);
        Kolab::factory()->create();

        $this->actingAs($user, 'admin')
            ->get(route('admin.kolabs.index'))
            ->assertForbidden();
    }
}
