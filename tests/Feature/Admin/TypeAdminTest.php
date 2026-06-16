<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessType;
use App\Models\CommunityType;
use App\Models\Icon;
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
        foreach (['business', 'community'] as $kind) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.types.index', ['kind' => $kind]))->assertOk();
        }
    }

    public function test_business_index_shows_applies_to_column(): void
    {
        $admin = $this->maintainer();
        BusinessType::query()->create([
            'name' => 'Cafe', 'slug' => 'cafe', 'applies_to' => 'venue', 'is_active' => true,
        ]);

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
        $type = BusinessType::query()->create([
            'name' => 'Cafe', 'slug' => 'cafe', 'applies_to' => 'product', 'is_active' => true,
        ]);

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
        $type = CommunityType::query()->create([
            'name' => 'Run Club', 'slug' => 'run_club', 'is_active' => true,
        ]);

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
        $type = BusinessType::query()->create([
            'name' => 'Cafe', 'slug' => 'cafe', 'applies_to' => 'both', 'is_active' => true,
        ]);

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
        $type = CommunityType::query()->create([
            'name' => 'Run Club', 'slug' => 'run_club', 'is_active' => true,
        ]);

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
        $type = BusinessType::query()->create([
            'name' => 'Cafe', 'slug' => 'cafe', 'applies_to' => 'both', 'is_active' => true,
        ]);

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
}
