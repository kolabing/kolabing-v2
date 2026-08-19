<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmBoardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function community(string $name, string $stage, array $metrics = []): CrmAccount
    {
        return CrmAccount::query()->create([
            'type' => 'community',
            'name' => $name,
            'status' => $stage,
            'owner' => 'Neil',
            'metrics' => array_merge(['city' => 'Madrid', 'confidence' => 'High'], $metrics),
        ]);
    }

    public function test_board_renders_with_a_column_per_stage(): void
    {
        $this->community('Volta Run Club', 'Target');
        $this->community('DEGENS Run Club', 'Contacted');

        $res = $this->actingAs($this->maintainer(), 'admin')->get(route('admin.crm.board'));

        $res->assertOk()->assertSee('Volta Run Club')->assertSee('DEGENS Run Club');
        foreach (CrmAccount::COMMUNITY_STAGES as $stage) {
            $res->assertSee($stage);
        }
    }

    public function test_dragging_a_card_moves_its_stage_via_json(): void
    {
        $account = $this->community('Volta Run Club', 'Target');

        $this->actingAs($this->maintainer(), 'admin')
            ->postJson(route('admin.crm.stage', $account), ['stage' => 'Interested'])
            ->assertOk()
            ->assertJson(['ok' => true, 'stage' => 'Interested']);

        $this->assertSame('Interested', $account->fresh()->status);
        $this->assertDatabaseHas('crm_activities', ['crm_account_id' => $account->id, 'type' => 'stage_change']);
    }

    public function test_a_bad_stage_over_json_is_rejected_without_moving(): void
    {
        $account = $this->community('Volta Run Club', 'Target');

        $this->actingAs($this->maintainer(), 'admin')
            ->postJson(route('admin.crm.stage', $account), ['stage' => 'Nope'])
            ->assertStatus(422);

        $this->assertSame('Target', $account->fresh()->status);
    }

    public function test_board_respects_the_city_filter(): void
    {
        $this->community('Madrid Runners XT', 'Target', ['city' => 'Madrid']);
        $this->community('Berlin Runners XT', 'Target', ['city' => 'Berlin']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.crm.board', ['city' => 'Madrid']))
            ->assertOk()
            ->assertSee('Madrid Runners XT')
            ->assertDontSee('Berlin Runners XT');
    }
}
