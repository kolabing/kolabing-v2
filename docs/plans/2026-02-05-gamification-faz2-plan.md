# Gamification System — Phase 2 Implementation Plan (Rewards & Leaderboard)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the reward system (organizer reward pool, spin-the-wheel, reward wallet with QR redeem) and leaderboard system (event-based + global ranking).

**Architecture:** Extends Phase 1 gamification infrastructure. Two new tables: `event_rewards` (organizer-managed reward pool per event) and `reward_claims` (attendee reward wallet). Spin-the-wheel is a probability-based random selection triggered after challenge verification. Leaderboards are computed from `challenge_completions` (event) and `attendee_profiles` (global) — no separate materialized table needed for MVP.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL, Sanctum auth, existing service layer pattern

**Design Doc:** `docs/plans/2026-02-05-gamification-system-design.md` (sections 8, 9)

---

## Critical Codebase Patterns (READ FIRST)

Before implementing ANY task, understand these patterns from Phase 1:

1. **Profile is the authenticatable model** — NOT User. `Profile extends Authenticatable` with `HasUuids`.
2. **Authorization uses `$profile->cannot()`** — NOT `$this->authorize()`. The base Controller lacks `AuthorizesRequests` trait.
3. **Tests use `LazilyRefreshDatabase`** — NOT `RefreshDatabase` or `DatabaseTransactions`.
4. **Response format:** `{ "success": true, "data": {...}, "message": "..." }`
5. **Service layer pattern:** All business logic in Services, controllers are thin.
6. **UUID primary keys** on all tables via `HasUuids` trait.
7. **Factory states:** ProfileFactory has `->business()`, `->community()`, `->attendee()` states.
8. **Exceptions in services:** `InvalidArgumentException` for auth/validation errors, `LogicException` for state conflicts. Controllers catch with appropriate HTTP codes (403, 409, 422).
9. **Existing Phase 1 models:** `AttendeeProfile`, `EventCheckin`, `Challenge`, `ChallengeCompletion` (with relationships).
10. **ChallengeCompletionService::verify()** awards points inside a DB transaction — spin-the-wheel should be a separate endpoint called after verify.
11. **Policy pattern:** See `app/Policies/ChallengePolicy.php` — Profile as first param, uses `$user->id === $event->profile_id` pattern.
12. **FormRequest pattern:** See `app/Http/Requests/Api/V1/InitiateChallengeRequest.php` — array-based rules, custom messages().
13. **Resource pattern:** See `app/Http/Resources/Api/V1/ChallengeResource.php` — toArray with enum->value, toIso8601String() for dates.

---

### Task 1: RewardClaimStatus Enum + EventReward Model + Migration + Factory

**Files:**
- Create: `app/Enums/RewardClaimStatus.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_event_rewards_table.php`
- Create: `app/Models/EventReward.php`
- Create: `database/factories/EventRewardFactory.php`
- Modify: `app/Models/Event.php` (add `rewards` relationship)

**Step 1: Create RewardClaimStatus enum**

Create `app/Enums/RewardClaimStatus.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum RewardClaimStatus: string
{
    case Available = 'available';
    case Redeemed = 'redeemed';
    case Expired = 'expired';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

**Step 2: Create migration**

Run: `php artisan make:migration create_event_rewards_table --no-interaction`

Migration content:

```php
public function up(): void
{
    Schema::create('event_rewards', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
        $table->string('name', 150);
        $table->text('description')->nullable();
        $table->unsignedInteger('total_quantity');
        $table->unsignedInteger('remaining_quantity');
        $table->decimal('probability', 5, 4);  // 0.0000 to 1.0000
        $table->timestamp('expires_at')->nullable();
        $table->timestamps();

        $table->index('event_id');
        $table->index('remaining_quantity');
    });
}
```

**Step 3: Create EventReward model**

Run: `php artisan make:model EventReward --no-interaction`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $event_id
 * @property string $name
 * @property string|null $description
 * @property int $total_quantity
 * @property int $remaining_quantity
 * @property string $probability
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardClaim> $claims
 */
class EventReward extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'name',
        'description',
        'total_quantity',
        'remaining_quantity',
        'probability',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'probability' => 'decimal:4',
            'expires_at' => 'datetime',
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
     * @return HasMany<RewardClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(RewardClaim::class);
    }

    /**
     * Check if this reward still has stock available.
     */
    public function hasStock(): bool
    {
        return $this->remaining_quantity > 0;
    }

    /**
     * Check if this reward has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
```

**Step 4: Create factory**

Run: `php artisan make:factory EventRewardFactory --model=EventReward --no-interaction`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReward>
 */
class EventRewardFactory extends Factory
{
    protected $model = EventReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(5, 50);

        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'total_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'probability' => $this->faker->randomFloat(4, 0.05, 0.5),
            'expires_at' => null,
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => [
            'event_id' => $event->id,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => [
            'remaining_quantity' => 0,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function withExpiry(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->addMonth(),
        ]);
    }

    public function highProbability(): static
    {
        return $this->state(fn (): array => [
            'probability' => 0.8,
        ]);
    }

    public function lowProbability(): static
    {
        return $this->state(fn (): array => [
            'probability' => 0.05,
        ]);
    }
}
```

**Step 5: Add `rewards()` relationship to Event model**

In `app/Models/Event.php`, add:

```php
/**
 * @return HasMany<EventReward, $this>
 */
public function rewards(): HasMany
{
    return $this->hasMany(EventReward::class);
}
```

Add to PHPDoc block:
```php
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventReward> $rewards
```

**Step 6: Run migration**

Run: `php artisan migrate --no-interaction`

**Step 7: Commit**

```bash
git add app/Enums/RewardClaimStatus.php database/migrations/*create_event_rewards_table* app/Models/EventReward.php database/factories/EventRewardFactory.php app/Models/Event.php
git commit -m "feat: add RewardClaimStatus enum, EventReward model, migration, and factory"
```

---

### Task 2: RewardClaim Model + Migration + Factory

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_reward_claims_table.php`
- Create: `app/Models/RewardClaim.php`
- Create: `database/factories/RewardClaimFactory.php`
- Modify: `app/Models/Profile.php` (add `rewardClaims` relationship)

**Step 1: Create migration**

Run: `php artisan make:migration create_reward_claims_table --no-interaction`

```php
public function up(): void
{
    Schema::create('reward_claims', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('event_reward_id')->constrained('event_rewards')->cascadeOnDelete();
        $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
        $table->foreignUuid('challenge_completion_id')->nullable()->constrained('challenge_completions')->nullOnDelete();
        $table->string('status', 15)->default('available');  // available, redeemed, expired
        $table->timestamp('won_at');
        $table->timestamp('redeemed_at')->nullable();
        $table->string('redeem_token', 64)->nullable()->unique();
        $table->timestamps();

        $table->index('profile_id');
        $table->index('event_reward_id');
        $table->index('status');
        $table->index('redeem_token');
    });
}
```

**Step 2: Create model**

`app/Models/RewardClaim.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RewardClaimStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $event_reward_id
 * @property string $profile_id
 * @property string|null $challenge_completion_id
 * @property RewardClaimStatus $status
 * @property \Illuminate\Support\Carbon $won_at
 * @property \Illuminate\Support\Carbon|null $redeemed_at
 * @property string|null $redeem_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read EventReward $eventReward
 * @property-read Profile $profile
 * @property-read ChallengeCompletion|null $challengeCompletion
 */
class RewardClaim extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_reward_id',
        'profile_id',
        'challenge_completion_id',
        'status',
        'won_at',
        'redeemed_at',
        'redeem_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RewardClaimStatus::class,
            'won_at' => 'datetime',
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EventReward, $this>
     */
    public function eventReward(): BelongsTo
    {
        return $this->belongsTo(EventReward::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return BelongsTo<ChallengeCompletion, $this>
     */
    public function challengeCompletion(): BelongsTo
    {
        return $this->belongsTo(ChallengeCompletion::class);
    }

    /**
     * Check if this reward claim is available for redemption.
     */
    public function isAvailable(): bool
    {
        return $this->status === RewardClaimStatus::Available;
    }

    /**
     * Check if this reward claim has been redeemed.
     */
    public function isRedeemed(): bool
    {
        return $this->status === RewardClaimStatus::Redeemed;
    }
}
```

**Step 3: Create factory**

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RewardClaimStatus;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardClaim>
 */
class RewardClaimFactory extends Factory
{
    protected $model = RewardClaim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_reward_id' => EventReward::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'status' => RewardClaimStatus::Available,
            'won_at' => now(),
        ];
    }

    public function redeemed(): static
    {
        return $this->state(fn (): array => [
            'status' => RewardClaimStatus::Redeemed,
            'redeemed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => RewardClaimStatus::Expired,
        ]);
    }

    public function withRedeemToken(): static
    {
        return $this->state(fn (): array => [
            'redeem_token' => bin2hex(random_bytes(32)),
        ]);
    }
}
```

**Step 4: Add `rewardClaims()` relationship to Profile model**

In `app/Models/Profile.php`, add:

```php
/**
 * Get reward claims for this profile.
 *
 * @return HasMany<RewardClaim, $this>
 */
public function rewardClaims(): HasMany
{
    return $this->hasMany(RewardClaim::class);
}
```

Add to PHPDoc block:
```php
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RewardClaim> $rewardClaims
```

**Step 5: Run migration**

Run: `php artisan migrate --no-interaction`

**Step 6: Commit**

```bash
git add database/migrations/*create_reward_claims_table* app/Models/RewardClaim.php database/factories/RewardClaimFactory.php app/Models/Profile.php
git commit -m "feat: add RewardClaim model with migration, factory, and Profile relationship"
```

---

### Task 3: EventRewardService + EventRewardController + Routes (Organizer CRUD)

**Files:**
- Create: `app/Services/EventRewardService.php`
- Create: `app/Http/Controllers/Api/V1/EventRewardController.php`
- Create: `app/Http/Requests/Api/V1/StoreEventRewardRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateEventRewardRequest.php`
- Create: `app/Http/Resources/Api/V1/EventRewardResource.php`
- Create: `app/Policies/EventRewardPolicy.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/EventRewardTest.php`

**Service: `app/Services/EventRewardService.php`**

Methods:
- `listForEvent(Event $event): Collection` — returns all rewards for an event
- `create(Event $event, array $data): EventReward` — creates reward, sets remaining_quantity = total_quantity
- `update(EventReward $reward, array $data): EventReward` — updates reward. If total_quantity changed, adjust remaining_quantity proportionally (remaining += new_total - old_total, clamped to 0)
- `delete(EventReward $reward): void` — deletes reward (only if no claims exist)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventReward;
use Illuminate\Database\Eloquent\Collection;

class EventRewardService
{
    /**
     * List all rewards for an event.
     *
     * @return Collection<int, EventReward>
     */
    public function listForEvent(Event $event): Collection
    {
        return $event->rewards()->orderByDesc('created_at')->get();
    }

    /**
     * Create a new reward for an event.
     *
     * @param  array{name: string, description?: string|null, total_quantity: int, probability: float, expires_at?: string|null}  $data
     */
    public function create(Event $event, array $data): EventReward
    {
        $reward = EventReward::query()->create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'total_quantity' => $data['total_quantity'],
            'remaining_quantity' => $data['total_quantity'],
            'probability' => $data['probability'],
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return $reward->load('event');
    }

    /**
     * Update an existing reward.
     *
     * @param  array{name?: string, description?: string|null, total_quantity?: int, probability?: float, expires_at?: string|null}  $data
     */
    public function update(EventReward $reward, array $data): EventReward
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['total_quantity'])) {
            $oldTotal = $reward->total_quantity;
            $newTotal = $data['total_quantity'];
            $difference = $newTotal - $oldTotal;

            $updateData['total_quantity'] = $newTotal;
            $updateData['remaining_quantity'] = max(0, $reward->remaining_quantity + $difference);
        }

        if (isset($data['probability'])) {
            $updateData['probability'] = $data['probability'];
        }

        if (array_key_exists('expires_at', $data)) {
            $updateData['expires_at'] = $data['expires_at'];
        }

        $reward->update($updateData);

        return $reward->load('event');
    }

    /**
     * Delete a reward (only if no claims exist).
     */
    public function delete(EventReward $reward): void
    {
        if ($reward->claims()->exists()) {
            throw new \LogicException('Cannot delete a reward that has existing claims.');
        }

        $reward->delete();
    }
}
```

**Policy: `app/Policies/EventRewardPolicy.php`**

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;

class EventRewardPolicy
{
    /**
     * Any authenticated user can view rewards for an event.
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Only the event owner can create rewards.
     */
    public function create(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }

    /**
     * Only the event owner can update rewards.
     */
    public function update(Profile $user, EventReward $reward): bool
    {
        return $reward->event !== null
            && $user->id === $reward->event->profile_id;
    }

    /**
     * Only the event owner can delete rewards.
     */
    public function delete(Profile $user, EventReward $reward): bool
    {
        return $reward->event !== null
            && $user->id === $reward->event->profile_id;
    }
}
```

**StoreEventRewardRequest validation:**
- `name`: required, string, min:2, max:150
- `description`: nullable, string, max:500
- `total_quantity`: required, integer, min:1
- `probability`: required, numeric, min:0.0001, max:1
- `expires_at`: nullable, date, after:now

**UpdateEventRewardRequest validation:**
- `name`: sometimes, string, min:2, max:150
- `description`: nullable, string, max:500
- `total_quantity`: sometimes, integer, min:1
- `probability`: sometimes, numeric, min:0.0001, max:1
- `expires_at`: nullable, date, after:now

**Controller:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRewardRequest;
use App\Http\Requests\Api\V1\UpdateEventRewardRequest;
use App\Http\Resources\Api\V1\EventRewardResource;
use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;
use App\Services\EventRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRewardController extends Controller
{
    public function __construct(
        private readonly EventRewardService $eventRewardService
    ) {}

    /**
     * List rewards for an event.
     *
     * GET /api/v1/events/{event}/rewards
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $rewards = $this->eventRewardService->listForEvent($event);

        return response()->json([
            'success' => true,
            'data' => EventRewardResource::collection($rewards),
        ]);
    }

    /**
     * Create a new reward for an event.
     *
     * POST /api/v1/events/{event}/rewards
     */
    public function store(StoreEventRewardRequest $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', [EventReward::class, $event])) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage rewards for this event.'),
            ], 403);
        }

        $reward = $this->eventRewardService->create($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Reward created successfully.'),
            'data' => new EventRewardResource($reward),
        ], 201);
    }

    /**
     * Update an existing reward.
     *
     * PUT /api/v1/event-rewards/{eventReward}
     */
    public function update(UpdateEventRewardRequest $request, EventReward $eventReward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $eventReward)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this reward.'),
            ], 403);
        }

        $reward = $this->eventRewardService->update($eventReward, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Reward updated successfully.'),
            'data' => new EventRewardResource($reward),
        ]);
    }

    /**
     * Delete a reward.
     *
     * DELETE /api/v1/event-rewards/{eventReward}
     */
    public function destroy(Request $request, EventReward $eventReward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $eventReward)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this reward.'),
            ], 403);
        }

        try {
            $this->eventRewardService->delete($eventReward);

            return response()->json([
                'success' => true,
                'message' => __('Reward deleted successfully.'),
            ]);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
```

**EventRewardResource:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EventReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventReward
 */
class EventRewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'description' => $this->description,
            'total_quantity' => $this->total_quantity,
            'remaining_quantity' => $this->remaining_quantity,
            'probability' => (float) $this->probability,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Routes (inside `auth:sanctum` group):**
```php
// Gamification - Rewards (Organizer Management)
Route::get('events/{event}/rewards', [EventRewardController::class, 'index'])
    ->name('api.v1.events.rewards.index');

Route::post('events/{event}/rewards', [EventRewardController::class, 'store'])
    ->name('api.v1.events.rewards.store');

Route::put('event-rewards/{eventReward}', [EventRewardController::class, 'update'])
    ->name('api.v1.event-rewards.update');

Route::delete('event-rewards/{eventReward}', [EventRewardController::class, 'destroy'])
    ->name('api.v1.event-rewards.destroy');
```

**Test key scenarios:**
1. List rewards for event returns all rewards
2. Event owner can create a reward with valid data
3. Non-owner cannot create reward for event (403)
4. Event owner can update reward name, quantity, probability
5. Updating total_quantity adjusts remaining_quantity proportionally
6. Event owner can delete reward with no claims
7. Cannot delete reward that has existing claims (409)
8. Non-owner cannot update/delete reward (403)
9. Validation errors for invalid input (missing name, probability > 1, etc.)
10. Unauthenticated gets 401

**Commit message:** `feat: add event reward pool management CRUD for organizers`

---

### Task 4: SpinWheelService + SpinWheelController + Route

**Files:**
- Create: `app/Services/SpinWheelService.php`
- Create: `app/Http/Controllers/Api/V1/SpinWheelController.php`
- Create: `app/Http/Requests/Api/V1/SpinWheelRequest.php`
- Create: `app/Http/Resources/Api/V1/RewardClaimResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/SpinWheelTest.php`

**Service: `app/Services/SpinWheelService.php`**

The spin-the-wheel mechanism:
1. Attendee completes a challenge (verify) → frontend calls spin endpoint
2. Backend checks: challenge_completion_id is verified, belongs to the caller, and hasn't been spun already
3. Get all available rewards for the event (remaining_quantity > 0, not expired)
4. Probability-based random selection:
   - Sum all probabilities of available rewards
   - Generate random float 0-1
   - If random > sum → no reward (miss)
   - Otherwise, weighted selection among available rewards
5. If won: create RewardClaim, decrement remaining_quantity (use DB lock for race condition)
6. Return spin result (won or not, reward details if won)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\RewardClaimStatus;
use App\Models\ChallengeCompletion;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Support\Facades\DB;

class SpinWheelService
{
    /**
     * Perform a spin-the-wheel for a verified challenge completion.
     *
     * @return array{won: bool, reward_claim: RewardClaim|null}
     */
    public function spin(Profile $profile, ChallengeCompletion $completion): array
    {
        // Validate: completion must be verified
        if ($completion->status !== ChallengeCompletionStatus::Verified) {
            throw new \InvalidArgumentException('Only verified challenge completions can trigger a spin.');
        }

        // Validate: caller must be the challenger
        if ($completion->challenger_profile_id !== $profile->id) {
            throw new \InvalidArgumentException('You are not the challenger for this completion.');
        }

        // Validate: not already spun for this completion
        $alreadySpun = RewardClaim::query()
            ->where('challenge_completion_id', $completion->id)
            ->where('profile_id', $profile->id)
            ->exists();

        if ($alreadySpun) {
            throw new \LogicException('You have already spun the wheel for this challenge completion.');
        }

        // Get available rewards for the event
        $rewards = EventReward::query()
            ->where('event_id', $completion->event_id)
            ->where('remaining_quantity', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();

        if ($rewards->isEmpty()) {
            return ['won' => false, 'reward_claim' => null];
        }

        // Probability-based random selection
        $random = mt_rand() / mt_getrandmax();  // 0.0 to 1.0

        $cumulativeProbability = 0.0;
        $selectedReward = null;

        foreach ($rewards as $reward) {
            $cumulativeProbability += (float) $reward->probability;
            if ($random <= $cumulativeProbability) {
                $selectedReward = $reward;
                break;
            }
        }

        if ($selectedReward === null) {
            return ['won' => false, 'reward_claim' => null];
        }

        // Award the reward inside a transaction with locking
        return DB::transaction(function () use ($selectedReward, $profile, $completion): array {
            // Re-fetch with lock to prevent race conditions
            $lockedReward = EventReward::query()
                ->lockForUpdate()
                ->where('id', $selectedReward->id)
                ->where('remaining_quantity', '>', 0)
                ->first();

            if (! $lockedReward) {
                return ['won' => false, 'reward_claim' => null];
            }

            $lockedReward->decrement('remaining_quantity');

            $claim = RewardClaim::query()->create([
                'event_reward_id' => $lockedReward->id,
                'profile_id' => $profile->id,
                'challenge_completion_id' => $completion->id,
                'status' => RewardClaimStatus::Available,
                'won_at' => now(),
            ]);

            return [
                'won' => true,
                'reward_claim' => $claim->load('eventReward'),
            ];
        });
    }
}
```

**SpinWheelRequest validation:**
- `challenge_completion_id`: required, uuid, exists:challenge_completions,id

**Controller:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SpinWheelRequest;
use App\Http\Resources\Api\V1\RewardClaimResource;
use App\Models\ChallengeCompletion;
use App\Models\Profile;
use App\Services\SpinWheelService;
use Illuminate\Http\JsonResponse;

class SpinWheelController extends Controller
{
    public function __construct(
        private readonly SpinWheelService $spinWheelService
    ) {}

    /**
     * Spin the wheel after a verified challenge completion.
     *
     * POST /api/v1/rewards/spin
     */
    public function spin(SpinWheelRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $completion = ChallengeCompletion::query()->findOrFail(
            $request->validated('challenge_completion_id')
        );

        try {
            $result = $this->spinWheelService->spin($profile, $completion);

            if ($result['won']) {
                return response()->json([
                    'success' => true,
                    'message' => __('Congratulations! You won a reward!'),
                    'data' => [
                        'won' => true,
                        'reward_claim' => new RewardClaimResource($result['reward_claim']),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('Better luck next time!'),
                'data' => [
                    'won' => false,
                    'reward_claim' => null,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
```

**RewardClaimResource:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\RewardClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RewardClaim
 */
class RewardClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_reward' => new EventRewardResource($this->whenLoaded('eventReward')),
            'profile_id' => $this->profile_id,
            'status' => $this->status->value,
            'won_at' => $this->won_at->toIso8601String(),
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'redeem_token' => $this->redeem_token,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

**Route (inside `auth:sanctum` group):**
```php
// Gamification - Spin the Wheel
Route::post('rewards/spin', [SpinWheelController::class, 'spin'])
    ->name('api.v1.rewards.spin');
```

**Test key scenarios:**
1. Spin after verified challenge completion — may win or not (test with high probability reward = 1.0 to guarantee win)
2. Spin creates RewardClaim with status `available`
3. Spin decrements EventReward remaining_quantity
4. Cannot spin for unverified (pending) completion (422)
5. Cannot spin if not the challenger (422)
6. Cannot spin twice for same completion (409)
7. Spin returns `won: false` when no rewards available (all out of stock)
8. Spin returns `won: false` when all rewards expired
9. Spin returns `won: false` when random exceeds all probabilities (test with probability = 0.0001 on seeded random — alternatively, just test the no-rewards-available case)
10. Unauthenticated gets 401

**Commit message:** `feat: add spin-the-wheel mechanism with probability-based reward selection`

---

### Task 5: RewardWalletController + Routes (Wallet + QR Redeem)

**Files:**
- Create: `app/Services/RewardWalletService.php`
- Create: `app/Http/Controllers/Api/V1/RewardWalletController.php`
- Create: `app/Http/Requests/Api/V1/ConfirmRedeemRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/RewardWalletTest.php`

**Service: `app/Services/RewardWalletService.php`**

Methods:
- `getMyRewards(Profile $profile, int $perPage): LengthAwarePaginator` — paginated list of the profile's reward claims with eventReward loaded, ordered by won_at desc
- `generateRedeemToken(Profile $profile, RewardClaim $claim): RewardClaim` — generates a unique 64-char token for the claim. Validates: claim belongs to profile, status is available. Sets `redeem_token` on claim.
- `confirmRedeem(Profile $organizer, string $token): RewardClaim` — organizer scans QR/submits token. Validates: claim found by token, organizer is the event owner. Sets status to redeemed, redeemed_at to now, clears redeem_token.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RewardClaimStatus;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class RewardWalletService
{
    /**
     * Get the profile's reward claims (wallet).
     */
    public function getMyRewards(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return RewardClaim::query()
            ->where('profile_id', $profile->id)
            ->with(['eventReward.event'])
            ->orderByDesc('won_at')
            ->paginate($perPage);
    }

    /**
     * Generate a temporary redeem QR token for a reward claim.
     */
    public function generateRedeemToken(Profile $profile, RewardClaim $claim): RewardClaim
    {
        if ($claim->profile_id !== $profile->id) {
            throw new \InvalidArgumentException('This reward claim does not belong to you.');
        }

        if (! $claim->isAvailable()) {
            throw new \LogicException('This reward has already been redeemed or has expired.');
        }

        // Check if the underlying reward has expired
        $claim->load('eventReward');
        if ($claim->eventReward->isExpired()) {
            $claim->update(['status' => RewardClaimStatus::Expired]);
            throw new \LogicException('This reward has expired.');
        }

        $claim->update([
            'redeem_token' => Str::random(64),
        ]);

        return $claim->load('eventReward');
    }

    /**
     * Confirm a reward redemption using the redeem token (organizer action).
     */
    public function confirmRedeem(Profile $organizer, string $token): RewardClaim
    {
        $claim = RewardClaim::query()
            ->where('redeem_token', $token)
            ->with(['eventReward.event'])
            ->first();

        if (! $claim) {
            throw new \InvalidArgumentException('Invalid redeem token.');
        }

        // Validate: organizer is the event owner
        if ($claim->eventReward->event->profile_id !== $organizer->id) {
            throw new \InvalidArgumentException('You are not the organizer for this reward\'s event.');
        }

        if (! $claim->isAvailable()) {
            throw new \LogicException('This reward has already been redeemed or has expired.');
        }

        $claim->update([
            'status' => RewardClaimStatus::Redeemed,
            'redeemed_at' => now(),
            'redeem_token' => null,
        ]);

        return $claim->load('eventReward');
    }
}
```

**ConfirmRedeemRequest validation:**
- `token`: required, string, size:64

**Controller:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmRedeemRequest;
use App\Http\Resources\Api\V1\RewardClaimResource;
use App\Models\Profile;
use App\Models\RewardClaim;
use App\Services\RewardWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardWalletController extends Controller
{
    public function __construct(
        private readonly RewardWalletService $rewardWalletService
    ) {}

    /**
     * Get my reward wallet (all claimed rewards).
     *
     * GET /api/v1/me/rewards
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('limit', '10'), 50);

        $paginator = $this->rewardWalletService->getMyRewards($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'rewards' => RewardClaimResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Generate a redeem QR token for a reward claim.
     *
     * POST /api/v1/reward-claims/{rewardClaim}/generate-redeem-qr
     */
    public function generateRedeemQr(Request $request, RewardClaim $rewardClaim): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $claim = $this->rewardWalletService->generateRedeemToken($profile, $rewardClaim);

            return response()->json([
                'success' => true,
                'message' => __('Redeem QR generated. Show this to the organizer.'),
                'data' => new RewardClaimResource($claim),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Confirm reward redemption using the redeem token (organizer scans QR).
     *
     * POST /api/v1/reward-claims/confirm-redeem
     */
    public function confirmRedeem(ConfirmRedeemRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $claim = $this->rewardWalletService->confirmRedeem($profile, $request->validated('token'));

            return response()->json([
                'success' => true,
                'message' => __('Reward redeemed successfully.'),
                'data' => new RewardClaimResource($claim),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getMessage() === 'Invalid redeem token.' ? 404 : 403);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
```

**Routes (inside `auth:sanctum` group):**
```php
// Gamification - Reward Wallet
Route::get('me/rewards', [RewardWalletController::class, 'index'])
    ->name('api.v1.me.rewards');

Route::post('reward-claims/{rewardClaim}/generate-redeem-qr', [RewardWalletController::class, 'generateRedeemQr'])
    ->name('api.v1.reward-claims.generate-redeem-qr');

Route::post('reward-claims/confirm-redeem', [RewardWalletController::class, 'confirmRedeem'])
    ->name('api.v1.reward-claims.confirm-redeem');
```

**Test key scenarios:**
1. My rewards returns paginated reward claims
2. My rewards returns empty when no claims
3. Generate redeem QR sets token on claim
4. Cannot generate redeem QR for claim that doesn't belong to you (403)
5. Cannot generate redeem QR for already redeemed claim (409)
6. Cannot generate redeem QR for expired reward (409, status changes to expired)
7. Confirm redeem sets status to redeemed and clears token
8. Confirm redeem fails with invalid token (404)
9. Confirm redeem fails when caller is not event owner (403)
10. Cannot confirm redeem for already redeemed claim (409)
11. Unauthenticated gets 401

**Commit message:** `feat: add reward wallet with QR-based redemption flow`

---

### Task 6: LeaderboardService + LeaderboardController + Routes

**Files:**
- Create: `app/Services/LeaderboardService.php`
- Create: `app/Http/Controllers/Api/V1/LeaderboardController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/LeaderboardTest.php`

**Service: `app/Services/LeaderboardService.php`**

Methods:
- `getEventLeaderboard(Event $event, int $limit): Collection` — aggregates points from challenge_completions for the event, grouped by challenger_profile_id, ordered by total points desc. Returns array of `{ profile, points, rank }`.
- `getGlobalLeaderboard(int $limit): Collection` — reads from attendee_profiles ordered by total_points desc. Returns `{ profile, total_points, rank }`.
- `getMyEventRank(Event $event, Profile $profile): ?array` — returns the profile's rank and points in the event leaderboard, or null if not on the board.
- `getMyGlobalRank(Profile $profile): ?array` — returns the profile's global rank and total points.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Get the leaderboard for a specific event.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, total_points: int, rank: int}>
     */
    public function getEventLeaderboard(Event $event, int $limit = 50): Collection
    {
        $results = ChallengeCompletion::query()
            ->select([
                'challenger_profile_id as profile_id',
                DB::raw('SUM(points_earned) as total_points'),
            ])
            ->where('event_id', $event->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->groupBy('challenger_profile_id')
            ->orderByDesc('total_points')
            ->limit($limit)
            ->get();

        $profileIds = $results->pluck('profile_id');
        $profiles = Profile::query()->whereIn('id', $profileIds)->get()->keyBy('id');

        $rank = 0;

        return $results->map(function ($row) use ($profiles, &$rank) {
            $rank++;
            $profile = $profiles->get($row->profile_id);

            return [
                'profile_id' => $row->profile_id,
                'display_name' => $profile?->display_name ?? 'Unknown',
                'profile_photo' => $profile?->profile_photo,
                'total_points' => (int) $row->total_points,
                'rank' => $rank,
            ];
        });
    }

    /**
     * Get the global leaderboard.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, total_points: int, rank: int}>
     */
    public function getGlobalLeaderboard(int $limit = 50): Collection
    {
        $results = AttendeeProfile::query()
            ->where('total_points', '>', 0)
            ->orderByDesc('total_points')
            ->limit($limit)
            ->with('profile')
            ->get();

        $rank = 0;

        return $results->map(function (AttendeeProfile $attendeeProfile) use (&$rank) {
            $rank++;

            return [
                'profile_id' => $attendeeProfile->profile_id,
                'display_name' => $attendeeProfile->profile?->display_name ?? 'Unknown',
                'profile_photo' => $attendeeProfile->profile?->profile_photo ?? null,
                'total_points' => $attendeeProfile->total_points,
                'rank' => $rank,
            ];
        });
    }

    /**
     * Get the authenticated user's rank in an event leaderboard.
     *
     * @return array{profile_id: string, total_points: int, rank: int}|null
     */
    public function getMyEventRank(Event $event, Profile $profile): ?array
    {
        $myPoints = ChallengeCompletion::query()
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $profile->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->sum('points_earned');

        if ($myPoints === 0) {
            return null;
        }

        $rank = ChallengeCompletion::query()
            ->select('challenger_profile_id')
            ->where('event_id', $event->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->groupBy('challenger_profile_id')
            ->havingRaw('SUM(points_earned) > ?', [$myPoints])
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'total_points' => (int) $myPoints,
            'rank' => $rank,
        ];
    }

    /**
     * Get the authenticated user's global rank.
     *
     * @return array{profile_id: string, total_points: int, rank: int}|null
     */
    public function getMyGlobalRank(Profile $profile): ?array
    {
        $attendeeProfile = $profile->attendeeProfile;

        if (! $attendeeProfile || $attendeeProfile->total_points === 0) {
            return null;
        }

        $rank = AttendeeProfile::query()
            ->where('total_points', '>', $attendeeProfile->total_points)
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'total_points' => $attendeeProfile->total_points,
            'rank' => $rank,
        ];
    }
}
```

**Controller:**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Profile;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService
    ) {}

    /**
     * Get the leaderboard for a specific event.
     *
     * GET /api/v1/events/{event}/leaderboard
     */
    public function eventLeaderboard(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $leaderboard = $this->leaderboardService->getEventLeaderboard($event, $limit);
        $myRank = $this->leaderboardService->getMyEventRank($event, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values(),
                'my_rank' => $myRank,
            ],
        ]);
    }

    /**
     * Get the global leaderboard.
     *
     * GET /api/v1/leaderboard/global
     */
    public function globalLeaderboard(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $leaderboard = $this->leaderboardService->getGlobalLeaderboard($limit);
        $myRank = $this->leaderboardService->getMyGlobalRank($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values(),
                'my_rank' => $myRank,
            ],
        ]);
    }
}
```

**Routes (inside `auth:sanctum` group):**
```php
// Gamification - Leaderboard
Route::get('events/{event}/leaderboard', [LeaderboardController::class, 'eventLeaderboard'])
    ->name('api.v1.events.leaderboard');

Route::get('leaderboard/global', [LeaderboardController::class, 'globalLeaderboard'])
    ->name('api.v1.leaderboard.global');
```

**Test key scenarios:**
1. Event leaderboard returns ranked profiles by points
2. Event leaderboard returns empty when no verified completions
3. Global leaderboard returns ranked attendees by total_points
4. Global leaderboard excludes profiles with 0 points
5. My event rank returns correct rank when user has points
6. My event rank returns null when user has no points
7. My global rank returns correct rank
8. My global rank returns null for non-attendee
9. Leaderboard respects limit parameter
10. Multiple users with different points are ranked correctly
11. Unauthenticated gets 401

**Commit message:** `feat: add event and global leaderboard with user rank`

---

### Task 7: Run Pint + Full Test Suite

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: ALL tests pass (existing + new Phase 2 tests)

**Step 3: Commit any formatting fixes**

```bash
git add -A
git commit -m "style: apply pint formatting for Phase 2"
```

---

## Task Dependency Graph

```
Task 1 (EventReward model + enum)
    ↓
Task 2 (RewardClaim model) ← depends on Task 1
    ↓
Task 3 (EventReward CRUD) ← depends on Task 1
Task 4 (Spin-the-Wheel) ← depends on Task 1, 2
Task 5 (Reward Wallet + Redeem) ← depends on Task 2
    ↓
Task 6 (Leaderboard) ← independent of Tasks 3-5 (uses existing Phase 1 models)
    ↓
Task 7 (Pint + Full Suite) ← depends on all
```

**Parallelizable groups:**
- Tasks 1-2: sequential (EventReward before RewardClaim)
- Tasks 3, 4, 5: can run after Task 2
- Task 6: independent, can run after Phase 1 (no dependency on Tasks 1-5)
- Task 7: last

**Execution suggestion:**
1. Task 1 → Task 2 (sequential, models first)
2. Task 3 + Task 6 (parallel — CRUD and leaderboard are independent)
3. Task 4 → Task 5 (sequential — spin creates claims, wallet reads them)
4. Task 7 (final)
