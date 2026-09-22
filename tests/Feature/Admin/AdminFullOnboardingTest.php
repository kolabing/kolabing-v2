<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessType;
use App\Models\City;
use App\Models\CommunityType;
use App\Models\Kolab;
use App\Models\OfferOption;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Full onboarding from the admin panel (BE-NF-64).
 *
 * The claim this file has to hold up is narrow and total: a profile a maintainer
 * onboards is the *same* profile the owner would have produced in the app. Not
 * similar — the same, because both run `OnboardingService`. So the assertions below
 * are mostly about the things quick-add silently skipped: `categories`, `has_venue`,
 * the auto-provisioned first Kolab, and `onboardingCompleted()`, which is what the
 * app reads to decide whether to drop the owner back into the wizard on first
 * sign-in. A maintainer who filled in every field by hand still produced an account
 * the app considered unfinished.
 */
class AdminFullOnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function city(string $name = 'Barcelona'): City
    {
        return City::factory()->create(['is_active' => true, 'name' => $name]);
    }

    /** firstOrCreate, not create: the base test database already carries the seeded taxonomy. */
    private function businessType(string $slug = 'cafe'): BusinessType
    {
        return BusinessType::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function communityType(string $slug = 'run_club'): CommunityType
    {
        return CommunityType::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => 'Run Club', 'is_active' => true, 'sort_order' => 1],
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function venuePayload(City $city, BusinessType $type, array $overrides = []): array
    {
        return [
            'email' => 'eixample@example.com',
            'name' => 'Eixample 46',
            'about' => 'A neighbourhood cafe with a back room.',
            'business_type' => $type->slug,
            'has_venue' => '1',
            'city_id' => $city->id,
            'offering' => 'Back room, free coffee for the group.',
            'instagram' => '@eixample46',
            // The shared Places partial posts the whole imported place as one JSON
            // string; the two fields Google cannot supply come from `venue[...]`.
            'primary_venue' => json_encode([
                'name' => 'Eixample 46',
                'formatted_address' => 'Carrer de Mallorca 46, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'place_id' => 'ChIJtest',
            ]),
            'venue' => [
                'venue_type' => 'cafe',
                'capacity' => 40,
            ],
            ...$overrides,
        ];
    }

    // ── Reachability ────────────────────────────────────────────────────

    public function test_the_form_is_maintainer_only(): void
    {
        $this->get(route('admin.users.onboard'))->assertRedirect();
    }

    public function test_the_form_defaults_to_business_and_can_switch_to_community(): void
    {
        $admin = $this->maintainer();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.onboard'))
            ->assertOk()
            ->assertSee('Onboard business');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.onboard', ['type' => 'community']))
            ->assertOk()
            ->assertSee('Onboard community');
    }

    // ── Business ────────────────────────────────────────────────────────

    public function test_onboarding_a_business_fills_every_field_the_app_would(): void
    {
        Mail::fake();
        $city = $this->city();
        $type = $this->businessType();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($city, $type))
            ->assertRedirect();

        $profile = Profile::query()->where('email', 'eixample@example.com')->firstOrFail();
        $business = $profile->businessProfile;

        $this->assertTrue($profile->isBusiness());
        $this->assertSame('Eixample 46', $business->name);
        $this->assertSame($city->id, $business->city_id);
        $this->assertTrue((bool) $business->has_venue);

        // The three quick-add never set.
        $this->assertSame([$type->slug], $business->categories);
        $this->assertSame($type->slug, $business->business_type);
        $this->assertSame('cafe', $business->primary_venue['venue_type']);
        $this->assertSame(40, (int) $business->primary_venue['capacity']);
    }

    /**
     * The difference that matters on the owner's first sign-in: an incomplete profile
     * puts them back at step one of a wizard they never started.
     */
    public function test_the_onboarded_business_is_complete_in_the_apps_own_terms(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($this->city(), $this->businessType()))
            ->assertRedirect();

        $profile = Profile::query()->where('email', 'eixample@example.com')->firstOrFail();

        $this->assertTrue($profile->fresh()->onboardingCompleted());
    }

    /** Free tier = exactly one published auto-offer, provisioned by OnboardingService. */
    public function test_onboarding_a_business_provisions_its_first_kolab(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($this->city(), $this->businessType()))
            ->assertRedirect();

        $profile = Profile::query()->where('email', 'eixample@example.com')->firstOrFail();

        $this->assertSame(
            1,
            Kolab::query()->where('creator_profile_id', $profile->id)->count(),
            'A freshly onboarded business gets exactly one auto-offer, same as the app.'
        );
    }

    /**
     * The public listing page reads `business_profiles.opening_hours` — the column,
     * not the copy nested under `primary_venue` — so dropping it means a fully
     * onboarded business shows no hours while a merely quick-added one does. That is
     * a parity regression against BE-NF-62 on the very surface this form exists to
     * reach parity with. Caught in review before merge.
     */
    public function test_opening_hours_reach_the_column_the_public_page_reads(): void
    {
        Mail::fake();
        $hours = ['Monday: 9:00 – 18:00', 'Tuesday: 9:00 – 18:00'];

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload(
                $this->city(),
                $this->businessType(),
                ['opening_hours' => json_encode($hours)],
            ))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business = Profile::query()->where('email', 'eixample@example.com')->firstOrFail()->businessProfile;

        $this->assertSame($hours, $business->opening_hours);
        // And still nested under the venue, which is where the app models them.
        $this->assertSame($hours, $business->primary_venue['opening_hours']);
    }

    /**
     * A place in a locality with no `cities` row imports with `city_id = null`, which
     * is exactly what the "City Name (unlisted)" input is for. Before the fix the
     * `required_without` pair rejected a submission whose venue plainly named the
     * city, telling the maintainer "the city field is required" twice with a
     * filled-in address on screen.
     */
    public function test_an_unlisted_city_falls_back_to_the_imported_venues_city(): void
    {
        Mail::fake();
        $type = $this->businessType();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), [
                'email' => 'cuajimalpa@example.com',
                'name' => 'Exploradores de Cafe',
                'business_type' => $type->slug,
                'has_venue' => '1',
                // Neither city field filled — the import could not resolve one.
                'city_id' => '',
                'city_name' => '',
                'primary_venue' => json_encode([
                    'name' => 'Exploradores de Cafe',
                    'formatted_address' => 'Av. Juarez 12, Cuajimalpa, CDMX',
                    'city' => 'Cuajimalpa',
                ]),
                'venue' => ['venue_type' => 'cafe', 'capacity' => 25],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business = Profile::query()->where('email', 'cuajimalpa@example.com')->firstOrFail()->businessProfile;

        $this->assertSame('Cuajimalpa', $business->city_name);
    }

    /**
     * A product-promoting business has no venue, so venue_type/capacity must not be
     * demanded — `required_with:primary_venue` would otherwise fire on the empty
     * array the form still posts.
     */
    public function test_a_business_without_a_venue_needs_no_venue_details(): void
    {
        Mail::fake();
        $city = $this->city();
        $type = $this->businessType('retail');

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), [
                'email' => 'sauce@example.com',
                'name' => 'Hot Sauce Co',
                'business_type' => $type->slug,
                'has_venue' => '0',
                'city_id' => $city->id,
                'primary_venue' => '',
                'venue' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = Profile::query()->where('email', 'sauce@example.com')->firstOrFail();

        $this->assertFalse((bool) $profile->businessProfile->has_venue);
        $this->assertNull($profile->businessProfile->primary_venue);
    }

    /** Up to 3, enforced by the app's own rule because the rule set is inherited. */
    public function test_more_than_three_categories_is_rejected_by_the_apps_own_rule(): void
    {
        $city = $this->city();
        foreach (['cafe', 'bar', 'bakery', 'gym'] as $slug) {
            $this->businessType($slug);
        }

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), [
                'email' => 'toomany@example.com',
                'name' => 'Too Many',
                'has_venue' => '0',
                'city_id' => $city->id,
                'categories' => ['cafe', 'bar', 'bakery', 'gym'],
            ])
            ->assertSessionHasErrors('categories');

        $this->assertDatabaseMissing('profiles', ['email' => 'toomany@example.com']);
    }

    // ── Choice boxes, same source as the app ────────────────────────────

    /**
     * Venue type, offerings and product type are the admin-managed `offer_options`
     * taxonomy — the same lists the app's own chips read. The form used a hardcoded
     * enum and a free-text box, so an option a maintainer added in /admin/offer-options
     * appeared on phones and not here.
     */
    public function test_the_form_reads_its_choices_from_the_same_lookups_the_app_uses(): void
    {
        $page = $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.onboard'))
            ->assertOk();

        foreach (['/api/v1/lookup/venue-types', '/api/v1/lookup/offerings', '/api/v1/lookup/product-types'] as $endpoint) {
            $page->assertSee($endpoint, false);
        }

        // The rendered attributes, not the Blade source: single-select for venue
        // type, multi for offerings — as in the app.
        $page->assertSee('data-field="venue[venue_type]"', false)
            ->assertSee('data-field="offering_options[]"', false);
    }

    /** The address field autocompletes against the same Places endpoint as mobile. */
    public function test_the_address_field_autocompletes_against_places(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.onboard'))
            ->assertOk()
            ->assertSee('/api/v1/places/autocomplete?query=', false);
    }

    /**
     * Picked options become their human LABELS, never slugs.
     *
     * `offering` is free text and `provisionBusinessAutoOffer()` uses it as the
     * auto-offer's description when About is empty — so slugs here would publish
     * "venue,food_drink" to communities as the pitch.
     */
    public function test_chosen_offerings_are_stored_as_labels_not_slugs(): void
    {
        Mail::fake();
        $city = $this->city();
        $type = $this->businessType();

        $venue = OfferOption::query()->firstOrCreate(
            ['kind' => OfferOption::KIND_OFFERING, 'slug' => 'venue'],
            ['name' => 'Venue', 'is_active' => true, 'sort_order' => 1],
        );
        $food = OfferOption::query()->firstOrCreate(
            ['kind' => OfferOption::KIND_OFFERING, 'slug' => 'food_drink'],
            ['name' => 'Food & drink', 'is_active' => true, 'sort_order' => 2],
        );

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($city, $type, [
                'offering' => null,
                'offering_options' => [$venue->slug, $food->slug],
                'offering_detail' => 'and the back room after 8pm',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business = Profile::query()->where('email', 'eixample@example.com')->firstOrFail()->businessProfile;

        $this->assertSame(
            $venue->name.', '.$food->name.' — and the back room after 8pm',
            $business->offering,
        );
        $this->assertStringNotContainsString('food_drink', (string) $business->offering);
    }

    /** Free text alone still works — the options are not mandatory. */
    public function test_offering_detail_alone_is_kept(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($this->city(), $this->businessType(), [
                'offering' => null,
                'offering_options' => [],
                'offering_detail' => 'Whatever the community needs, really',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business = Profile::query()->where('email', 'eixample@example.com')->firstOrFail()->businessProfile;

        $this->assertSame('Whatever the community needs, really', $business->offering);
    }

    /** A venue type picked from the chips reaches the stored venue. */
    public function test_a_venue_type_chosen_from_the_lookup_is_stored(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($this->city(), $this->businessType(), [
                'venue' => ['venue_type' => 'bar_lounge', 'capacity' => 60],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $business = Profile::query()->where('email', 'eixample@example.com')->firstOrFail()->businessProfile;

        $this->assertSame('bar_lounge', $business->primary_venue['venue_type']);
    }

    // ── Community ───────────────────────────────────────────────────────

    public function test_onboarding_a_community_sets_the_type_quick_add_could_not(): void
    {
        Mail::fake();
        $city = $this->city();
        $type = $this->communityType();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.community'), [
                'email' => 'runclub@example.com',
                'name' => 'Barcelona Run Club',
                'community_type' => $type->slug,
                'community_size' => 120,
                'city_id' => $city->id,
                'instagram' => '@bcnrunclub',
                'tiktok' => '@bcnrun',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = Profile::query()->where('email', 'runclub@example.com')->firstOrFail();
        $community = $profile->communityProfile;

        $this->assertTrue($profile->isCommunity());
        $this->assertSame('Barcelona Run Club', $community->name);
        $this->assertSame($city->id, $community->city_id);
        $this->assertSame(120, (int) $community->community_size);
        $this->assertNotNull($community->community_type);
        $this->assertTrue($profile->fresh()->onboardingCompleted());
    }

    /** Communities are never paywalled and never get a subscription row. */
    public function test_onboarding_a_community_creates_no_subscription(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.community'), [
                'email' => 'nosub@example.com',
                'name' => 'No Sub Club',
                'community_type' => $this->communityType()->slug,
                'city_id' => $this->city()->id,
            ])
            ->assertRedirect();

        $profile = Profile::query()->where('email', 'nosub@example.com')->firstOrFail();

        $this->assertDatabaseMissing('business_subscriptions', ['profile_id' => $profile->id]);
    }

    public function test_a_community_without_a_type_is_rejected(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.community'), [
                'email' => 'notype@example.com',
                'name' => 'No Type',
                'city_id' => $this->city()->id,
            ])
            ->assertSessionHasErrors('community_type');

        $this->assertDatabaseMissing('profiles', ['email' => 'notype@example.com']);
    }

    // ── The account ─────────────────────────────────────────────────────

    /**
     * Sending stays a deliberate, language-picked follow-up (Daniel 2026-09-14).
     * Onboarding creates the account; it does not decide how to greet its owner.
     */
    public function test_onboarding_does_not_send_anything(): void
    {
        Mail::fake();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload($this->city(), $this->businessType()))
            ->assertRedirect();

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    }

    /** A duplicate email must not half-create an account before failing. */
    public function test_a_taken_email_is_rejected(): void
    {
        $existing = Profile::factory()->business()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), $this->venuePayload(
                $this->city(),
                $this->businessType(),
                ['email' => $existing->email],
            ))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Profile::query()->where('email', $existing->email)->count());
    }
}
