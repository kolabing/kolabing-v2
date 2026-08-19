# Event Suggestion System Implementation Plan (BE-NF-28)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ship a two-sided suggestion surface that tells a business which community to partner with (and what event to run), and tells a community which business to ask (and for what) — built only from data the platform already holds.

**Architecture:** A nightly command scores candidate (viewer, counterpart) pairs in PHP across six weighted signals, writes the top N per profile into a new `kolab_suggestions` table together with a human-readable reason per signal and a proposed event format, and serves them through three additive `/api/v1` endpoints plus a Blade+Alpine page on `app.kolabing.com`. Accepting a suggestion opens a pre-filled Kolab form; a weekly Postmark digest drives traffic back in. No new paywall: a non-subscribed business sees the counterpart identity blurred, exactly as Explore already does.

**Tech Stack:** Laravel 12 / PHP 8.4, PostgreSQL (prod) + SQLite (tests), PHPUnit 11, Blade + Alpine (self-hosted), Postmark templates via `EmailService`, PostHog for funnel events.

**Spec:** `docs/superpowers/specs/2026-08-19-event-suggestion-system-design.md` — read it before Task 1.

---

## Before you start — repo conventions that will bite you

Read these once; they are not optional and they are not obvious.

1. **`Profile` is the authenticatable model, not `User`.** `$request->user()` returns a `Profile`. Authorization reads `$profile->cannot('view', $model)`; the base `Controller` does have `AuthorizesRequests`, but every existing API controller uses the `cannot()` + manual 403 JSON style. Follow that.
2. **UUID primary keys everywhere** via `use HasUuids;`. Migrations use `$table->uuid('id')->primary();` and `$table->foreignUuid('x')->constrained('table')->cascadeOnDelete();`.
3. **Tests use `LazilyRefreshDatabase`** (see `tests/TestCase.php`) — never `RefreshDatabase` or `DatabaseTransactions`, whatever CLAUDE.md's older prose says.
4. **The test suite runs on SQLite; production is Postgres.** Read the BE-FX-12 entry in `BACKLOG.md` before writing any query. Concretely, for this feature: **all scoring happens in PHP**, SQL is used only to filter candidates, `signals`/`evidence` jsonb is never queried or aggregated, and no aggregate function is ever applied to a `uuid` column (`MAX(uuid)` does not exist in Postgres and silently works in SQLite).
5. **Response envelope:** `{"success": true, "data": …, "message": …}`. Paginated list endpoints put rows at `data.data` with `data.meta` — the web client reads them through `kb.rows()`.
6. **Every file starts with `declare(strict_types=1);`**, explicit return types on every method, constructor property promotion, PHPDoc over inline comments.
7. **Run `vendor/bin/pint` (not `--test`) before every commit.** Run tests with `php artisan test --compact --filter=…`.
8. **Never commit to `master`.** Work on `feat/event-suggestion-system` (already created, spec commit `72ebbfe` is on it).
9. **Docs sync is part of the change, not housekeeping** — Task 17 is mandatory, not optional.
10. **`.env` points at the production database.** Never run `php artisan migrate` with the default env. Always override: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan migrate`. The test suite already forces sqlite via `phpunit.xml`.

---

## Phase 1 — Backend, shipped dark behind a flag

### Task 1: Config + migration + model + factory

**Files:**
- Create: `config/suggestions.php`
- Create: `database/migrations/2026_08_19_120000_create_kolab_suggestions_table.php`
- Create: `app/Models/KolabSuggestion.php`
- Create: `database/factories/KolabSuggestionFactory.php`
- Test: `tests/Unit/Models/KolabSuggestionTest.php`

**Step 1: Write the config**

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------
    | Feature flag
    |--------------------------------------------------------------------
    |
    | Gates the generation command, the API endpoints and the web nav entry.
    | Ships false so the backend can run a batch on production data and be
    | inspected before anyone sees a card. See the design spec section 3.8.
    |
    */
    'enabled' => (bool) env('SUGGESTIONS_ENABLED', false),

    /*
    |--------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------
    |
    | Weights sum to 1.0 and are renormalised over the signals that actually
    | have data behind them (see SignalScorer::score). `min_score` is the
    | floor below which a pair is not written at all — better an empty state
    | than a bad suggestion. Both are first guesses to be tuned against the
    | first real batch; tuning is a config change, not a code change.
    |
    */
    'weights' => [
        'category_fit' => 0.25,
        'location_fit' => 0.15,
        'scale_fit' => 0.15,
        'offer_need_fit' => 0.20,
        'delivery_proof' => 0.15,
        'momentum' => 0.10,
    ],

    'min_score' => 45,

    'confidence_thresholds' => [
        'high' => 0.75,
        'medium' => 0.45,
    ],

    /*
    |--------------------------------------------------------------------
    | Generation
    |--------------------------------------------------------------------
    */
    'per_profile' => 5,
    'expires_after_days' => 14,
    'dismissal_cooldown_days' => 60,
    'momentum_window_days' => 90,
    'max_distance_km' => 60,

    /*
    |--------------------------------------------------------------------
    | Digest
    |--------------------------------------------------------------------
    */
    'digest' => [
        'per_email' => 3,
        'resend_after_days' => 6,
        'template_business' => 'suggestion-digest-business',
        'template_community' => 'suggestion-digest-community',
    ],

];
```

**Step 2: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated collaboration suggestions: one row per (viewer, counterpart)
     * pair per batch, addressed to one side (`audience`). Carries the score,
     * the per-signal reasons behind it, a proposed event format, the evidence
     * that produced it, and the shown/clicked/dismissed/converted funnel.
     *
     * `signals` and `evidence` are write-once, read-only jsonb — never queried
     * or aggregated in SQL (BE-FX-12: the suite runs on SQLite, prod is
     * Postgres, so Postgres-only SQL cannot be caught by CI).
     */
    public function up(): void
    {
        Schema::create('kolab_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('audience');
            $table->foreignUuid('viewer_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('counterpart_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('city_id')->nullable()
                ->constrained('cities')
                ->nullOnDelete();
            $table->unsignedSmallInteger('score');
            $table->string('confidence');
            $table->jsonb('signals');
            $table->jsonb('suggested_format');
            $table->jsonb('evidence');
            $table->date('batch_key');
            $table->timestamp('expires_at');
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignUuid('converted_kolab_id')->nullable()
                ->constrained('kolabs')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['viewer_profile_id', 'counterpart_profile_id', 'batch_key'],
                'kolab_suggestions_pair_batch_unique'
            );
            $table->index(['viewer_profile_id', 'score'], 'kolab_suggestions_viewer_score_index');
            $table->index(['audience', 'batch_key'], 'kolab_suggestions_audience_batch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolab_suggestions');
    }
};
```

**Step 3: Write the model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SuggestionAudience;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A generated collaboration suggestion shown to one side of a proposed pair.
 * Rows are produced by app:generate-suggestions and are only ever read back
 * by the profile named in `viewer_profile_id` (see SuggestionPolicy).
 */
class KolabSuggestion extends Model
{
    /** @use HasFactory<\Database\Factories\KolabSuggestionFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'audience',
        'viewer_profile_id',
        'counterpart_profile_id',
        'city_id',
        'score',
        'confidence',
        'signals',
        'suggested_format',
        'evidence',
        'batch_key',
        'expires_at',
        'shown_at',
        'clicked_at',
        'dismissed_at',
        'converted_kolab_id',
    ];

    protected function casts(): array
    {
        return [
            'audience' => SuggestionAudience::class,
            'score' => 'integer',
            'signals' => 'array',
            'suggested_format' => 'array',
            'evidence' => 'array',
            'batch_key' => 'date',
            'expires_at' => 'datetime',
            'shown_at' => 'datetime',
            'clicked_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function viewerProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'viewer_profile_id');
    }

    public function counterpartProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'counterpart_profile_id');
    }

    /**
     * Live = not expired, not dismissed, not already converted.
     *
     * @param  Builder<KolabSuggestion>  $query
     * @return Builder<KolabSuggestion>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at')
            ->whereNull('converted_kolab_id')
            ->where('expires_at', '>', now());
    }
}
```

Also create `app/Enums/SuggestionAudience.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum SuggestionAudience: string
{
    case Business = 'business';
    case Community = 'community';
}
```

**Step 4: Write the factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SuggestionAudience;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KolabSuggestion>
 */
class KolabSuggestionFactory extends Factory
{
    protected $model = KolabSuggestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => SuggestionAudience::Business,
            'viewer_profile_id' => Profile::factory()->business(),
            'counterpart_profile_id' => Profile::factory()->community(),
            'city_id' => null,
            'score' => fake()->numberBetween(45, 95),
            'confidence' => 'medium',
            'signals' => [[
                'key' => 'category_fit',
                'label' => 'Category fit',
                'weight' => 0.25,
                'score' => 0.9,
                'reason' => 'Run clubs and cafés collaborate often.',
            ]],
            'suggested_format' => [
                'title' => 'Sunday morning run + coffee',
                'intent_type' => 'product_promotion',
                'weekday' => 'sunday',
                'time_of_day' => '09:00',
                'expected_attendance' => 40,
                'offer' => ['food_drink'],
                'expects' => ['social_media'],
            ],
            'evidence' => ['event_ids' => [], 'collaboration_ids' => []],
            'batch_key' => now()->toDateString(),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function forCommunityAudience(): static
    {
        return $this->state(fn (): array => [
            'audience' => SuggestionAudience::Community,
            'viewer_profile_id' => Profile::factory()->community(),
            'counterpart_profile_id' => Profile::factory()->business(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (): array => ['dismissed_at' => now()->subDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }
}
```

**Step 5: Write the model test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\KolabSuggestion;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabSuggestionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_live_scope_excludes_dismissed_and_expired_rows(): void
    {
        $live = KolabSuggestion::factory()->create();
        KolabSuggestion::factory()->dismissed()->create();
        KolabSuggestion::factory()->expired()->create();

        $ids = KolabSuggestion::query()->live()->pluck('id')->all();

        $this->assertSame([$live->id], $ids);
    }

    public function test_json_columns_round_trip_as_arrays(): void
    {
        $suggestion = KolabSuggestion::factory()->create();

        $fresh = $suggestion->fresh();

        $this->assertIsArray($fresh->signals);
        $this->assertSame('category_fit', $fresh->signals[0]['key']);
        $this->assertSame(40, $fresh->suggested_format['expected_attendance']);
    }
}
```

**Step 6: Run it and watch it fail, then pass**

Run: `php artisan test --compact tests/Unit/Models/KolabSuggestionTest.php`
Expected before the migration/model exist: FAIL (`Class "App\Models\KolabSuggestion" not found`). After Steps 1–4: PASS, 2 tests.

**Step 7: Commit**

```bash
vendor/bin/pint
git add config/suggestions.php database/migrations/2026_08_19_120000_create_kolab_suggestions_table.php app/Models/KolabSuggestion.php app/Enums/SuggestionAudience.php database/factories/KolabSuggestionFactory.php tests/Unit/Models/KolabSuggestionTest.php
git commit -m "feat(suggestions): kolab_suggestions table, model, factory and config"
```

---

### Task 2: Extract the category-fit matrix (refactor, zero behaviour change)

The matrix that scores community-type × business-category already exists as a private const in `DiscoveryOpportunityService`. Both Explore and the new engine need it. Extract it; prove Explore is untouched.

**Files:**
- Create: `app/Support/Matching/CategoryFitMatrix.php`
- Modify: `app/Services/DiscoveryOpportunityService.php` (delete the const, delegate the lookup)
- Test: `tests/Unit/Support/CategoryFitMatrixTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Matching\CategoryFitMatrix;
use Tests\TestCase;

class CategoryFitMatrixTest extends TestCase
{
    public function test_known_pairing_scores_high(): void
    {
        $this->assertSame(1.0, CategoryFitMatrix::score('food_community', 'cafe'));
    }

    public function test_weak_pairing_scores_low(): void
    {
        $this->assertLessThan(0.5, CategoryFitMatrix::score('food_community', 'coworking'));
    }

    public function test_unknown_pairing_returns_null_so_the_signal_can_be_skipped(): void
    {
        $this->assertNull(CategoryFitMatrix::score('not_a_type', 'not_a_category'));
    }
}
```

**Step 2: Run it**

Run: `php artisan test --compact tests/Unit/Support/CategoryFitMatrixTest.php`
Expected: FAIL, class not found.

**Step 3: Create the class** — move the `COMMUNITY_BUSINESS_CATEGORY_SCORES` array verbatim out of `DiscoveryOpportunityService` into a `public const MATRIX` on `CategoryFitMatrix`, and add:

```php
public static function score(?string $communityType, ?string $businessCategory): ?float
{
    if ($communityType === null || $businessCategory === null) {
        return null;
    }

    return self::MATRIX[$communityType][$businessCategory] ?? null;
}
```

**Step 4: Point the old call site at it.** In `DiscoveryOpportunityService` (around line 1286) replace
`$mappedScore = self::COMMUNITY_BUSINESS_CATEGORY_SCORES[$communityType][$businessCategory] ?? null;`
with `$mappedScore = CategoryFitMatrix::score($communityType, $businessCategory);` and delete the const. Nothing else changes.

**Step 5: Prove Explore is unchanged**

Run: `php artisan test --compact tests/Feature/Api/V1/DiscoveryOpportunityControllerTest.php tests/Unit/Support/CategoryFitMatrixTest.php`
Expected: PASS, all existing discovery assertions green.

**Step 6: Commit**

```bash
vendor/bin/pint
git add app/Support/Matching/CategoryFitMatrix.php app/Services/DiscoveryOpportunityService.php tests/Unit/Support/CategoryFitMatrixTest.php
git commit -m "refactor(matching): extract CategoryFitMatrix so suggestions and Explore share one matrix"
```

---

### Task 3: `SignalScorer` — the six signals

This is the heart of the feature. Build it signal by signal, test first each time. The class takes a *pair context* (a small DTO of pre-loaded aggregates, so no query runs inside the scorer — that keeps it unit-testable and keeps the N+1 out).

**Files:**
- Create: `app/Services/Suggestions/PairContext.php`
- Create: `app/Services/Suggestions/SignalScorer.php`
- Test: `tests/Unit/Services/Suggestions/SignalScorerTest.php`

**Step 1: Write `PairContext`** — a readonly value object, no behaviour:

```php
<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;

/**
 * Everything the scorer needs about one candidate pair, pre-loaded by
 * PairCandidateFinder. The scorer never touches the database — that keeps
 * scoring a pure function and keeps the queries batched in one place.
 *
 * @phpstan-type OfferList array<int, string>
 */
readonly class PairContext
{
    /**
     * @param  array<int, string>  $viewerOffers      what the viewer can give
     * @param  array<int, string>  $counterpartNeeds  what the counterpart wants
     * @param  array<int, int>     $pastAttendance    attendee_count of the community's past events
     * @param  array<string, mixed> $evidence         ids + aggregates for the audit trail
     */
    public function __construct(
        public SuggestionAudience $audience,
        public string $viewerProfileId,
        public string $counterpartProfileId,
        public ?string $communityType,
        public ?string $businessCategory,
        public ?string $viewerCityId,
        public ?string $counterpartCityId,
        public ?float $distanceKm,
        public array $pastAttendance,
        public ?int $communitySize,
        public ?int $venueCapacity,
        public array $viewerOffers,
        public array $counterpartNeeds,
        public ?float $averageRating,
        public ?float $repeatRatio,
        public int $contentDelivered,
        public int $reviewCount,
        public int $recentEventCount,
        public bool $hasActiveSeries,
        public array $evidence = [],
    ) {}
}
```

**Step 2: Write the failing test for `category_fit` only**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Services\Suggestions\PairContext;
use App\Services\Suggestions\SignalScorer;
use Tests\TestCase;

class SignalScorerTest extends TestCase
{
    private function context(array $overrides = []): PairContext
    {
        return new PairContext(...array_merge([
            'audience' => SuggestionAudience::Business,
            'viewerProfileId' => 'viewer',
            'counterpartProfileId' => 'counterpart',
            'communityType' => 'food_community',
            'businessCategory' => 'cafe',
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-1',
            'distanceKm' => 2.0,
            'pastAttendance' => [40, 45, 50],
            'communitySize' => 120,
            'venueCapacity' => 45,
            'viewerOffers' => ['food_drink', 'venue'],
            'counterpartNeeds' => ['food_drink'],
            'averageRating' => 4.6,
            'repeatRatio' => 0.9,
            'contentDelivered' => 5,
            'reviewCount' => 4,
            'recentEventCount' => 3,
            'hasActiveSeries' => true,
        ], $overrides));
    }

    public function test_category_fit_uses_the_shared_matrix(): void
    {
        $result = (new SignalScorer)->score($this->context());

        $signal = collect($result['signals'])->firstWhere('key', 'category_fit');

        $this->assertSame(1.0, $signal['score']);
        $this->assertStringContainsString('café', mb_strtolower($signal['reason']));
    }
}
```

**Step 3: Run it** — Run: `php artisan test --compact --filter=test_category_fit_uses_the_shared_matrix`. Expected: FAIL, class not found.

**Step 4: Implement `SignalScorer`** — the whole class, all six signals. Each signal returns `null` when it has no data; nulls are dropped and the remaining weights are renormalised.

```php
<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;

/**
 * Scores one candidate pair across six signals. Pure: no database access, no
 * randomness, no clock. A signal with no data behind it returns null, is
 * dropped from the weighted sum, and its weight is removed from the
 * denominator — so a cold-start profile is scored fairly on what we do know
 * and labelled with a lower `confidence` instead of being unfairly penalised.
 */
class SignalScorer
{
    /**
     * @return array{score: int, confidence: string, signals: array<int, array<string, mixed>>}
     */
    public function score(PairContext $context): array
    {
        $weights = config('suggestions.weights');

        $raw = [
            'category_fit' => $this->categoryFit($context),
            'location_fit' => $this->locationFit($context),
            'scale_fit' => $this->scaleFit($context),
            'offer_need_fit' => $this->offerNeedFit($context),
            'delivery_proof' => $this->deliveryProof($context),
            'momentum' => $this->momentum($context),
        ];

        $signals = [];
        $weightedSum = 0.0;
        $availableWeight = 0.0;

        foreach ($raw as $key => $result) {
            if ($result === null) {
                continue;
            }

            [$value, $reason] = $result;
            $weight = (float) $weights[$key];

            $weightedSum += $weight * $value;
            $availableWeight += $weight;

            $signals[] = [
                'key' => $key,
                'label' => $this->label($key),
                'weight' => $weight,
                'score' => round($value, 3),
                'reason' => $reason,
            ];
        }

        $score = $availableWeight > 0.0
            ? (int) round($weightedSum / $availableWeight * 100)
            : 0;

        return [
            'score' => $score,
            'confidence' => $this->confidence($availableWeight),
            'signals' => $signals,
        ];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function categoryFit(PairContext $context): ?array
    {
        $score = CategoryFitMatrix::score($context->communityType, $context->businessCategory);

        if ($score === null) {
            return null;
        }

        return [$score, __('suggestions.reason.category_fit', [
            'community_type' => str_replace('_', ' ', (string) $context->communityType),
            'business_category' => str_replace('_', ' ', (string) $context->businessCategory),
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function locationFit(PairContext $context): ?array
    {
        if ($context->distanceKm !== null) {
            $max = (float) config('suggestions.max_distance_km');
            $value = max(0.0, 1.0 - ($context->distanceKm / $max));

            return [$value, __('suggestions.reason.location_distance', [
                'km' => number_format($context->distanceKm, 1),
            ])];
        }

        if ($context->viewerCityId === null || $context->counterpartCityId === null) {
            return null;
        }

        return $context->viewerCityId === $context->counterpartCityId
            ? [1.0, __('suggestions.reason.location_same_city')]
            : [0.0, __('suggestions.reason.location_other_city')];
    }

    /**
     * Expected attendance against the venue that would host it. Perfect fit is
     * "fills the room without overflowing"; both under-filling and overflowing
     * lose points, and overflow is reported so the copy can name the constraint.
     *
     * @return array{0: float, 1: string}|null
     */
    private function scaleFit(PairContext $context): ?array
    {
        $expected = $this->expectedAttendance($context);

        if ($expected === null || $context->venueCapacity === null || $context->venueCapacity <= 0) {
            return null;
        }

        $ratio = $expected / $context->venueCapacity;

        $value = match (true) {
            $ratio <= 0.0 => 0.0,
            $ratio <= 1.0 => $ratio,
            default => max(0.0, 1.0 - (($ratio - 1.0) / 2.0)),
        };

        return [$value, __('suggestions.reason.scale_fit', [
            'expected' => $expected,
            'capacity' => $context->venueCapacity,
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function offerNeedFit(PairContext $context): ?array
    {
        if ($context->viewerOffers === [] || $context->counterpartNeeds === []) {
            return null;
        }

        $overlap = array_values(array_intersect($context->viewerOffers, $context->counterpartNeeds));
        $value = count($overlap) / count($context->counterpartNeeds);

        if ($overlap === []) {
            return [0.0, __('suggestions.reason.offer_need_none')];
        }

        return [min(1.0, $value), __('suggestions.reason.offer_need_overlap', [
            'items' => implode(', ', array_map(
                fn (string $item): string => str_replace('_', ' ', $item),
                $overlap
            )),
        ])];
    }

    /**
     * Proven delivery. For a business audience: what the community actually
     * delivered (reels/stories) plus its received ratings. For a community
     * audience: the business's reliability record. Both come out of real
     * collaboration history, which is why this is the signal worth selling.
     *
     * @return array{0: float, 1: string}|null
     */
    private function deliveryProof(PairContext $context): ?array
    {
        if ($context->reviewCount === 0 && $context->contentDelivered === 0) {
            return null;
        }

        $ratingPart = $context->averageRating !== null
            ? min(1.0, $context->averageRating / 5.0)
            : 0.0;
        $repeatPart = $context->repeatRatio ?? 0.0;
        $contentPart = min(1.0, $context->contentDelivered / 6.0);

        $value = ($ratingPart * 0.4) + ($repeatPart * 0.3) + ($contentPart * 0.3);

        if ($context->audience === SuggestionAudience::Business) {
            return [$value, __('suggestions.reason.delivery_proof_community', [
                'content' => $context->contentDelivered,
                'rating' => number_format((float) ($context->averageRating ?? 0), 1),
            ])];
        }

        return [$value, __('suggestions.reason.delivery_proof_business', [
            'reviews' => $context->reviewCount,
            'rating' => number_format((float) ($context->averageRating ?? 0), 1),
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function momentum(PairContext $context): ?array
    {
        if ($context->recentEventCount === 0 && ! $context->hasActiveSeries) {
            return null;
        }

        $value = min(1.0, $context->recentEventCount / 4.0);

        if ($context->hasActiveSeries) {
            $value = min(1.0, $value + 0.25);
        }

        return [$value, __('suggestions.reason.momentum', [
            'count' => $context->recentEventCount,
            'days' => (int) config('suggestions.momentum_window_days'),
        ])];
    }

    private function expectedAttendance(PairContext $context): ?int
    {
        if ($context->pastAttendance !== []) {
            $values = $context->pastAttendance;
            sort($values);
            $middle = (int) floor((count($values) - 1) / 2);

            return (int) round(count($values) % 2 === 1
                ? $values[$middle]
                : ($values[$middle] + $values[$middle + 1]) / 2);
        }

        return $context->communitySize !== null && $context->communitySize > 0
            ? (int) round($context->communitySize * 0.25)
            : null;
    }

    private function confidence(float $availableWeight): string
    {
        $thresholds = config('suggestions.confidence_thresholds');

        return match (true) {
            $availableWeight >= (float) $thresholds['high'] => 'high',
            $availableWeight >= (float) $thresholds['medium'] => 'medium',
            default => 'low',
        };
    }

    private function label(string $key): string
    {
        return __('suggestions.signal.'.$key);
    }
}
```

**Step 5: Run the one test** — Expected: PASS.

**Step 6: Add the remaining test cases one at a time**, running after each. Write them in this order, and implement nothing new — they exercise code you already wrote, so any failure is a real bug in it:

1. `test_location_fit_prefers_near_over_far` — `distanceKm: 1` scores higher than `distanceKm: 55`.
2. `test_location_fit_falls_back_to_city_equality_when_distance_is_unknown` — `distanceKm: null`, same city → 1.0; different city → 0.0.
3. `test_scale_fit_is_perfect_when_expected_attendance_fills_the_venue` — attendance `[40]`, capacity `40` → 1.0.
4. `test_scale_fit_penalises_overflow_and_names_the_constraint` — attendance `[90]`, capacity `30` → score < 0.5 and the reason string contains both `90` and `30`.
5. `test_scale_fit_falls_back_to_a_quarter_of_community_size_without_event_history` — `pastAttendance: []`, `communitySize: 120`, capacity `30` → expected 30 → 1.0.
6. `test_offer_need_fit_scores_the_share_of_needs_covered` — offers `['venue']`, needs `['venue','food_drink']` → 0.5.
7. `test_signals_without_data_are_dropped_and_weights_renormalised` — a context with `communityType: null`, `distanceKm: null`, `viewerCityId: null`, `counterpartCityId: null`, `reviewCount: 0`, `contentDelivered: 0`, `recentEventCount: 0`, `hasActiveSeries: false` returns only the signals that had data, and `score` is still computed from the surviving weights (assert `score > 0` and `count($result['signals']) === 2`).
8. `test_confidence_is_low_when_most_signal_weight_is_missing` — the context from (7) → `confidence === 'low'`.
9. `test_confidence_is_high_when_every_signal_has_data` — the default context → `'high'`.
10. `test_a_totally_unknown_pair_scores_zero_rather_than_throwing` — every field null/empty → `score === 0`, `signals === []`.

**Step 7: Add the reason strings.** Create `lang/en/suggestions.php` (plus `es`/`ca` copies — the repo is at 100% es/ca and Task 12 asserts it) with the `signal.*` and `reason.*` keys used above. Example shape:

```php
<?php

declare(strict_types=1);

return [
    'signal' => [
        'category_fit' => 'Category fit',
        'location_fit' => 'Location',
        'scale_fit' => 'Size fit',
        'offer_need_fit' => 'Offer fit',
        'delivery_proof' => 'Proven delivery',
        'momentum' => 'Momentum',
    ],
    'reason' => [
        'category_fit' => ':community_type communities and :business_category businesses collaborate often.',
        'location_distance' => 'About :km km apart.',
        'location_same_city' => 'Same city.',
        'location_other_city' => 'Different city.',
        'scale_fit' => 'Expect around :expected people; the space holds :capacity.',
        'offer_need_none' => 'No overlap between what you offer and what they need yet.',
        'offer_need_overlap' => 'You already offer what they ask for: :items.',
        'delivery_proof_community' => 'Delivered :content posts across past Kolabs, rated :rating.',
        'delivery_proof_business' => ':reviews reviews from past partners, rated :rating.',
        'momentum' => ':count events in the last :days days.',
        'no_history' => 'No past events yet — matched on profile.',
    ],
];
```

**Step 8: Run the whole file** — Run: `php artisan test --compact tests/Unit/Services/Suggestions/SignalScorerTest.php`. Expected: PASS, 11 tests.

**Step 9: Commit**

```bash
vendor/bin/pint
git add app/Services/Suggestions/ lang/*/suggestions.php tests/Unit/Services/Suggestions/SignalScorerTest.php
git commit -m "feat(suggestions): six-signal scorer with weight renormalisation and confidence"
```

---

### Task 4: `FormatSuggester` — the proposed event

**Files:**
- Create: `app/Services/Suggestions/FormatSuggester.php`
- Test: `tests/Unit/Services/Suggestions/FormatSuggesterTest.php`

**Step 1: Write the failing tests** (all four up front — this class is small and pure):

```php
public function test_weekday_and_time_come_from_the_active_series(): void
public function test_weekday_falls_back_to_the_modal_weekday_of_past_events(): void
public function test_expected_attendance_is_capped_by_venue_capacity(): void
public function test_without_history_the_copy_makes_no_numeric_claim(): void
```

The last one is the important one: assert the returned `title` and the returned `evidence['basis']` say `profile_only`, and assert `expected_attendance` is `null` rather than a made-up number.

**Step 2: Run** — Expected: FAIL, class not found.

**Step 3: Implement.** `suggest(PairContext $context, ?string $seriesWeekday, ?string $seriesTime, array $modalWeekdays): array` returns the `suggested_format` array documented in the spec §3.2:

- `weekday`: `$seriesWeekday`, else the modal weekday of past events, else `null`
- `time_of_day`: `$seriesTime`, else `null`
- `expected_attendance`: `SignalScorer`'s median logic, capped at `venueCapacity`; `null` when there is no history and no `community_size`
- `intent_type`: `SuggestionAudience::Business` → `product_promotion` (or venue promotion when the business `has_venue`), `Community` → `community_seeking`
- `offer` / `expects`: the intersection computed in `offerNeedFit`, passed in
- `title`: a template keyed by `community_type`, from `lang/*/suggestions.php` (`format.title.*`), with a generic fallback key
- The method never invents a number. If a value is unknown it is omitted and the copy degrades.

**Step 4: Run** — Expected: PASS, 4 tests.

**Step 5: Commit**

```bash
vendor/bin/pint
git add app/Services/Suggestions/FormatSuggester.php lang/*/suggestions.php tests/Unit/Services/Suggestions/FormatSuggesterTest.php
git commit -m "feat(suggestions): format suggester derived from real event cadence"
```

---

### Task 5: `PairCandidateFinder` — the SQL half

**Files:**
- Create: `app/Services/Suggestions/PairCandidateFinder.php`
- Test: `tests/Feature/Suggestions/PairCandidateFinderTest.php`

This is the only class allowed to query. It must load aggregates **in batch** — no query inside a per-pair loop. Build one `PairContext` per candidate.

**Step 1: Write the failing tests**

```php
public function test_returns_counterparts_in_the_same_city(): void
public function test_widens_to_business_target_cities(): void
public function test_excludes_blocked_pairs_in_either_direction(): void
public function test_excludes_pairs_with_an_open_application_or_active_collaboration(): void
public function test_excludes_pairs_dismissed_within_the_cooldown_window(): void
public function test_excludes_counterparts_with_no_categories_and_no_history(): void
public function test_loads_past_attendance_and_delivery_aggregates_without_n_plus_one(): void
```

For the last one, wrap the call in `DB::listen()` and assert the query count is bounded (`assertLessThan(12, $queries)`) — the point is to catch a per-pair query being reintroduced later, not to pin an exact number.

**Step 2: Run** — Expected: FAIL.

**Step 3: Implement.** Shape:

```php
/**
 * @return array<int, PairContext>
 */
public function candidatesFor(Profile $viewer, SuggestionAudience $audience): array
```

- Base query: `Profile::query()->where('user_type', $counterpartType)` scoped to the city set (`profiles.city_id` in the viewer's cities; for a business viewer the set is `[city_id, ...business_profiles.target_city_ids]`).
- `whereNotExists` for `user_blocks` in either direction.
- `whereNotExists` for `applications` in a pending state and `collaborations` in `scheduled`/`active` between the pair (use `collaborations.business_profile_id` / `community_profile_id` — they exist and read cleaner than the creator/applicant pair).
- `whereNotExists` for `kolab_suggestions` with `dismissed_at >= now()->subDays(config('suggestions.dismissal_cooldown_days'))` for this viewer+counterpart.
- Completeness filter: counterpart must have either non-empty `categories`/`community_type` **or** at least one past event.
- Then three batched aggregate queries over the resulting counterpart ids:
  1. `events`: `attendee_count` per profile for the last 24 months + count within `momentum_window_days` + city (one query, grouped in PHP).
  2. `collaboration_reviews`: `avg(rating)`, `count(*)`, and the `would_collaborate_again` ratio grouped by `reviewed_profile_id`.
  3. `collaboration_feedback` joined to `collaborations`: `sum(posts_reels + stories_posted)` grouped by the counterpart's side of the collaboration.
  Plus `event_series` existence for `hasActiveSeries`.
- Distance: only when both sides have `location_lat`/`location_lng` available (business `primary_venue`, community's last event) — otherwise leave `distanceKm` null and let the scorer fall back to city equality. **Compute the Haversine in PHP**, not in SQL (SQLite/Postgres divergence).

**Step 4: Run** — Expected: PASS, 7 tests.

**Step 5: Commit**

```bash
vendor/bin/pint
git add app/Services/Suggestions/PairCandidateFinder.php tests/Feature/Suggestions/PairCandidateFinderTest.php
git commit -m "feat(suggestions): batched candidate finder with block/collab/dismissal exclusions"
```

---

### Task 6: `SuggestionGenerator` + the command

**Files:**
- Create: `app/Services/Suggestions/SuggestionGenerator.php`
- Create: `app/Console/Commands/GenerateSuggestions.php`
- Create: `app/Jobs/GenerateSuggestionsForProfile.php`
- Modify: `routes/console.php`
- Modify: `app/Services/AuthService.php` (dispatch the job on registration, next to the existing `startOnboardingDrip` call)
- Test: `tests/Feature/Suggestions/SuggestionGenerationTest.php`

**Step 1: Write the failing tests**

```php
public function test_generates_up_to_the_configured_number_per_profile(): void
public function test_is_idempotent_within_a_batch(): void          // run twice, same row count
public function test_a_rerun_preserves_funnel_timestamps(): void   // shown_at survives updateOrCreate
public function test_pairs_below_the_minimum_score_are_not_written(): void
public function test_one_failing_profile_does_not_abort_the_batch(): void
public function test_writes_both_audiences(): void
public function test_the_command_is_a_noop_when_the_feature_flag_is_off(): void
```

**Step 2: Run** — Expected: FAIL.

**Step 3: Implement the generator**

```php
public function generateFor(Profile $viewer): int
```

- Resolve the audience from `$viewer->user_type` (`Business` → suggestions about communities; `Community` → about businesses; attendee → return 0 immediately).
- `PairCandidateFinder::candidatesFor()` → score each with `SignalScorer` → drop below `min_score` → sort desc → take `per_profile`.
- `FormatSuggester::suggest()` per survivor.
- `KolabSuggestion::updateOrCreate(['viewer_profile_id','counterpart_profile_id','batch_key'], [...])` — the unique key means a same-day re-run updates in place and never duplicates. **Do not write `shown_at`/`clicked_at`/`dismissed_at` in the update payload** or a re-run resets the funnel.
- Return the number written.

The command wraps it:

```php
protected $signature = 'app:generate-suggestions
    {--profile= : Only this profile id}
    {--dry-run : Score and report without writing}';
```

- Returns `self::SUCCESS` immediately with an info line when `! config('suggestions.enabled')`.
- `Profile::query()->whereIn('user_type', [UserType::Business, UserType::Community])->chunkById(200, …)`.
- Each profile inside its own `try/catch (\Throwable $e)` → `report($e)` (Sentry is wired) + `$this->warn(...)` + continue.

`routes/console.php`, following the commented style of its neighbours:

```php
// Generate two-sided collaboration suggestions (BE-NF-28). Scores candidate
// pairs in PHP and writes the top N per profile; idempotent per batch_key so
// re-runs are safe. 04:00 is the only free nightly slot (02:00 tiers,
// 03:00 auto-complete, 08:00/09:00 reminders, 14:20 partner statuses).
Schedule::command('app:generate-suggestions')
    ->dailyAt('04:00')
    ->withoutOverlapping();
```

**Step 4: Run** — Run: `php artisan test --compact tests/Feature/Suggestions/SuggestionGenerationTest.php`. Expected: PASS, 7 tests.

**Step 5: Commit**

```bash
vendor/bin/pint
git add app/Services/Suggestions/SuggestionGenerator.php app/Console/Commands/GenerateSuggestions.php app/Jobs/GenerateSuggestionsForProfile.php routes/console.php app/Services/AuthService.php tests/Feature/Suggestions/SuggestionGenerationTest.php
git commit -m "feat(suggestions): nightly generation command, per-profile job and schedule"
```

---

### Task 7: API — endpoints, policy, resource, blur

**Files:**
- Create: `app/Policies/KolabSuggestionPolicy.php`
- Create: `app/Http/Resources/Api/V1/SuggestionResource.php`
- Create: `app/Http/Controllers/Api/V1/SuggestionController.php`
- Create: `app/Services/Suggestions/SuggestionReader.php`
- Modify: `routes/api.php` (inside the `auth:sanctum` group, next to `discovery/opportunities`)
- Test: `tests/Feature/Api/V1/SuggestionApiTest.php`

**Step 1: Write the failing tests** — this is the security-critical task, so write every case before implementing:

```php
public function test_business_lists_its_own_live_suggestions_ordered_by_score(): void
public function test_reading_someone_elses_suggestion_is_forbidden(): void          // 403
public function test_dismissing_someone_elses_suggestion_is_forbidden(): void       // 403
public function test_non_subscribed_business_sees_the_counterpart_identity_blurred(): void
public function test_subscribed_business_sees_the_counterpart_identity(): void
public function test_community_identity_is_never_blurred_for_a_community_viewer(): void
public function test_blurred_payload_still_carries_score_signals_and_format(): void
public function test_expired_dismissed_and_converted_rows_are_absent(): void
public function test_a_suggestion_whose_counterpart_was_deactivated_is_absent(): void
public function test_first_serve_stamps_shown_at(): void
public function test_detail_stamps_clicked_at(): void
public function test_dismiss_stamps_dismissed_at_and_is_idempotent(): void
public function test_attendee_gets_an_empty_list(): void
public function test_endpoints_404_when_the_feature_flag_is_off(): void
```

The blur assertions are the ones a reviewer will check hardest. Assert both directions explicitly:

```php
$response->assertJsonPath('data.data.0.is_identity_blurred', true)
    ->assertJsonPath('data.data.0.counterpart.name', null)
    ->assertJsonPath('data.data.0.counterpart.avatar_url', null)
    // Everything that is NOT identity stays visible — blur, never block.
    ->assertJsonPath('data.data.0.score', $suggestion->score)
    ->assertJsonStructure(['data' => ['data' => [['signals', 'suggested_format']]]]);
```

**Step 2: Run** — Expected: FAIL.

**Step 3: Implement**

`KolabSuggestionPolicy`:

```php
public function view(Profile $user, KolabSuggestion $suggestion): bool
{
    return $user->id === $suggestion->viewer_profile_id;
}

public function dismiss(Profile $user, KolabSuggestion $suggestion): bool
{
    return $this->view($user, $suggestion);
}
```

Laravel 12 auto-discovers `App\Policies\KolabSuggestionPolicy` for `App\Models\KolabSuggestion` — no registration needed. Verify with the IDOR test rather than assuming.

`SuggestionReader::liveFor(Profile $viewer, int $perPage)`:
- `KolabSuggestion::query()->live()->where('viewer_profile_id', $viewer->id)`
- `whereHas('counterpartProfile', …)` for the counterpart still being active (this is what makes a stale counterpart invisible instead of a 500)
- eager-load `counterpartProfile.businessProfile`, `counterpartProfile.communityProfile`
- `orderByDesc('score')->orderByDesc('created_at')` — the tiebreaker keeps pagination stable
- stamp `shown_at` for the returned ids in **one** `whereIn(...)->whereNull('shown_at')->update([...])`, never per row

`SuggestionResource` — the blur lives here and nowhere else:

```php
/**
 * A non-subscribed business sees the counterpart's identity masked, exactly
 * as Explore masks it (ROLES-AND-PERMISSIONS.md 2.4). This is a downstream
 * effect of the two existing gates, NOT a new paywall: every non-identity
 * field stays visible, and communities are never masked.
 */
private function shouldBlurIdentity(Request $request): bool
{
    /** @var Profile $viewer */
    $viewer = $request->user();

    return $viewer->user_type === UserType::Business
        && ! $viewer->hasActiveSubscription();
}
```

`SuggestionController`:
- `index(Request)` → `SuggestionReader` → the standard `{success, data: {data, meta}, meta}` envelope
- `show(Request, KolabSuggestion)` → `cannot('view')` → 403; stamps `clicked_at`; returns the resource
- `dismiss(Request, KolabSuggestion)` → `cannot('dismiss')` → 403; `update(['dismissed_at' => now()])` when null; `response()->noContent()`

Routes, inside the existing `auth:sanctum` group:

```php
/*
|--------------------------------------------------------------------------
| Suggestions (BE-NF-28)
|--------------------------------------------------------------------------
*/
Route::middleware('feature:suggestions')->group(function (): void {
    Route::get('suggestions', [SuggestionController::class, 'index'])
        ->name('api.v1.suggestions.index');
    Route::get('suggestions/{suggestion}', [SuggestionController::class, 'show'])
        ->name('api.v1.suggestions.show');
    Route::post('suggestions/{suggestion}/dismiss', [SuggestionController::class, 'dismiss'])
        ->middleware('throttle:30,1')
        ->name('api.v1.suggestions.dismiss');
});
```

Create the tiny `feature` middleware (`app/Http/Middleware/EnsureFeatureEnabled.php`, registered by alias in `bootstrap/app.php` next to the existing aliases) that aborts 404 when `config("{$feature}.enabled")` is false. 404 rather than 403: a disabled feature should not advertise itself.

**Step 4: Run** — Expected: PASS, 14 tests.

**Step 5: Commit**

```bash
vendor/bin/pint
git add app/Policies/KolabSuggestionPolicy.php app/Http/Resources/Api/V1/SuggestionResource.php app/Http/Controllers/Api/V1/SuggestionController.php app/Services/Suggestions/SuggestionReader.php app/Http/Middleware/EnsureFeatureEnabled.php bootstrap/app.php routes/api.php tests/Feature/Api/V1/SuggestionApiTest.php
git commit -m "feat(suggestions): list/detail/dismiss endpoints with IDOR guard and Explore-style blur"
```

---

### Task 8: Close the funnel — `suggestion_id` on Kolab creation

**Files:**
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php` (add the optional rule)
- Modify: `app/Services/KolabService.php` (`create()` — write `converted_kolab_id`)
- Test: `tests/Feature/Api/V1/SuggestionConversionTest.php`

**Step 1: Write the failing tests**

```php
public function test_creating_a_kolab_from_a_suggestion_marks_it_converted(): void
public function test_a_suggestion_belonging_to_someone_else_is_rejected(): void   // 422
public function test_an_unknown_suggestion_id_is_rejected(): void                 // 422
public function test_creating_a_kolab_without_a_suggestion_id_still_works(): void // regression
```

**Step 2: Run** — Expected: FAIL on the first three.

**Step 3: Implement.** Rule: `'suggestion_id' => ['sometimes', 'uuid', Rule::exists('kolab_suggestions', 'id')->where('viewer_profile_id', $this->user()->id)]`. Scoping ownership **in the validation rule** is what makes a foreign id a clean 422 instead of a silent no-op. In `KolabService::create()`, after the Kolab is persisted, `KolabSuggestion::where('id', $data['suggestion_id'])->where('viewer_profile_id', $creator->id)->update(['converted_kolab_id' => $kolab->id])` — the redundant viewer check is deliberate defence in depth.

**Step 4: Run** — Expected: PASS, 4 tests. Then run the existing Kolab suite to prove nothing broke: `php artisan test --compact --filter=Kolab`.

**Step 5: Commit**

```bash
vendor/bin/pint
git add app/Http/Requests/Api/V1/CreateKolabRequest.php app/Services/KolabService.php tests/Feature/Api/V1/SuggestionConversionTest.php
git commit -m "feat(suggestions): optional suggestion_id on POST /kolabs closes the conversion funnel"
```

---

### Task 9: PostHog funnel events

**Files:**
- Modify: `app/Services/Suggestions/SuggestionReader.php`, `SuggestionController.php`, `KolabService.php`
- Test: `tests/Feature/Suggestions/SuggestionTelemetryTest.php`

Emit `suggestion_shown`, `suggestion_clicked`, `suggestion_dismissed`, `suggestion_converted` through the existing PostHog service, **every event tagged `audience`** — that tag is the whole reason the two-sided launch stays measurable. Follow `app/Services/PostHog` and `app/Jobs/SendPostHogEvent.php`; assert with the fake/spy pattern used in `tests/Unit/Services/PostHogServiceTest.php`.

Commit: `feat(suggestions): PostHog funnel events tagged per audience`

---

## Phase 2 — Web surface

### Task 10: i18n strings

**Files:** Modify `lang/en/webapp.php`, `lang/es/webapp.php`, `lang/ca/webapp.php`

Add a `suggestions.*` block: `title`, `subtitle_business`, `subtitle_community`, `why_this`, `create_cta`, `dismiss_cta`, `blurred_title`, `blurred_cta`, `empty_title`, `empty_body_business`, `empty_body_community`, `confidence_low|medium|high`, `expected_attendance`, `dashboard_block_title`. All three locales in the same commit — the web app is at 100% es/ca and Task 12 asserts it.

Commit: `feat(suggestions): en/es/ca copy for the suggestions surface`

### Task 11: The page

**Files:**
- Create: `resources/views/webapp/suggestions.blade.php`
- Modify: `resources/views/webapp/partials/sidebar.blade.php` (nav entry, hidden when the flag is off)
- Modify: `routes/web.php` (register in **both** the root and the `{locale}` group, like `/feed`)
- Test: `tests/Feature/WebApp/WebAppRoutesTest.php` (extend)

Copy the structure of `feed.blade.php`: `@extends('webapp.layout')`, `x-data="kbMerge(kbShell(), kbModalMixin(), suggestionsPage())"`, rows via `kb.rows()`, Anton headings, cream/`#FFE28C` palette, `rounded-pill` controls.

Card contents, in this order: score badge · confidence chip · counterpart (blurred variant when `is_identity_blurred`) · the three `signals[].reason` lines · proposed format (weekday/time/expected attendance) · proposed offer chips · **Create this Kolab** → `/kolabs/create?suggestion={id}` · **Not interested** → `POST /suggestions/{id}/dismiss` then remove the card optimistically.

Blurred variant: `blur-sm select-none` on name and avatar, plus a CTA to `/subscription?reason=suggestion`. Empty state uses the `empty_*` strings and links to `/account` — never a fabricated card.

Test assertions: `/suggestions` and `/es/suggestions` return 200 and contain `suggestionsPage(`; with the flag off, the sidebar entry is absent.

Commit: `feat(suggestions): /suggestions page, sidebar entry and locale routes`

### Task 12: Pre-filled create + paywall reason

**Files:**
- Modify: `resources/views/webapp/kolab-form.blade.php` (read `?suggestion=`, `GET /suggestions/{id}`, prefill `title`/`intent_type`/`offer`/`expects`/date, keep `suggestion_id` in the POST body)
- Modify: wherever the `?reason=` banner copy is resolved (the `/subscription` page) — add `suggestion`
- Test: extend `tests/Feature/WebApp/WebAppRoutesTest.php`

The form must stay fully editable — a prefill is a starting point, never a lock. If the fetch fails, fall back to a blank form and say nothing; a broken suggestion must not block Kolab creation.

Commit: `feat(suggestions): pre-filled Kolab form from a suggestion + paywall reason`

### Task 13: Dashboard block

**Files:** Modify `resources/views/webapp/partials/dashboard-widgets.blade.php`

Top suggestion + "N suggestions this week" linking to `/suggestions`. Renders nothing when the list is empty or the flag is off.

Commit: `feat(suggestions): dashboard entry point`

---

## Phase 3 — Weekly digest

### Task 14: Notification type + service method

**Files:**
- Modify: `app/Enums/NotificationType.php` (add `case SuggestionsReady = 'suggestions_ready';`)
- Modify: `app/Services/NotificationService.php` (add `notifySuggestionsReady(Profile $profile, int $count): void`, following `notifyReactivation`)
- Test: `tests/Feature/Notifications/SuggestionNotificationTest.php`

Commit: `feat(suggestions): SuggestionsReady notification type`

### Task 15: Digest command

**Files:**
- Create: `app/Console/Commands/SendSuggestionDigest.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Console/SuggestionDigestTest.php`

**Step 1: Write the failing tests**

```php
public function test_sends_at_most_the_configured_number_of_suggestions(): void
public function test_skips_profiles_with_no_live_suggestions(): void
public function test_respects_the_marketing_tips_opt_out(): void
public function test_respects_the_email_notifications_master_switch(): void
public function test_does_not_resend_within_the_cooldown(): void          // notifications-table dedup
public function test_uses_the_business_template_for_a_business(): void
public function test_uses_the_community_template_for_a_community(): void
public function test_is_a_noop_when_the_feature_flag_is_off(): void
```

**Step 2: Implement.** Model the whole thing on `SendBusinessReactivationReminders`: `--dry-run` option, flag check first, per-profile try/catch, dedup via a `Notification` lookup on `NotificationType::SuggestionsReady` inside `config('suggestions.digest.resend_after_days')`.

Send through `EmailService::send($profile, $template, $model, EmailService::CATEGORY_MARKETING)`. Using `CATEGORY_MARKETING` is the whole point: `EmailService::shouldSend()` already routes it through `notification_preferences.marketing_tips` **and** the `email_notifications` master switch, so opt-out works with **no new column**. Do not invent a preference field.

Schedule:

```php
// Weekly suggestion digest (BE-NF-28). 09:30 Monday — after the 09:00
// reactivation pass so the two nudges never land together. Dedup via the
// notifications table, so a re-run is safe.
Schedule::command('app:send-suggestion-digest')
    ->weeklyOn(1, '09:30')
    ->withoutOverlapping();
```

**Step 3: Run** — Expected: PASS, 8 tests.

**Step 4: Commit**

```bash
vendor/bin/pint
git add app/Console/Commands/SendSuggestionDigest.php routes/console.php tests/Feature/Console/SuggestionDigestTest.php
git commit -m "feat(suggestions): weekly digest command reusing the marketing_tips opt-out"
```

### Task 16: Postmark templates (ops, not code)

Create `suggestion-digest-business` and `suggestion-digest-community` in the Postmark dashboard. Check `app/Console/Commands/SyncPostmarkTemplates.php` first — if templates are versioned in this repo, add them there instead of clicking. Model variables: `name`, `count`, `suggestions[] {counterpart_name, score, reason, format_line, url}`, `unsubscribe_url`.

**This is a deploy blocker for Phase 3 only.** Phases 1–2 ship without it.

---

## Task 17: Docs, backlog and tracking (mandatory)

**Files:**
- Modify: `docs/ROLES-AND-PERMISSIONS.md` — new **§2.13**: the suggestion surface, the blur rule and an explicit statement that it is **not** a new paywall, that communities see it free and unmasked, and the new `?reason=suggestion`. Bump *Last updated* at the top.
- Modify: `docs/ROLES-BACKEND-DB-MAP.md` — new **§15**: table + columns, the four services, three endpoints, the policy, both commands + schedule slots, config keys, feature flag. Bump *Last updated*.
- Modify: `BACKLOG.md` — add **BE-NF-28** under *Incomplete Features* while in flight; update *Last updated*.
- Mirror §2.13 / §15 into the `kolabing-app` copies of both role docs.
- Open the GitHub Projects item on **Kolabing Engineering** with `.github/ISSUE_TEMPLATE/ticket.yml`.

Commit: `docs(suggestions): ROLES 2.13, backend map 15, BACKLOG BE-NF-28`

---

## Task 18: Full verification before the PR

**Step 1:** `php artisan test --compact` — expect the existing 1486 plus roughly 60 new. Paste the real count into the PR; do not estimate.
**Step 2:** `vendor/bin/pint` — clean.
**Step 3:** Sanity-check a real batch locally against a seeded database:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan app:generate-suggestions --dry-run
```

Read the output. If the reasons do not read like something you would send a paying customer, the copy or the weights are wrong — that is a finding, not a formatting nit.

**Step 4:** Open the PR with `.github/pull_request_template.md`, every required section filled. **Mobile impact:** additive only — three new endpoints plus one optional field on `POST /kolabs`, no existing payload changed; link the `kolabing-app` ticket for the mobile surface and state explicitly that v1 does not block on it.

**Step 5:** Deploy notes for the PR description:
- `migrate --force` runs on the `master` deploy — the new table lands automatically.
- `SUGGESTIONS_ENABLED` stays **unset (false)** at first. Run `app:generate-suggestions` once on prod, inspect real rows, tune `weights` / `min_score`, then enable.
- Phase 3 additionally needs the two Postmark templates before the digest schedule is allowed to fire.

---

## Deliberately out of scope

LLM-written copy, Instagram/TikTok engagement ingestion, a new price tier, admin-editable weights (folds into BE-NF-5), and the mobile UI. If any of these start feeling necessary mid-implementation, stop and re-open the spec instead of widening the plan.
