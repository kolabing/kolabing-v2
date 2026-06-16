<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Kolab;
use App\Models\OfferOption;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OfferOptionAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_index_renders_for_all_kinds(): void
    {
        $admin = $this->maintainer();
        foreach (OfferOption::KINDS as $kind) {
            $this->actingAs($admin, 'admin')
                ->get(route('admin.offer-options.index', ['kind' => $kind]))->assertOk();
        }
    }

    public function test_create_option_with_slug_from_name(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.offer-options.store'), [
                'kind' => OfferOption::KIND_OFFERING,
                'name' => 'Gift Card',
                'icon' => 'gift',
                'is_active' => '1',
            ])->assertRedirect();

        $option = OfferOption::query()->where('name', 'Gift Card')->first();
        $this->assertNotNull($option);
        $this->assertSame('gift_card', $option->slug); // auto-slug, underscore
        $this->assertSame(OfferOption::KIND_OFFERING, $option->kind);
        $this->assertSame('gift', $option->icon);
        $this->assertTrue($option->is_active);
    }

    public function test_edit_form_renders_lucide_icon_gallery_picker(): void
    {
        $admin = $this->maintainer();
        $option = OfferOption::query()->create([
            'kind' => OfferOption::KIND_OFFERING, 'slug' => 'coffee_opt', 'name' => 'Coffee', 'icon' => 'coffee', 'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.offer-options.edit', ['kind' => OfferOption::KIND_OFFERING, 'id' => $option->id]))
            ->assertOk()
            ->assertSee('id="iconGallery"', false)
            ->assertSee('data-lucide="coffee"', false)   // the saved icon is rendered + pre-selected
            ->assertSee('id="iconFilter"', false)         // searchable filter box
            ->assertSee('id="iconManual"', false);        // advanced fallback text field
    }

    public function test_update_persists_icon_slug_chosen_in_gallery(): void
    {
        $admin = $this->maintainer();
        $option = OfferOption::query()->create([
            'kind' => OfferOption::KIND_OFFERING, 'slug' => 'opt_icon', 'name' => 'Opt', 'icon' => 'star', 'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.offer-options.update', ['kind' => OfferOption::KIND_OFFERING, 'id' => $option->id]), [
                'kind' => OfferOption::KIND_OFFERING,
                'name' => 'Opt',
                'slug' => 'opt_icon',
                'icon' => 'wine',
                'is_active' => '1',
            ])->assertRedirect();

        $this->assertSame('wine', $option->fresh()->icon);
    }

    public function test_same_slug_allowed_across_kinds_but_not_within_a_kind(): void
    {
        $admin = $this->maintainer();

        // "venue" exists as both an offering and a need by design.
        OfferOption::query()->create(['kind' => OfferOption::KIND_OFFERING, 'slug' => 'venue', 'name' => 'Venue', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->post(route('admin.offer-options.store'), ['kind' => OfferOption::KIND_NEED, 'name' => 'Venue', 'slug' => 'venue'])
            ->assertRedirect();
        $this->assertSame(1, OfferOption::query()->where('kind', OfferOption::KIND_NEED)->where('slug', 'venue')->count());

        // Duplicate within the same kind is rejected.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.offer-options.store'), ['kind' => OfferOption::KIND_NEED, 'name' => 'Venue 2', 'slug' => 'venue'])
            ->assertSessionHasErrors('slug');
    }

    public function test_destroy_deactivates_when_in_use_but_deletes_when_unused(): void
    {
        $admin = $this->maintainer();

        // In use (referenced by a kolab's offering[]) → deactivate, not delete.
        $used = OfferOption::query()->create(['kind' => OfferOption::KIND_OFFERING, 'slug' => 'used_opt', 'name' => 'Used', 'is_active' => true]);
        Kolab::factory()->create(['offering' => ['used_opt', 'other']]);

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.offer-options.destroy', ['kind' => OfferOption::KIND_OFFERING, 'id' => $used->id]))->assertRedirect();
        $this->assertDatabaseHas('offer_options', ['id' => $used->id, 'is_active' => false]);

        // Unused → hard delete.
        $unused = OfferOption::query()->create(['kind' => OfferOption::KIND_OFFERING, 'slug' => 'unused_opt', 'name' => 'Unused', 'is_active' => true]);
        $this->actingAs($admin, 'admin')
            ->delete(route('admin.offer-options.destroy', ['kind' => OfferOption::KIND_OFFERING, 'id' => $unused->id]))->assertRedirect();
        $this->assertDatabaseMissing('offer_options', ['id' => $unused->id]);
    }

    public function test_toggle_and_reorder(): void
    {
        $admin = $this->maintainer();
        $a = OfferOption::query()->create(['kind' => OfferOption::KIND_NEED, 'slug' => 'aaa', 'name' => 'AAA', 'sort_order' => 1, 'is_active' => true]);
        $b = OfferOption::query()->create(['kind' => OfferOption::KIND_NEED, 'slug' => 'bbb', 'name' => 'BBB', 'sort_order' => 2, 'is_active' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.offer-options.toggle', ['kind' => OfferOption::KIND_NEED, 'id' => $a->id]))->assertRedirect();
        $this->assertFalse($a->fresh()->is_active);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.offer-options.reorder', ['kind' => OfferOption::KIND_NEED]), ['order' => [$b->id, $a->id]])->assertRedirect();
        $this->assertSame(1, $b->fresh()->sort_order);
        $this->assertSame(2, $a->fresh()->sort_order);
    }
}
