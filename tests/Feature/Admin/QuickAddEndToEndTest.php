<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\AdminProfileWelcomeMail;
use App\Models\AdminWelcomeEmailTemplate;
use App\Models\Profile;
use App\Models\User;
use App\Support\PublicProfileLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * One real, full-journey regression test covering every step a maintainer takes to
 * list a business and send its welcome email -- form load, submit, edit-page load,
 * preview load, send, public URL resolution. Added 2026-09-14 after three separate
 * live failures in this exact flow (quick-add 500, email-template 500, quick-add
 * 500 again) each slipped through because every existing test covered one step in
 * isolation. This test exists so "does the whole thing actually work" has a single
 * answer, not an inference from N passing unit-ish tests.
 */
class QuickAddEndToEndTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_full_quick_add_to_send_journey_works_end_to_end(): void
    {
        Mail::fake();
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'es', 'label' => 'Español']);
        $maintainer = User::factory()->create(['is_maintainer' => true]);

        // Step 1: the quick-add form must actually load.
        $this->actingAs($maintainer, 'admin')
            ->get(route('admin.users.quick-add'))
            ->assertOk();

        // Step 2: submit real data.
        $store = $this->actingAs($maintainer, 'admin')->post(route('admin.users.quick-add.store'), [
            'user_type' => 'business',
            'name' => 'Exploradores de Café',
            'email' => 'antonio@grupoexploradores.com',
            'instagram' => 'exploradoresdecafe',
        ]);
        $store->assertSessionDoesntHaveErrors();
        $store->assertRedirect();

        $profile = Profile::where('email', 'antonio@grupoexploradores.com')->first();
        $this->assertNotNull($profile, 'Profile was NOT created.');
        $this->assertSame('Exploradores de Café', $profile->businessProfile->name);

        // Step 3: the edit page (where the redirect lands) must load, with the public-profile link.
        $this->actingAs($maintainer, 'admin')
            ->get(route('admin.users.edit', $profile))
            ->assertOk()
            ->assertSee('View public profile', false);

        // Step 4: preview, then send, in a real chosen language.
        $this->actingAs($maintainer, 'admin')
            ->get(route('admin.users.welcome-email-preview', $profile).'?locale=es')
            ->assertOk()
            ->assertSee('Exploradores de Café', false);

        $send = $this->actingAs($maintainer, 'admin')
            ->post(route('admin.users.send-welcome-email', $profile), ['locale' => 'es']);
        $send->assertRedirect(route('admin.users.edit', $profile));
        $send->assertSessionDoesntHaveErrors();

        Mail::assertQueued(AdminProfileWelcomeMail::class, fn (AdminProfileWelcomeMail $m) => $m->hasTo('antonio@grupoexploradores.com'));

        // Step 5: the public profile URL actually resolves back to this exact profile.
        $resolved = PublicProfileLink::resolve(PublicProfileLink::slugFor($profile));
        $this->assertSame($profile->id, $resolved?->id);
    }
}
