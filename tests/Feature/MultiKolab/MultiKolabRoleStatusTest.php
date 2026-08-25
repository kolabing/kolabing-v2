<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\MultiKolabRoleStatus;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Task 10 addition — the organizer must be able to stop and resume
 * recruiting for a single role without hard-deleting it (deletion is
 * refused once an application has been accepted, and destroys applicant
 * history). The contract already has `MultiKolabRoleStatus::Closed` and the
 * Explore query (contract §13) already excludes anything not `open`; the
 * only thing missing was an API that can set it.
 */
class MultiKolabRoleStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ownedRole(Profile $owner, array $attributes = []): MultiKolabRole
    {
        $event = MultiKolabEvent::factory()->for($owner, 'creatorProfile')->create();

        return MultiKolabRole::factory()->for($event, 'event')->create($attributes);
    }

    public function test_organizer_can_close_an_open_role(): void
    {
        $owner = Profile::factory()->business()->create();
        $role = $this->ownedRole($owner);

        $this->actingAs($owner)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame(MultiKolabRoleStatus::Closed, $role->fresh()->status);
    }

    public function test_organizer_can_reopen_a_closed_role_with_remaining_capacity(): void
    {
        $owner = Profile::factory()->business()->create();
        $role = $this->ownedRole($owner, [
            'status' => MultiKolabRoleStatus::Closed,
            'positions_needed' => 2,
            'positions_filled' => 1,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'open'])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_reopening_a_role_without_remaining_capacity_is_refused(): void
    {
        $owner = Profile::factory()->business()->create();
        $role = $this->ownedRole($owner, [
            'status' => MultiKolabRoleStatus::Closed,
            'positions_needed' => 1,
            'positions_filled' => 1,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'open'])
            ->assertStatus(409)
            ->assertJsonPath('errors.role.0', 'role_capacity_exceeded');

        $this->assertSame(MultiKolabRoleStatus::Closed, $role->fresh()->status);
    }

    public function test_client_cannot_set_the_derived_filled_status(): void
    {
        $owner = Profile::factory()->business()->create();
        $role = $this->ownedRole($owner);

        $this->actingAs($owner)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'filled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(MultiKolabRoleStatus::Open, $role->fresh()->status);
    }

    public function test_a_non_owner_cannot_change_role_status(): void
    {
        $owner = Profile::factory()->business()->create();
        $stranger = Profile::factory()->community()->create();
        $role = $this->ownedRole($owner);

        $this->actingAs($stranger)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'closed'])
            ->assertStatus(403)
            ->assertJsonPath('errors.owner.0', 'not_owner');
    }

    public function test_a_closed_role_is_not_offered_in_the_explore_feed(): void
    {
        $owner = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()
            ->for($owner, 'creatorProfile')
            ->recruiting()
            ->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => \App\Enums\MultiKolabEligibleAccountType::Community,
        ]);
        $viewer = Profile::factory()->community()->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities')
            ->assertOk()
            ->assertJsonFragment(['id' => $role->id]);

        $this->actingAs($owner)
            ->patchJson("/api/v1/multi-kolab-roles/{$role->id}", ['status' => 'closed'])
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities')
            ->assertOk()
            ->assertJsonMissing(['id' => $role->id]);
    }
}
