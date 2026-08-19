<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmPipelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function community(array $attrs = []): CrmAccount
    {
        return CrmAccount::query()->create(array_merge([
            'type' => 'community',
            'name' => 'Volta Run Club',
            'status' => 'Target',
            'owner' => 'Neil',
            'metrics' => ['city' => 'Madrid', 'confidence' => 'High', 'audience' => '7,124 IG followers'],
        ], $attrs));
    }

    public function test_show_renders_lead_detail(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.crm.show', $account))
            ->assertOk()
            ->assertSee('Volta Run Club')
            ->assertSee('Pipeline')
            ->assertSee('Activity');
    }

    public function test_moving_stage_updates_status_and_logs_an_activity(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.crm.stage', $account), ['stage' => 'Contacted'])
            ->assertRedirect(route('admin.crm.show', $account));

        $this->assertSame('Contacted', $account->fresh()->status);
        $this->assertDatabaseHas('crm_activities', [
            'crm_account_id' => $account->id,
            'type' => 'stage_change',
        ]);
        $activity = CrmActivity::query()->where('crm_account_id', $account->id)->first();
        $this->assertSame(['from' => 'Target', 'to' => 'Contacted'], $activity->meta);
        $this->assertNotNull($account->fresh()->last_activity_at);
    }

    public function test_a_lead_can_be_rejected_then_reopened(): void
    {
        $account = $this->community(['status' => 'Interested']);
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')->post(route('admin.crm.stage', $account), ['stage' => 'Rejected']);
        $this->assertSame('Rejected', $account->fresh()->status);

        $this->actingAs($admin, 'admin')->post(route('admin.crm.stage', $account), ['stage' => 'Target']);
        $this->assertSame('Target', $account->fresh()->status);
        $this->assertSame(2, CrmActivity::query()->where('crm_account_id', $account->id)->count());
    }

    public function test_an_unknown_stage_is_rejected(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.crm.show', $account))
            ->post(route('admin.crm.stage', $account), ['stage' => 'Bananas'])
            ->assertSessionHasErrors('stage');

        $this->assertSame('Target', $account->fresh()->status);
        $this->assertDatabaseCount('crm_activities', 0);
    }

    public function test_a_note_is_appended_to_the_timeline(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.crm.activity', $account), ['body' => 'Left a DM on Instagram.'])
            ->assertRedirect(route('admin.crm.show', $account));

        $this->assertDatabaseHas('crm_activities', [
            'crm_account_id' => $account->id,
            'type' => 'note',
            'body' => 'Left a DM on Instagram.',
        ]);
    }

    public function test_a_note_body_is_required(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.crm.show', $account))
            ->post(route('admin.crm.activity', $account), ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_the_index_links_a_community_to_its_detail_page(): void
    {
        $account = $this->community();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.crm.index', ['type' => 'community']))
            ->assertOk()
            ->assertSee(route('admin.crm.show', $account), false);
    }
}
