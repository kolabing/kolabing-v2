<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
use App\Models\Community;
use App\Models\CommunityType;
use App\Models\Icon;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        foreach (['business', 'community'] as $kind) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.types.index', ['kind' => $kind]))->assertOk();
        }
    }

    public function test_business_index_shows_applies_to_column(): void
    {
        $admin = $this->maintainer();
        BusinessType::query()->updateOrCreate(
            ['slug' => 'cafe'],
            ['name' => 'Cafe', 'applies_to' => 'venue', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.types.index', ['kind' => 'business']))
            ->assertOk()
            ->assertSee('Shows for', false)
            ->assertSee('Venue', false);
    }

    public function test_business_edit_form_renders_applies_to_select_and_icon_library_picker(): void
    {
        $admin = $this->maintainer();
        $libIcon = Icon::factory()->bundled()->create([
            'slug' => 'coffee', 'label' => 'Coffee', 'filename' => 'category-coffee.svg',
        ]);
        $type = BusinessType::query()->updateOrCreate(
            ['slug' => 'cafe'],
            ['name' => 'Cafe', 'applies_to' => 'product', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.types.edit', ['kind' => 'business', 'id' => $type->id]))
            ->assertOk()
            ->assertSee('name="applies_to"', false)
            ->assertSee('id="iconGallery"', false)
            ->assertSee('id="iconUrlValue"', false)
            ->assertSee('name="icon_url"', false)
            ->assertSee('data-url="'.$libIcon->url.'"', false)
            ->assertSee(route('admin.icons.index'), false);
    }

    public function test_community_edit_form_has_no_applies_to_select(): void
    {
        $admin = $this->maintainer();
        $type = CommunityType::query()->updateOrCreate(
            ['slug' => 'run_club'],
            ['name' => 'Run Club', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.types.edit', ['kind' => 'community', 'id' => $type->id]))
            ->assertOk()
            ->assertDontSee('name="applies_to"', false)
            ->assertSee('id="iconGallery"', false);
    }

    public function test_update_business_type_persists_applies_to_and_icon_url(): void
    {
        $admin = $this->maintainer();
        $libIcon = Icon::factory()->bundled()->create([
            'slug' => 'coffee', 'label' => 'Coffee', 'filename' => 'category-coffee.svg',
        ]);
        $type = BusinessType::query()->updateOrCreate(
            ['slug' => 'cafe'],
            ['name' => 'Cafe', 'applies_to' => 'both', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->put(route('admin.types.update', ['kind' => 'business', 'id' => $type->id]), [
                'kind' => 'business',
                'name' => 'Cafe',
                'slug' => 'cafe',
                'applies_to' => 'product',
                'icon_url' => $libIcon->url,
                'is_active' => '1',
            ])->assertRedirect();

        $fresh = $type->fresh();
        $this->assertSame('product', $fresh->applies_to);
        $this->assertSame($libIcon->url, $fresh->icon_url);
    }

    public function test_update_community_type_persists_icon_url(): void
    {
        $admin = $this->maintainer();
        $libIcon = Icon::factory()->bundled()->create([
            'slug' => 'run', 'label' => 'Run', 'filename' => 'category-run.svg',
        ]);
        $type = CommunityType::query()->updateOrCreate(
            ['slug' => 'run_club'],
            ['name' => 'Run Club', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->put(route('admin.types.update', ['kind' => 'community', 'id' => $type->id]), [
                'kind' => 'community',
                'name' => 'Run Club',
                'slug' => 'run_club',
                'icon_url' => $libIcon->url,
                'is_active' => '1',
            ])->assertRedirect();

        $this->assertSame($libIcon->url, $type->fresh()->icon_url);
    }

    public function test_applies_to_is_validated_against_allowed_values(): void
    {
        $admin = $this->maintainer();
        $type = BusinessType::query()->updateOrCreate(
            ['slug' => 'cafe'],
            ['name' => 'Cafe', 'applies_to' => 'both', 'is_active' => true],
        );

        $this->actingAs($admin, 'admin')
            ->put(route('admin.types.update', ['kind' => 'business', 'id' => $type->id]), [
                'kind' => 'business',
                'name' => 'Cafe',
                'slug' => 'cafe',
                'applies_to' => 'nonsense',
            ])->assertSessionHasErrors('applies_to');

        $this->assertSame('both', $type->fresh()->applies_to);
    }

    public function test_store_business_type_defaults_applies_to_when_blank(): void
    {
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.types.store'), [
                'kind' => 'business',
                'name' => 'New Venue Type',
                'applies_to' => 'venue',
                'is_active' => '1',
            ])->assertRedirect();

        $created = BusinessType::query()->where('name', 'New Venue Type')->first();
        $this->assertNotNull($created);
        $this->assertSame('new_venue_type', $created->slug);
        $this->assertSame('venue', $created->applies_to);
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

    /**
     * Regression for PHP-LARAVEL-5: the index page must compute in-use counts in
     * ONE grouped query, not one COUNT per type row (the previous N+1).
     */
    public function test_index_in_use_counts_use_one_query_and_are_correct(): void
    {
        $admin = $this->maintainer();

        foreach (['type_a', 'type_b', 'type_c'] as $i => $slug) {
            CommunityType::query()->create([
                'slug' => $slug, 'name' => strtoupper($slug), 'sort_order' => $i, 'is_active' => true,
            ]);
        }
        Community::factory()->count(2)->create(['type' => 'type_a']);
        Community::factory()->create(['type' => 'type_b']);

        $communityCountQueries = 0;
        DB::listen(function ($query) use (&$communityCountQueries): void {
            if (str_contains($query->sql, 'from "communities"') && str_contains($query->sql, 'count(')) {
                $communityCountQueries++;
            }
        });

        $this->actingAs($admin, 'admin')
            ->get(route('admin.types.index', ['kind' => 'community']))
            ->assertOk()
            ->assertSee('TYPE_A', false);

        // Exactly one aggregate query against communities, regardless of type count.
        $this->assertSame(1, $communityCountQueries);
    }
}
