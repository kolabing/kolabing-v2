<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\AdminProfileWelcomeMail;
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

        Mail::assertQueued(AdminProfileWelcomeMail::class, fn (AdminProfileWelcomeMail $mail) => $mail->hasTo('riverside@example.com'));
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

    public function test_quick_added_welcome_email_reset_link_actually_authenticates(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Riverside Cafe',
                'email' => 'riverside@example.com',
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'riverside@example.com')->firstOrFail();

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
        Mail::fake();

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

    public function test_quick_add_persists_google_places_import_data_for_a_business(): void
    {
        Mail::fake();

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

    public function test_quick_add_works_fine_without_any_places_import_data(): void
    {
        Mail::fake();

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
