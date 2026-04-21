# Kolabs API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create the `kolabs` table and full CRUD + publish/close API endpoints for the new intent-driven kolab creation flow that the Flutter mobile app expects.

**Architecture:** New `kolabs` table (separate from `collab_opportunities`). New `Kolab` model, `KolabController`, `KolabService`, `KolabResource`, `CreateKolabRequest`, `UpdateKolabRequest`. Old `/opportunities` endpoints remain untouched for backward compatibility.

**Tech Stack:** Laravel 12, PostgreSQL, Sanctum auth, Stripe subscriptions (existing)

---

## Codebase Conventions (MUST follow)

These are the project's established patterns. Deviating breaks consistency.

- **`declare(strict_types=1);`** at the top of every PHP file
- **Profile is the auth model** (`Profile extends Authenticatable`), NOT User
- **`HasUuids` trait** on all models for UUID primary keys
- **Array-based validation rules**: `['required', 'string', 'max:255']`, NOT pipe-separated strings
- **Response format**: `{"success": true, "data": {...}, "message": "..."}` — always wrap in this envelope
- **Authorization**: `$profile->cannot('action', $model)` in controllers (base Controller lacks `AuthorizesRequests`)
- **Policy registration**: `Gate::policy()` in `AppServiceProvider::registerPolicies()`
- **Service layer**: all business logic in Services, controllers are thin
- **Form requests**: namespace `App\Http\Requests\Api\V1`, include `failedValidation()` override returning `{"success": false, "message": "Validation failed", "errors": ...}` with 422 status
- **Tests**: `use LazilyRefreshDatabase;`, PHPUnit classes, `actingAs($profile)`, `assertJsonPath()`
- **Factories**: custom states like `.business()`, `.community()`, `.published()`, `.forCreator($profile)`
- **Constructor injection**: `public function __construct(private readonly Service $service) {}`
- **Enum keys**: TitleCase (`case CommunitySeeking = 'community_seeking'`)
- **Casts**: use `protected function casts(): array` method, NOT `$casts` property
- **DB search**: use `ilike` for PostgreSQL, `LOWER() LIKE` fallback for SQLite (tests)

---

## Task 1: Migration — Create `kolabs` Table

**Files:**
- Create: `database/migrations/2026_03_24_000001_create_kolabs_table.php`

**Step 1: Generate migration**

Run:
```bash
php artisan make:migration create_kolabs_table --no-interaction
```

**Step 2: Write migration code**

Replace the generated file content with:

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
        Schema::create('kolabs', function (Blueprint $table): void {
            // Identity
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            // Intent
            $table->string('intent_type', 30);
            $table->string('status', 20)->default('draft');

            // Common
            $table->string('title', 255);
            $table->text('description');
            $table->string('preferred_city', 100);
            $table->string('area', 100)->nullable();
            $table->json('media')->nullable();

            // Availability
            $table->string('availability_mode', 20)->nullable();
            $table->date('availability_start')->nullable();
            $table->date('availability_end')->nullable();
            $table->time('selected_time')->nullable();
            $table->json('recurring_days')->nullable();

            // Community Seeking fields
            $table->json('needs')->nullable();
            $table->json('community_types')->nullable();
            $table->integer('community_size')->nullable();
            $table->integer('typical_attendance')->nullable();
            $table->json('offers_in_return')->nullable();
            $table->string('venue_preference', 30)->nullable();

            // Venue Promotion fields
            $table->string('venue_name', 255)->nullable();
            $table->string('venue_type', 50)->nullable();
            $table->integer('capacity')->nullable();
            $table->text('venue_address')->nullable();

            // Product Promotion fields
            $table->string('product_name', 255)->nullable();
            $table->string('product_type', 50)->nullable();

            // Business Targeting (venue + product)
            $table->json('offering')->nullable();
            $table->json('seeking_communities')->nullable();
            $table->integer('min_community_size')->nullable();
            $table->json('expects')->nullable();

            // Social Proof
            $table->json('past_events')->nullable();

            // Timestamps
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['intent_type', 'status']);
            $table->index('preferred_city');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolabs');
    }
};
```

**Step 3: Run migration**

Run: `php artisan migrate`
Expected: "DONE" with kolabs table created. No errors.

**Step 4: Commit**

```bash
git add database/migrations/*_create_kolabs_table.php
git commit -m "feat: add kolabs table migration"
```

---

## Task 2: Enums — IntentType, KolabStatus, VenueType, ProductType, VenuePreference

**Files:**
- Create: `app/Enums/IntentType.php`
- Create: `app/Enums/KolabStatus.php`
- Create: `app/Enums/VenueType.php`
- Create: `app/Enums/ProductType.php`
- Create: `app/Enums/VenuePreference.php`

**Reference:** Existing enums are in `app/Enums/` — follow `OfferStatus.php` or `UserType.php` pattern (TitleCase keys, `values()` static helper).

**Step 1: Create IntentType enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum IntentType: string
{
    case CommunitySeeking = 'community_seeking';
    case VenuePromotion = 'venue_promotion';
    case ProductPromotion = 'product_promotion';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 2: Create KolabStatus enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum KolabStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 3: Create VenueType enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum VenueType: string
{
    case Restaurant = 'restaurant';
    case Cafe = 'cafe';
    case BarLounge = 'bar_lounge';
    case Hotel = 'hotel';
    case Coworking = 'coworking';
    case SportsFacility = 'sports_facility';
    case EventSpace = 'event_space';
    case Rooftop = 'rooftop';
    case BeachClub = 'beach_club';
    case RetailStore = 'retail_store';
    case Other = 'other';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 4: Create ProductType enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    case FoodProduct = 'food_product';
    case Beverage = 'beverage';
    case HealthBeauty = 'health_beauty';
    case SportsEquipment = 'sports_equipment';
    case Fashion = 'fashion';
    case TechGadget = 'tech_gadget';
    case ExperienceService = 'experience_service';
    case Other = 'other';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 5: Create VenuePreference enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum VenuePreference: string
{
    case BusinessProvides = 'business_provides';
    case CommunityProvides = 'community_provides';
    case NoVenue = 'no_venue';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 6: Commit**

```bash
git add app/Enums/IntentType.php app/Enums/KolabStatus.php app/Enums/VenueType.php app/Enums/ProductType.php app/Enums/VenuePreference.php
git commit -m "feat: add kolab enums (IntentType, KolabStatus, VenueType, ProductType, VenuePreference)"
```

---

## Task 3: Kolab Model + Factory

**Files:**
- Create: `app/Models/Kolab.php`
- Create: `database/factories/KolabFactory.php`
- Modify: `app/Models/Profile.php` (add `kolabs()` relationship)

**Reference:** Follow `CollabOpportunity` model pattern for JSONB casts, scopes, status helpers. Follow `CollabOpportunityFactory` for factory states.

**Step 1: Create the Kolab model**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kolab extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'creator_profile_id',
        'intent_type',
        'status',
        'title',
        'description',
        'preferred_city',
        'area',
        'media',
        'availability_mode',
        'availability_start',
        'availability_end',
        'selected_time',
        'recurring_days',
        'needs',
        'community_types',
        'community_size',
        'typical_attendance',
        'offers_in_return',
        'venue_preference',
        'venue_name',
        'venue_type',
        'capacity',
        'venue_address',
        'product_name',
        'product_type',
        'offering',
        'seeking_communities',
        'min_community_size',
        'expects',
        'past_events',
        'published_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'intent_type' => IntentType::class,
            'status' => KolabStatus::class,
            'media' => 'array',
            'recurring_days' => 'array',
            'needs' => 'array',
            'community_types' => 'array',
            'offers_in_return' => 'array',
            'offering' => 'array',
            'seeking_communities' => 'array',
            'expects' => 'array',
            'past_events' => 'array',
            'availability_start' => 'date',
            'availability_end' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function creatorProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'creator_profile_id');
    }

    /**
     * Scope to only published kolabs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Kolab>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Kolab>
     */
    public function scopePublished($query)
    {
        return $query->where('status', KolabStatus::Published);
    }

    /**
     * Scope to filter by preferred city.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Kolab>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Kolab>
     */
    public function scopeForCity($query, string $city)
    {
        return $query->where('preferred_city', $city);
    }

    /**
     * Scope to filter by intent type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Kolab>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Kolab>
     */
    public function scopeByIntent($query, IntentType $type)
    {
        return $query->where('intent_type', $type);
    }

    public function isDraft(): bool
    {
        return $this->status === KolabStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === KolabStatus::Published;
    }

    public function isClosed(): bool
    {
        return $this->status === KolabStatus::Closed;
    }
}
```

**Step 2: Create the KolabFactory**

Run: `php artisan make:factory KolabFactory --model=Kolab --no-interaction`

Then replace the generated file content with:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kolab>
 */
class KolabFactory extends Factory
{
    /**
     * @var class-string<Kolab>
     */
    protected $model = Kolab::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_profile_id' => Profile::factory()->community(),
            'intent_type' => IntentType::CommunitySeeking,
            'status' => KolabStatus::Draft,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'preferred_city' => fake()->randomElement(['Barcelona', 'Madrid', 'Sevilla', 'Valencia', 'Malaga']),
            'area' => fake()->optional()->word(),
            'needs' => ['venue', 'food_drink'],
            'community_types' => ['Sports', 'Fitness'],
            'community_size' => fake()->numberBetween(50, 500),
            'typical_attendance' => fake()->numberBetween(20, 100),
            'offers_in_return' => ['social_media', 'event_activation'],
            'venue_preference' => 'business_provides',
            'availability_mode' => 'flexible',
        ];
    }

    /**
     * Set the intent to venue_promotion with relevant fields.
     */
    public function venuePromotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => Profile::factory()->business(),
            'intent_type' => IntentType::VenuePromotion,
            'venue_name' => fake()->company(),
            'venue_type' => fake()->randomElement(['restaurant', 'cafe', 'bar_lounge', 'hotel']),
            'capacity' => fake()->numberBetween(30, 200),
            'venue_address' => fake()->address(),
            'offering' => ['venue', 'food_drink', 'discount'],
            'seeking_communities' => ['Sports', 'Fitness', 'Wellness'],
            'expects' => ['social_media', 'event_activation'],
            // Clear community-seeking fields
            'needs' => null,
            'community_types' => null,
            'community_size' => null,
            'typical_attendance' => null,
            'offers_in_return' => null,
            'venue_preference' => null,
        ]);
    }

    /**
     * Set the intent to product_promotion with relevant fields.
     */
    public function productPromotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => Profile::factory()->business(),
            'intent_type' => IntentType::ProductPromotion,
            'product_name' => fake()->words(3, true),
            'product_type' => fake()->randomElement(['food_product', 'beverage', 'health_beauty']),
            'offering' => ['products', 'social_media', 'discount'],
            'seeking_communities' => ['Sports', 'Fitness', 'Wellness'],
            'expects' => ['social_media', 'product_placement'],
            // Clear community-seeking fields
            'needs' => null,
            'community_types' => null,
            'community_size' => null,
            'typical_attendance' => null,
            'offers_in_return' => null,
            'venue_preference' => null,
        ]);
    }

    /**
     * Mark the kolab as published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KolabStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Mark the kolab as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KolabStatus::Closed,
            'published_at' => now()->subDays(fake()->numberBetween(5, 30)),
        ]);
    }

    /**
     * Set a specific creator profile.
     */
    public function forCreator(Profile $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => $profile->id,
        ]);
    }
}
```

**Step 3: Add `kolabs()` relationship to Profile model**

In `app/Models/Profile.php`, add after the existing `createdOpportunities()` relationship:

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<Kolab, $this>
 */
public function kolabs(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Kolab::class, 'creator_profile_id');
}
```

Also add the `use App\Models\Kolab;` import if not auto-loaded (same-namespace so not needed).

**Step 4: Verify factory works**

Run:
```bash
php artisan tinker --execute="App\Models\Kolab::factory()->make()->toArray();"
```
Expected: Array output with kolab fields, no errors.

**Step 5: Commit**

```bash
git add app/Models/Kolab.php database/factories/KolabFactory.php app/Models/Profile.php
git commit -m "feat: add Kolab model, factory, and Profile relationship"
```

---

## Task 4: KolabPolicy — Authorization

**Files:**
- Create: `app/Policies/KolabPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register policy)

**Reference:** Follow `app/Policies/OpportunityPolicy.php` pattern — receives `Profile $user` as first param, uses `private isCreator()` helper.

**Step 1: Create the policy**

Run: `php artisan make:policy KolabPolicy --model=Kolab --no-interaction`

Then replace the generated content with:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Kolab;
use App\Models\Profile;

class KolabPolicy
{
    /**
     * Any authenticated user can create a kolab.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Published kolabs are visible to all; drafts only to creator.
     */
    public function view(Profile $user, Kolab $kolab): bool
    {
        return $kolab->isPublished() || $this->isCreator($user, $kolab);
    }

    /**
     * Only the creator can update their kolab.
     */
    public function update(Profile $user, Kolab $kolab): bool
    {
        return $this->isCreator($user, $kolab);
    }

    /**
     * Only the creator can delete, and only drafts.
     */
    public function delete(Profile $user, Kolab $kolab): bool
    {
        return $this->isCreator($user, $kolab) && $kolab->isDraft();
    }

    /**
     * Only the creator can publish, and only drafts.
     */
    public function publish(Profile $user, Kolab $kolab): bool
    {
        if (! $this->isCreator($user, $kolab)) {
            return false;
        }

        return $kolab->isDraft();
    }

    /**
     * Only the creator can close, and only published kolabs.
     */
    public function close(Profile $user, Kolab $kolab): bool
    {
        if (! $this->isCreator($user, $kolab)) {
            return false;
        }

        return $kolab->isPublished();
    }

    private function isCreator(Profile $user, Kolab $kolab): bool
    {
        return $user->id === $kolab->creator_profile_id;
    }
}
```

**Step 2: Register policy in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add to the `registerPolicies()` method:

```php
Gate::policy(Kolab::class, KolabPolicy::class);
```

And add the imports at top:
```php
use App\Models\Kolab;
use App\Policies\KolabPolicy;
```

**Step 3: Commit**

```bash
git add app/Policies/KolabPolicy.php app/Providers/AppServiceProvider.php
git commit -m "feat: add KolabPolicy with authorization rules"
```

---

## Task 5: KolabResource + KolabCollection — API Response

**Files:**
- Create: `app/Http/Resources/Api/V1/KolabResource.php`
- Create: `app/Http/Resources/Api/V1/KolabCollection.php`

**Reference:** Follow `OpportunityResource.php` for the resource structure and `OpportunityCollection.php` for the collection wrapper. Use `whenLoaded()` for conditional relationships. Use existing `ProfileSummaryResource` for creator_profile.

**Step 1: Create KolabResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KolabResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intent_type' => $this->intent_type,
            'status' => $this->status,
            'title' => $this->title,
            'description' => $this->description,
            'preferred_city' => $this->preferred_city,
            'area' => $this->area,
            'media' => $this->media ?? [],

            // Availability
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'selected_time' => $this->selected_time,
            'recurring_days' => $this->recurring_days ?? [],

            // Community Seeking
            'needs' => $this->needs ?? [],
            'community_types' => $this->community_types ?? [],
            'community_size' => $this->community_size,
            'typical_attendance' => $this->typical_attendance,
            'offers_in_return' => $this->offers_in_return ?? [],
            'venue_preference' => $this->venue_preference,

            // Venue Promotion
            'venue_name' => $this->venue_name,
            'venue_type' => $this->venue_type,
            'capacity' => $this->capacity,
            'venue_address' => $this->venue_address,

            // Product Promotion
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,

            // Business Targeting
            'offering' => $this->offering ?? [],
            'seeking_communities' => $this->seeking_communities ?? [],
            'min_community_size' => $this->min_community_size,
            'expects' => $this->expects ?? [],

            // Social Proof
            'past_events' => $this->past_events ?? [],

            // Timestamps
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'creator_profile' => new ProfileSummaryResource($this->whenLoaded('creatorProfile')),
        ];
    }
}
```

**Step 2: Create KolabCollection**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class KolabCollection extends ResourceCollection
{
    /**
     * @var string
     */
    public $collects = KolabResource::class;

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Resources/Api/V1/KolabResource.php app/Http/Resources/Api/V1/KolabCollection.php
git commit -m "feat: add KolabResource and KolabCollection"
```

---

## Task 6: Form Requests — CreateKolabRequest + UpdateKolabRequest

**Files:**
- Create: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateKolabRequest.php`

**Reference:** Follow `CreateOpportunityRequest.php` exactly — array-based rules, `failedValidation()` override returning JSON 422, `messages()` for custom error strings.

**Step 1: Create CreateKolabRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateKolabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Always required
            'intent_type' => ['required', 'string', 'in:community_seeking,venue_promotion,product_promotion'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'preferred_city' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],

            // Media
            'media' => ['nullable', 'array', 'max:5'],
            'media.*.url' => ['required_with:media', 'url'],
            'media.*.type' => ['required_with:media', 'in:photo,video'],
            'media.*.sort_order' => ['nullable', 'integer', 'min:0'],

            // Availability
            'availability_mode' => ['nullable', 'string', 'in:one_time,recurring,flexible'],
            'availability_start' => ['nullable', 'date'],
            'availability_end' => ['nullable', 'date', 'after_or_equal:availability_start'],
            'selected_time' => ['nullable', 'date_format:H:i'],
            'recurring_days' => ['nullable', 'array'],
            'recurring_days.*' => ['integer', 'between:1,7'],

            // Community Seeking
            'needs' => ['required_if:intent_type,community_seeking', 'nullable', 'array', 'min:1'],
            'needs.*' => ['string', 'in:venue,food_drink,sponsor,products,discount,other'],
            'community_types' => ['required_if:intent_type,community_seeking', 'nullable', 'array', 'min:1', 'max:3'],
            'community_types.*' => ['string', 'max:50'],
            'community_size' => ['required_if:intent_type,community_seeking', 'nullable', 'integer', 'min:1'],
            'typical_attendance' => ['required_if:intent_type,community_seeking', 'nullable', 'integer', 'min:1'],
            'offers_in_return' => ['required_if:intent_type,community_seeking', 'nullable', 'array', 'min:1'],
            'offers_in_return.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],
            'venue_preference' => ['required_if:intent_type,community_seeking', 'nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion
            'venue_name' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'max:255'],
            'venue_type' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'in:restaurant,cafe,bar_lounge,hotel,coworking,sports_facility,event_space,rooftop,beach_club,retail_store,other'],
            'capacity' => ['required_if:intent_type,venue_promotion', 'nullable', 'integer', 'min:1'],
            'venue_address' => ['required_if:intent_type,venue_promotion', 'nullable', 'string', 'max:500'],

            // Product Promotion
            'product_name' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'max:255'],
            'product_type' => ['required_if:intent_type,product_promotion', 'nullable', 'string', 'in:food_product,beverage,health_beauty,sports_equipment,fashion,tech_gadget,experience_service,other'],

            // Business Targeting (venue + product)
            'offering' => ['required_unless:intent_type,community_seeking', 'nullable', 'array', 'min:1'],
            'offering.*' => ['string', 'in:venue,food_drink,discount,products,social_media,content_creation,sponsorship,other'],
            'seeking_communities' => ['nullable', 'array', 'max:5'],
            'seeking_communities.*' => ['string', 'max:50'],
            'min_community_size' => ['nullable', 'integer', 'min:1'],
            'expects' => ['nullable', 'array'],
            'expects.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],

            // Past Events
            'past_events' => ['nullable', 'array', 'max:5'],
            'past_events.*.name' => ['required_with:past_events', 'string', 'max:255'],
            'past_events.*.date' => ['required_with:past_events', 'date'],
            'past_events.*.partner_name' => ['nullable', 'string', 'max:255'],
            'past_events.*.photos' => ['nullable', 'array', 'max:3'],
            'past_events.*.photos.*' => ['url'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'intent_type.required' => __('validation.required', ['attribute' => 'intent type']),
            'intent_type.in' => __('validation.in', ['attribute' => 'intent type']),
            'title.required' => __('validation.required', ['attribute' => 'title']),
            'title.max' => __('validation.max.string', ['attribute' => 'title', 'max' => 255]),
            'description.required' => __('validation.required', ['attribute' => 'description']),
            'description.max' => __('validation.max.string', ['attribute' => 'description', 'max' => 5000]),
            'preferred_city.required' => __('validation.required', ['attribute' => 'preferred city']),
            'needs.required_if' => __('validation.required_if', ['attribute' => 'needs', 'other' => 'intent type', 'value' => 'community_seeking']),
            'community_types.required_if' => __('validation.required_if', ['attribute' => 'community types', 'other' => 'intent type', 'value' => 'community_seeking']),
            'community_size.required_if' => __('validation.required_if', ['attribute' => 'community size', 'other' => 'intent type', 'value' => 'community_seeking']),
            'typical_attendance.required_if' => __('validation.required_if', ['attribute' => 'typical attendance', 'other' => 'intent type', 'value' => 'community_seeking']),
            'offers_in_return.required_if' => __('validation.required_if', ['attribute' => 'offers in return', 'other' => 'intent type', 'value' => 'community_seeking']),
            'venue_preference.required_if' => __('validation.required_if', ['attribute' => 'venue preference', 'other' => 'intent type', 'value' => 'community_seeking']),
            'venue_name.required_if' => __('validation.required_if', ['attribute' => 'venue name', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'venue_type.required_if' => __('validation.required_if', ['attribute' => 'venue type', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'capacity.required_if' => __('validation.required_if', ['attribute' => 'capacity', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'venue_address.required_if' => __('validation.required_if', ['attribute' => 'venue address', 'other' => 'intent type', 'value' => 'venue_promotion']),
            'product_name.required_if' => __('validation.required_if', ['attribute' => 'product name', 'other' => 'intent type', 'value' => 'product_promotion']),
            'product_type.required_if' => __('validation.required_if', ['attribute' => 'product type', 'other' => 'intent type', 'value' => 'product_promotion']),
            'offering.required_unless' => __('validation.required_unless', ['attribute' => 'offering', 'other' => 'intent type', 'values' => 'community_seeking']),
        ];
    }

    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
```

**Step 2: Create UpdateKolabRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateKolabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // All fields are optional on update (use 'sometimes')
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'preferred_city' => ['sometimes', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],

            // Media
            'media' => ['nullable', 'array', 'max:5'],
            'media.*.url' => ['required_with:media', 'url'],
            'media.*.type' => ['required_with:media', 'in:photo,video'],
            'media.*.sort_order' => ['nullable', 'integer', 'min:0'],

            // Availability
            'availability_mode' => ['nullable', 'string', 'in:one_time,recurring,flexible'],
            'availability_start' => ['nullable', 'date'],
            'availability_end' => ['nullable', 'date', 'after_or_equal:availability_start'],
            'selected_time' => ['nullable', 'date_format:H:i'],
            'recurring_days' => ['nullable', 'array'],
            'recurring_days.*' => ['integer', 'between:1,7'],

            // Community Seeking
            'needs' => ['nullable', 'array', 'min:1'],
            'needs.*' => ['string', 'in:venue,food_drink,sponsor,products,discount,other'],
            'community_types' => ['nullable', 'array', 'min:1', 'max:3'],
            'community_types.*' => ['string', 'max:50'],
            'community_size' => ['nullable', 'integer', 'min:1'],
            'typical_attendance' => ['nullable', 'integer', 'min:1'],
            'offers_in_return' => ['nullable', 'array', 'min:1'],
            'offers_in_return.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],
            'venue_preference' => ['nullable', 'string', 'in:business_provides,community_provides,no_venue'],

            // Venue Promotion
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_type' => ['nullable', 'string', 'in:restaurant,cafe,bar_lounge,hotel,coworking,sports_facility,event_space,rooftop,beach_club,retail_store,other'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'venue_address' => ['nullable', 'string', 'max:500'],

            // Product Promotion
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_type' => ['nullable', 'string', 'in:food_product,beverage,health_beauty,sports_equipment,fashion,tech_gadget,experience_service,other'],

            // Business Targeting
            'offering' => ['nullable', 'array', 'min:1'],
            'offering.*' => ['string', 'in:venue,food_drink,discount,products,social_media,content_creation,sponsorship,other'],
            'seeking_communities' => ['nullable', 'array', 'max:5'],
            'seeking_communities.*' => ['string', 'max:50'],
            'min_community_size' => ['nullable', 'integer', 'min:1'],
            'expects' => ['nullable', 'array'],
            'expects.*' => ['string', 'in:social_media,event_activation,product_placement,community_reach,review_feedback'],

            // Past Events
            'past_events' => ['nullable', 'array', 'max:5'],
            'past_events.*.name' => ['required_with:past_events', 'string', 'max:255'],
            'past_events.*.date' => ['required_with:past_events', 'date'],
            'past_events.*.partner_name' => ['nullable', 'string', 'max:255'],
            'past_events.*.photos' => ['nullable', 'array', 'max:3'],
            'past_events.*.photos.*' => ['url'],
        ];
    }

    /**
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => __('Validation failed'),
            'errors' => $validator->errors(),
        ], 422));
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Requests/Api/V1/CreateKolabRequest.php app/Http/Requests/Api/V1/UpdateKolabRequest.php
git commit -m "feat: add CreateKolabRequest and UpdateKolabRequest validation"
```

---

## Task 7: KolabService — Business Logic

**Files:**
- Create: `app/Services/KolabService.php`

**Reference:** Follow `OpportunityService.php` exactly — PHPDoc array shapes, `LengthAwarePaginator` return types, `DB::connection()->getDriverName()` for ilike/like detection, `InvalidArgumentException` for state violations, `Carbon::now()` for published_at.

**Step 1: Create KolabService**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class KolabService
{
    /**
     * Browse published kolabs with filters.
     *
     * @param  array{
     *     intent_type?: string|null,
     *     city?: string|null,
     *     venue_type?: string|null,
     *     product_type?: string|null,
     *     needs?: array<string>|null,
     *     community_types?: array<string>|null,
     *     search?: string|null,
     * }  $filters
     */
    public function browse(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('status', KolabStatus::Published)
            ->with('creatorProfile');

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Get kolabs created by a profile.
     *
     * @param  array{status?: string|null}  $filters
     */
    public function getMyKolabs(Profile $profile, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->with('creatorProfile');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = KolabStatus::tryFrom($filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new kolab in draft status.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Profile $creator, array $data): Kolab
    {
        return Kolab::query()->create([
            'creator_profile_id' => $creator->id,
            'intent_type' => $data['intent_type'],
            'status' => KolabStatus::Draft,
            'title' => $data['title'],
            'description' => $data['description'],
            'preferred_city' => $data['preferred_city'],
            'area' => $data['area'] ?? null,
            'media' => $data['media'] ?? null,
            'availability_mode' => $data['availability_mode'] ?? null,
            'availability_start' => $data['availability_start'] ?? null,
            'availability_end' => $data['availability_end'] ?? null,
            'selected_time' => $data['selected_time'] ?? null,
            'recurring_days' => $data['recurring_days'] ?? null,
            'needs' => $data['needs'] ?? null,
            'community_types' => $data['community_types'] ?? null,
            'community_size' => $data['community_size'] ?? null,
            'typical_attendance' => $data['typical_attendance'] ?? null,
            'offers_in_return' => $data['offers_in_return'] ?? null,
            'venue_preference' => $data['venue_preference'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_type' => $data['venue_type'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_type' => $data['product_type'] ?? null,
            'offering' => $data['offering'] ?? null,
            'seeking_communities' => $data['seeking_communities'] ?? null,
            'min_community_size' => $data['min_community_size'] ?? null,
            'expects' => $data['expects'] ?? null,
            'past_events' => $data['past_events'] ?? null,
        ]);
    }

    /**
     * Update an existing kolab.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public function update(Kolab $kolab, array $data): Kolab
    {
        $kolab->update($data);
        $kolab->refresh();

        return $kolab;
    }

    /**
     * Delete a draft kolab.
     *
     * @throws InvalidArgumentException
     */
    public function delete(Kolab $kolab): void
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be deleted.'
            );
        }

        $kolab->delete();
    }

    /**
     * Publish a draft kolab.
     *
     * Subscription check: community_seeking intents are free.
     * All other intents require an active subscription.
     *
     * @throws InvalidArgumentException
     */
    public function publish(Kolab $kolab): Kolab
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be published.'
            );
        }

        $creator = $kolab->creatorProfile;

        if ($kolab->intent_type !== IntentType::CommunitySeeking) {
            if (! $creator->hasActiveSubscription()) {
                throw new InvalidArgumentException(
                    'Subscription required to publish.'
                );
            }
        }

        $kolab->update([
            'status' => KolabStatus::Published,
            'published_at' => Carbon::now(),
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Close a published kolab.
     *
     * @throws InvalidArgumentException
     */
    public function close(Kolab $kolab): Kolab
    {
        if (! $kolab->isPublished()) {
            throw new InvalidArgumentException(
                'Only published kolabs can be closed.'
            );
        }

        $kolab->update([
            'status' => KolabStatus::Closed,
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Apply filters to the kolab query.
     *
     * @param  Builder<Kolab>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['intent_type']) && $filters['intent_type'] !== '') {
            $intentType = IntentType::tryFrom($filters['intent_type']);
            if ($intentType !== null) {
                $query->where('intent_type', $intentType);
            }
        }

        if (isset($filters['city']) && $filters['city'] !== '') {
            $query->where('preferred_city', $filters['city']);
        }

        if (isset($filters['venue_type']) && $filters['venue_type'] !== '') {
            $query->where('venue_type', $filters['venue_type']);
        }

        if (isset($filters['product_type']) && $filters['product_type'] !== '') {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['needs']) && ! empty($filters['needs'])) {
            $query->where(function (Builder $q) use ($filters): void {
                foreach ($filters['needs'] as $need) {
                    $q->orWhereJsonContains('needs', $need);
                }
            });
        }

        if (isset($filters['community_types']) && ! empty($filters['community_types'])) {
            $query->where(function (Builder $q) use ($filters): void {
                foreach ($filters['community_types'] as $type) {
                    $q->orWhereJsonContains('community_types', $type);
                }
            });
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = '%'.strtolower($filters['search']).'%';
            $likeOperator = $this->getCaseInsensitiveLikeOperator();

            $query->where(function (Builder $q) use ($searchTerm, $likeOperator): void {
                if ($likeOperator === 'ilike') {
                    $q->where('kolabs.title', 'ilike', $searchTerm)
                        ->orWhere('kolabs.description', 'ilike', $searchTerm);
                } else {
                    $q->whereRaw('LOWER(kolabs.title) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(kolabs.description) LIKE ?', [$searchTerm]);
                }
            });
        }
    }

    private function getCaseInsensitiveLikeOperator(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/KolabService.php
git commit -m "feat: add KolabService with CRUD, publish, close, and browse logic"
```

---

## Task 8: KolabController + Routes — HTTP Layer

**Files:**
- Create: `app/Http/Controllers/Api/V1/KolabController.php`
- Modify: `routes/api.php`

**Reference:** Follow `OpportunityController.php` exactly — `$request->user()` returns `Profile`, `$profile->cannot()` for authorization, try/catch `InvalidArgumentException`, `FreemiumLimitExceededException` pattern for subscription 402.

**Step 1: Create KolabController**

Run: `php artisan make:controller Api/V1/KolabController --api --no-interaction`

Then replace the generated content with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateKolabRequest;
use App\Http\Requests\Api\V1\UpdateKolabRequest;
use App\Http\Resources\Api\V1\KolabCollection;
use App\Http\Resources\Api\V1\KolabResource;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\KolabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class KolabController extends Controller
{
    public function __construct(
        private readonly KolabService $kolabService,
    ) {}

    /**
     * Browse published kolabs with filters.
     *
     * GET /api/v1/kolabs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'intent_type' => $request->query('intent_type'),
            'city' => $request->query('city'),
            'venue_type' => $request->query('venue_type'),
            'product_type' => $request->query('product_type'),
            'needs' => $request->query('needs'),
            'community_types' => $request->query('community_types'),
            'search' => $request->query('search'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $kolabs = $this->kolabService->browse($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new KolabCollection($kolabs),
            'meta' => [
                'current_page' => $kolabs->currentPage(),
                'last_page' => $kolabs->lastPage(),
                'per_page' => $kolabs->perPage(),
                'total' => $kolabs->total(),
            ],
        ]);
    }

    /**
     * List kolabs created by the authenticated user.
     *
     * GET /api/v1/kolabs/me
     */
    public function myKolabs(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $kolabs = $this->kolabService->getMyKolabs($profile, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new KolabCollection($kolabs),
            'meta' => [
                'current_page' => $kolabs->currentPage(),
                'last_page' => $kolabs->lastPage(),
                'per_page' => $kolabs->perPage(),
                'total' => $kolabs->total(),
            ],
        ]);
    }

    /**
     * Create a new kolab (draft).
     *
     * POST /api/v1/kolabs
     */
    public function store(CreateKolabRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $kolab = $this->kolabService->create($profile, $request->validated());
        $kolab->load('creatorProfile');

        return response()->json([
            'success' => true,
            'message' => __('Kolab created successfully.'),
            'data' => new KolabResource($kolab),
        ], 201);
    }

    /**
     * Get a single kolab.
     *
     * GET /api/v1/kolabs/{kolab}
     */
    public function show(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this kolab.'),
            ], 403);
        }

        $kolab->load('creatorProfile');

        return response()->json([
            'success' => true,
            'data' => new KolabResource($kolab),
        ]);
    }

    /**
     * Update a kolab.
     *
     * PUT /api/v1/kolabs/{kolab}
     */
    public function update(UpdateKolabRequest $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this kolab.'),
            ], 403);
        }

        $kolab = $this->kolabService->update($kolab, $request->validated());
        $kolab->load('creatorProfile');

        return response()->json([
            'success' => true,
            'message' => __('Kolab updated successfully.'),
            'data' => new KolabResource($kolab),
        ]);
    }

    /**
     * Delete a draft kolab.
     *
     * DELETE /api/v1/kolabs/{kolab}
     */
    public function destroy(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this kolab.'),
            ], 403);
        }

        try {
            $this->kolabService->delete($kolab);

            return response()->json([
                'success' => true,
                'message' => __('Kolab deleted successfully.'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish a draft kolab.
     *
     * POST /api/v1/kolabs/{kolab}/publish
     */
    public function publish(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('publish', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to publish this kolab.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->publish($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab published successfully.'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'requires_subscription' => true,
            ], 402);
        }
    }

    /**
     * Close a published kolab.
     *
     * POST /api/v1/kolabs/{kolab}/close
     */
    public function close(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('close', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to close this kolab.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->close($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab closed successfully.'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
```

**Step 2: Add routes to `routes/api.php`**

Inside the `Route::middleware('auth:sanctum')->group(function (): void {` block, add a new section after the Opportunities section:

```php
/*
|--------------------------------------------------------------------------
| Kolabs
|--------------------------------------------------------------------------
*/

// Browse published kolabs
Route::get('kolabs', [KolabController::class, 'index'])
    ->name('api.v1.kolabs.index');

// My kolabs (must be before {kolab} route)
Route::get('kolabs/me', [KolabController::class, 'myKolabs'])
    ->name('api.v1.kolabs.me');

// Create kolab
Route::post('kolabs', [KolabController::class, 'store'])
    ->name('api.v1.kolabs.store');

// Single kolab
Route::get('kolabs/{kolab}', [KolabController::class, 'show'])
    ->name('api.v1.kolabs.show');

// Update kolab
Route::put('kolabs/{kolab}', [KolabController::class, 'update'])
    ->name('api.v1.kolabs.update');

// Delete kolab
Route::delete('kolabs/{kolab}', [KolabController::class, 'destroy'])
    ->name('api.v1.kolabs.destroy');

// Publish kolab
Route::post('kolabs/{kolab}/publish', [KolabController::class, 'publish'])
    ->name('api.v1.kolabs.publish');

// Close kolab
Route::post('kolabs/{kolab}/close', [KolabController::class, 'close'])
    ->name('api.v1.kolabs.close');
```

Also add the import at the top of `routes/api.php`:
```php
use App\Http\Controllers\Api\V1\KolabController;
```

**IMPORTANT:** The `/kolabs/me` route MUST come before `/kolabs/{kolab}` to avoid "me" being interpreted as a UUID.

**Step 3: Verify routes are registered**

Run: `php artisan route:list --path=kolabs`
Expected: 8 routes listed (GET index, GET me, POST store, GET show, PUT update, DELETE destroy, POST publish, POST close)

**Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/KolabController.php routes/api.php
git commit -m "feat: add KolabController with routes for full CRUD + publish/close"
```

---

## Task 9: Feature Tests — Create (Community Seeking)

**Files:**
- Create: `tests/Feature/Api/V1/KolabCreateTest.php`

**Reference:** Follow `OpportunityPublishTest.php` pattern — `LazilyRefreshDatabase`, `Profile::factory()->community()`, `actingAs()`, `assertJsonPath()`.

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabCreateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_user_can_create_community_seeking_kolab(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'community_seeking',
                'title' => 'Post-Run Brunch Series',
                'description' => 'Our running community is looking for a restaurant.',
                'preferred_city' => 'Barcelona',
                'needs' => ['venue', 'food_drink'],
                'community_types' => ['Sports', 'Fitness'],
                'community_size' => 250,
                'typical_attendance' => 40,
                'offers_in_return' => ['social_media', 'event_activation'],
                'venue_preference' => 'business_provides',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'community_seeking')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Post-Run Brunch Series')
            ->assertJsonPath('data.preferred_city', 'Barcelona')
            ->assertJsonPath('data.needs', ['venue', 'food_drink'])
            ->assertJsonPath('data.community_size', 250);

        $this->assertDatabaseHas('kolabs', [
            'creator_profile_id' => $profile->id,
            'intent_type' => 'community_seeking',
            'title' => 'Post-Run Brunch Series',
            'status' => 'draft',
        ]);
    }

    public function test_business_user_can_create_venue_promotion_kolab(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'venue_promotion',
                'title' => 'Cafe Montjuic',
                'description' => 'Beautiful rooftop cafe.',
                'preferred_city' => 'Barcelona',
                'venue_name' => 'Cafe Montjuic',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'venue_address' => 'Carrer de Montjuic 42',
                'offering' => ['venue', 'food_drink'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'venue_promotion')
            ->assertJsonPath('data.venue_name', 'Cafe Montjuic')
            ->assertJsonPath('data.venue_type', 'cafe')
            ->assertJsonPath('data.capacity', 80);
    }

    public function test_business_user_can_create_product_promotion_kolab(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'product_promotion',
                'title' => 'Organic Cold Brew',
                'description' => 'Our cold brew line is perfect for events.',
                'preferred_city' => 'Barcelona',
                'product_name' => 'Organic Cold Brew Line',
                'product_type' => 'beverage',
                'offering' => ['products', 'discount'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.intent_type', 'product_promotion')
            ->assertJsonPath('data.product_name', 'Organic Cold Brew Line')
            ->assertJsonPath('data.product_type', 'beverage');
    }

    public function test_create_kolab_requires_intent_type(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'title' => 'Test',
                'description' => 'Test description',
                'preferred_city' => 'Barcelona',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['intent_type']);
    }

    public function test_community_seeking_requires_community_fields(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'community_seeking',
                'title' => 'Test',
                'description' => 'Test description',
                'preferred_city' => 'Barcelona',
                // Missing: needs, community_types, community_size, typical_attendance, offers_in_return, venue_preference
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['needs', 'community_types', 'community_size', 'typical_attendance', 'offers_in_return', 'venue_preference']);
    }

    public function test_venue_promotion_requires_venue_fields(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'venue_promotion',
                'title' => 'Test',
                'description' => 'Test description',
                'preferred_city' => 'Barcelona',
                // Missing: venue_name, venue_type, capacity, venue_address, offering
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['venue_name', 'venue_type', 'capacity', 'venue_address', 'offering']);
    }

    public function test_product_promotion_requires_product_fields(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'product_promotion',
                'title' => 'Test',
                'description' => 'Test description',
                'preferred_city' => 'Barcelona',
                // Missing: product_name, product_type, offering
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_name', 'product_type', 'offering']);
    }

    public function test_unauthenticated_user_cannot_create_kolab(): void
    {
        $response = $this->postJson('/api/v1/kolabs', [
            'intent_type' => 'community_seeking',
            'title' => 'Test',
            'description' => 'Test description',
            'preferred_city' => 'Barcelona',
        ]);

        $response->assertStatus(401);
    }

    public function test_kolab_creates_with_availability_fields(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/kolabs', [
                'intent_type' => 'community_seeking',
                'title' => 'Test with availability',
                'description' => 'Testing availability fields.',
                'preferred_city' => 'Barcelona',
                'needs' => ['venue'],
                'community_types' => ['Sports'],
                'community_size' => 100,
                'typical_attendance' => 30,
                'offers_in_return' => ['social_media'],
                'venue_preference' => 'business_provides',
                'availability_mode' => 'recurring',
                'availability_start' => '2026-04-01',
                'availability_end' => '2026-06-30',
                'selected_time' => '11:00',
                'recurring_days' => [6, 7],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.availability_mode', 'recurring')
            ->assertJsonPath('data.availability_start', '2026-04-01')
            ->assertJsonPath('data.selected_time', '11:00:00')
            ->assertJsonPath('data.recurring_days', [6, 7]);
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --compact tests/Feature/Api/V1/KolabCreateTest.php`
Expected: All tests PASS.

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/KolabCreateTest.php
git commit -m "test: add KolabCreateTest for all three intent types + validation"
```

---

## Task 10: Feature Tests — Show, Update, Delete

**Files:**
- Create: `tests/Feature/Api/V1/KolabCrudTest.php`

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- SHOW ---

    public function test_creator_can_view_own_draft_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $kolab->id)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_other_user_cannot_view_draft_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }

    public function test_any_user_can_view_published_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $viewer = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');
    }

    // --- UPDATE ---

    public function test_creator_can_update_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'title' => 'Updated Title',
                'community_size' => 300,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.community_size', 300);
    }

    public function test_other_user_cannot_update_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    // --- DELETE ---

    public function test_creator_can_delete_draft_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('kolabs', ['id' => $kolab->id]);
    }

    public function test_creator_cannot_delete_published_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_delete_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --compact tests/Feature/Api/V1/KolabCrudTest.php`
Expected: All tests PASS.

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/KolabCrudTest.php
git commit -m "test: add KolabCrudTest for show, update, delete endpoints"
```

---

## Task 11: Feature Tests — Publish, Close

**Files:**
- Create: `tests/Feature/Api/V1/KolabPublishCloseTest.php`

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessSubscription;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabPublishCloseTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- PUBLISH ---

    public function test_community_user_can_publish_community_seeking_without_subscription(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($kolab->fresh()->published_at);
    }

    public function test_venue_promotion_requires_subscription_to_publish(): void
    {
        $profile = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->venuePromotion()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_product_promotion_requires_subscription_to_publish(): void
    {
        $profile = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->productPromotion()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(402)
            ->assertJsonPath('requires_subscription', true);
    }

    public function test_business_with_subscription_can_publish_venue_promotion(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessSubscription::factory()->create(['profile_id' => $profile->id, 'status' => 'active']);
        $kolab = Kolab::factory()->venuePromotion()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_cannot_publish_already_published_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_publish_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->postJson("/api/v1/kolabs/{$kolab->id}/publish");

        $response->assertStatus(403);
    }

    // --- CLOSE ---

    public function test_creator_can_close_published_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_cannot_close_draft_kolab(): void
    {
        $profile = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_close_kolab(): void
    {
        $creator = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->postJson("/api/v1/kolabs/{$kolab->id}/close");

        $response->assertStatus(403);
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --compact tests/Feature/Api/V1/KolabPublishCloseTest.php`
Expected: All tests PASS.

**Note:** The `BusinessSubscription::factory()` usage depends on existing factory. If it doesn't exist, check the existing codebase for how subscription is created in tests (might be `$profile->businessSubscription()->create([...])` instead).

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/KolabPublishCloseTest.php
git commit -m "test: add KolabPublishCloseTest with subscription checks"
```

---

## Task 12: Feature Tests — Browse (Index) + MyKolabs

**Files:**
- Create: `tests/Feature/Api/V1/KolabBrowseTest.php`

**Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabBrowseTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- INDEX (Browse published) ---

    public function test_browse_returns_only_published_kolabs(): void
    {
        $profile = Profile::factory()->community()->create();

        Kolab::factory()->published()->count(2)->create();
        Kolab::factory()->count(3)->create(); // drafts

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_filters_by_intent_type(): void
    {
        $profile = Profile::factory()->community()->create();

        Kolab::factory()->published()->count(2)->create(); // community_seeking (default)
        Kolab::factory()->venuePromotion()->published()->count(3)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs?intent_type=venue_promotion');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_browse_filters_by_city(): void
    {
        $profile = Profile::factory()->community()->create();

        Kolab::factory()->published()->create(['preferred_city' => 'Barcelona']);
        Kolab::factory()->published()->create(['preferred_city' => 'Madrid']);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs?city=Barcelona');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_includes_creator_profile(): void
    {
        $profile = Profile::factory()->community()->create();
        Kolab::factory()->published()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'intent_type', 'title', 'creator_profile'],
                    ],
                ],
            ]);
    }

    public function test_browse_paginates_results(): void
    {
        $profile = Profile::factory()->community()->create();
        Kolab::factory()->published()->count(20)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs?per_page=5&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    // --- MY KOLABS ---

    public function test_my_kolabs_returns_only_own_kolabs(): void
    {
        $profile = Profile::factory()->community()->create();
        $other = Profile::factory()->community()->create();

        Kolab::factory()->forCreator($profile)->count(3)->create();
        Kolab::factory()->forCreator($other)->count(2)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_kolabs_filters_by_status(): void
    {
        $profile = Profile::factory()->community()->create();

        Kolab::factory()->forCreator($profile)->count(2)->create(); // draft
        Kolab::factory()->published()->forCreator($profile)->count(1)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/kolabs/me?status=draft');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_unauthenticated_user_cannot_browse(): void
    {
        $response = $this->getJson('/api/v1/kolabs');

        $response->assertStatus(401);
    }
}
```

**Step 2: Run the tests**

Run: `php artisan test --compact tests/Feature/Api/V1/KolabBrowseTest.php`
Expected: All tests PASS.

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/KolabBrowseTest.php
git commit -m "test: add KolabBrowseTest for index and myKolabs endpoints"
```

---

## Task 13: Run Full Test Suite + Pint

**Step 1: Run Pint to fix formatting**

Run: `vendor/bin/pint --dirty`
Expected: Any formatting issues auto-fixed.

**Step 2: Run all kolab tests together**

Run: `php artisan test --compact --filter=Kolab`
Expected: All kolab tests PASS.

**Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: All tests PASS (existing tests should not be broken).

**Step 4: Commit any pint fixes**

```bash
git add -A
git commit -m "style: apply pint formatting to kolab files"
```

---

## Summary of Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/*_create_kolabs_table.php` |
| Create | `app/Enums/IntentType.php` |
| Create | `app/Enums/KolabStatus.php` |
| Create | `app/Enums/VenueType.php` |
| Create | `app/Enums/ProductType.php` |
| Create | `app/Enums/VenuePreference.php` |
| Create | `app/Models/Kolab.php` |
| Create | `database/factories/KolabFactory.php` |
| Create | `app/Policies/KolabPolicy.php` |
| Create | `app/Http/Resources/Api/V1/KolabResource.php` |
| Create | `app/Http/Resources/Api/V1/KolabCollection.php` |
| Create | `app/Http/Requests/Api/V1/CreateKolabRequest.php` |
| Create | `app/Http/Requests/Api/V1/UpdateKolabRequest.php` |
| Create | `app/Services/KolabService.php` |
| Create | `app/Http/Controllers/Api/V1/KolabController.php` |
| Create | `tests/Feature/Api/V1/KolabCreateTest.php` |
| Create | `tests/Feature/Api/V1/KolabCrudTest.php` |
| Create | `tests/Feature/Api/V1/KolabPublishCloseTest.php` |
| Create | `tests/Feature/Api/V1/KolabBrowseTest.php` |
| Modify | `app/Models/Profile.php` (add `kolabs()` relationship) |
| Modify | `app/Providers/AppServiceProvider.php` (register KolabPolicy) |
| Modify | `routes/api.php` (add kolab routes) |
