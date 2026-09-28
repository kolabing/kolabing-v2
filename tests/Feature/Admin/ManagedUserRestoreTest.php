<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Bug report 2026-09-28, item 11: admin Delete is a soft delete, but deleted
 * accounts were invisible in /admin/users and could not be undone, and the user
 * page showed neither the created date nor the account's kolabs, so a case like
 * LabTwentyTwo (live kolab on a deleted account) could not be diagnosed.
 */
class ManagedUserRestoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function business(string $name, string $email): Profile
    {
        $profile = Profile::factory()->business()->create(['email' => $email]);
        BusinessProfile::factory()->for($profile, 'profile')->create(['name' => $name]);

        return $profile;
    }

    public function test_deleted_filter_lists_only_soft_deleted_profiles(): void
    {
        $this->business('Live Venue', 'owner@livevenue.com');
        $gone = $this->business('Gone Venue', 'owner@gonevenue.com');
        $gone->delete();

        $admin = $this->actingAs($this->maintainer(), 'admin');

        $admin->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Live Venue', false)
            ->assertDontSee('Gone Venue', false)
            ->assertSee('Deleted accounts', false);

        $admin->get(route('admin.users.index', ['deleted' => 1]))
            ->assertOk()
            ->assertSee('Gone Venue', false)
            ->assertDontSee('Live Venue', false)
            ->assertSee(route('admin.users.restore', $gone), false);
    }

    public function test_restore_brings_a_deleted_profile_back(): void
    {
        $gone = $this->business('Gone Venue', 'owner@gonevenue.com');
        $gone->delete();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.restore', $gone))
            ->assertRedirect(route('admin.users.edit', $gone))
            ->assertSessionHas('status', 'Account restored.');

        $this->assertNotSoftDeleted('profiles', ['id' => $gone->id]);
    }

    public function test_restore_is_refused_when_the_email_now_belongs_to_a_live_profile(): void
    {
        $old = $this->business('LabTwentyTwo', 'hotel@labtwentytwo.com');
        $old->delete();
        $this->business('LabTwentyTwo', 'hotel@labtwentytwo.com');

        $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.users.index', ['deleted' => 1]))
            ->post(route('admin.users.restore', $old))
            ->assertRedirect(route('admin.users.index', ['deleted' => 1]))
            ->assertSessionHasErrors('restore');

        $this->assertSoftDeleted('profiles', ['id' => $old->id]);
        $this->assertStringContainsString(
            'hotel@labtwentytwo.com now belongs to another live account',
            session('errors')->first('restore'),
        );
    }

    public function test_restore_of_a_live_profile_is_refused(): void
    {
        $live = $this->business('Live Venue', 'owner@livevenue.com');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.restore', $live))
            ->assertSessionHasErrors('restore');
    }

    public function test_restore_requires_a_maintainer(): void
    {
        $gone = $this->business('Gone Venue', 'owner@gonevenue.com');
        $gone->delete();

        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.users.restore', $gone))
            ->assertForbidden();

        $this->assertSoftDeleted('profiles', ['id' => $gone->id]);
    }

    public function test_edit_page_shows_created_date_and_kolab_counts(): void
    {
        $profile = $this->business('Counted Venue', 'owner@countedvenue.com');
        Kolab::factory()->forCreator($profile)->published()->create();
        Kolab::factory()->forCreator($profile)->closed()->count(2)->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.edit', $profile))
            ->assertOk()
            ->assertSee('Created', false)
            ->assertSee($profile->created_at->toDayDateTimeString(), false)
            ->assertSee('Published: 1', false)
            ->assertSee('Closed: 2', false)
            ->assertSee($profile->id, false);
    }

    public function test_a_deleted_profiles_page_is_readable_and_offers_restore(): void
    {
        $gone = $this->business('Gone Venue', 'owner@gonevenue.com');
        Kolab::factory()->forCreator($gone)->published()->create();
        $gone->delete();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.edit', $gone))
            ->assertOk()
            ->assertSee('Restore account', false)
            ->assertSee('Published: 1', false)
            ->assertDontSee('Delete user', false);
    }
}
