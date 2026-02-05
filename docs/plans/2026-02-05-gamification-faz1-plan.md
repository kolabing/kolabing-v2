# Gamification System — Phase 1 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the foundational gamification infrastructure: attendee user type, event check-in system, challenge models with hybrid pool, peer-to-peer challenge completion flow, and basic point system.

**Architecture:** Extends the existing dual-portal pattern (business/community) with a third user type `attendee`. New `attendee_profiles` table follows the 1:1 extended profile pattern. Check-in uses QR token-based system. Challenges support both system-defined pool and organizer custom challenges. Challenge completion is peer-to-peer with verification. Points are dynamic based on challenge difficulty.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL, Sanctum auth, existing service layer pattern

**Design Doc:** `docs/plans/2026-02-05-gamification-system-design.md`

---

## Critical Codebase Patterns (READ FIRST)

Before implementing ANY task, understand these patterns:

1. **Profile is the authenticatable model** — NOT User. `Profile extends Authenticatable` with `HasUuids`.
2. **Authorization uses `$profile->cannot()`** — NOT `$this->authorize()`. The base Controller lacks `AuthorizesRequests` trait.
3. **Tests use `LazilyRefreshDatabase`** — NOT `RefreshDatabase` or `DatabaseTransactions`.
4. **Response format:** `{ "success": true, "data": {...}, "message": "..." }`
5. **Service layer pattern:** All business logic in Services, controllers are thin.
6. **UUID primary keys** on all tables via `HasUuids` trait.
7. **Factory states:** ProfileFactory has `->business()` and `->community()` states.
8. **Extended profiles:** Each user type has a 1:1 extended profile table (business_profiles, community_profiles).
9. **Existing events table** already has: id, profile_id, name, partner_name, partner_type, event_date, attendee_count, timestamps.

---

### Task 1: Add Attendee to UserType Enum

**Files:**
- Modify: `app/Enums/UserType.php`
- Test: `tests/Unit/Enums/UserTypeTest.php` (create)

**Step 1: Write the failing test**

Create `tests/Unit/Enums/UserTypeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\UserType;
use PHPUnit\Framework\TestCase;

class UserTypeTest extends TestCase
{
    public function test_attendee_case_exists(): void
    {
        $this->assertSame('attendee', UserType::Attendee->value);
    }

    public function test_values_includes_attendee(): void
    {
        $values = UserType::values();

        $this->assertContains('attendee', $values);
        $this->assertCount(3, $values);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Enums/UserTypeTest.php`
Expected: FAIL — `Attendee` case does not exist

**Step 3: Add Attendee case to UserType enum**

In `app/Enums/UserType.php`, add after `case Community = 'community';`:

```php
case Attendee = 'attendee';
```

**Step 4: Update Profile model — add `isAttendee()` helper**

In `app/Models/Profile.php`, add after the `isCommunity()` method:

```php
/**
 * Check if the user is an attendee user.
 */
public function isAttendee(): bool
{
    return $this->user_type === UserType::Attendee;
}
```

**Step 5: Update ProfileFactory — add `attendee()` state**

In `database/factories/ProfileFactory.php`, add after the `community()` method:

```php
/**
 * Indicate that the profile is for an attendee user.
 */
public function attendee(): static
{
    return $this->state(fn (array $attributes) => [
        'user_type' => UserType::Attendee,
    ]);
}
```

**Step 6: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Enums/UserTypeTest.php`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Enums/UserType.php app/Models/Profile.php database/factories/ProfileFactory.php tests/Unit/Enums/UserTypeTest.php
git commit -m "feat: add attendee case to UserType enum with Profile helper and factory state"
```

---

### Task 2: Create AttendeeProfile Model + Migration + Factory

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_attendee_profiles_table.php`
- Create: `app/Models/AttendeeProfile.php`
- Create: `database/factories/AttendeeProfileFactory.php`
- Modify: `app/Models/Profile.php` (add relationship)
- Modify: `app/Services/AuthService.php` (handle attendee in registration)
- Test: `tests/Feature/Api/V1/AttendeeProfileTest.php` (create)

**Step 1: Create migration**

Run: `php artisan make:migration create_attendee_profiles_table --no-interaction`

Migration content:

```php
public function up(): void
{
    Schema::create('attendee_profiles', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
        $table->unsignedInteger('total_points')->default(0);
        $table->unsignedInteger('total_challenges_completed')->default(0);
        $table->unsignedInteger('total_events_attended')->default(0);
        $table->unsignedInteger('global_rank')->nullable();
        $table->timestamps();

        $table->unique('profile_id');
    });
}
```

**Step 2: Create AttendeeProfile model**

Run: `php artisan make:model AttendeeProfile --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $profile_id
 * @property int $total_points
 * @property int $total_challenges_completed
 * @property int $total_events_attended
 * @property int|null $global_rank
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 */
class AttendeeProfile extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'total_points',
        'total_challenges_completed',
        'total_events_attended',
        'global_rank',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'total_challenges_completed' => 'integer',
            'total_events_attended' => 'integer',
            'global_rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
```

**Step 3: Create factory**

Run: `php artisan make:factory AttendeeProfileFactory --model=AttendeeProfile --no-interaction`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendeeProfile;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeProfile>
 */
class AttendeeProfileFactory extends Factory
{
    protected $model = AttendeeProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->attendee(),
            'total_points' => 0,
            'total_challenges_completed' => 0,
            'total_events_attended' => 0,
        ];
    }
}
```

**Step 4: Add `attendeeProfile()` relationship to Profile model**

In `app/Models/Profile.php`, add after `communityProfile()`:

```php
/**
 * Get the attendee profile for this user.
 *
 * @return HasOne<AttendeeProfile, $this>
 */
public function attendeeProfile(): HasOne
{
    return $this->hasOne(AttendeeProfile::class);
}
```

Also update the `getExtendedProfile()` method to handle attendee:

```php
public function getExtendedProfile(): BusinessProfile|CommunityProfile|AttendeeProfile|null
{
    if ($this->isBusiness()) {
        return $this->businessProfile;
    }

    if ($this->isAttendee()) {
        return $this->attendeeProfile;
    }

    return $this->communityProfile;
}
```

Also update the PHPDoc `@property-read` block to include:
```php
 * @property-read AttendeeProfile|null $attendeeProfile
```

**Step 5: Update AuthService to handle attendee registration**

In `app/Services/AuthService.php`, inside the `registerNewUser()` method's DB::transaction, add an `elseif` for attendee after the community block:

```php
} elseif ($userType === UserType::Attendee) {
    AttendeeProfile::query()->create([
        'profile_id' => $profile->id,
    ]);
}
```

Also update `loadProfileRelationships()`:

```php
private function loadProfileRelationships(Profile $profile): void
{
    if ($profile->isBusiness()) {
        $profile->load(['businessProfile.city', 'subscription']);
    } elseif ($profile->isAttendee()) {
        $profile->load(['attendeeProfile']);
    } else {
        $profile->load(['communityProfile.city']);
    }
}
```

Add `use App\Models\AttendeeProfile;` import.

**Step 6: Write feature test**

Create `tests/Feature/Api/V1/AttendeeProfileTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeeProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_attendee_profile_is_created_with_defaults(): void
    {
        $profile = Profile::factory()->attendee()->create();
        $attendeeProfile = AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $this->assertSame(0, $attendeeProfile->total_points);
        $this->assertSame(0, $attendeeProfile->total_challenges_completed);
        $this->assertSame(0, $attendeeProfile->total_events_attended);
        $this->assertNull($attendeeProfile->global_rank);
    }

    public function test_profile_has_attendee_profile_relationship(): void
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $this->assertNotNull($profile->attendeeProfile);
        $this->assertInstanceOf(AttendeeProfile::class, $profile->attendeeProfile);
    }

    public function test_profile_is_attendee_returns_correct_value(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $business = Profile::factory()->business()->create();

        $this->assertTrue($attendee->isAttendee());
        $this->assertFalse($business->isAttendee());
    }

    public function test_attendee_user_type_value(): void
    {
        $profile = Profile::factory()->attendee()->create();

        $this->assertSame(UserType::Attendee, $profile->user_type);
        $this->assertSame('attendee', $profile->user_type->value);
    }

    public function test_get_extended_profile_returns_attendee_profile(): void
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        $extended = $profile->getExtendedProfile();

        $this->assertInstanceOf(AttendeeProfile::class, $extended);
    }
}
```

**Step 7: Run migration and tests**

Run: `php artisan migrate --no-interaction`
Run: `php artisan test --compact tests/Feature/Api/V1/AttendeeProfileTest.php`
Expected: PASS

**Step 8: Commit**

```bash
git add database/migrations/*create_attendee_profiles_table* app/Models/AttendeeProfile.php database/factories/AttendeeProfileFactory.php app/Models/Profile.php app/Services/AuthService.php tests/Feature/Api/V1/AttendeeProfileTest.php
git commit -m "feat: add AttendeeProfile model, migration, factory and Profile relationship"
```

---

### Task 3: Add Gamification Columns to Events Table + ChallengeDifficulty Enum

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_gamification_columns_to_events_table.php`
- Create: `app/Enums/ChallengeDifficulty.php`
- Create: `app/Enums/CheckinStatus.php` (not needed — simple)
- Create: `app/Enums/ChallengeCompletionStatus.php`
- Modify: `app/Models/Event.php` (add new columns to fillable/casts)
- Test: verify migration runs

**Step 1: Create ChallengeDifficulty enum**

Create `app/Enums/ChallengeDifficulty.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeDifficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function points(): int
    {
        return match ($this) {
            self::Easy => 5,
            self::Medium => 15,
            self::Hard => 30,
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 2: Create ChallengeCompletionStatus enum**

Create `app/Enums/ChallengeCompletionStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeCompletionStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 3: Create migration to add gamification columns to events**

Run: `php artisan make:migration add_gamification_columns_to_events_table --table=events --no-interaction`

```php
public function up(): void
{
    Schema::table('events', function (Blueprint $table) {
        $table->decimal('location_lat', 10, 7)->nullable()->after('attendee_count');
        $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
        $table->string('address', 255)->nullable()->after('location_lng');
        $table->unsignedInteger('max_challenges_per_attendee')->default(10)->after('address');
        $table->boolean('is_active')->default(false)->after('max_challenges_per_attendee');
        $table->string('checkin_token', 64)->nullable()->unique()->after('is_active');
    });
}

public function down(): void
{
    Schema::table('events', function (Blueprint $table) {
        $table->dropUnique(['checkin_token']);
        $table->dropColumn([
            'location_lat',
            'location_lng',
            'address',
            'max_challenges_per_attendee',
            'is_active',
            'checkin_token',
        ]);
    });
}
```

**Step 4: Update Event model**

In `app/Models/Event.php`, add to `$fillable`:
```php
'location_lat',
'location_lng',
'address',
'max_challenges_per_attendee',
'is_active',
'checkin_token',
```

Update `casts()`:
```php
protected function casts(): array
{
    return [
        'event_date' => 'date',
        'attendee_count' => 'integer',
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'max_challenges_per_attendee' => 'integer',
        'is_active' => 'boolean',
    ];
}
```

Update the PHPDoc block to add:
```php
 * @property string|null $location_lat
 * @property string|null $location_lng
 * @property string|null $address
 * @property int $max_challenges_per_attendee
 * @property bool $is_active
 * @property string|null $checkin_token
```

**Step 5: Run migration**

Run: `php artisan migrate --no-interaction`

**Step 6: Run existing event tests to verify no regression**

Run: `php artisan test --compact tests/Feature/Api/V1/EventTest.php`
Expected: All existing tests PASS

**Step 7: Commit**

```bash
git add app/Enums/ChallengeDifficulty.php app/Enums/ChallengeCompletionStatus.php database/migrations/*add_gamification_columns_to_events_table* app/Models/Event.php
git commit -m "feat: add gamification enums and event gamification columns (location, checkin_token, is_active)"
```

---

### Task 4: Create EventCheckin Model + Migration + Factory

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_event_checkins_table.php`
- Create: `app/Models/EventCheckin.php`
- Create: `database/factories/EventCheckinFactory.php`
- Modify: `app/Models/Event.php` (add `checkins` relationship)
- Modify: `app/Models/Profile.php` (add `eventCheckins` relationship)

**Step 1: Create migration**

Run: `php artisan make:migration create_event_checkins_table --no-interaction`

```php
public function up(): void
{
    Schema::create('event_checkins', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
        $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
        $table->timestamp('checked_in_at');
        $table->timestamps();

        $table->unique(['event_id', 'profile_id']);
        $table->index('profile_id');
    });
}
```

**Step 2: Create model**

`app/Models/EventCheckin.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_id
 * @property string $profile_id
 * @property \Illuminate\Support\Carbon $checked_in_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 * @property-read Profile $profile
 */
class EventCheckin extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'profile_id',
        'checked_in_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
```

**Step 3: Create factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCheckin>
 */
class EventCheckinFactory extends Factory
{
    protected $model = EventCheckin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'checked_in_at' => now(),
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => [
            'event_id' => $event->id,
        ]);
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => [
            'profile_id' => $profile->id,
        ]);
    }
}
```

**Step 4: Add relationships**

In `app/Models/Event.php`, add:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @return HasMany<EventCheckin, $this>
 */
public function checkins(): HasMany
{
    return $this->hasMany(EventCheckin::class);
}
```

In `app/Models/Profile.php`, add:
```php
/**
 * Get event check-ins for this profile.
 *
 * @return HasMany<EventCheckin, $this>
 */
public function eventCheckins(): HasMany
{
    return $this->hasMany(EventCheckin::class);
}
```

**Step 5: Run migration and verify**

Run: `php artisan migrate --no-interaction`

**Step 6: Commit**

```bash
git add database/migrations/*create_event_checkins_table* app/Models/EventCheckin.php database/factories/EventCheckinFactory.php app/Models/Event.php app/Models/Profile.php
git commit -m "feat: add EventCheckin model, migration, factory with Event and Profile relationships"
```

---

### Task 5: Create Challenge Model + Migration + Factory

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_challenges_table.php`
- Create: `app/Models/Challenge.php`
- Create: `database/factories/ChallengeFactory.php`
- Modify: `app/Models/Event.php` (add `challenges` relationship)

**Step 1: Create migration**

Run: `php artisan make:migration create_challenges_table --no-interaction`

```php
public function up(): void
{
    Schema::create('challenges', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name', 150);
        $table->text('description')->nullable();
        $table->string('difficulty', 10);  // easy, medium, hard
        $table->unsignedInteger('points');
        $table->boolean('is_system')->default(false);
        $table->foreignUuid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
        $table->timestamps();

        $table->index('is_system');
        $table->index('event_id');
        $table->index('difficulty');
    });
}
```

**Step 2: Create model**

`app/Models/Challenge.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeDifficulty;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property ChallengeDifficulty $difficulty
 * @property int $points
 * @property bool $is_system
 * @property string|null $event_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event|null $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ChallengeCompletion> $completions
 */
class Challenge extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'difficulty',
        'points',
        'is_system',
        'event_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'difficulty' => ChallengeDifficulty::class,
            'points' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<ChallengeCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(ChallengeCompletion::class);
    }

    /**
     * Check if this is a system-defined challenge.
     */
    public function isSystemChallenge(): bool
    {
        return $this->is_system;
    }
}
```

**Step 3: Create factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $difficulty = $this->faker->randomElement(ChallengeDifficulty::cases());

        return [
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'difficulty' => $difficulty,
            'points' => $difficulty->points(),
            'is_system' => false,
            'event_id' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'is_system' => true,
            'event_id' => null,
        ]);
    }

    public function easy(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Easy,
            'points' => ChallengeDifficulty::Easy->points(),
        ]);
    }

    public function medium(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Medium,
            'points' => ChallengeDifficulty::Medium->points(),
        ]);
    }

    public function hard(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Hard,
            'points' => ChallengeDifficulty::Hard->points(),
        ]);
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => [
            'event_id' => $event->id,
            'is_system' => false,
        ]);
    }
}
```

**Step 4: Add relationship to Event model**

In `app/Models/Event.php`:

```php
/**
 * @return HasMany<Challenge, $this>
 */
public function challenges(): HasMany
{
    return $this->hasMany(Challenge::class);
}
```

**Step 5: Run migration**

Run: `php artisan migrate --no-interaction`

**Step 6: Commit**

```bash
git add database/migrations/*create_challenges_table* app/Models/Challenge.php database/factories/ChallengeFactory.php app/Models/Event.php
git commit -m "feat: add Challenge model with difficulty enum, system/custom support, and factory"
```

---

### Task 6: Create ChallengeCompletion Model + Migration + Factory

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_challenge_completions_table.php`
- Create: `app/Models/ChallengeCompletion.php`
- Create: `database/factories/ChallengeCompletionFactory.php`

**Step 1: Create migration**

Run: `php artisan make:migration create_challenge_completions_table --no-interaction`

```php
public function up(): void
{
    Schema::create('challenge_completions', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
        $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
        $table->foreignUuid('challenger_profile_id')->constrained('profiles')->cascadeOnDelete();
        $table->foreignUuid('verifier_profile_id')->constrained('profiles')->cascadeOnDelete();
        $table->string('status', 10)->default('pending');  // pending, verified, rejected
        $table->unsignedInteger('points_earned')->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();

        $table->unique(
            ['challenge_id', 'event_id', 'challenger_profile_id', 'verifier_profile_id'],
            'challenge_completions_unique'
        );
        $table->index('challenger_profile_id');
        $table->index('verifier_profile_id');
        $table->index('event_id');
        $table->index('status');
    });
}
```

**Step 2: Create model**

`app/Models/ChallengeCompletion.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChallengeCompletionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $challenge_id
 * @property string $event_id
 * @property string $challenger_profile_id
 * @property string $verifier_profile_id
 * @property ChallengeCompletionStatus $status
 * @property int $points_earned
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Challenge $challenge
 * @property-read Event $event
 * @property-read Profile $challenger
 * @property-read Profile $verifier
 */
class ChallengeCompletion extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'challenge_id',
        'event_id',
        'challenger_profile_id',
        'verifier_profile_id',
        'status',
        'points_earned',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChallengeCompletionStatus::class,
            'points_earned' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Challenge, $this>
     */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'challenger_profile_id');
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'verifier_profile_id');
    }

    public function isPending(): bool
    {
        return $this->status === ChallengeCompletionStatus::Pending;
    }

    public function isVerified(): bool
    {
        return $this->status === ChallengeCompletionStatus::Verified;
    }
}
```

**Step 3: Create factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeCompletionStatus;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeCompletion>
 */
class ChallengeCompletionFactory extends Factory
{
    protected $model = ChallengeCompletion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'event_id' => Event::factory(),
            'challenger_profile_id' => Profile::factory()->attendee(),
            'verifier_profile_id' => Profile::factory()->attendee(),
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeCompletionStatus::Verified,
            'completed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeCompletionStatus::Rejected,
        ]);
    }
}
```

**Step 4: Run migration**

Run: `php artisan migrate --no-interaction`

**Step 5: Commit**

```bash
git add database/migrations/*create_challenge_completions_table* app/Models/ChallengeCompletion.php database/factories/ChallengeCompletionFactory.php
git commit -m "feat: add ChallengeCompletion model for peer-to-peer challenge verification"
```

---

### Task 7: CheckinService + CheckinController + Routes

**Files:**
- Create: `app/Services/CheckinService.php`
- Create: `app/Http/Controllers/Api/V1/CheckinController.php`
- Create: `app/Http/Requests/Api/V1/CheckinRequest.php`
- Create: `app/Http/Resources/Api/V1/EventCheckinResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CheckinTest.php`

**Service: `app/Services/CheckinService.php`**

Methods:
- `generateCheckinToken(Event $event): string` — generates a unique token for event QR, saves to event, returns token
- `checkin(Profile $profile, string $token): EventCheckin` — validates token, checks event is_active, checks no duplicate checkin, creates EventCheckin, increments attendee_profiles.total_events_attended
- `getCheckins(Event $event, int $perPage): LengthAwarePaginator` — lists attendees who checked in

**Controller: `app/Http/Controllers/Api/V1/CheckinController.php`**

Endpoints:
- `POST /api/v1/events/{event}/generate-qr` — organizer (event owner) generates QR token. Response: `{ success: true, data: { checkin_token: "..." } }`
- `POST /api/v1/checkin` — attendee checks in with `{ token: "..." }`. Response: `{ success: true, message: "Checked in successfully.", data: EventCheckinResource }`
- `GET /api/v1/events/{event}/checkins` — list who checked in (any auth user)

**Authorization:**
- `generate-qr`: only event owner (`$profile->id === $event->profile_id`)
- `checkin`: any authenticated attendee user
- `checkins list`: any authenticated user

**Validation:**
- CheckinRequest: `token` required, string, max:64

**Test key scenarios:**
1. Organizer can generate QR token
2. Non-owner cannot generate QR token
3. Attendee can check in with valid token
4. Attendee cannot check in twice (409)
5. Check-in fails with invalid token (404)
6. Check-in fails when event is not active (422)
7. List checkins returns paginated results
8. Unauthenticated user gets 401

**Step 1:** Write tests first (all failing)
**Step 2:** Create request, resource, service, controller
**Step 3:** Add routes to `routes/api.php`
**Step 4:** Run tests until all pass
**Step 5:** Commit

Routes to add (inside `auth:sanctum` group):
```php
// Gamification - Check-in
Route::post('events/{event}/generate-qr', [CheckinController::class, 'generateQr'])
    ->name('api.v1.events.generate-qr');

Route::post('checkin', [CheckinController::class, 'checkin'])
    ->name('api.v1.checkin');

Route::get('events/{event}/checkins', [CheckinController::class, 'index'])
    ->name('api.v1.events.checkins');
```

**Commit message:** `feat: add event check-in system with QR token generation and attendee check-in`

---

### Task 8: ChallengeService + ChallengeController + Routes (Organizer CRUD)

**Files:**
- Create: `app/Services/ChallengeService.php`
- Create: `app/Http/Controllers/Api/V1/ChallengeController.php`
- Create: `app/Http/Requests/Api/V1/StoreChallengeRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateChallengeRequest.php`
- Create: `app/Http/Resources/Api/V1/ChallengeResource.php`
- Create: `app/Policies/ChallengePolicy.php`
- Create: `database/seeders/SystemChallengeSeeder.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/ChallengeTest.php`

**Service methods:**
- `listForEvent(Event $event, int $perPage): LengthAwarePaginator` — returns system challenges + event-specific challenges
- `create(Event $event, array $data): Challenge` — creates custom challenge for event
- `update(Challenge $challenge, array $data): Challenge`
- `delete(Challenge $challenge): void`
- `getSystemChallenges(): Collection` — returns all system challenges

**Policy:**
- Only the event owner can create/update/delete challenges for their event
- System challenges cannot be modified via API
- Anyone authenticated can view challenges

**StoreChallengeRequest validation:**
- `name`: required, string, min:3, max:150
- `description`: nullable, string, max:500
- `difficulty`: required, string, in:easy,medium,hard
- `points`: sometimes, integer, min:1 (defaults to difficulty points if not provided)

**Seeder: `SystemChallengeSeeder`**

Seed initial system challenges:
```php
['name' => 'Take a selfie together', 'difficulty' => 'easy', 'points' => 5, 'is_system' => true],
['name' => 'Have a 2-minute conversation', 'difficulty' => 'medium', 'points' => 15, 'is_system' => true],
['name' => 'Dance on stage together', 'difficulty' => 'hard', 'points' => 30, 'is_system' => true],
['name' => 'Exchange social media handles', 'difficulty' => 'easy', 'points' => 5, 'is_system' => true],
['name' => 'Find 3 things you have in common', 'difficulty' => 'medium', 'points' => 15, 'is_system' => true],
```

**Routes (inside `auth:sanctum` group):**
```php
// Gamification - Challenges
Route::get('events/{event}/challenges', [ChallengeController::class, 'index'])
    ->name('api.v1.events.challenges.index');

Route::post('events/{event}/challenges', [ChallengeController::class, 'store'])
    ->name('api.v1.events.challenges.store');

Route::put('challenges/{challenge}', [ChallengeController::class, 'update'])
    ->name('api.v1.challenges.update');

Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy'])
    ->name('api.v1.challenges.destroy');
```

**Test key scenarios:**
1. List challenges returns system + event custom challenges
2. Event owner can create custom challenge
3. Non-owner cannot create challenge for event
4. Event owner can update own custom challenge
5. Cannot update system challenge
6. Event owner can delete own custom challenge
7. Cannot delete system challenge
8. Validation errors for invalid input
9. Default points from difficulty when not specified
10. Unauthenticated gets 401

**Commit message:** `feat: add challenge CRUD for organizers with system challenge seeder`

---

### Task 9: Challenge Initiation + Verification Flow (Peer-to-Peer)

**Files:**
- Create: `app/Services/ChallengeCompletionService.php`
- Create: `app/Http/Controllers/Api/V1/ChallengeCompletionController.php`
- Create: `app/Http/Requests/Api/V1/InitiateChallengeRequest.php`
- Create: `app/Http/Resources/Api/V1/ChallengeCompletionResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/ChallengeCompletionTest.php`

**Service: `ChallengeCompletionService`**

Methods:
- `initiate(Profile $challenger, array $data): ChallengeCompletion` — creates pending completion. Validates: both users checked in to same event, same challenge not already done between these two, challenger hasn't exceeded max_challenges_per_attendee
- `verify(Profile $verifier, ChallengeCompletion $completion): ChallengeCompletion` — verifier confirms. Awards points to challenger. Updates AttendeeProfile stats.
- `reject(Profile $verifier, ChallengeCompletion $completion): ChallengeCompletion` — verifier rejects
- `getMyCompletions(Profile $profile, int $perPage): LengthAwarePaginator` — challenge history

**Points awarding logic (inside `verify`):**
1. Set `status = verified`, `completed_at = now()`, `points_earned = challenge.points`
2. Increment `attendee_profiles.total_points += challenge.points`
3. Increment `attendee_profiles.total_challenges_completed += 1`

**InitiateChallengeRequest validation:**
- `challenge_id`: required, uuid, exists:challenges,id
- `event_id`: required, uuid, exists:events,id
- `verifier_profile_id`: required, uuid, exists:profiles,id, different from auth user

**Routes (inside `auth:sanctum` group):**
```php
// Gamification - Challenge Completion
Route::post('challenges/initiate', [ChallengeCompletionController::class, 'initiate'])
    ->name('api.v1.challenges.initiate');

Route::post('challenge-completions/{challengeCompletion}/verify', [ChallengeCompletionController::class, 'verify'])
    ->name('api.v1.challenge-completions.verify');

Route::post('challenge-completions/{challengeCompletion}/reject', [ChallengeCompletionController::class, 'reject'])
    ->name('api.v1.challenge-completions.reject');

Route::get('me/challenge-completions', [ChallengeCompletionController::class, 'myCompletions'])
    ->name('api.v1.me.challenge-completions');
```

**Test key scenarios:**
1. Challenger can initiate a challenge with valid data
2. Cannot initiate if not checked in to event
3. Cannot initiate if verifier not checked in to event
4. Cannot do same challenge with same pair twice (409)
5. Cannot exceed max_challenges_per_attendee
6. Verifier can verify completion — points awarded
7. Verifier can reject completion — no points
8. Non-verifier cannot verify/reject
9. Cannot verify already verified completion
10. Points correctly added to attendee_profiles
11. total_challenges_completed incremented on verify
12. My completions returns paginated history (both as challenger and verifier)
13. Unauthenticated gets 401

**Commit message:** `feat: add peer-to-peer challenge initiation and verification with point system`

---

### Task 10: Attendee Registration Endpoint

**Files:**
- Create: `app/Http/Requests/Api/V1/RegisterAttendeeRequest.php`
- Modify: `app/Services/AuthService.php` (add `registerAttendee` method)
- Modify: `app/Http/Controllers/Api/V1/AuthController.php` (add `registerAttendee` action)
- Modify: `routes/api.php` (add public route)
- Test: `tests/Feature/Api/V1/AttendeeRegistrationTest.php`

**RegisterAttendeeRequest:**
- `email`: required, email, unique:profiles,email
- `password`: required, string, min:8, confirmed

**AuthService::registerAttendee():**
- Creates Profile with `user_type = attendee`
- Creates AttendeeProfile with defaults (0 points, 0 challenges, etc.)
- Returns auth result with token

**Route (public):**
```php
Route::post('auth/register/attendee', [AuthController::class, 'registerAttendee'])
    ->name('api.v1.auth.register.attendee');
```

**Test key scenarios:**
1. Can register as attendee with valid email/password
2. AttendeeProfile created with default values
3. Token returned on successful registration
4. Duplicate email returns 422
5. Invalid password (too short) returns 422
6. Google OAuth login also works for attendee type

**Commit message:** `feat: add attendee registration endpoint with email/password`

---

### Task 11: Run Pint + Full Test Suite

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: ALL tests pass (existing + new)

**Step 3: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

## Task Dependency Graph

```
Task 1 (UserType enum)
    ↓
Task 2 (AttendeeProfile model) ← depends on Task 1
    ↓
Task 3 (Event gamification columns + enums) ← independent of Task 2
    ↓
Task 4 (EventCheckin model) ← depends on Task 3
    ↓
Task 5 (Challenge model) ← depends on Task 3
    ↓
Task 6 (ChallengeCompletion model) ← depends on Task 5
    ↓
Task 7 (CheckinService + Controller) ← depends on Task 4
Task 8 (ChallengeService + Controller) ← depends on Task 5
    ↓
Task 9 (ChallengeCompletion flow) ← depends on Task 6, 7, 8
    ↓
Task 10 (Attendee Registration) ← depends on Task 2
    ↓
Task 11 (Pint + Full Suite) ← depends on all
```

**Parallelizable groups:**
- Tasks 1-2: sequential (enum first)
- Task 3: can run after Task 1
- Tasks 4, 5: can run in parallel after Task 3
- Task 6: depends on Task 5
- Tasks 7, 8: can run in parallel (after 4, 5 respectively)
- Task 9: depends on 6, 7, 8
- Task 10: can run in parallel with 7-9 (only depends on Task 2)
- Task 11: last
