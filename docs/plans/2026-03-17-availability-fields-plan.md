# Availability Fields for Opportunities — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add `selected_time` and `recurring_days` columns to opportunities, update validation rules (conditional per availability_mode), and return both fields in API responses.

**Architecture:** Two nullable columns added via migration. Conditional validation in FormRequests using `required_if`/`exclude_if` rules. Model, resource, and factory updated. `availability_start`/`availability_end` become nullable for `recurring` mode.

**Tech Stack:** Laravel 12, PostgreSQL, PHPUnit

---

### Task 1: Migration — Add `selected_time` and `recurring_days` columns

**Files:**
- Create: `database/migrations/2026_03_17_000001_add_availability_fields_to_collab_opportunities_table.php`

**Step 1: Create the migration**

```bash
php artisan make:migration add_availability_fields_to_collab_opportunities_table --table=collab_opportunities --no-interaction
```

Replace the generated migration body with:

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
        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->time('selected_time')->nullable()->after('availability_end');
            $table->json('recurring_days')->nullable()->after('selected_time');
        });
    }

    public function down(): void
    {
        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->dropColumn(['selected_time', 'recurring_days']);
        });
    }
};
```

**Step 2: Run migration**

```bash
php artisan migrate
```

Expected: "DONE" with no errors.

**Step 3: Commit**

```bash
git add database/migrations/*add_availability_fields*
git commit -m "feat: add selected_time and recurring_days columns to collab_opportunities"
```

---

### Task 2: Update Model — Add new fields to fillable and casts

**Files:**
- Modify: `app/Models/CollabOpportunity.php`

**Step 1: Add to `$fillable` array**

Add `'selected_time'` and `'recurring_days'` to the `$fillable` array (after `'availability_end'`):

```php
protected $fillable = [
    'creator_profile_id',
    'creator_profile_type',
    'title',
    'description',
    'status',
    'business_offer',
    'community_deliverables',
    'categories',
    'availability_mode',
    'availability_start',
    'availability_end',
    'selected_time',
    'recurring_days',
    'venue_mode',
    'address',
    'preferred_city',
    'offer_photo',
    'published_at',
];
```

**Step 2: Add casts for `recurring_days`**

In the `casts()` method, add:

```php
'recurring_days' => 'array',
```

**Step 3: Add PHPDoc properties**

Add these to the model's PHPDoc block:

```php
 * @property string|null $selected_time
 * @property array<int>|null $recurring_days
```

**Step 4: Commit**

```bash
git add app/Models/CollabOpportunity.php
git commit -m "feat: add selected_time and recurring_days to CollabOpportunity model"
```

---

### Task 3: Update Factory — Generate realistic availability data per mode

**Files:**
- Modify: `database/factories/CollabOpportunityFactory.php`

**Step 1: Update factory `definition()` method**

The factory currently picks a random `availability_mode` but doesn't set `selected_time` or `recurring_days`. Update the definition to generate mode-consistent data:

Replace the availability-related lines in `definition()`:

```php
// Old lines to replace:
// 'availability_mode' => fake()->randomElement(['one_time', 'recurring', 'flexible']),
// 'availability_start' => $availabilityStart,
// 'availability_end' => $availabilityEnd,

// New: default to 'flexible' with dates, no time/days
'availability_mode' => 'flexible',
'availability_start' => $availabilityStart,
'availability_end' => $availabilityEnd,
'selected_time' => null,
'recurring_days' => null,
```

**Step 2: Add factory states for each availability mode**

Add these states after the existing `forCreator()` method:

```php
/**
 * Set availability mode to one_time with a fixed time.
 */
public function oneTime(): static
{
    return $this->state(fn (array $attributes) => [
        'availability_mode' => 'one_time',
        'availability_start' => fake()->dateTimeBetween('+1 week', '+3 months'),
        'availability_end' => fake()->dateTimeBetween('+3 months', '+6 months'),
        'selected_time' => sprintf('%02d:00', fake()->numberBetween(8, 21)),
        'recurring_days' => null,
    ]);
}

/**
 * Set availability mode to recurring with days and time.
 */
public function recurring(): static
{
    return $this->state(fn (array $attributes) => [
        'availability_mode' => 'recurring',
        'availability_start' => null,
        'availability_end' => null,
        'selected_time' => sprintf('%02d:00', fake()->numberBetween(8, 21)),
        'recurring_days' => fake()->randomElements([1, 2, 3, 4, 5, 6, 7], fake()->numberBetween(1, 4)),
    ]);
}

/**
 * Set availability mode to flexible with date range only.
 */
public function flexible(): static
{
    return $this->state(fn (array $attributes) => [
        'availability_mode' => 'flexible',
        'availability_start' => fake()->dateTimeBetween('+1 week', '+3 months'),
        'availability_end' => fake()->dateTimeBetween('+3 months', '+6 months'),
        'selected_time' => null,
        'recurring_days' => null,
    ]);
}
```

**Step 3: Commit**

```bash
git add database/factories/CollabOpportunityFactory.php
git commit -m "feat: add availability mode states to CollabOpportunityFactory"
```

---

### Task 4: Update API Resources — Return new fields

**Files:**
- Modify: `app/Http/Resources/Api/V1/OpportunityResource.php`
- Modify: `app/Http/Resources/Api/V1/OpportunitySummaryResource.php`

**Step 1: Add fields to `OpportunityResource`**

In `toArray()`, after the `'availability_end'` line (line 44), add:

```php
'selected_time' => $this->selected_time,
'recurring_days' => $this->recurring_days,
```

**Step 2: Add fields to `OpportunitySummaryResource`**

In `toArray()`, after the `'availability_end'` line (line 34), add:

```php
'selected_time' => $this->selected_time,
'recurring_days' => $this->recurring_days,
```

**Step 3: Commit**

```bash
git add app/Http/Resources/Api/V1/OpportunityResource.php app/Http/Resources/Api/V1/OpportunitySummaryResource.php
git commit -m "feat: return selected_time and recurring_days in opportunity API responses"
```

---

### Task 5: Update CreateOpportunityRequest — Conditional validation

**Files:**
- Modify: `app/Http/Requests/Api/V1/CreateOpportunityRequest.php`

**Step 1: Update `rules()` method**

Replace the entire `rules()` return array with:

```php
return [
    'title' => ['required', 'string', 'max:255'],
    'description' => ['required', 'string', 'max:5000'],
    'business_offer' => ['required', 'array'],
    'community_deliverables' => ['required', 'array'],
    'categories' => ['required', 'array', 'min:1', 'max:5'],
    'categories.*' => ['string'],
    'availability_mode' => ['required', 'string', 'in:one_time,recurring,flexible'],

    // Dates: required for one_time and flexible, nullable for recurring
    'availability_start' => ['required_if:availability_mode,one_time,flexible', 'nullable', 'date', 'after:today'],
    'availability_end' => ['required_if:availability_mode,one_time,flexible', 'nullable', 'date', 'after:availability_start'],

    // Time: required for one_time and recurring, prohibited for flexible
    'selected_time' => ['required_if:availability_mode,one_time,recurring', 'prohibited_if:availability_mode,flexible', 'nullable', 'date_format:H:i'],

    // Days: required for recurring, prohibited for one_time and flexible
    'recurring_days' => ['required_if:availability_mode,recurring', 'prohibited_unless:availability_mode,recurring', 'nullable', 'array', 'min:1'],
    'recurring_days.*' => ['integer', 'between:1,7'],

    'venue_mode' => ['required', 'string', 'in:business_venue,community_venue,no_venue'],
    'address' => ['required_unless:venue_mode,no_venue', 'nullable', 'string'],
    'preferred_city' => ['required', 'string', 'max:100'],
    'offer_photo' => ['nullable', 'string', 'url'],
];
```

**Step 2: Add custom error messages for new fields**

Add these to the `messages()` array:

```php
'selected_time.required_if' => __('validation.required_if', ['attribute' => 'selected time', 'other' => 'availability mode', 'value' => 'one_time or recurring']),
'selected_time.date_format' => __('validation.date_format', ['attribute' => 'selected time', 'format' => 'HH:mm']),
'selected_time.prohibited_if' => __('The selected time must be empty when availability mode is flexible.'),
'recurring_days.required_if' => __('validation.required_if', ['attribute' => 'recurring days', 'other' => 'availability mode', 'value' => 'recurring']),
'recurring_days.array' => __('validation.array', ['attribute' => 'recurring days']),
'recurring_days.min' => __('validation.min.array', ['attribute' => 'recurring days', 'min' => 1]),
'recurring_days.*.between' => __('validation.between.numeric', ['attribute' => 'recurring day', 'min' => 1, 'max' => 7]),
'recurring_days.prohibited_unless' => __('Recurring days must be empty unless availability mode is recurring.'),
'availability_start.required_if' => __('validation.required_if', ['attribute' => 'availability start', 'other' => 'availability mode', 'value' => 'one_time or flexible']),
'availability_end.required_if' => __('validation.required_if', ['attribute' => 'availability end', 'other' => 'availability mode', 'value' => 'one_time or flexible']),
```

Also remove the old `availability_start.required` and `availability_end.required` messages.

**Step 3: Commit**

```bash
git add app/Http/Requests/Api/V1/CreateOpportunityRequest.php
git commit -m "feat: add conditional validation for availability fields on create"
```

---

### Task 6: Update UpdateOpportunityRequest — Conditional validation

**Files:**
- Modify: `app/Http/Requests/Api/V1/UpdateOpportunityRequest.php`

**Step 1: Update `rules()` method**

Replace the entire `rules()` return array with:

```php
return [
    'title' => ['sometimes', 'string', 'max:255'],
    'description' => ['sometimes', 'string', 'max:5000'],
    'business_offer' => ['sometimes', 'array'],
    'community_deliverables' => ['sometimes', 'array'],
    'categories' => ['sometimes', 'array', 'min:1', 'max:5'],
    'categories.*' => ['string'],
    'availability_mode' => ['sometimes', 'string', 'in:one_time,recurring,flexible'],

    // Dates: conditionally required based on availability_mode (if sent)
    'availability_start' => ['sometimes', 'nullable', 'date', 'after:today'],
    'availability_end' => ['sometimes', 'nullable', 'date', 'after:availability_start'],

    // New fields
    'selected_time' => ['sometimes', 'nullable', 'date_format:H:i'],
    'recurring_days' => ['sometimes', 'nullable', 'array'],
    'recurring_days.*' => ['integer', 'between:1,7'],

    'venue_mode' => ['sometimes', 'string', 'in:business_venue,community_venue,no_venue'],
    'address' => ['sometimes', 'nullable', 'string'],
    'preferred_city' => ['sometimes', 'string', 'max:100'],
    'offer_photo' => ['sometimes', 'nullable', 'string', 'url'],
];
```

**Step 2: Add error messages for new fields**

Add to the `messages()` array:

```php
'selected_time.date_format' => __('validation.date_format', ['attribute' => 'selected time', 'format' => 'HH:mm']),
'recurring_days.array' => __('validation.array', ['attribute' => 'recurring days']),
'recurring_days.*.between' => __('validation.between.numeric', ['attribute' => 'recurring day', 'min' => 1, 'max' => 7]),
```

**Step 3: Commit**

```bash
git add app/Http/Requests/Api/V1/UpdateOpportunityRequest.php
git commit -m "feat: add selected_time and recurring_days validation to update request"
```

---

### Task 7: Write tests — Validation for each availability mode on CREATE

**Files:**
- Create: `tests/Feature/Api/V1/OpportunityAvailabilityTest.php`

**Step 1: Create the test file**

```bash
php artisan make:test --phpunit Feature/Api/V1/OpportunityAvailabilityTest --no-interaction
```

**Step 2: Write the test class**

Replace the generated content with:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Profile $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->business = Profile::factory()->business()->create();
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Opportunity',
            'description' => 'A test opportunity for collaboration.',
            'business_offer' => ['venue' => true, 'food_drink' => false],
            'community_deliverables' => ['instagram_post' => true],
            'categories' => ['Food & Drink'],
            'venue_mode' => 'business_venue',
            'address' => 'Calle Test 123, Sevilla',
            'preferred_city' => 'Sevilla',
        ], $overrides);
    }

    // ─── One Time Mode ───────────────────────────────────────────

    public function test_create_one_time_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'one_time')
            ->assertJsonPath('data.selected_time', '10:00')
            ->assertJsonPath('data.recurring_days', null);
    }

    public function test_create_one_time_requires_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_one_time_rejects_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => [1, 3],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_one_time_requires_date_range(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'selected_time' => '10:00',
            'availability_start' => null,
            'availability_end' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability_start', 'availability_end']);
    }

    // ─── Recurring Mode ──────────────────────────────────────────

    public function test_create_recurring_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => [4, 6],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'recurring')
            ->assertJsonPath('data.selected_time', '20:00')
            ->assertJsonPath('data.recurring_days', [4, 6]);
    }

    public function test_create_recurring_requires_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => null,
            'recurring_days' => [1, 3],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_recurring_requires_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_recurring_allows_null_dates(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '18:00',
            'recurring_days' => [1],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_start', null)
            ->assertJsonPath('data.availability_end', null);
    }

    public function test_create_recurring_rejects_invalid_day_number(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'recurring',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => '20:00',
            'recurring_days' => [0, 8],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days.0');
    }

    // ─── Flexible Mode ───────────────────────────────────────────

    public function test_create_flexible_with_valid_data(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'flexible')
            ->assertJsonPath('data.selected_time', null)
            ->assertJsonPath('data.recurring_days', null);
    }

    public function test_create_flexible_rejects_selected_time(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '10:00',
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    public function test_create_flexible_rejects_recurring_days(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => null,
            'recurring_days' => [1, 2],
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('recurring_days');
    }

    public function test_create_flexible_requires_date_range(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
            'selected_time' => null,
            'recurring_days' => null,
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability_start', 'availability_end']);
    }

    // ─── Time Format Validation ──────────────────────────────────

    public function test_selected_time_rejects_invalid_format(): void
    {
        $data = $this->baseData([
            'availability_mode' => 'one_time',
            'availability_start' => now()->addWeek()->toDateString(),
            'availability_end' => now()->addMonth()->toDateString(),
            'selected_time' => '25:00',
        ]);

        $response = $this->actingAs($this->business)
            ->postJson('/api/v1/opportunities', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('selected_time');
    }

    // ─── GET Response ────────────────────────────────────────────

    public function test_show_returns_new_availability_fields(): void
    {
        $opportunity = CollabOpportunity::factory()
            ->forCreator($this->business)
            ->recurring()
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson("/api/v1/opportunities/{$opportunity->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'selected_time',
                    'recurring_days',
                ],
            ]);
    }

    public function test_index_returns_new_availability_fields(): void
    {
        CollabOpportunity::factory()
            ->forCreator($this->business)
            ->oneTime()
            ->published()
            ->create();

        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->getJson('/api/v1/opportunities');

        $response->assertStatus(200);

        $firstItem = $response->json('data.0');
        $this->assertArrayHasKey('selected_time', $firstItem);
        $this->assertArrayHasKey('recurring_days', $firstItem);
    }
}
```

**Step 3: Run tests to verify they fail (migration/model not yet applied)**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityAvailabilityTest.php
```

Expected: Tests fail (columns don't exist yet — or pass if migration already ran).

**Step 4: Commit**

```bash
git add tests/Feature/Api/V1/OpportunityAvailabilityTest.php
git commit -m "test: add availability fields validation tests for all three modes"
```

---

### Task 8: Update existing test helper — Fix `validOpportunityData()` in OpportunityCreationLimitTest

**Files:**
- Modify: `tests/Feature/Api/V1/OpportunityCreationLimitTest.php`

**Step 1: Update `validOpportunityData()` to include new fields**

The `availability_mode` is `'flexible'`, so `selected_time` should be null and `recurring_days` should be null. The existing test data sets `availability_mode => 'flexible'` so add:

After the `'availability_end'` line in `validOpportunityData()`, add:

```php
'selected_time' => null,
'recurring_days' => null,
```

This ensures existing tests continue to pass with the new `prohibited_if` validation rules.

**Step 2: Run existing tests to confirm no regressions**

```bash
php artisan test --compact tests/Feature/Api/V1/OpportunityCreationLimitTest.php
```

Expected: All 13 tests pass.

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/OpportunityCreationLimitTest.php
git commit -m "test: update existing test helper with new availability fields"
```

---

### Task 9: Run full test suite — Verify no regressions

**Step 1: Run all opportunity tests**

```bash
php artisan test --compact tests/Feature/Api/V1/Opportunity
```

Expected: All tests pass (existing + new).

**Step 2: Run full suite**

```bash
php artisan test --compact
```

Expected: All tests pass. Fix any failures before proceeding.

**Step 3: Run Pint**

```bash
vendor/bin/pint --dirty
```

**Step 4: Final commit if Pint made changes**

```bash
git add -A && git commit -m "style: apply pint formatting"
```
