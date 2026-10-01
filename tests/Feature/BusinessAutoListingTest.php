<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\BusinessType;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use App\Services\BusinessAutoListingService;
use App\Services\KolabService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every business is listed in community Explore from the moment it exists
 * (Daniel 2026-09-28: prod had 111 Kolabs, 5 from businesses, and communities
 * saw an empty Explore).
 */
class BusinessAutoListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const MAPS_PHOTO = 'https://app.kolabing.test/api/v1/places/photo?name=places%2Fabc%2Fphotos%2F1&max_width=800';

    private const MAPS_PHOTO_2 = 'https://app.kolabing.test/api/v1/places/photo?name=places%2Fabc%2Fphotos%2F2&max_width=800';

    private function city(): City
    {
        return City::query()->where('name', 'Barcelona')->first()
            ?? City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain', 'is_active' => true]);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Kolab>
     */
    private function kolabsOf(Profile $profile)
    {
        return Kolab::query()->where('creator_profile_id', $profile->id)->get();
    }

    private function assertSingleOpenAutoListing(Profile $profile, IntentType $intent = IntentType::VenuePromotion): Kolab
    {
        $kolabs = $this->kolabsOf($profile);

        $this->assertCount(1, $kolabs, 'Exactly one listing per business.');

        $kolab = $kolabs->first();
        $this->assertTrue($kolab->is_auto_listing);
        $this->assertSame($intent, $kolab->intent_type);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertNotNull($kolab->published_at);
        $this->assertNull($kolab->availability_start, 'Open-ended: no window to expire out of Explore.');
        $this->assertNull($kolab->availability_end);

        return $kolab;
    }

    /**
     * A venue business with Maps photos, the shape the admin Maps import stores.
     *
     * @param  array<string, mixed>  $businessOverrides
     * @param  array<string, mixed>  $profileOverrides
     */
    private function venueBusiness(array $businessOverrides = [], array $profileOverrides = []): Profile
    {
        $city = $this->city();
        $profile = Profile::factory()->business()->create($profileOverrides);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Eixample 46',
            'about' => 'A neighbourhood cafe with a back room.',
            'offering' => 'Back room, free coffee for the group.',
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'city_id' => $city->id,
            'city_name' => 'Barcelona',
            'city_country' => 'Spain',
            'profile_photo' => null,
            'primary_venue' => [
                'name' => 'Eixample 46',
                'venue_type' => 'cafe',
                'capacity' => 40,
                'formatted_address' => 'Carrer de Mallorca 46, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [
                    ['resource_name' => 'places/abc/photos/1', 'preview_url' => self::MAPS_PHOTO],
                    ['resource_name' => 'places/abc/photos/2', 'preview_url' => self::MAPS_PHOTO_2],
                ],
            ],
            ...$businessOverrides,
        ]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        return $profile->fresh();
    }

    // ── Every creation path lists the business exactly once ─────────────

    public function test_api_registration_creates_one_open_ended_auto_listing(): void
    {
        $city = $this->city();

        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Cafe Barcelona',
            'about' => 'A cozy cafe',
            'business_type' => 'cafe',
            'has_venue' => true,
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Cafe Barcelona Terrace',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ])->assertStatus(201);

        $profile = Profile::where('email', 'register@example.com')->firstOrFail();
        $kolab = $this->assertSingleOpenAutoListing($profile);

        $this->assertSame('Cafe Barcelona Terrace', $kolab->venue_name);
        $this->assertSame('Barcelona', $kolab->preferred_city);
        $this->assertStringContainsString('Cafe Barcelona', $kolab->title);
    }

    public function test_api_onboarding_creates_one_listing_and_rerunning_it_does_not_add_another(): void
    {
        $city = $this->city();
        $profile = Profile::factory()->business()->create(['preferred_locale' => 'en']);
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $payload = [
            'name' => 'Cafe Barcelona',
            'about' => 'A cozy cafe.',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Cafe Barcelona Terrace',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ];

        $this->actingAs($profile)->putJson('/api/v1/onboarding/business', $payload)->assertOk();
        $this->actingAs($profile)->putJson('/api/v1/onboarding/business', $payload)->assertOk();

        $kolab = $this->assertSingleOpenAutoListing($profile);
        $this->assertSame('Host your community at Cafe Barcelona', $kolab->title);
    }

    public function test_admin_quick_add_with_maps_import_lists_the_business_and_uses_the_top_photo(): void
    {
        Mail::fake();
        $city = $this->city();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Eixample 46',
                'email' => 'quickadd@example.com',
                'city_id' => $city->id,
                'primary_venue' => json_encode([
                    'name' => 'Eixample 46',
                    'venue_type' => 'cafe',
                    'capacity' => null,
                    'formatted_address' => 'Carrer de Mallorca 46, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'photos' => [
                        ['resource_name' => 'places/abc/photos/1', 'preview_url' => self::MAPS_PHOTO],
                        ['resource_name' => 'places/abc/photos/2', 'preview_url' => self::MAPS_PHOTO_2],
                    ],
                ]),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = Profile::where('email', 'quickadd@example.com')->firstOrFail();
        $kolab = $this->assertSingleOpenAutoListing($profile);

        $this->assertSame('Barcelona', $kolab->preferred_city);
        $this->assertSame([self::MAPS_PHOTO, self::MAPS_PHOTO_2], array_column($kolab->media, 'url'));
        $this->assertSame(self::MAPS_PHOTO, $profile->avatar_url, 'No logo: the top Maps photo becomes the avatar.');

        // Nothing is sent to an owner who has not opened the app yet.
        Mail::assertNothingQueued();
    }

    public function test_admin_quick_add_of_a_business_without_a_venue_gets_a_product_listing(): void
    {
        Mail::fake();
        $city = $this->city();

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.quick-add.store'), [
                'user_type' => 'business',
                'name' => 'Bean Brand',
                'email' => 'beans@example.com',
                'city_id' => $city->id,
            ])
            ->assertRedirect();

        $profile = Profile::where('email', 'beans@example.com')->firstOrFail();
        $kolab = $this->assertSingleOpenAutoListing($profile, IntentType::ProductPromotion);

        $this->assertSame('Bean Brand', $kolab->product_name);
        $this->assertSame('Barcelona', $kolab->preferred_city);
    }

    public function test_admin_full_onboarding_creates_exactly_one_auto_listing(): void
    {
        Mail::fake();
        $city = $this->city();
        $type = BusinessType::query()->firstOrCreate(['slug' => 'cafe'], ['name' => 'Cafe', 'is_active' => true]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.business'), [
                'email' => 'onboard@example.com',
                'name' => 'Eixample 46',
                'about' => 'A neighbourhood cafe with a back room.',
                'business_type' => $type->slug,
                'has_venue' => '1',
                'city_id' => $city->id,
                'offering' => 'Back room, free coffee for the group.',
                'primary_venue' => json_encode([
                    'name' => 'Eixample 46',
                    'formatted_address' => 'Carrer de Mallorca 46, Barcelona',
                    'city' => 'Barcelona',
                    'country' => 'Spain',
                    'place_id' => 'ChIJtest',
                ]),
                'venue' => ['venue_type' => 'cafe', 'capacity' => 40],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = Profile::where('email', 'onboard@example.com')->firstOrFail();
        $kolab = $this->assertSingleOpenAutoListing($profile);

        $this->assertStringContainsString('Back room, free coffee for the group.', $kolab->description);
        $this->assertStringContainsString('Cafe', $kolab->description);
    }

    // ── Idempotency, closing, editing ───────────────────────────────────

    public function test_provisioning_twice_creates_one_listing(): void
    {
        $profile = $this->venueBusiness();
        $service = app(BusinessAutoListingService::class);

        $this->assertNotNull($service->provision($profile));
        $this->assertNull($service->provision($profile));

        $this->assertSingleOpenAutoListing($profile);
    }

    public function test_a_closed_auto_listing_is_never_recreated(): void
    {
        $profile = $this->venueBusiness();
        $service = app(BusinessAutoListingService::class);

        $kolab = $service->provision($profile);
        app(KolabService::class)->close($kolab);

        $this->assertNull($service->provision($profile));
        $this->assertSame(BusinessAutoListingService::SKIP_AUTO_LISTING_EXISTS, $service->skipReason($profile));

        $this->artisan('kolabing:autolist-businesses', ['--apply' => true])->assertSuccessful();

        $kolabs = $this->kolabsOf($profile);
        $this->assertCount(1, $kolabs);
        $this->assertSame(KolabStatus::Closed, $kolabs->first()->status);
    }

    public function test_the_business_can_edit_its_auto_listing(): void
    {
        $profile = $this->venueBusiness();
        $kolab = app(BusinessAutoListingService::class)->provision($profile);

        $this->actingAs($profile)
            ->putJson("/api/v1/kolabs/{$kolab->id}", ['title' => 'Our back room is yours'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Our back room is yours')
            ->assertJsonPath('data.is_auto_listing', true);
    }

    public function test_a_business_with_its_own_open_offer_is_not_auto_listed(): void
    {
        $profile = $this->venueBusiness();
        Kolab::factory()->published()->venuePromotion()->forCreator($profile)->create([
            'preferred_city' => 'Barcelona',
            'availability_start' => null,
            'availability_end' => null,
        ]);

        $this->assertNull(app(BusinessAutoListingService::class)->provision($profile));
        $this->assertCount(1, $this->kolabsOf($profile));
    }

    public function test_a_flexible_offer_that_started_in_the_past_with_no_end_still_counts_as_open(): void
    {
        $profile = $this->venueBusiness();
        Kolab::factory()->published()->venuePromotion()->forCreator($profile)->create([
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'flexible',
            'availability_start' => now()->subDays(10)->toDateString(),
            'availability_end' => null,
        ]);

        $this->assertSame(BusinessAutoListingService::SKIP_HAS_OPEN_KOLAB, app(BusinessAutoListingService::class)->skipReason($profile));
        $this->assertNull(app(BusinessAutoListingService::class)->provision($profile));
        $this->assertCount(1, $this->kolabsOf($profile), 'No duplicate card next to an offer Explore still shows.');
    }

    public function test_a_business_whose_own_offer_expired_is_auto_listed_and_reappears_in_explore(): void
    {
        $business = $this->venueBusiness();
        Kolab::factory()->published()->venuePromotion()->forCreator($business)->create([
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'one_time',
            'availability_start' => now()->subDays(40)->toDateString(),
            'availability_end' => now()->subDays(20)->toDateString(),
        ]);

        $listing = app(BusinessAutoListingService::class)->provision($business);

        $this->assertNotNull($listing);
        $this->assertTrue($listing->is_auto_listing);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Barcelona Brunch Club',
            'community_type' => 'run_club',
            'city_id' => $this->city()->id,
        ]);

        $this->actingAs($community)
            ->getJson('/api/v1/discovery/opportunities?city=Barcelona')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $listing->id);
    }

    public function test_a_recurring_offer_with_no_bookable_day_left_does_not_count_as_open(): void
    {
        $this->travelTo(now()->next('Monday'));

        $profile = $this->venueBusiness();
        Kolab::factory()->published()->venuePromotion()->forCreator($profile)->create([
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'recurring',
            'availability_start' => now()->subDays(30)->toDateString(),
            'availability_end' => now()->addDays(2)->toDateString(),
            'recurring_days' => [6, 7],
        ]);

        $this->assertNull(app(BusinessAutoListingService::class)->skipReason($profile));
        $this->assertNotNull(app(BusinessAutoListingService::class)->provision($profile));
    }

    public function test_an_offer_addressed_to_one_community_does_not_count_as_open(): void
    {
        $profile = $this->venueBusiness();
        $recipient = Profile::factory()->community()->create();
        Kolab::factory()->published()->venuePromotion()->forCreator($profile)->create([
            'preferred_city' => 'Barcelona',
            'availability_start' => null,
            'availability_end' => null,
            'recipient_community_id' => $recipient->id,
        ]);

        $this->assertNull(app(BusinessAutoListingService::class)->skipReason($profile));
        $this->assertNotNull(app(BusinessAutoListingService::class)->provision($profile));
    }

    public function test_the_backfill_runs_daily_so_a_business_never_drops_out_of_explore(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('kolabing:autolist-businesses --apply')
            ->assertSuccessful();
    }

    public function test_a_business_that_already_used_its_free_kolab_is_still_listed(): void
    {
        $profile = $this->venueBusiness();
        Kolab::factory()->venuePromotion()->forCreator($profile)->create([
            'status' => KolabStatus::Closed,
            'published_at' => now()->subMonth(),
            'preferred_city' => 'Barcelona',
        ]);
        $this->assertTrue($profile->hasUsedFreeKolab());

        $this->assertNotNull(app(BusinessAutoListingService::class)->provision($profile));
        $this->assertSame(1, Kolab::query()->where('creator_profile_id', $profile->id)->autoListing()->count());
    }

    // ── Localisation ────────────────────────────────────────────────────

    public function test_title_follows_the_owner_locale(): void
    {
        $service = app(BusinessAutoListingService::class);

        $catalan = $this->venueBusiness(['name' => 'Bar Catala'], ['preferred_locale' => 'ca']);
        $this->assertSame('Reuneix la teva comunitat a Bar Catala', $service->provision($catalan)->title);

        $spanish = $this->venueBusiness(['name' => 'Bar Espanol'], ['preferred_locale' => 'es']);
        $this->assertSame('Reúne a tu comunidad en Bar Espanol', $service->provision($spanish)->title);

        $english = $this->venueBusiness(['name' => 'Bar English'], ['preferred_locale' => 'en']);
        $this->assertSame('Host your community at Bar English', $service->provision($english)->title);

        // No preference, business in Spain: Spanish.
        $unset = $this->venueBusiness(['name' => 'Bar Sin Idioma'], ['preferred_locale' => null]);
        $this->assertSame('Reúne a tu comunidad en Bar Sin Idioma', $service->provision($unset)->title);
    }

    // ── Discovery ───────────────────────────────────────────────────────

    public function test_a_community_in_the_same_city_finds_the_auto_listing_and_it_never_expires(): void
    {
        $business = $this->venueBusiness();
        $kolab = app(BusinessAutoListingService::class)->provision($business);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
            'city_id' => City::query()->where('name', 'Barcelona')->value('id'),
        ]);

        $this->actingAs($community)
            ->getJson('/api/v1/discovery/opportunities?city=Barcelona')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id);

        $this->travel(120)->days();

        $this->actingAs($community)
            ->getJson('/api/v1/discovery/opportunities?city=Barcelona')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id);
    }

    // ── Avatar fallback ─────────────────────────────────────────────────

    public function test_an_uploaded_logo_is_never_overwritten(): void
    {
        $profile = $this->venueBusiness(['profile_photo' => 'https://cdn.example.com/logo.png'], ['avatar_url' => 'https://cdn.example.com/logo.png']);

        app(BusinessAutoListingService::class)->provision($profile);

        $this->assertSame('https://cdn.example.com/logo.png', $profile->fresh()->avatar_url);
        $this->assertSame('https://cdn.example.com/logo.png', $profile->businessProfile->fresh()->profile_photo);
    }

    public function test_an_avatar_set_without_a_business_logo_is_kept(): void
    {
        $profile = $this->venueBusiness([], ['avatar_url' => 'https://cdn.example.com/own.png']);

        $this->assertFalse(app(BusinessAutoListingService::class)->applyMapsAvatarFallback($profile));
        $this->assertSame('https://cdn.example.com/own.png', $profile->fresh()->avatar_url);
    }

    public function test_a_logo_on_the_business_profile_fills_an_empty_avatar_before_any_maps_photo(): void
    {
        $profile = $this->venueBusiness(['profile_photo' => 'https://cdn.example.com/logo.png'], ['avatar_url' => null]);

        app(BusinessAutoListingService::class)->applyMapsAvatarFallback($profile);

        $this->assertSame('https://cdn.example.com/logo.png', $profile->fresh()->avatar_url);
    }

    public function test_the_top_maps_photo_fills_an_empty_avatar_and_logo(): void
    {
        $profile = $this->venueBusiness([], ['avatar_url' => null]);

        $this->assertTrue(app(BusinessAutoListingService::class)->applyMapsAvatarFallback($profile));

        $this->assertSame(self::MAPS_PHOTO, $profile->fresh()->avatar_url);
        $this->assertSame(self::MAPS_PHOTO, $profile->businessProfile->fresh()->profile_photo);
    }

    public function test_no_photo_leaves_the_avatar_empty(): void
    {
        $profile = $this->venueBusiness(['primary_venue' => null, 'offer_photos' => null], ['avatar_url' => null]);

        $this->assertFalse(app(BusinessAutoListingService::class)->applyMapsAvatarFallback($profile));
        $this->assertNull($profile->fresh()->avatar_url);
    }

    // ── Backfill ────────────────────────────────────────────────────────

    public function test_backfill_dry_run_writes_nothing_and_apply_lists_only_eligible_businesses(): void
    {
        $eligible = $this->venueBusiness(['name' => 'Eligible Cafe'], ['avatar_url' => null]);
        $closedOnly = $this->venueBusiness(['name' => 'Closed Only Cafe']);
        Kolab::factory()->venuePromotion()->forCreator($closedOnly)->create([
            'status' => KolabStatus::Closed,
            'preferred_city' => 'Barcelona',
        ]);
        $withOpen = $this->venueBusiness(['name' => 'Open Offer Cafe']);
        Kolab::factory()->published()->venuePromotion()->forCreator($withOpen)->create([
            'preferred_city' => 'Barcelona',
            'availability_start' => null,
            'availability_end' => null,
        ]);
        $tester = $this->venueBusiness(['name' => 'Test Cafe'], ['is_test_user' => true]);
        $inactive = $this->venueBusiness(['name' => 'Inactive Cafe'], ['is_active' => false]);
        $deleted = $this->venueBusiness(['name' => 'Deleted Cafe']);
        $deleted->delete();

        $this->artisan('kolabing:autolist-businesses')
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('Eligible Cafe')
            ->expectsOutputToContain('Closed Only Cafe')
            ->expectsOutputToContain('2 listing(s)')
            ->assertSuccessful();

        $this->assertSame(0, Kolab::query()->autoListing()->count(), 'A dry run writes nothing.');
        $this->assertNull($eligible->fresh()->avatar_url);

        $this->artisan('kolabing:autolist-businesses', ['--apply' => true])->assertSuccessful();

        $this->assertSingleOpenAutoListing($eligible);
        $this->assertSame(1, Kolab::query()->where('creator_profile_id', $closedOnly->id)->autoListing()->count());
        $this->assertSame(self::MAPS_PHOTO, $eligible->fresh()->avatar_url);

        foreach ([$withOpen, $tester, $inactive, $deleted] as $skipped) {
            $this->assertSame(0, Kolab::query()->where('creator_profile_id', $skipped->id)->autoListing()->count());
        }

        $this->artisan('kolabing:autolist-businesses', ['--apply' => true])->assertSuccessful();
        $this->assertSame(2, Kolab::query()->autoListing()->count(), 'Re-running the backfill adds nothing.');
    }
}
