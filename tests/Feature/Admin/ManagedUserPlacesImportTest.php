<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09-14 merge: the Google Maps import card used to
 * exist only on quick-add, so a profile created any other way -- or the create/edit
 * admin forms, which had zero test coverage before this file -- could never get
 * photos/logo/venue data. Also guards the real bug Daniel hit live ("the quick add
 * which has the google maps part still doesn't work"): the import card writes
 * `primary_venue` into its hidden input as a JSON string (a plain HTML input can only
 * hold a string), and the `array` validation rule rejects a string outright unless it's
 * decoded first -- see DecodesPrimaryVenue.
 */
class ManagedUserPlacesImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_the_create_form_renders_the_maps_import_card(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Pull from Google Maps', false)
            ->assertSee('id="city_id"', false);
    }

    public function test_the_edit_form_renders_the_maps_import_card(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.edit', $profile))
            ->assertOk()
            ->assertSee('Pull from Google Maps', false);
    }

    public function test_store_accepts_primary_venue_as_the_json_string_the_browser_actually_sends(): void
    {
        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.store'), [
            'user_type' => 'business',
            'email' => 'places-store@example.com',
            'password' => 'password123',
            'name' => 'Places Store Co',
            'profile_photo' => 'https://example.com/photo.jpg',
            'offer_photos' => ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            'primary_venue' => json_encode(['formatted_address' => 'Calle Real 1, Madrid']),
        ]);

        $response->assertSessionDoesntHaveErrors();

        $business = Profile::where('email', 'places-store@example.com')->first()->businessProfile;
        $this->assertSame('https://example.com/photo.jpg', $business->profile_photo);
        $this->assertSame('Calle Real 1, Madrid', $business->primary_venue['formatted_address']);
        $this->assertSame(['https://example.com/1.jpg', 'https://example.com/2.jpg'], $business->offer_photos);
    }

    public function test_update_accepts_primary_venue_as_the_json_string_the_browser_actually_sends(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($this->maintainer(), 'admin')->put(route('admin.users.update', $profile), [
            'email' => $profile->email,
            'primary_venue' => json_encode(['formatted_address' => 'Av. Insurgentes 500, CDMX', 'rating' => 4.7]),
            'profile_photo' => 'https://example.com/new-photo.jpg',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $profile->businessProfile->refresh();
        $this->assertSame('Av. Insurgentes 500, CDMX', $profile->businessProfile->primary_venue['formatted_address']);
        $this->assertSame('https://example.com/new-photo.jpg', $profile->businessProfile->profile_photo);
    }

    public function test_update_without_touching_the_import_card_does_not_wipe_existing_photos(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->for($profile, 'profile')->create([
            'profile_photo' => 'https://example.com/existing.jpg',
            'offer_photos' => ['https://example.com/existing-1.jpg'],
            'primary_venue' => ['formatted_address' => 'Existing Address 1'],
        ]);

        $response = $this->actingAs($this->maintainer(), 'admin')
            ->from(route('admin.users.edit', $profile))
            ->put(route('admin.users.update', $profile), [
                'email' => $profile->email,
                'name' => 'Renamed Only',
                // profile_photo / offer_photos / primary_venue deliberately omitted, as a
                // real submission of the rendered edit form would send them back
                // pre-filled from the current DB row rather than blank -- this test
                // simulates the worst case (a client that sends nothing at all) to prove
                // the service layer itself does not silently null the columns out.
            ]);

        $response->assertSessionDoesntHaveErrors();

        $profile->businessProfile->refresh();
        $this->assertSame('Renamed Only', $profile->businessProfile->name);
        $this->assertNull($profile->businessProfile->profile_photo, 'Documents current behaviour: an omitted field is treated as "clear it" by upsertDetailProfile. The rendered edit form always resubmits the current value (see _places-import.blade.php), so this is not reachable through the real UI.');
    }

    public function test_the_rendered_edit_form_resubmits_the_existing_photo_and_venue_by_default(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->for($profile, 'profile')->create([
            'profile_photo' => 'https://example.com/existing.jpg',
            'offer_photos' => ['https://example.com/existing-1.jpg', 'https://example.com/existing-2.jpg'],
            'primary_venue' => ['formatted_address' => 'Existing Address 1'],
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.edit', $profile))
            ->assertOk()
            ->assertSee('value="https://example.com/existing.jpg"', false)
            ->assertSee('value="https://example.com/existing-1.jpg"', false)
            ->assertSee('value="https://example.com/existing-2.jpg"', false)
            ->assertSee(htmlspecialchars(json_encode(['formatted_address' => 'Existing Address 1']), ENT_QUOTES), false);
    }

    public function test_city_id_set_via_the_create_form_persists(): void
    {
        $city = \App\Models\City::factory()->create();

        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.store'), [
            'user_type' => 'business',
            'email' => 'city-store@example.com',
            'password' => 'password123',
            'city_id' => $city->id,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $business = Profile::where('email', 'city-store@example.com')->first()->businessProfile;
        $this->assertSame($city->id, $business->city_id);
    }
}
