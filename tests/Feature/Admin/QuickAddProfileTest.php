<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\AdminProfileWelcomeMail;
use App\Models\AdminWelcomeEmailTemplate;
use App\Models\City;
use App\Models\Profile;
use App\Models\User;
use App\Support\PublicProfileLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuickAddProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_maintainer_can_quick_add_a_business(): void
    {
        Mail::fake();
        $city = City::factory()->create(['is_active' => true]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside@example.com',
                'city_id' => $city->id,
                'instagram' => '@riverside',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside@example.com')->firstOrFail();

        $this->assertTrue($profile->isBusiness());
        $this->assertNotNull($profile->email_verified_at);
        $this->assertSame($city->id, $profile->businessProfile->city_id);
        $this->assertSame('Riverside Cafe', $profile->businessProfile->name);
        $this->assertSame('@riverside', $profile->businessProfile->instagram);

        // Sending is now a deliberate follow-up step, not a quick-add side effect —
        // Daniel 2026-09-14: outreach happens across languages, so a maintainer picks
        // the right one explicitly rather than an automatic send guessing.
        Mail::assertNothingQueued();
    }

    public function test_maintainer_can_quick_add_a_community_without_a_city(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'community',
                'name' => 'Run Club BCN',
                'email' => 'runclub@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'runclub@example.com')->firstOrFail();

        $this->assertTrue($profile->isCommunity());
        $this->assertNull($profile->communityProfile->city_id);
    }

    public function test_maintainer_can_send_the_welcome_email_in_a_chosen_language(): void
    {
        Mail::fake();
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'es', 'label' => 'Español']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside@example.com')->firstOrFail();

        Mail::assertNothingQueued();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.send-welcome-email', $profile), ['locale' => 'es'])
            ->assertRedirect(route('admin.users.edit', $profile));

        Mail::assertQueued(AdminProfileWelcomeMail::class, function (AdminProfileWelcomeMail $mail) use ($profile) {
            return $mail->hasTo($profile->email) && $mail->template->locale === 'es';
        });
    }

    public function test_preview_renders_the_real_content_and_sends_nothing(): void
    {
        // Daniel 2026-09-14: "i don't want them to get an unapproved email" — the preview
        // must show the real, recipient-specific rendered email without queuing anything.
        Mail::fake();
        AdminWelcomeEmailTemplate::factory()->create([
            'locale' => 'es',
            'intro_markdown' => 'Hola {{name}}, bienvenido.',
        ]);
        $profile = \App\Models\BusinessProfile::factory()->create(['name' => 'Exploradores de Café'])->profile;

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.welcome-email-preview', $profile).'?locale=es');

        $response->assertOk();
        $response->assertSee('Exploradores de Café', false);
        $response->assertSee('Hola Exploradores de Café, bienvenido.', false);

        Mail::assertNothingQueued();
    }

    public function test_preview_rejects_an_inactive_locale(): void
    {
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'de', 'is_active' => false]);
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.welcome-email-preview', $profile).'?locale=de')
            ->assertSessionHasErrors('locale');
    }

    public function test_preview_rejects_non_maintainer(): void
    {
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'en']);
        $profile = Profile::factory()->business()->create();
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.users.welcome-email-preview', $profile).'?locale=en')
            ->assertForbidden();
    }

    public function test_send_welcome_email_rejects_an_inactive_locale(): void
    {
        Mail::fake();
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'fr', 'is_active' => false]);
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.send-welcome-email', $profile), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        Mail::assertNothingQueued();
    }

    public function test_send_welcome_email_rejects_non_maintainer(): void
    {
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'en']);
        $profile = Profile::factory()->business()->create();
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->post(route('admin.users.send-welcome-email', $profile), ['locale' => 'en'])
            ->assertForbidden();
    }

    public function test_welcome_email_reset_link_actually_authenticates(): void
    {
        Mail::fake();
        AdminWelcomeEmailTemplate::factory()->create(['locale' => 'en', 'label' => 'English']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside@example.com')->firstOrFail();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.send-welcome-email', $profile), ['locale' => 'en'])
            ->assertRedirect();

        Mail::assertQueued(AdminProfileWelcomeMail::class, function (AdminProfileWelcomeMail $mail) use ($profile) {
            $this->post(route('password.reset.update'), [
                'token' => $mail->resetToken,
                'email' => $profile->email,
                'password' => 'a-new-strong-password',
                'password_confirmation' => 'a-new-strong-password',
            ])->assertRedirect(route('password.reset'));

            $this->assertTrue(\Illuminate\Support\Facades\Hash::check(
                'a-new-strong-password',
                $profile->fresh()->password,
            ));

            return true;
        });
    }

    public function test_quick_added_profile_public_url_resolves_back_to_the_same_profile(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside@example.com')->firstOrFail();
        $slug = PublicProfileLink::slugFor($profile);

        $this->assertSame($profile->id, PublicProfileLink::resolve($slug)?->id);
    }

    public function test_quick_add_rejects_duplicate_email(): void
    {
        Profile::factory()->business()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'taken@example.com',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_quick_add_rejects_non_maintainer(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.users.quick-add'))
            ->assertForbidden();
    }

    public function test_maintainer_can_render_the_quick_add_form(): void
    {
        // Regression guard 2026-09-14: an @error( literal inside a plain JS code comment
        // in this same file's <script> block was compiled by Blade as a real directive
        // (Blade is a regex-based text transformer, not a real parser -- it doesn't know
        // "this is inside a <script> comment") leaving one @error() with no matching
        // @enderror. 500'd in prod, undetected here because no test did a direct GET of
        // this route with a maintainer session before.
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.quick-add'))
            ->assertOk();
    }

    public function test_quick_add_persists_google_places_import_data_for_a_business(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Exploradores de Café',
                'email' => 'antonio@grupoexploradores.com',
                'instagram' => 'exploradoresdecafe',
                'about' => 'Coffee workshop with a working roaster and cupping room.',
                'website' => 'https://www.grupoexploradores.com/club-cafe',
                'profile_photo' => 'https://kolabing.com/api/v1/places/photo?name=places/abc/photos/1',
                'offer_photos' => [
                    'https://kolabing.com/api/v1/places/photo?name=places/abc/photos/1',
                    'https://kolabing.com/api/v1/places/photo?name=places/abc/photos/2',
                ],
                'primary_venue' => ['formatted_address' => 'Av. Santa Fe 596, CDMX', 'rating' => 4.5],
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'antonio@grupoexploradores.com')->firstOrFail();
        $business = $profile->businessProfile;

        $this->assertSame('Coffee workshop with a working roaster and cupping room.', $business->about);
        $this->assertSame('https://www.grupoexploradores.com/club-cafe', $business->website);
        $this->assertSame('https://kolabing.com/api/v1/places/photo?name=places/abc/photos/1', $business->profile_photo);
        $this->assertCount(2, $business->offer_photos);
        $this->assertSame('Av. Santa Fe 596, CDMX', $business->primary_venue['formatted_address']);
    }

    public function test_a_relative_photo_url_fails_validation_visibly(): void
    {
        // Regression guard 2026-09-14: the Places-import JS built relative photo URLs
        // (/api/v1/places/photo?...), which fail the `url` validation rule on
        // profile_photo/offer_photos.* -- silently, since those hidden fields had no
        // @error() markup, so the form just reloaded with no visible reason why. Fixed
        // the JS to build absolute URLs AND added a form-level error summary so any
        // future hidden-field validation failure is visible, not just this one field.
        $response = $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.users.quick-add'))
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside3@example.com',
                'profile_photo' => '/api/v1/places/photo?name=places/abc/photos/1',
            ]);

        $response->assertSessionHasErrors('profile_photo');
        $this->assertDatabaseMissing('profiles', ['email' => 'riverside3@example.com']);

        $follow = $this->get(route('admin.users.quick-add'));
        $follow->assertSee('valid URL', false);
    }

    public function test_quick_add_works_fine_without_any_places_import_data(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside2@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside2@example.com')->firstOrFail();

        $this->assertNull($profile->businessProfile->profile_photo);
        $this->assertNull($profile->businessProfile->offer_photos);
        $this->assertNull($profile->businessProfile->primary_venue);
    }
}
