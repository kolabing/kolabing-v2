<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Profile;
use App\Models\User;
use App\Services\Admin\ManagedProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bulk activate / deactivate on /admin/users (#256).
 */
class BulkProfileActiveTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_a_maintainer_can_deactivate_a_selection(): void
    {
        $picked = Profile::factory()->count(3)->create();
        $untouched = Profile::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.bulk-deactivate'), [
                'profile_ids' => $picked->pluck('id')->all(),
            ])
            ->assertRedirect();

        foreach ($picked as $profile) {
            $this->assertFalse($profile->fresh()->is_active);
        }

        $this->assertTrue($untouched->fresh()->is_active, 'A row nobody ticked must not change.');
    }

    public function test_bulk_deactivate_revokes_every_selected_token(): void
    {
        $picked = Profile::factory()->count(2)->create();
        $untouched = Profile::factory()->create();

        foreach ($picked as $profile) {
            $profile->createToken('mobile');
            $profile->createToken('refresh', ['refresh']);
        }
        $untouched->createToken('mobile');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.bulk-deactivate'), [
                'profile_ids' => $picked->pluck('id')->all(),
            ]);

        foreach ($picked as $profile) {
            $this->assertSame(0, $profile->tokens()->count());
        }

        $this->assertSame(1, $untouched->tokens()->count(), 'An unselected account keeps its session.');
    }

    public function test_bulk_deactivate_is_two_statements_however_many_rows(): void
    {
        // The whole point of the bulk path: looping the single-row method would
        // issue an UPDATE and a DELETE per account.
        $profiles = Profile::factory()->count(10)->create();
        foreach ($profiles as $profile) {
            $profile->createToken('mobile');
        }

        $writes = 0;
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(update|delete)/i', $query->sql) === 1) {
                $writes++;
            }
        });

        app(ManagedProfileService::class)->deactivateMany($profiles->pluck('id')->all());

        $this->assertSame(2, $writes, "Expected one UPDATE and one DELETE, got {$writes} write statements.");
    }

    public function test_a_maintainer_can_activate_a_selection(): void
    {
        $profiles = Profile::factory()->count(3)->create();
        app(ManagedProfileService::class)->deactivateMany($profiles->pluck('id')->all());

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.bulk-activate'), [
                'profile_ids' => $profiles->pluck('id')->all(),
            ])
            ->assertRedirect();

        foreach ($profiles as $profile) {
            $this->assertTrue($profile->fresh()->is_active);
        }
    }

    public function test_the_count_reported_is_what_changed_not_what_was_ticked(): void
    {
        $alreadyOff = Profile::factory()->create();
        $live = Profile::factory()->create();
        app(ManagedProfileService::class)->deactivateMany([$alreadyOff->id]);

        $changed = app(ManagedProfileService::class)
            ->deactivateMany([$alreadyOff->id, $live->id]);

        $this->assertSame(1, $changed, 'Re-selecting an already passive account must not inflate the count.');
    }

    public function test_an_unknown_id_fails_the_whole_request(): void
    {
        // Partial application is the dangerous outcome: an admin who thinks they
        // switched three off must not have switched two.
        $profiles = Profile::factory()->count(2)->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.users.index'))
            ->post(route('admin.users.bulk-deactivate'), [
                'profile_ids' => [...$profiles->pluck('id')->all(), '01a00000-0000-7000-8000-000000000000'],
            ])
            ->assertSessionHasErrors('profile_ids.2');

        foreach ($profiles as $profile) {
            $this->assertTrue($profile->fresh()->is_active, 'Nothing may change when validation fails.');
        }
    }

    public function test_an_empty_selection_is_rejected(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.users.index'))
            ->post(route('admin.users.bulk-deactivate'), ['profile_ids' => []])
            ->assertSessionHasErrors('profile_ids');
    }

    public function test_bulk_actions_are_maintainer_only(): void
    {
        $profile = Profile::factory()->create();

        $this->post(route('admin.users.bulk-deactivate'), ['profile_ids' => [$profile->id]])
            ->assertRedirect();
        $this->assertTrue($profile->fresh()->is_active);

        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.users.bulk-deactivate'), ['profile_ids' => [$profile->id]])
            ->assertForbidden();

        $this->assertTrue($profile->fresh()->is_active);
    }

    public function test_a_duplicated_id_is_applied_once(): void
    {
        $profile = Profile::factory()->create();

        $changed = app(ManagedProfileService::class)
            ->deactivateMany([$profile->id, $profile->id]);

        $this->assertSame(1, $changed);
        $this->assertFalse($profile->fresh()->is_active);
    }

    public function test_the_page_renders_the_bulk_controls(): void
    {
        Profile::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->assertStatus(200)
            ->assertSee('Activate selected')
            ->assertSee('Deactivate selected')
            ->assertSee('bulk-select-all')
            ->assertSee('profile_ids[]', false);
    }

    public function test_the_row_forms_are_not_nested_inside_the_bulk_form(): void
    {
        // HTML forbids nested forms; a browser silently drops the inner one, which
        // would make every per-row button post the bulk action instead.
        Profile::factory()->create();

        $html = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->getContent();

        $bulkStart = strpos($html, 'id="bulk-users-form"');
        $bulkEnd = strpos($html, '</form>', (int) $bulkStart);
        $inner = substr($html, (int) $bulkStart, (int) $bulkEnd - (int) $bulkStart);

        $this->assertStringNotContainsString('<form', $inner, 'A row form ended up inside the bulk form.');
    }
}
