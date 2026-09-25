<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\FreeListingState;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\FreeListingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Issue #341 (BE-NF-73): the free listing ends at the EARLIER of
 * `kolab_limit` completed kolabs or `day_limit` days after the business
 * profile was created. Numbers confirmed by Daniel 2026-09-25 (deliverable
 * 01239a7b, decision d4): 3 kolabs, 90 days — asserted directly against
 * config here so a config change can't silently drift from what was decided.
 */
class FreeListingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FreeListingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(FreeListingService::class);
    }

    public function test_confirmed_limits_are_three_kolabs_and_ninety_days(): void
    {
        $this->assertSame(3, config('subscriptions.free_listing.kolab_limit'));
        $this->assertSame(90, config('subscriptions.free_listing.day_limit'));
    }

    public function test_community_profile_is_not_applicable(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->assertSame(FreeListingState::NotApplicable, $this->service->state($profile));
        $this->assertNull($this->service->endsAt($profile));
    }

    public function test_new_business_with_no_kolabs_is_free(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $this->assertSame(FreeListingState::Free, $this->service->state($profile));
        $this->assertNotNull($this->service->endsAt($profile));
    }

    public function test_expires_on_the_third_completed_kolab_before_the_day_limit(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        // 3 completed kolabs, all well within the 90-day window.
        for ($i = 0; $i < 3; $i++) {
            Collaboration::factory()->completed()->create([
                'business_profile_id' => $business->id,
                'completed_at' => now()->subDays(10 - $i),
            ]);
        }

        $this->assertSame(FreeListingState::Expired, $this->service->state($profile));
        $this->assertTrue($this->service->endsAt($profile)->isPast());

        $remaining = $this->service->remaining($profile);
        $this->assertSame(0, $remaining['kolabs_remaining']);
    }

    public function test_two_completed_kolabs_is_still_free(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        for ($i = 0; $i < 2; $i++) {
            Collaboration::factory()->completed()->create([
                'business_profile_id' => $business->id,
                'completed_at' => now()->subDay(),
            ]);
        }

        $this->assertSame(FreeListingState::Free, $this->service->state($profile));
        $this->assertSame(1, $this->service->remaining($profile)['kolabs_remaining']);
    }

    public function test_expires_after_ninety_days_with_fewer_than_three_kolabs(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'created_at' => now()->subDays(91),
        ]);

        $this->assertSame(FreeListingState::Expired, $this->service->state($profile));
    }

    public function test_within_ninety_days_and_under_the_kolab_limit_is_free(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'created_at' => now()->subDays(89),
        ]);

        $this->assertSame(FreeListingState::Free, $this->service->state($profile));
        $this->assertSame(1, $this->service->remaining($profile)['days_remaining']);
    }

    public function test_subscribed_business_is_never_expired_even_past_both_limits(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'created_at' => now()->subDays(200),
        ]);
        BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);

        for ($i = 0; $i < 5; $i++) {
            Collaboration::factory()->completed()->create([
                'business_profile_id' => $business->id,
                'completed_at' => now()->subDays(150),
            ]);
        }

        $this->assertSame(FreeListingState::Subscribed, $this->service->state($profile));
    }

    public function test_only_completed_collaborations_count_toward_the_kolab_limit(): void
    {
        $profile = Profile::factory()->business()->create();
        $business = BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        Collaboration::factory()->scheduled()->create(['business_profile_id' => $business->id]);
        Collaboration::factory()->active()->create(['business_profile_id' => $business->id]);
        Collaboration::factory()->cancelled()->create(['business_profile_id' => $business->id]);

        $this->assertSame(FreeListingState::Free, $this->service->state($profile));
        $this->assertSame(3, $this->service->remaining($profile)['kolabs_remaining']);
    }

    public function test_enforcement_is_off_for_every_city_by_default(): void
    {
        $this->assertSame([], config('subscriptions.free_listing.enforcement_city_ids'));
        $this->assertFalse($this->service->isEnforcedForCity('any-city-id'));
        $this->assertFalse($this->service->isEnforcedForCity(null));
    }
}
