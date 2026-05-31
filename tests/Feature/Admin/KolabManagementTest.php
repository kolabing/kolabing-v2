<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_maintainer_sees_kolab_list(): void
    {
        $kolab = Kolab::factory()->create(['title' => 'Beach club takeover']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index'))
            ->assertOk()
            ->assertSee('Beach club takeover');
    }

    public function test_index_filters_by_status(): void
    {
        Kolab::factory()->create(['status' => KolabStatus::Draft, 'title' => 'Draft Kolab Alpha']);
        Kolab::factory()->published()->create(['title' => 'Published Kolab Beta']);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.kolabs.index', ['status' => 'published']));

        $response->assertSee('Published Kolab Beta');
        $response->assertDontSee('Draft Kolab Alpha');
    }

    public function test_maintainer_can_edit_a_kolab(): void
    {
        $kolab = Kolab::factory()->create([
            'title' => 'Old title',
            'status' => KolabStatus::Draft,
        ]);

        $payload = [
            'title' => 'New title',
            'description' => $kolab->description,
            'preferred_city' => 'Barcelona',
            'area' => 'El Born',
            'status' => 'published',
            'offer_headline' => null,
            'base_offer' => null,
        ];

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.kolabs.update', $kolab), $payload)
            ->assertRedirect(route('admin.kolabs.edit', $kolab));

        $kolab->refresh();
        $this->assertSame('New title', $kolab->title);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertNotNull($kolab->published_at);
    }

    public function test_update_validates_required_fields(): void
    {
        $kolab = Kolab::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.kolabs.update', $kolab), [])
            ->assertSessionHasErrors(['title', 'description', 'preferred_city', 'status']);
    }

    public function test_maintainer_can_delete_a_kolab(): void
    {
        $kolab = Kolab::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.kolabs.destroy', $kolab))
            ->assertRedirect(route('admin.kolabs.index'));

        $this->assertDatabaseMissing('kolabs', ['id' => $kolab->id]);
    }

    public function test_non_maintainer_cannot_touch_kolabs(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);
        $kolab = Kolab::factory()->create();

        $this->actingAs($user, 'admin')
            ->get(route('admin.kolabs.index'))
            ->assertForbidden();
    }
}
