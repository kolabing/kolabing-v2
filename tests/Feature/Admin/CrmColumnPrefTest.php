<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AdminColumnPref;
use App\Models\CrmAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmColumnPrefTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_columns_persist_in_the_submitted_order(): void
    {
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')->post(route('admin.crm.columns'), [
            'type' => 'community',
            'columns' => ['score', 'city', 'audience'], // deliberately not catalog order; name omitted
        ])->assertRedirect();

        $pref = AdminColumnPref::query()->where('admin_id', $admin->id)->where('table_key', 'crm.community')->firstOrFail();
        // name is force-prepended; the rest keep the submitted order.
        $this->assertSame(['name', 'score', 'city', 'audience'], $pref->visible_columns);
    }

    public function test_the_saved_order_drives_the_table_header_order(): void
    {
        $admin = $this->maintainer();
        CrmAccount::query()->create(['type' => 'community', 'name' => 'Volta Run Club', 'status' => 'Target', 'metrics' => ['city' => 'Madrid']]);

        AdminColumnPref::query()->create([
            'admin_id' => $admin->id,
            'table_key' => 'crm.community',
            'visible_columns' => ['name', 'score', 'audience'],
        ]);

        // Fit header must appear before Audience header, mirroring the saved order.
        $this->actingAs($admin, 'admin')
            ->get(route('admin.crm.index', ['type' => 'community']))
            ->assertOk()
            ->assertSeeInOrder(['<th>Score</th>', '<th>Audience</th>'], false);
    }

    public function test_unknown_columns_are_dropped_from_the_pref(): void
    {
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')->post(route('admin.crm.columns'), [
            'type' => 'community',
            'columns' => ['city', 'not_a_real_column', 'score'],
        ]);

        $pref = AdminColumnPref::query()->where('admin_id', $admin->id)->where('table_key', 'crm.community')->firstOrFail();
        $this->assertSame(['name', 'city', 'score'], $pref->visible_columns);
    }
}
