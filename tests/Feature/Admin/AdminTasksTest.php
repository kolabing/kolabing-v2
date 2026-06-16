<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AdminTasksTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function nonMaintainer(): User
    {
        return User::factory()->create(['is_maintainer' => false]);
    }

    private function taskData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Send deck',
            'area' => 'sales',
            'subarea' => 'business',
            'status' => 'open',
            'assignee' => 'maria',
            'due_on' => now()->addDays(7)->toDateString(),
            'notes' => null,
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | Auth guard
    |--------------------------------------------------------------------------
    */

    public function test_index_rejects_non_maintainer(): void
    {
        $this->actingAs($this->nonMaintainer(), 'admin')
            ->get(route('admin.tasks.index'))
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Index & filters
    |--------------------------------------------------------------------------
    */

    public function test_index_defaults_to_active_status(): void
    {
        CrmTask::factory()->create(['status' => 'open', 'title' => 'Open Task']);
        CrmTask::factory()->create(['status' => 'doing', 'title' => 'Doing Task']);
        CrmTask::factory()->create(['status' => 'done', 'title' => 'Done Task']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index'));

        $response->assertOk()
            ->assertSee('Open Task')
            ->assertSee('Doing Task')
            ->assertDontSee('Done Task');
    }

    public function test_index_filters_by_status(): void
    {
        CrmTask::factory()->create(['status' => 'open', 'title' => 'Open Task']);
        CrmTask::factory()->create(['status' => 'done', 'title' => 'Done Task']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index', ['status' => 'done']));

        $response->assertOk()
            ->assertSee('Done Task')
            ->assertDontSee('Open Task');
    }

    public function test_index_filters_by_assignee(): void
    {
        CrmTask::factory()->create(['assignee' => 'alice', 'title' => 'Alice Task', 'status' => 'open']);
        CrmTask::factory()->create(['assignee' => 'bob', 'title' => 'Bob Task', 'status' => 'open']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index', ['assignee' => 'alice']));

        $response->assertOk()
            ->assertSee('Alice Task')
            ->assertDontSee('Bob Task');
    }

    public function test_index_filters_by_area(): void
    {
        CrmTask::factory()->create(['area' => 'sales', 'title' => 'Sales Task', 'status' => 'open']);
        CrmTask::factory()->create(['area' => 'dev', 'subarea' => 'business', 'title' => 'Dev Task', 'status' => 'open']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index', ['area' => 'sales']));

        $response->assertOk()
            ->assertSee('Sales Task')
            ->assertDontSee('Dev Task');
    }

    public function test_overdue_task_with_past_due_on_appears_in_index(): void
    {
        $overdue = CrmTask::factory()->create([
            'title' => 'Overdue Task',
            'due_on' => now()->subDay()->toDateString(),
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.index'));

        $response->assertOk()->assertSee('Overdue Task');

        $this->assertTrue($overdue->fresh()->due_on->isPast());
    }

    /*
    |--------------------------------------------------------------------------
    | Create / Store
    |--------------------------------------------------------------------------
    */

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.create'))
            ->assertOk();
    }

    public function test_store_creates_task(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.tasks.store'), $this->taskData())
            ->assertRedirect(route('admin.tasks.index'));

        $this->assertDatabaseHas('crm_tasks', ['title' => 'Send deck', 'assignee' => 'maria']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.tasks.store'), [])
            ->assertSessionHasErrors(['title', 'area', 'subarea', 'status']);
    }

    public function test_store_rejects_ambassadors_task_under_dev_area(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.tasks.store'), $this->taskData([
                'area' => 'dev',
                'subarea' => 'ambassadors',
            ]))
            ->assertSessionHasErrors('subarea');
    }

    /*
    |--------------------------------------------------------------------------
    | Edit / Update
    |--------------------------------------------------------------------------
    */

    public function test_edit_page_renders(): void
    {
        $task = CrmTask::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.tasks.edit', $task))
            ->assertOk();
    }

    public function test_update_modifies_task(): void
    {
        $task = CrmTask::factory()->create(['title' => 'Old Title', 'area' => 'sales', 'subarea' => 'business', 'status' => 'open']);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.tasks.update', $task), $this->taskData(['title' => 'New Title', 'status' => 'doing']))
            ->assertRedirect(route('admin.tasks.index'));

        $this->assertDatabaseHas('crm_tasks', ['id' => $task->id, 'title' => 'New Title', 'status' => 'doing']);
    }

    /*
    |--------------------------------------------------------------------------
    | Destroy
    |--------------------------------------------------------------------------
    */

    public function test_destroy_deletes_task(): void
    {
        $task = CrmTask::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.tasks.destroy', $task))
            ->assertRedirect(route('admin.tasks.index'));

        $this->assertDatabaseMissing('crm_tasks', ['id' => $task->id]);
    }
}
