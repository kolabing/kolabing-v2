# Business Kolab Flow — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the minimal backend surface the redesigned business Kolab creation flow needs: a `goal` + `highlights` field on `kolabs`, four new admin-managed `OfferOption` taxonomy kinds (`goal`, `product_interaction`, `venue_fit`, `kolab_highlight`), an expanded `deliverable` taxonomy, and one validation fix so "Immediate / always available" kolabs can actually save.

**Architecture:** Everything new follows the existing `OfferOption` pattern exactly (`app/Models/OfferOption.php`, `app/Http/Controllers/Admin/OfferOptionController.php`, `app/Http/Controllers/Api/V1/LookupController.php`) — a `kind`-discriminated table with admin CRUD + a public read-only lookup endpoint per kind. No new tables. One migration adds two nullable, additive columns to `kolabs`.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit, PostgreSQL.

## Global Constraints

- No changes to `collab_opportunities` — confirmed legacy/dead (no model, no active controller reads/writes it; `OpportunityController`/`OpportunityService` already operate on `Kolab`/`KolabService` despite the legacy name).
- `goal` and `highlights` are additive nullable columns only — no existing column changes, no data migration.
- All new selectable-option taxonomies are admin-manageable via `OfferOption` (kind-discriminated) — never hardcode option lists in PHP beyond the one-time seeder defaults.
- `deliverable` kind: additive only. Do not delete or deactivate the existing 5 rows (`social_media`, `event_activation`, `product_placement`, `community_reach`, `review_feedback`) as part of this work.
- Follow existing code style: `declare(strict_types=1)`, explicit return types, PHPDoc over inline comments, PSR-12 via Pint.
- Run `vendor/bin/pint --dirty` before considering any task done.
- Run `php artisan test --compact` with a filter for touched tests after each task; run the full suite only at the end per Laravel Boost guidance (ask before doing so).

---

### Task 1: Migration — `goal` and `highlights` columns on `kolabs`

**Files:**
- Create: `database/migrations/2026_06_24_000001_add_goal_and_highlights_to_kolabs_table.php`
- Test: `tests/Unit/Models/KolabGoalHighlightsColumnTest.php`

**Interfaces:**
- Produces: `kolabs.goal` (nullable string, max 50), `kolabs.highlights` (nullable json, no default — matches existing json column style in this table, e.g. `expects`).

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->string('goal', 50)->nullable()->after('description');
            $table->json('highlights')->nullable()->after('past_events');
        });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropColumn(['goal', 'highlights']);
        });
    }
};
```

- [ ] **Step 2: Run the migration locally**

Run: `php artisan migrate --no-interaction`
Expected: `2026_06_24_000001_add_goal_and_highlights_to_kolabs_table ... DONE`

- [ ] **Step 3: Write a test asserting the columns exist and round-trip**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabGoalHighlightsColumnTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_goal_and_highlights_persist(): void
    {
        $profile = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->for($profile, 'creatorProfile')->create([
            'goal' => 'more_visits',
            'highlights' => ['good_location', 'free_samples'],
        ]);

        $kolab->refresh();

        $this->assertSame('more_visits', $kolab->goal);
        $this->assertSame(['good_location', 'free_samples'], $kolab->highlights);
    }

    public function test_goal_and_highlights_are_nullable(): void
    {
        $profile = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->for($profile, 'creatorProfile')->create([
            'goal' => null,
            'highlights' => null,
        ]);

        $kolab->refresh();

        $this->assertNull($kolab->goal);
        $this->assertNull($kolab->highlights);
    }
}
```

(Check `App\Models\Kolab` for the actual `creatorProfile` relation name and `Kolab::factory()` defaults — read `app/Models/Kolab.php` and `database/factories/KolabFactory.php` first if the relation/factory call above doesn't match; adjust the test to use whatever factory states already exist, e.g. `Kolab::factory()->venuePromotion()->create([...])` if such a state exists.)

- [ ] **Step 4: Add `goal` and `highlights` to `Kolab`'s `$casts()`**

Read `app/Models/Kolab.php`'s `casts()` method (it already casts `past_events`, `expects`, etc. — likely via a custom cast or plain `array`). Add `'highlights' => 'array'` alongside the existing array-cast JSON columns. `goal` needs no cast (plain string).

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact tests/Unit/Models/KolabGoalHighlightsColumnTest.php`
Expected: PASS

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty
git add database/migrations/2026_06_24_000001_add_goal_and_highlights_to_kolabs_table.php tests/Unit/Models/KolabGoalHighlightsColumnTest.php app/Models/Kolab.php
git commit -m "feat: add nullable goal and highlights columns to kolabs"
```

---

### Task 2: Register 4 new `OfferOption` kinds in the model

**Files:**
- Modify: `app/Models/OfferOption.php:35-52`

**Interfaces:**
- Produces: `OfferOption::KIND_GOAL`, `OfferOption::KIND_PRODUCT_INTERACTION`, `OfferOption::KIND_VENUE_FIT`, `OfferOption::KIND_KOLAB_HIGHLIGHT`, all added to `OfferOption::KINDS`.

- [ ] **Step 1: Add the constants and extend `KINDS`**

```php
    public const KIND_OFFERING = 'offering';

    public const KIND_DELIVERABLE = 'deliverable';

    public const KIND_NEED = 'need';

    public const KIND_PRODUCT_TYPE = 'product_type';

    public const KIND_VENUE_TYPE = 'venue_type';

    public const KIND_GOAL = 'goal';

    public const KIND_PRODUCT_INTERACTION = 'product_interaction';

    public const KIND_VENUE_FIT = 'venue_fit';

    public const KIND_KOLAB_HIGHLIGHT = 'kolab_highlight';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_OFFERING,
        self::KIND_DELIVERABLE,
        self::KIND_NEED,
        self::KIND_PRODUCT_TYPE,
        self::KIND_VENUE_TYPE,
        self::KIND_GOAL,
        self::KIND_PRODUCT_INTERACTION,
        self::KIND_VENUE_FIT,
        self::KIND_KOLAB_HIGHLIGHT,
    ];
```

- [ ] **Step 2: Update the class doc-comment** (the `@property`/usage comment at the top of the file) to mention the 4 new kinds, mirroring the existing description style.

- [ ] **Step 3: No test needed for this step alone** — it's exercised by Tasks 3-6's tests. Proceed.

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Models/OfferOption.php
git commit -m "feat: register goal, product_interaction, venue_fit, kolab_highlight OfferOption kinds"
```

---

### Task 3: Extend `OfferOptionValues` fallback + validation wiring for the 4 new kinds + `deliverable`

**Files:**
- Modify: `app/Support/OfferOptionValues.php`

**Interfaces:**
- Consumes: `OfferOption::KIND_GOAL` etc. from Task 2.
- Produces: `OfferOptionValues::for(OfferOption::KIND_GOAL)` etc. — used by Task 6 (request validation).

- [ ] **Step 1: Add fallback constant lists and extend `DELIVERABLE`**

```php
final class OfferOptionValues
{
    /** @var array<int, string> */
    public const OFFERING = [
        'venue', 'venue_space', 'food_drink', 'free_drinks', 'discount',
        'products', 'social_media', 'content_creation', 'sponsorship', 'other',
    ];

    /**
     * @var array<int, string> Offered in return: community offers_in_return / business expects.
     * Additive — the original 5 broad slugs stay valid; new granular slugs are appended.
     */
    public const DELIVERABLE = [
        'social_media', 'event_activation', 'product_placement', 'community_reach', 'review_feedback',
        'minimum_attendance', 'minimum_spend', 'tagged_stories', 'instagram_post_reel', 'ugc_content',
        'reviews', 'product_feedback', 'community_photos', 'newsletter_mention', 'long_term_partnership',
        'open_to_ideas',
    ];

    /** @var array<int, string> Community asks (needs[]). */
    public const NEED = ['venue', 'food_drink', 'sponsor', 'products', 'discount', 'other'];

    /** @var array<int, string> What a Kolab is meant to achieve (goal). */
    public const GOAL = [
        'more_visits', 'product_awareness', 'content_tagged_posts', 'reviews', 'sales_revenue',
        'community_event', 'product_testing', 'recurring_partnership', 'community_perk', 'open_to_ideas',
    ];

    /** @var array<int, string> How communities can interact with a product. */
    public const PRODUCT_INTERACTION = [
        'try_samples', 'review_it', 'create_content', 'use_during_event', 'give_feedback',
        'giveaway', 'discount_code', 'sell_during_event', 'open_to_ideas',
    ];

    /** @var array<int, string> "Best for" chips on a venue promotion. */
    public const VENUE_FIT = [
        'coffee', 'brunch', 'dinner', 'drinks', 'wellness', 'shopping', 'workshops', 'content',
        'after_run', 'after_work', 'networking', 'pop_ups', 'recurring_plans',
    ];

    /** @var array<int, string> "Why communities will like this" chips. */
    public const KOLAB_HIGHLIGHT = [
        'good_location', 'nice_space_for_groups', 'great_photo_spot', 'healthy_sporty_offer',
        'free_samples', 'discount_for_members', 'good_for_after_work', 'good_after_workout',
        'recurring_kolabs', 'unique_experience', 'new_product_to_try', 'premium_experience',
        'easy_public_transport', 'outdoor_friendly', 'cozy_indoor_space', 'good_for_content',
    ];

    /**
     * Active slugs for a kind: DB-backed, falling back to the launch defaults.
     *
     * @return list<string>
     */
    public static function for(string $kind): array
    {
        $fallback = match ($kind) {
            OfferOption::KIND_OFFERING => self::OFFERING,
            OfferOption::KIND_DELIVERABLE => self::DELIVERABLE,
            OfferOption::KIND_NEED => self::NEED,
            OfferOption::KIND_PRODUCT_TYPE => ProductType::values(),
            OfferOption::KIND_VENUE_TYPE => VenueType::values(),
            OfferOption::KIND_GOAL => self::GOAL,
            OfferOption::KIND_PRODUCT_INTERACTION => self::PRODUCT_INTERACTION,
            OfferOption::KIND_VENUE_FIT => self::VENUE_FIT,
            OfferOption::KIND_KOLAB_HIGHLIGHT => self::KOLAB_HIGHLIGHT,
            default => [],
        };

        try {
            $slugs = OfferOption::activeSlugs($kind);

            return $slugs !== [] ? $slugs : array_values($fallback);
        } catch (Throwable) {
            // Table missing (pre-migration) or DB error → keep validating on defaults.
            return array_values($fallback);
        }
    }
}
```

- [ ] **Step 2: No standalone test** — covered by Task 6's request-validation tests and Task 4/5's lookup/admin tests. Proceed.

- [ ] **Step 3: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Support/OfferOptionValues.php
git commit -m "feat: add fallback slug lists for goal, product_interaction, venue_fit, kolab_highlight; extend deliverable"
```

---

### Task 4: Admin controller — register the 4 new kinds

**Files:**
- Modify: `app/Http/Controllers/Admin/OfferOptionController.php:28-54`
- Test: `tests/Feature/Admin/OfferOptionAdminTest.php` (extend existing — read it first to match its exact assertion/auth style, then add cases for the new kinds following the same pattern as the existing 5)

**Interfaces:**
- Consumes: `OfferOption::KIND_GOAL` etc. (Task 2).
- Produces: `/admin/offer-options?kind=goal` (and the other 3) work identically to the existing 5 kinds — index/create/edit/store/update/destroy/toggle/reorder, no controller logic changes beyond the lookup tables below.

- [ ] **Step 1: Extend `LABELS`**

```php
    private const LABELS = [
        OfferOption::KIND_OFFERING => 'Offerings',
        OfferOption::KIND_DELIVERABLE => 'Deliverables',
        OfferOption::KIND_NEED => 'Needs',
        OfferOption::KIND_PRODUCT_TYPE => 'Product types',
        OfferOption::KIND_VENUE_TYPE => 'Venue types',
        OfferOption::KIND_GOAL => 'Goals',
        OfferOption::KIND_PRODUCT_INTERACTION => 'Product interactions',
        OfferOption::KIND_VENUE_FIT => 'Venue fits',
        OfferOption::KIND_KOLAB_HIGHLIGHT => 'Kolab highlights',
    ];
```

**Decision:** `venue_fit` and `product_interaction` are *informational chip labels only* — there is no dedicated `kolabs` column for either, and no new validated request field. The frontend composes the chosen chip labels into the existing freeform `description` text when building the submission payload (e.g. appending "Best for: Coffee, Brunch, Workshops." to the description the business already writes). The lookup endpoints (Task 5) exist purely so the chip options themselves are admin-managed/dynamic, not so selections are stored as structured data. Because nothing on `kolabs` references these slugs directly, `inUseCount()` cannot detect usage for them — map them to an empty column list so `destroy()` always hard-deletes (no in-use protection), matching the fact that they're not structurally referenced.

- [ ] **Step 2: Extend `inUseCount()`'s column map**

```php
    private function inUseCount(string $kind, string $slug): int
    {
        $columns = match ($kind) {
            OfferOption::KIND_OFFERING => ['offering'],
            OfferOption::KIND_DELIVERABLE => ['offers_in_return', 'expects'],
            OfferOption::KIND_NEED => ['needs'],
            OfferOption::KIND_PRODUCT_TYPE => ['product_type'],
            OfferOption::KIND_VENUE_TYPE => ['venue_type'],
            OfferOption::KIND_GOAL => ['goal'],
            OfferOption::KIND_PRODUCT_INTERACTION => [],
            OfferOption::KIND_VENUE_FIT => [],
            OfferOption::KIND_KOLAB_HIGHLIGHT => ['highlights'],
            default => [],
        };

        $isScalar = in_array($kind, self::SCALAR_KINDS, true);
        // ...unchanged below
```

Note: `goal` is a **scalar** column (`string`), not a JSON array — add it to `SCALAR_KINDS` too:

```php
    /** Kinds stored as a scalar string column on kolabs (not a JSON array). */
    private const SCALAR_KINDS = [
        OfferOption::KIND_PRODUCT_TYPE,
        OfferOption::KIND_VENUE_TYPE,
        OfferOption::KIND_GOAL,
    ];
```

- [ ] **Step 3: Read `tests/Feature/Admin/OfferOptionAdminTest.php` in full**, then add 4 new dataset entries (or a new `#[DataProvider]` case per kind) mirroring however the existing 5 kinds are tested — likely a parameterized test already iterating `OfferOption::KINDS`-like list; if so, just adding the constants in Task 2 may already extend test coverage. Run the existing test file first to check:

Run: `php artisan test --compact tests/Feature/Admin/OfferOptionAdminTest.php`
Expected: confirm whether it's already kind-agnostic (passes with no changes) or needs explicit new cases. If the latter, copy the pattern used for `venue_type`/`product_type` and substitute the new kind constants.

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact tests/Feature/Admin/OfferOptionAdminTest.php`
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Admin/OfferOptionController.php tests/Feature/Admin/OfferOptionAdminTest.php
git commit -m "feat: admin-manage goal, product_interaction, venue_fit, kolab_highlight option kinds"
```

---

### Task 5: Public lookup endpoints for the 4 new kinds

**Files:**
- Modify: `app/Http/Controllers/Api/V1/LookupController.php` (add 4 methods after `venueTypes()`, around line 385)
- Modify: `routes/api.php` (add 4 routes after `lookup/venue-types`, around line 139)
- Modify: `tests/Feature/Api/V1/OfferOptionLookupTest.php` (extend the `endpoints()` data provider)

**Interfaces:**
- Produces: `GET /api/v1/lookup/goals`, `GET /api/v1/lookup/product-interactions`, `GET /api/v1/lookup/venue-fits`, `GET /api/v1/lookup/kolab-highlights`, each returning `{success, data: [{value, label, icon, icon_url, is_active, sort_order}], meta: {total}}`.

- [ ] **Step 1: Add the 4 controller methods**

```php
    /**
     * Get the list of goal options for a business Kolab.
     *
     * GET /api/v1/lookup/goals
     */
    public function goals(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_GOAL);
    }

    /**
     * Get the list of product-interaction options (how communities can engage
     * with a product promotion).
     *
     * GET /api/v1/lookup/product-interactions
     */
    public function productInteractions(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_PRODUCT_INTERACTION);
    }

    /**
     * Get the list of venue-fit options ("Best for:" chips on a venue promotion).
     *
     * GET /api/v1/lookup/venue-fits
     */
    public function venueFits(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_VENUE_FIT);
    }

    /**
     * Get the list of kolab-highlight options ("Why communities will like this").
     *
     * GET /api/v1/lookup/kolab-highlights
     */
    public function kolabHighlights(): JsonResponse
    {
        return $this->offerOptionResponse(\App\Models\OfferOption::KIND_KOLAB_HIGHLIGHT);
    }
```

- [ ] **Step 2: Add the 4 routes** (in the same `Route::get('lookup/...')` block as the existing 5, after line 139)

```php
    Route::get('lookup/goals', [LookupController::class, 'goals'])
        ->name('api.v1.lookup.goals');

    Route::get('lookup/product-interactions', [LookupController::class, 'productInteractions'])
        ->name('api.v1.lookup.product-interactions');

    Route::get('lookup/venue-fits', [LookupController::class, 'venueFits'])
        ->name('api.v1.lookup.venue-fits');

    Route::get('lookup/kolab-highlights', [LookupController::class, 'kolabHighlights'])
        ->name('api.v1.lookup.kolab-highlights');
```

- [ ] **Step 3: Extend `OfferOptionLookupTest::endpoints()`**

```php
    public static function endpoints(): array
    {
        return [
            'offerings' => ['api.v1.lookup.offerings', OfferOption::KIND_OFFERING],
            'deliverables' => ['api.v1.lookup.deliverables', OfferOption::KIND_DELIVERABLE],
            'needs' => ['api.v1.lookup.needs', OfferOption::KIND_NEED],
            'product-types' => ['api.v1.lookup.product-types', OfferOption::KIND_PRODUCT_TYPE],
            'venue-types' => ['api.v1.lookup.venue-types', OfferOption::KIND_VENUE_TYPE],
            'goals' => ['api.v1.lookup.goals', OfferOption::KIND_GOAL],
            'product-interactions' => ['api.v1.lookup.product-interactions', OfferOption::KIND_PRODUCT_INTERACTION],
            'venue-fits' => ['api.v1.lookup.venue-fits', OfferOption::KIND_VENUE_FIT],
            'kolab-highlights' => ['api.v1.lookup.kolab-highlights', OfferOption::KIND_KOLAB_HIGHLIGHT],
        ];
    }
```

Also add the 4 new route names to `test_lookup_endpoints_are_public()`:

```php
    public function test_lookup_endpoints_are_public(): void
    {
        $this->getJson(route('api.v1.lookup.offerings'))->assertOk();
        $this->getJson(route('api.v1.lookup.deliverables'))->assertOk();
        $this->getJson(route('api.v1.lookup.needs'))->assertOk();
        $this->getJson(route('api.v1.lookup.product-types'))->assertOk();
        $this->getJson(route('api.v1.lookup.venue-types'))->assertOk();
        $this->getJson(route('api.v1.lookup.goals'))->assertOk();
        $this->getJson(route('api.v1.lookup.product-interactions'))->assertOk();
        $this->getJson(route('api.v1.lookup.venue-fits'))->assertOk();
        $this->getJson(route('api.v1.lookup.kolab-highlights'))->assertOk();
    }
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact tests/Feature/Api/V1/OfferOptionLookupTest.php`
Expected: PASS (9 datasets for the parameterized test, both public-endpoints assertions pass)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Controllers/Api/V1/LookupController.php routes/api.php tests/Feature/Api/V1/OfferOptionLookupTest.php
git commit -m "feat: add lookup endpoints for goals, product-interactions, venue-fits, kolab-highlights"
```

---

### Task 6: Seed initial defaults for the 4 new kinds + extend `deliverable`

**Files:**
- Modify: `database/seeders/OfferOptionSeeder.php`

**Interfaces:**
- Consumes: `OfferOption::KIND_GOAL` etc. (Task 2).
- Produces: seeded rows admins can then edit/deactivate/reorder via `/admin/offer-options`.

- [ ] **Step 1: Extend the `options()` array** — add 4 new kind blocks, and append (don't replace) 11 new rows to the existing `KIND_DELIVERABLE` block:

```php
            // What's offered IN RETURN (community offers_in_return[] / business expects[]).
            // Additive: the original 5 broad rows stay — do not remove them.
            OfferOption::KIND_DELIVERABLE => [
                ['name' => 'Social Media', 'slug' => 'social_media', 'icon' => 'share-2'],
                ['name' => 'Event Activation', 'slug' => 'event_activation', 'icon' => 'sparkles'],
                ['name' => 'Product Placement', 'slug' => 'product_placement', 'icon' => 'package'],
                ['name' => 'Community Reach', 'slug' => 'community_reach', 'icon' => 'users'],
                ['name' => 'Review & Feedback', 'slug' => 'review_feedback', 'icon' => 'star'],
                ['name' => 'Minimum Attendance', 'slug' => 'minimum_attendance', 'icon' => 'users'],
                ['name' => 'Minimum Revenue / Spend', 'slug' => 'minimum_spend', 'icon' => 'banknote'],
                ['name' => 'Tagged Stories', 'slug' => 'tagged_stories', 'icon' => 'at-sign'],
                ['name' => 'Instagram Post or Reel', 'slug' => 'instagram_post_reel', 'icon' => 'instagram'],
                ['name' => 'UGC / Content', 'slug' => 'ugc_content', 'icon' => 'camera'],
                ['name' => 'Reviews', 'slug' => 'reviews', 'icon' => 'star'],
                ['name' => 'Product Feedback', 'slug' => 'product_feedback', 'icon' => 'message-square'],
                ['name' => 'Community Photos', 'slug' => 'community_photos', 'icon' => 'image'],
                ['name' => 'Newsletter Mention', 'slug' => 'newsletter_mention', 'icon' => 'mail'],
                ['name' => 'Long-Term Partnership', 'slug' => 'long_term_partnership', 'icon' => 'handshake'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
```

```php
            // What a business Kolab is meant to achieve.
            OfferOption::KIND_GOAL => [
                ['name' => 'More Visits', 'slug' => 'more_visits', 'icon' => 'map-pin'],
                ['name' => 'Product Awareness', 'slug' => 'product_awareness', 'icon' => 'megaphone'],
                ['name' => 'Content / Tagged Posts', 'slug' => 'content_tagged_posts', 'icon' => 'camera'],
                ['name' => 'Reviews', 'slug' => 'reviews', 'icon' => 'star'],
                ['name' => 'Sales / Revenue', 'slug' => 'sales_revenue', 'icon' => 'banknote'],
                ['name' => 'Community Event', 'slug' => 'community_event', 'icon' => 'calendar'],
                ['name' => 'Product Testing', 'slug' => 'product_testing', 'icon' => 'flask-conical'],
                ['name' => 'Recurring Partnership', 'slug' => 'recurring_partnership', 'icon' => 'repeat'],
                ['name' => 'Community Perk / Member Discount', 'slug' => 'community_perk', 'icon' => 'percent'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
            // How communities can interact with a product (product promotion only).
            OfferOption::KIND_PRODUCT_INTERACTION => [
                ['name' => 'Try Samples', 'slug' => 'try_samples', 'icon' => 'package'],
                ['name' => 'Review It', 'slug' => 'review_it', 'icon' => 'star'],
                ['name' => 'Create Content', 'slug' => 'create_content', 'icon' => 'camera'],
                ['name' => 'Use During an Event', 'slug' => 'use_during_event', 'icon' => 'calendar'],
                ['name' => 'Give Feedback', 'slug' => 'give_feedback', 'icon' => 'message-square'],
                ['name' => 'Offer as a Giveaway', 'slug' => 'giveaway', 'icon' => 'gift'],
                ['name' => 'Promote a Discount Code', 'slug' => 'discount_code', 'icon' => 'percent'],
                ['name' => 'Sell During an Event', 'slug' => 'sell_during_event', 'icon' => 'shopping-cart'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
            // "Best for:" chips on a venue promotion.
            OfferOption::KIND_VENUE_FIT => [
                ['name' => 'Coffee', 'slug' => 'coffee', 'icon' => 'coffee'],
                ['name' => 'Brunch', 'slug' => 'brunch', 'icon' => 'utensils'],
                ['name' => 'Dinner', 'slug' => 'dinner', 'icon' => 'utensils'],
                ['name' => 'Drinks', 'slug' => 'drinks', 'icon' => 'wine'],
                ['name' => 'Wellness', 'slug' => 'wellness', 'icon' => 'sparkles'],
                ['name' => 'Shopping', 'slug' => 'shopping', 'icon' => 'shopping-bag'],
                ['name' => 'Workshops', 'slug' => 'workshops', 'icon' => 'hammer'],
                ['name' => 'Content', 'slug' => 'content', 'icon' => 'camera'],
                ['name' => 'After-Run', 'slug' => 'after_run', 'icon' => 'footprints'],
                ['name' => 'After-Work', 'slug' => 'after_work', 'icon' => 'briefcase'],
                ['name' => 'Networking', 'slug' => 'networking', 'icon' => 'users'],
                ['name' => 'Pop-Ups', 'slug' => 'pop_ups', 'icon' => 'store'],
                ['name' => 'Recurring Plans', 'slug' => 'recurring_plans', 'icon' => 'repeat'],
            ],
            // "Why communities will like this" chips.
            OfferOption::KIND_KOLAB_HIGHLIGHT => [
                ['name' => 'Good Location', 'slug' => 'good_location', 'icon' => 'map-pin'],
                ['name' => 'Nice Space for Groups', 'slug' => 'nice_space_for_groups', 'icon' => 'users'],
                ['name' => 'Great Photo Spot', 'slug' => 'great_photo_spot', 'icon' => 'camera'],
                ['name' => 'Healthy / Sporty Offer', 'slug' => 'healthy_sporty_offer', 'icon' => 'dumbbell'],
                ['name' => 'Free Samples', 'slug' => 'free_samples', 'icon' => 'package'],
                ['name' => 'Discount for Members', 'slug' => 'discount_for_members', 'icon' => 'percent'],
                ['name' => 'Good for After-Work Plans', 'slug' => 'good_for_after_work', 'icon' => 'briefcase'],
                ['name' => 'Good After a Workout', 'slug' => 'good_after_workout', 'icon' => 'dumbbell'],
                ['name' => 'Can Host Recurring Kolabs', 'slug' => 'recurring_kolabs', 'icon' => 'repeat'],
                ['name' => 'Unique Experience', 'slug' => 'unique_experience', 'icon' => 'sparkles'],
                ['name' => 'New Product to Try', 'slug' => 'new_product_to_try', 'icon' => 'package'],
                ['name' => 'Premium Experience', 'slug' => 'premium_experience', 'icon' => 'gem'],
                ['name' => 'Easy to Reach by Public Transport', 'slug' => 'easy_public_transport', 'icon' => 'train'],
                ['name' => 'Outdoor-Friendly', 'slug' => 'outdoor_friendly', 'icon' => 'sun'],
                ['name' => 'Cozy Indoor Space', 'slug' => 'cozy_indoor_space', 'icon' => 'home'],
                ['name' => 'Good for Content', 'slug' => 'good_for_content', 'icon' => 'camera'],
            ],
```

Note the seeder's `run()` loop is re-seed-safe (updates name/icon/order on existing slugs, never touches `is_active`) — adding new array entries is purely additive and safe to run against a live DB.

- [ ] **Step 2: Run the seeder locally**

Run: `php artisan db:seed --class=Database\\Seeders\\OfferOptionSeeder --no-interaction`
Expected: completes with no errors.

- [ ] **Step 3: Verify via tinker**

Run: `php artisan tinker --execute="echo App\Models\OfferOption::where('kind','goal')->count();"`
Expected: `10`

- [ ] **Step 4: Pint + commit**

```bash
vendor/bin/pint --dirty
git add database/seeders/OfferOptionSeeder.php
git commit -m "feat: seed default options for goal, product_interaction, venue_fit, kolab_highlight, and expand deliverable"
```

---

### Task 7: Request validation + resource output for `goal` and `highlights`

**Files:**
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php` (rules + messages)
- Modify: `app/Http/Requests/Api/V1/UpdateKolabRequest.php` (mirror the same rule, read it first — it has its own `rules()`, likely very close to `CreateKolabRequest`'s)
- Modify: `app/Http/Resources/Api/V1/KolabResource.php:25-75`
- Test: `tests/Feature/Api/V1/KolabControllerTest.php` or equivalent existing create/update Kolab feature test (search for one first, e.g. `grep -rl "offer_headline" tests/Feature` to find the right file to extend)

**Interfaces:**
- Consumes: `OfferOptionValues::for(OfferOption::KIND_GOAL)`, `OfferOptionValues::for(OfferOption::KIND_KOLAB_HIGHLIGHT)` (Task 3).
- Produces: `goal` and `highlights` accepted on create/update, returned in the Kolab API resource.

- [ ] **Step 1: Add validation rules to `CreateKolabRequest::rules()`** (insert near `offer_headline`/`base_offer`, around line 54-55)

```php
            'offer_headline' => ['sometimes', 'nullable', 'string', 'max:50'],
            'base_offer' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'goal' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_GOAL))],
            'highlights' => ['sometimes', 'nullable', 'array'],
            'highlights.*' => ['string', 'in:'.implode(',', OfferOptionValues::for(OfferOption::KIND_KOLAB_HIGHLIGHT))],
```

Add a matching message entry:

```php
            'goal.in' => __('validation.in', ['attribute' => 'goal']),
            'highlights.*.in' => __('validation.in', ['attribute' => 'highlights item']),
```

- [ ] **Step 2: Mirror the exact same two rule lines + messages in `UpdateKolabRequest`** — read its current `rules()`/`messages()` first since it may already differ slightly from `CreateKolabRequest` (e.g. fewer `required_if`s since it's a partial update); insert the `goal`/`highlights` rules in the equivalent position.

- [ ] **Step 3: Add to `KolabResource::toArray()`** (insert near `offer_headline`/`base_offer`, around line 37-38)

```php
            'offer_headline' => $this->resolveOfferHeadline(),
            'base_offer' => $this->resolveBaseOffer(),
            'goal' => $this->goal,
            'highlights' => $this->highlights ?? [],
```

- [ ] **Step 4: Find the existing Kolab create/update feature test**

Run: `grep -rl "offer_headline" tests/Feature`
Read whichever file(s) it returns to find the existing pattern for asserting a field round-trips through create → resource response.

- [ ] **Step 5: Add a test case following that exact pattern**, e.g. (adapt field/route names to match what Step 4 reveals):

```php
    public function test_create_kolab_persists_goal_and_highlights(): void
    {
        $profile = Profile::factory()->business()->withSubscription()->create();

        $response = $this->actingAs($profile->user)->postJson(route('api.v1.kolabs.store'), [
            'intent_type' => 'venue_promotion',
            'title' => 'Coffee tasting',
            'description' => 'Free coffee tasting for runners.',
            'preferred_city' => 'Madrid',
            'goal' => 'more_visits',
            'highlights' => ['good_location', 'free_samples'],
            'offering' => ['venue'],
            'venue_name' => 'Test Cafe',
            'venue_type' => 'cafe',
            'capacity' => 20,
            'venue_address' => 'Calle Test 1',
            'media' => [['url' => 'https://example.com/photo.jpg', 'type' => 'image']],
            'availability_mode' => 'flexible',
            'availability_start' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $response->assertJsonPath('data.goal', 'more_visits');
        $response->assertJsonPath('data.highlights', ['good_location', 'free_samples']);
    }

    public function test_create_kolab_rejects_invalid_goal(): void
    {
        $profile = Profile::factory()->business()->withSubscription()->create();

        $this->actingAs($profile->user)->postJson(route('api.v1.kolabs.store'), [
            'intent_type' => 'venue_promotion',
            'title' => 'Coffee tasting',
            'description' => 'Free coffee tasting for runners.',
            'preferred_city' => 'Madrid',
            'goal' => 'not_a_real_goal',
            'offering' => ['venue'],
            'venue_name' => 'Test Cafe',
            'venue_type' => 'cafe',
            'capacity' => 20,
            'venue_address' => 'Calle Test 1',
            'media' => [['url' => 'https://example.com/photo.jpg', 'type' => 'image']],
            'availability_mode' => 'flexible',
            'availability_start' => now()->addDay()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('goal');
    }
```

Adjust the exact required fields above (route name, business profile setup, `withSubscription()` availability) to match whatever the existing test file in Step 4 already does for a minimal valid venue-promotion payload — copy a passing example from that file and only add `goal`/`highlights`.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact --filter=test_create_kolab_persists_goal_and_highlights`
Run: `php artisan test --compact --filter=test_create_kolab_rejects_invalid_goal`
Expected: both PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Api/V1/CreateKolabRequest.php app/Http/Requests/Api/V1/UpdateKolabRequest.php app/Http/Resources/Api/V1/KolabResource.php tests/Feature/Api/V1/
git commit -m "feat: accept and expose goal and highlights on kolab create/update"
```

---

### Task 8: Allow "Immediate / always available" to start today

**Files:**
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php:96-97` (and the matching block in `UpdateKolabRequest`)

**Interfaces:**
- Consumes: a new `availability_mode` value `immediate` (frontend plan adds this option; no DB enum to update since `availability_mode` is a free `string(20)` column).
- Produces: `availability_start` accepts today's date when `availability_mode = immediate`; still requires strictly-after-today for every other mode (unchanged behavior).

**Why this task is necessary:** `availability_start` currently has a hard `after:today` rule applied unconditionally whenever the field is present. The new "Immediate / always available" mode (frontend plan) needs `today` to validate, or every immediate kolab would fail to save with a 422. This is the one validation-only backend change beyond the additive taxonomy/columns work above.

- [ ] **Step 1: Change the rule from a flat array to a closure that branches on `availability_mode`**

```php
            'availability_mode' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'in:one_time,recurring,flexible,specific_dates,immediate'],
            'availability_start' => [
                'required_if:intent_type,venue_promotion',
                'nullable',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $isImmediate = $this->input('availability_mode') === 'immediate';
                    $minDate = $isImmediate ? today() : today()->addDay();

                    if (\Illuminate\Support\Carbon::parse($value)->lt($minDate)) {
                        $fail($isImmediate
                            ? __('The availability start date cannot be in the past.')
                            : __('validation.after', ['attribute' => 'availability start', 'date' => 'today']));
                    }
                },
            ],
```

- [ ] **Step 2: Apply the identical change to `UpdateKolabRequest`** — read its current `availability_mode`/`availability_start` rules first (they may already differ from Create's `required_if`); keep its existing requiredness semantics, only changing the date-floor logic to match Step 1.

- [ ] **Step 3: Write a test for both branches**

Find the same existing Kolab feature test file from Task 7 Step 4 and add:

```php
    public function test_immediate_availability_accepts_today(): void
    {
        $profile = Profile::factory()->business()->withSubscription()->create();

        $this->actingAs($profile->user)->postJson(route('api.v1.kolabs.store'), [
            'intent_type' => 'venue_promotion',
            'title' => 'Coffee tasting',
            'description' => 'Free coffee tasting for runners.',
            'preferred_city' => 'Madrid',
            'offering' => ['venue'],
            'venue_name' => 'Test Cafe',
            'venue_type' => 'cafe',
            'capacity' => 20,
            'venue_address' => 'Calle Test 1',
            'media' => [['url' => 'https://example.com/photo.jpg', 'type' => 'image']],
            'availability_mode' => 'immediate',
            'availability_start' => now()->toDateString(),
        ])->assertCreated();
    }

    public function test_non_immediate_availability_rejects_today(): void
    {
        $profile = Profile::factory()->business()->withSubscription()->create();

        $this->actingAs($profile->user)->postJson(route('api.v1.kolabs.store'), [
            'intent_type' => 'venue_promotion',
            'title' => 'Coffee tasting',
            'description' => 'Free coffee tasting for runners.',
            'preferred_city' => 'Madrid',
            'offering' => ['venue'],
            'venue_name' => 'Test Cafe',
            'venue_type' => 'cafe',
            'capacity' => 20,
            'venue_address' => 'Calle Test 1',
            'media' => [['url' => 'https://example.com/photo.jpg', 'type' => 'image']],
            'availability_mode' => 'flexible',
            'availability_start' => now()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors('availability_start');
    }
```

(Again, copy the minimal-valid-payload shape from whichever passing test you found in Task 7 Step 4, rather than guessing field requiredness blind.)

- [ ] **Step 4: Run the tests**

Run: `php artisan test --compact --filter=test_immediate_availability_accepts_today`
Run: `php artisan test --compact --filter=test_non_immediate_availability_rejects_today`
Expected: both PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty
git add app/Http/Requests/Api/V1/CreateKolabRequest.php app/Http/Requests/Api/V1/UpdateKolabRequest.php tests/Feature/Api/V1/
git commit -m "fix: allow immediate availability mode to start today"
```

---

### Task 9: Full backend test suite + PR

**Files:** none (verification + PR only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: all green, including every test added/modified in Tasks 1-8.

- [ ] **Step 2: Run Pint across the whole diff**

Run: `vendor/bin/pint --dirty`
Expected: no remaining style violations.

- [ ] **Step 3: Open a GitHub Projects ticket** per this repo's CLAUDE.md rule (every new piece of work tracked before/while built) — use the standard issue template, fill Summary/Context/Acceptance criteria/Work Type/Priority/Area/**Mobile impact** (state that the `kolabing-app` companion plan adds the screens consuming these endpoints)/Definition of done, add to the Kolabing Engineering board.

- [ ] **Step 4: Create the PR** from this branch into `master` using `.github/pull_request_template.md`, filling every required section (Mobile impact: describe the new `goal`/`highlights` fields and 4 new lookup endpoints the Flutter app will consume; link the ticket from Step 3 with `Closes #<n>`).

```bash
gh pr create --title "feat: backend support for business Kolab flow redesign (goal, highlights, expanded taxonomies)" --body "$(cat <<'EOF'
## Summary
- Adds nullable `goal`/`highlights` columns to `kolabs` (additive only)
- Adds 4 new admin-managed OfferOption kinds (goal, product_interaction, venue_fit, kolab_highlight) + matching public lookup endpoints
- Expands the `deliverable` taxonomy with granular options (additive, existing 5 rows untouched)
- Fixes availability validation to allow "today" only for the new immediate availability mode

## Mobile impact (kolabing-app)
New optional request fields (`goal`, `highlights`) and 4 new GET /api/v1/lookup/* endpoints. No existing field renamed or removed. Companion Flutter plan: docs/superpowers/plans/2026-06-24-business-kolab-flow-frontend.md (kolabing-app repo).

## Testing
[paste php artisan test --compact counts here]

Closes #<ticket-number>
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:** migration (Task 1), 4 new OfferOption kinds + admin (Task 2/4), lookup endpoints (Task 5), seeders (Task 6), request/resource wiring (Task 7), availability fix (Task 8) — all Decisions 1-5 from the spec are covered. `collab_opportunities` is explicitly left untouched per the confirmed-legacy finding.
- **Open item carried into Task 4:** the exact DB-column mapping for `venue_fit`/`product_interaction` (dedicated column vs. folded into `offering`) is intentionally deferred to be resolved jointly with the frontend plan's field-mapping choice — flagged inline in Task 4 rather than guessed.
