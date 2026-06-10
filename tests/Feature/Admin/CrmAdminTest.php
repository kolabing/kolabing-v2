<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use App\Models\User;
use Database\Seeders\CrmSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_crm_index_renders_for_each_type(): void
    {
        (new CrmSeeder)->run();
        $admin = $this->maintainer();

        foreach (['business', 'community', 'ambassador'] as $type) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.crm.index', ['type' => $type]))
                ->assertOk();
        }
    }

    public function test_tasks_index_renders(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index'))
            ->assertOk();
    }

    public function test_save_columns_persists_and_create_works(): void
    {
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.crm.columns'), ['type' => 'business', 'columns' => ['status', 'score', 'email']])
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.crm.store'), [
                'type' => 'business', 'name' => 'Test Biz', 'owner' => 'Maria',
                'next_action' => 'Send deck',
                'metrics' => ['active_ig' => '1', 'good_fit' => '1'],
            ])->assertRedirect();

        $biz = CrmAccount::query()->where('name', 'Test Biz')->first();
        $this->assertSame(30, $biz->score);                 // active_ig 10 + good_fit 20
        $this->assertSame(1, $biz->tasks()->count());        // next-action synced to a task
        $this->assertSame('Send deck', $biz->tasks()->first()->title);
    }

    public function test_ambassador_task_rejected_under_dev(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.tasks.store'), [
                'title' => 'x', 'area' => 'dev', 'subarea' => 'ambassadors', 'status' => 'open',
            ])->assertSessionHasErrors('subarea');
    }
}
