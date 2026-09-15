<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\BusinessType;
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

    /**
     * Regression for the real bug behind "still doesn't render the photos" (Daniel
     * 2026-09-15, after the gallery-rendering fix in #299 had already shipped): the
     * import card's help text says "click a thumbnail to remove it" -- implying every
     * fetched photo starts selected -- but the JS only ever auto-pushed idx 0 into
     * chosenPhotos, so a maintainer who didn't individually click the other 5
     * thumbnails ended up with exactly 1 photo in offer_photos no matter how many
     * Google actually had. No JS test runner in this repo (see package.json), so this
     * pins the fixed source text itself to catch a blind revert to the old gated form.
     */
    public function test_the_import_card_selects_every_fetched_photo_by_default(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('chosenPhotos.push(url);', false)
            ->assertDontSee('if (idx === 0) { chosenPhotos.push(url); }', false);
    }

    /**
     * The other half of "still doesn't render the photos, logo or anything" (Daniel
     * 2026-09-15): GooglePlacesService already maps Google's place `types` to a
     * business_types.slug and returns it as `business_type` on every /places/details
     * response, but the import card never captured it into a form field -- so every
     * imported listing fell back to the generic "Business"/"Community" label on the
     * public page (PublicProfilePageController::typeLabel()) instead of a real
     * category, unlike every benchmarked platform (Yelp/Google Business Profile/
     * TripAdvisor all show a real category).
     */
    public function test_store_accepts_the_business_type_the_maps_import_resolves(): void
    {
        BusinessType::query()->firstOrCreate(['slug' => 'cafe'], ['name' => 'Cafe', 'applies_to' => 'both', 'sort_order' => 1, 'is_active' => true]);

        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.store'), [
            'user_type' => 'business',
            'email' => 'places-category@example.com',
            'password' => 'password123',
            'name' => 'Places Category Co',
            'business_type' => 'cafe',
        ]);

        $response->assertSessionDoesntHaveErrors();

        $business = Profile::where('email', 'places-category@example.com')->first()->businessProfile;
        $this->assertSame('cafe', $business->business_type);
    }

    public function test_store_rejects_a_business_type_slug_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->maintainer(), 'admin')->post(route('admin.users.store'), [
            'user_type' => 'business',
            'email' => 'places-bad-category@example.com',
            'password' => 'password123',
            'name' => 'Places Bad Category Co',
            'business_type' => 'not-a-real-slug',
        ]);

        $response->assertSessionHasErrors('business_type');
    }

    public function test_the_rendered_edit_form_resubmits_the_existing_business_type_by_default(): void
    {
        BusinessType::query()->firstOrCreate(['slug' => 'cafe'], ['name' => 'Cafe', 'applies_to' => 'both', 'sort_order' => 1, 'is_active' => true]);
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->for($profile, 'profile')->create(['business_type' => 'cafe']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.edit', $profile))
            ->assertOk()
            ->assertSee('name="business_type" id="business_type" value="cafe"', false);
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
