<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmExportFirstTouchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function community(string $name, string $stage = 'Target', array $metrics = []): CrmAccount
    {
        return CrmAccount::query()->create([
            'type' => 'community',
            'name' => $name,
            'status' => $stage,
            'owner' => 'Neil',
            'score' => 70,
            'metrics' => array_merge(['city' => 'Madrid', 'confidence' => 'High', 'audience' => '7,124 IG followers'], $metrics),
        ]);
    }

    public function test_export_streams_csv_of_communities(): void
    {
        $this->community('Volta Run Club', 'Contacted');

        $res = $this->actingAs($this->maintainer(), 'admin')->get(route('admin.crm.export'));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('content-type'));

        $csv = $res->streamedContent();
        $this->assertStringContainsString('Name,City,Type,Audience,Confidence,Fit,Stage', $csv);
        $this->assertStringContainsString('Volta Run Club', $csv);
        $this->assertStringContainsString('Contacted', $csv);
    }

    public function test_export_respects_the_city_filter(): void
    {
        $this->community('Madrid Runners XT', 'Target', ['city' => 'Madrid']);
        $this->community('Berlin Runners XT', 'Target', ['city' => 'Berlin']);

        $csv = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.crm.export', ['city' => 'Madrid']))->streamedContent();

        $this->assertStringContainsString('Madrid Runners XT', $csv);
        $this->assertStringNotContainsString('Berlin Runners XT', $csv);
    }

    public function test_first_touch_logs_and_advances_a_target_lead(): void
    {
        $account = $this->community('Volta Run Club', 'Target');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.crm.first-touch', $account))
            ->assertRedirect(route('admin.crm.show', $account));

        $this->assertSame('Contacted', $account->fresh()->status);
        $this->assertDatabaseHas('crm_activities', ['crm_account_id' => $account->id, 'type' => 'first_touch']);
        $this->assertDatabaseHas('crm_activities', ['crm_account_id' => $account->id, 'type' => 'stage_change']);
    }

    public function test_first_touch_on_a_later_stage_logs_but_does_not_move(): void
    {
        $account = $this->community('Volta Run Club', 'Interested');

        $this->actingAs($this->maintainer(), 'admin')->post(route('admin.crm.first-touch', $account));

        $this->assertSame('Interested', $account->fresh()->status);
        $this->assertDatabaseHas('crm_activities', ['crm_account_id' => $account->id, 'type' => 'first_touch']);
        $this->assertSame(0, $account->activities()->where('type', 'stage_change')->count());
    }

    public function test_the_detail_page_shows_a_personalised_first_touch_draft(): void
    {
        $account = $this->community('Volta Run Club', 'Target', ['collab_businesses' => 'Electrolit (@electrolit_es)']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.crm.show', $account))
            ->assertOk()
            ->assertSee('First-touch draft')
            ->assertSee('Loved seeing your work with Electrolit');
    }
}
