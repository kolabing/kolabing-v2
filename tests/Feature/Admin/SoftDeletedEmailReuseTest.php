<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * `Profile` uses SoftDeletes, but `Rule::unique('profiles', 'email')` does not apply
 * Eloquent's soft-delete scope on its own -- so a deleted profile's email was
 * permanently blocked from reuse forever, on quick-add, the full create form, AND
 * edit. The block was invisible everywhere else too: a soft-deleted profile doesn't
 * show in the admin list, so a maintainer would see "the email has already been
 * taken" for an email that shows as taken nowhere. Caught live 2026-09-14/15:
 * several delete attempts on Exploradores de Café left the profile soft-deleted,
 * silently blocking every recreate attempt afterward.
 */
class SoftDeletedEmailReuseTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_quick_add_allows_reusing_a_soft_deleted_profiles_email(): void
    {
        $old = Profile::factory()->business()->create(['email' => 'antonio@grupoexploradores.com']);
        $old->delete();
        $this->assertSoftDeleted('profiles', ['id' => $old->id]);

        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.quick-add.store'), [
            'user_type' => 'business',
            'name' => 'Exploradores de Café',
            'email' => 'antonio@grupoexploradores.com',
            'instagram' => 'exploradoresdecafe',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect();

        // Two rows now share the email: the trashed original and the fresh one --
        // the fresh one is the only one any live query (or a human in the admin
        // list) will ever see.
        $this->assertSame(2, Profile::withTrashed()->where('email', 'antonio@grupoexploradores.com')->count());
        $fresh = Profile::where('email', 'antonio@grupoexploradores.com')->whereNull('deleted_at')->first();
        $this->assertNotNull($fresh);
        $this->assertSame('Exploradores de Café', $fresh->businessProfile->name);
    }

    public function test_the_full_create_form_allows_reusing_a_soft_deleted_profiles_email(): void
    {
        $old = Profile::factory()->create(['email' => 'reused@example.com']);
        $old->delete();

        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.store'), [
            'user_type' => 'business',
            'email' => 'reused@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_editing_a_live_profile_to_a_soft_deleted_emails_address_is_allowed(): void
    {
        $old = Profile::factory()->create(['email' => 'reused2@example.com']);
        $old->delete();
        $live = Profile::factory()->create();

        $response = $this->actingAs($this->maintainer(), 'admin')->put(route('admin.users.update', $live), [
            'email' => 'reused2@example.com',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('reused2@example.com', $live->fresh()->email);
    }

    public function test_email_still_correctly_blocked_against_a_live_non_deleted_profile(): void
    {
        Profile::factory()->create(['email' => 'still-taken@example.com']);

        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.quick-add.store'), [
            'user_type' => 'business',
            'name' => 'Someone Else',
            'email' => 'still-taken@example.com',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
