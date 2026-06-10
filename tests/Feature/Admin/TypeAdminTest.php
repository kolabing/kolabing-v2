<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\CommunityType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TypeAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_index_renders_for_both_kinds(): void
    {
        $admin = $this->maintainer();
        foreach (['community', 'business'] as $kind) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.types.index', ['kind' => $kind]))->assertOk();
        }
    }

    public function test_create_type_with_slug_from_name(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.types.store'), ['kind' => 'community', 'name' => 'Surf Club', 'icon' => 'waves', 'is_active' => '1'])
            ->assertRedirect();

        $type = CommunityType::query()->where('name', 'Surf Club')->first();
        $this->assertSame('surf_club', $type->slug);   // auto-slug, underscore
        $this->assertSame('waves', $type->icon);
        $this->assertTrue($type->is_active);
    }

    public function test_destroy_deactivates_when_in_use_but_deletes_when_unused(): void
    {
        $admin = $this->maintainer();

        // In use → deactivate, not delete.
        $used = BusinessType::query()->create(['slug' => 'used_type', 'name' => 'Used', 'is_active' => true]);
        BusinessProfile::factory()->create(['business_type' => 'used_type']);
        $this->actingAs($admin, 'admin')
            ->delete(route('admin.types.destroy', ['kind' => 'business', 'id' => $used->id]))->assertRedirect();
        $this->assertDatabaseHas('business_types', ['id' => $used->id, 'is_active' => false]);

        // Unused → hard delete.
        $unused = BusinessType::query()->create(['slug' => 'unused_type', 'name' => 'Unused', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->delete(route('admin.types.destroy', ['kind' => 'business', 'id' => $unused->id]))->assertRedirect();
        $this->assertDatabaseMissing('business_types', ['id' => $unused->id]);
    }

    public function test_toggle_and_reorder(): void
    {
        $admin = $this->maintainer();
        $a = CommunityType::query()->create(['slug' => 'aaa', 'name' => 'AAA', 'sort_order' => 1, 'is_active' => true]);
        $b = CommunityType::query()->create(['slug' => 'bbb', 'name' => 'BBB', 'sort_order' => 2, 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.types.toggle', ['kind' => 'community', 'id' => $a->id]))->assertRedirect();
        $this->assertFalse($a->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.types.reorder', ['kind' => 'community']), ['order' => [$b->id, $a->id]])->assertRedirect();
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }
}
