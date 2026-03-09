# Freemium Collaboration Gate Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Gate opportunity creation at 1 free collaboration — unsubscribed businesses that already have ≥1 collaboration get HTTP 402 with `requires_subscription: true`.

**Architecture:** Change `OpportunityService::hasReachedFreeLimit()` to count `createdCollaborations` with threshold 1, rename it to `hasReachedFreemiumCollabLimit()`, and update the controller catch block to return 402 instead of 403. Rewrite `OpportunityCreationLimitTest` to match the new rule.

**Tech Stack:** Laravel 12, PHPUnit, `CollaborationFactory::forCreator()`, `BusinessSubscription::factory()->active()`

---

### Task 1: Rewrite the test file to express the new behaviour

The existing `OpportunityCreationLimitTest.php` tests the old 3-opportunity / 403 rule.
Replace it entirely with tests for the new 1-collaboration / 402 rule.

**Files:**
- Modify: `tests/Feature/Api/V1/OpportunityCreationLimitTest.php`

**Step 1: Replace the file content with the new test class**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\OpportunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityCreationLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function validOpportunityData(): array
    {
        return [
            'title' => 'Test Opportunity',
            'description' => 'A test opportunity for collaboration.',
            'business_offer' => ['venue' => true, 'food_drink' => false],
            'community_deliverables' => ['instagram_post' => true, 'attendee_count' => 50],
            'categories' => ['Food & Drink'],
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'venue_mode' => 'business_venue',
            'address' => 'Calle Test 123, Sevilla',
            'preferred_city' => 'Sevilla',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Freemium Gate: 1 free collaboration, then subscription required
    |--------------------------------------------------------------------------
    */

    public function test_business_without_subscription_and_no_collabs_can_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_business_without_subscription_and_one_collab_cannot_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_without_subscription_and_multiple_collabs_cannot_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        Collaboration::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_active_subscription_and_collabs_can_create_opportunity(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        Collaboration::factory()->count(3)->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_community_user_can_create_opportunities_regardless_of_collabs(): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        Collaboration::factory()->count(5)->forApplicant($community)->create();

        $response = $this->actingAs($community)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_all_collab_statuses_count_toward_limit(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        // One cancelled collaboration still triggers the gate
        Collaboration::factory()->cancelled()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_cancelled_subscription_and_collab_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->cancelled()->create(['profile_id' => $business->id]);

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_past_due_subscription_and_collab_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->pastDue()->create(['profile_id' => $business->id]);

        Collaboration::factory()->forCreator($business)->create();

        $response = $this->actingAs($business)
            ->postJson('/api/v1/opportunities', $this->validOpportunityData());

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_subscription', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Service Unit Tests
    |--------------------------------------------------------------------------
    */

    public function test_has_reached_freemium_collab_limit_returns_false_for_community_user(): void
    {
        $community = Profile::factory()->community()->create();
        Collaboration::factory()->count(5)->forApplicant($community)->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($community));
    }

    public function test_has_reached_freemium_collab_limit_returns_false_for_subscribed_business(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);
        Collaboration::factory()->count(5)->forCreator($business)->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($business));
    }

    public function test_has_reached_freemium_collab_limit_returns_true_with_one_collab(): void
    {
        $business = Profile::factory()->business()->create();
        Collaboration::factory()->forCreator($business)->create();

        $service = new OpportunityService;
        $this->assertTrue($service->hasReachedFreemiumCollabLimit($business));
    }

    public function test_has_reached_freemium_collab_limit_returns_false_with_no_collabs(): void
    {
        $business = Profile::factory()->business()->create();

        $service = new OpportunityService;
        $this->assertFalse($service->hasReachedFreemiumCollabLimit($business));
    }
}
```

**Step 2: Run the tests to confirm they fail**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityCreationLimitTest.php
```

Expected: Multiple failures — `hasReachedFreemiumCollabLimit` does not exist, 403 instead of 402, old method returns wrong values.

---

### Task 2: Update OpportunityService

**Files:**
- Modify: `app/Services/OpportunityService.php`

**Step 1: Remove the constant and rename/rewrite the limit method**

Remove these lines:
```php
/**
 * Maximum number of opportunities a business user can create without a subscription.
 */
public const int FREE_OPPORTUNITY_LIMIT = 3;

/**
 * Check if a business user has reached the free opportunity limit.
 */
public function hasReachedFreeLimit(Profile $profile): bool
{
    if (! $profile->isBusiness()) {
        return false;
    }

    if ($profile->hasActiveSubscription()) {
        return false;
    }

    return $profile->createdOpportunities()->count() >= self::FREE_OPPORTUNITY_LIMIT;
}
```

Replace with:
```php
/**
 * Check if a business user has reached the freemium collaboration limit.
 *
 * Unsubscribed business profiles may only accumulate 0 collaborations before
 * being required to subscribe. Once they have ≥1 collaboration, further
 * opportunity creation is blocked until they subscribe.
 */
public function hasReachedFreemiumCollabLimit(Profile $profile): bool
{
    if (! $profile->isBusiness()) {
        return false;
    }

    if ($profile->hasActiveSubscription()) {
        return false;
    }

    return $profile->createdCollaborations()->count() >= 1;
}
```

**Step 2: Update the `create()` method to call the renamed method**

Change:
```php
if ($this->hasReachedFreeLimit($creator)) {
```

To:
```php
if ($this->hasReachedFreemiumCollabLimit($creator)) {
```

Also update the exception message:
```php
throw new InvalidArgumentException(
    'A subscription is required to create more opportunities.'
);
```

**Step 3: Run tests to check progress**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityCreationLimitTest.php
```

Expected: Service unit tests now pass. HTTP tests still failing (402 vs 403).

---

### Task 3: Update OpportunityController to return 402

**Files:**
- Modify: `app/Http/Controllers/Api/V1/OpportunityController.php:145-151`

**Step 1: Change the catch block status code in `store()`**

Change:
```php
} catch (InvalidArgumentException $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'requires_subscription' => true,
    ], 403);
}
```

To:
```php
} catch (InvalidArgumentException $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
        'requires_subscription' => true,
    ], 402);
}
```

**Step 2: Run the full test file**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityCreationLimitTest.php
```

Expected: All tests pass.

---

### Task 4: Run pint, verify no regressions, commit

**Step 1: Run pint to fix formatting**

```bash
vendor/bin/pint --dirty
```

**Step 2: Run the broader opportunity test suite**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityPublishTest.php tests/Feature/Api/V1/OpportunityListingTest.php tests/Feature/Api/V1/OpportunitySearchTest.php tests/Feature/Api/V1/OpportunityCreationLimitTest.php
```

Expected: All pass.

**Step 3: Commit**

```bash
git add app/Services/OpportunityService.php \
        app/Http/Controllers/Api/V1/OpportunityController.php \
        tests/Feature/Api/V1/OpportunityCreationLimitTest.php
git commit -m "feat: gate opportunity creation on collab count, return 402"
```
