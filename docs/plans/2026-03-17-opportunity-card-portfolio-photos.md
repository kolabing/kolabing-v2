# Opportunity Card Portfolio Photos — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Return the opportunity creator's past event photos (and gallery photos) in the opportunity API response so the mobile app can display them as a background slider on the opportunity card.

**Architecture:** Add a `portfolio_photos` field to `ProfileSummaryResource` that merges the creator's latest event photos and gallery photos (max 10). Eager load this data in the `OpportunityService::browse()` query to avoid N+1. Create a seeder to populate realistic event + photo data for testing.

**Tech Stack:** Laravel 12, PostgreSQL, PHPUnit

---

### Task 1: Add `portfolio_photos` to `ProfileSummaryResource`

**Files:**
- Modify: `app/Http/Resources/Api/V1/ProfileSummaryResource.php`

**Step 1: Write the failing test**

Create test file:

```bash
php artisan make:test --phpunit Feature/Api/V1/OpportunityPortfolioPhotosTest --no-interaction
```

Replace content with:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CollabOpportunity;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityPortfolioPhotosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_opportunity_listing_includes_portfolio_photos_from_events(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(3)->for($event)->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(3, $creatorProfile['portfolio_photos']);
        $this->assertArrayHasKey('url', $creatorProfile['portfolio_photos'][0]);
    }

    public function test_opportunity_listing_includes_portfolio_photos_from_gallery(): void
    {
        $business = Profile::factory()->business()->create();

        ProfileGalleryPhoto::factory()->count(2)->for($business, 'profile')->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(2, $creatorProfile['portfolio_photos']);
    }

    public function test_portfolio_photos_merges_events_and_gallery_max_10(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(8)->for($event)->create();
        ProfileGalleryPhoto::factory()->count(5)->for($business, 'profile')->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertLessThanOrEqual(10, count($creatorProfile['portfolio_photos']));
    }

    public function test_portfolio_photos_empty_when_no_media(): void
    {
        $business = Profile::factory()->business()->create();

        CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.data.0.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertEmpty($creatorProfile['portfolio_photos']);
    }

    public function test_opportunity_show_includes_portfolio_photos(): void
    {
        $business = Profile::factory()->business()->create();

        $event = Event::factory()->forProfile($business)->create();
        EventPhoto::factory()->count(2)->for($event)->create();

        $opportunity = CollabOpportunity::factory()
            ->forCreator($business)
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertStatus(200);

        $creatorProfile = $response->json('data.creator_profile');
        $this->assertArrayHasKey('portfolio_photos', $creatorProfile);
        $this->assertCount(2, $creatorProfile['portfolio_photos']);
    }
}
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityPortfolioPhotosTest.php
```

Expected: FAIL — `portfolio_photos` key not found in response.

**Step 3: Implement — Update ProfileSummaryResource**

In `app/Http/Resources/Api/V1/ProfileSummaryResource.php`, add `portfolio_photos` to the return array:

```php
public function toArray(Request $request): array
{
    $extendedProfile = $this->getExtendedProfile();
    $city = $extendedProfile?->city ?? null;

    return [
        'id' => $this->id,
        'user_type' => $this->user_type->value,
        'display_name' => $extendedProfile?->name,
        'avatar_url' => $this->avatar_url,
        'city' => $city ? new CityResource($city) : null,
        'business_type' => $this->when($this->isBusiness(), fn () => $this->businessProfile?->business_type),
        'community_type' => $this->when($this->isCommunity(), fn () => $this->communityProfile?->community_type),
        'portfolio_photos' => $this->getPortfolioPhotos(),
    ];
}

/**
 * Get merged portfolio photos from events and gallery (max 10).
 *
 * @return array<int, array{url: string, source: string}>
 */
private function getPortfolioPhotos(): array
{
    $photos = collect();

    // Event photos (most recent events first)
    if ($this->relationLoaded('events')) {
        foreach ($this->events as $event) {
            if ($event->relationLoaded('photos')) {
                foreach ($event->photos as $photo) {
                    $photos->push([
                        'url' => $photo->url,
                        'thumbnail_url' => $photo->thumbnail_url,
                        'source' => 'event',
                    ]);
                }
            }
        }
    }

    // Gallery photos
    if ($this->relationLoaded('galleryPhotos')) {
        foreach ($this->galleryPhotos as $photo) {
            $photos->push([
                'url' => $photo->url,
                'thumbnail_url' => null,
                'source' => 'gallery',
            ]);
        }
    }

    return $photos->take(10)->values()->all();
}
```

**Step 4: Commit**

```bash
git add app/Http/Resources/Api/V1/ProfileSummaryResource.php tests/Feature/Api/V1/OpportunityPortfolioPhotosTest.php
git commit -m "feat: add portfolio_photos to ProfileSummaryResource"
```

---

### Task 2: Eager load events + photos in opportunity queries

**Files:**
- Modify: `app/Services/OpportunityService.php`

**Step 1: Update `browse()` to eager load creator's events with photos and gallery**

In `OpportunityService::browse()`, change the `with` clause:

```php
// Old:
->with('creatorProfile')

// New:
->with([
    'creatorProfile' => function ($query) {
        $query->with([
            'events' => function ($q) {
                $q->orderByDesc('event_date')->limit(5);
            },
            'events.photos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
            'galleryPhotos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
        ]);
    },
])
```

Do the same for `findOrFail()`:

```php
// Old:
->with(['creatorProfile', 'applications'])

// New:
->with([
    'creatorProfile' => function ($query) {
        $query->with([
            'events' => function ($q) {
                $q->orderByDesc('event_date')->limit(5);
            },
            'events.photos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
            'galleryPhotos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
        ]);
    },
    'applications',
])
```

And `getMyOpportunities()`:

```php
// Old:
->with('creatorProfile')

// New:
->with([
    'creatorProfile' => function ($query) {
        $query->with([
            'events' => function ($q) {
                $q->orderByDesc('event_date')->limit(5);
            },
            'events.photos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
            'galleryPhotos' => function ($q) {
                $q->orderBy('sort_order')->limit(10);
            },
        ]);
    },
])
```

**Step 2: Run tests**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityPortfolioPhotosTest.php
```

Expected: All 5 tests pass.

**Step 3: Run all opportunity tests to verify no regressions**

```bash
php artisan test --compact tests/Feature/Api/V1/Opportunity
```

Expected: All tests pass.

**Step 4: Commit**

```bash
git add app/Services/OpportunityService.php
git commit -m "feat: eager load portfolio photos in opportunity queries"
```

---

### Task 3: Create seed data — Events with photos for businesses and communities

**Files:**
- Create: `database/seeders/PastEventSeeder.php`

**Step 1: Create the seeder**

```bash
php artisan make:seeder PastEventSeeder --no-interaction
```

Replace with:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class PastEventSeeder extends Seeder
{
    /**
     * Seed past events with photos for business and community profiles.
     */
    public function run(): void
    {
        $profiles = Profile::query()
            ->whereIn('user_type', ['business', 'community'])
            ->get();

        if ($profiles->isEmpty()) {
            $this->command->warn('No business or community profiles found. Run ProfileSeeder first.');

            return;
        }

        foreach ($profiles as $profile) {
            $eventCount = fake()->numberBetween(2, 5);

            for ($i = 0; $i < $eventCount; $i++) {
                $event = Event::factory()->forProfile($profile)->create([
                    'event_date' => fake()->dateTimeBetween('-6 months', '-1 week'),
                    'is_active' => false,
                ]);

                $photoCount = fake()->numberBetween(2, 6);
                EventPhoto::factory()->count($photoCount)->for($event)->create();
            }
        }

        $totalEvents = Event::query()->count();
        $totalPhotos = EventPhoto::query()->count();
        $this->command->info("Created {$totalEvents} past events with {$totalPhotos} photos.");
    }
}
```

**Step 2: Verify the EventFactory has `forProfile` state**

Check `database/factories/EventFactory.php` has a `forProfile(Profile $profile)` state. If not, add it.

**Step 3: Run the seeder**

```bash
php artisan db:seed --class=PastEventSeeder
```

Expected: Output like "Created 15 past events with 60 photos."

**Step 4: Commit**

```bash
git add database/seeders/PastEventSeeder.php
git commit -m "feat: add PastEventSeeder for event photos seed data"
```

---

### Task 4: Run full test suite + Pint

**Step 1: Run all tests**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 2: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 3: Final commit if needed**

```bash
git add -A && git commit -m "style: apply pint formatting"
```
