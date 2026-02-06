# Gamification Phase 3 — Gamification Zenginlestirme

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add badge system with auto-awarding, map-based event discovery, gamification stats / player card API, and gamification-related in-app notifications.

**Architecture:** Badge system uses a system-defined `badges` table seeded with 9 milestone badges, auto-awarded via `BadgeService` hooks in existing services. Event discovery uses Haversine formula for geospatial queries on existing `location_lat`/`location_lng` columns. Gamification notifications extend the existing `NotificationType` enum and `NotificationService`.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL, Sanctum auth, UUID primary keys

---

## Context

### Auth Pattern
- `Profile` is the authenticatable model (`extends Authenticatable` with `HasUuids`)
- Authorization: `$profile->cannot('action', $model)` — NOT `$this->authorize()`
- Tests use `LazilyRefreshDatabase` (NOT RefreshDatabase or DatabaseTransactions)

### Response Format
```json
{"success": true, "data": {...}, "message": "..."}
```

### Service Layer Pattern
- All business logic in Service classes
- Controllers are thin (delegate to services, catch exceptions, return JSON)
- Exceptions: `InvalidArgumentException` → 403/422, `LogicException` → 409

### Existing Models/Services (Phase 1 & 2)
- `AttendeeProfile` — total_points, total_challenges_completed, total_events_attended, global_rank
- `CheckinService::checkin()` — increments total_events_attended
- `ChallengeCompletionService::verify()` — increments total_points + total_challenges_completed
- `SpinWheelService::spin()` — creates RewardClaim on win
- `NotificationService::createNotification()` — generic notification creator
- `NotificationType` enum — NewMessage, ApplicationReceived, ApplicationAccepted, ApplicationDeclined
- `Event` model — has location_lat, location_lng, address, is_active columns

### Routes File
- `routes/api.php` — all routes under `Route::prefix('v1')` → `Route::middleware('auth:sanctum')`

---

## Task 1: BadgeMilestoneType Enum + Badge Model + Migration + Factory + Seeder

**Files:**
- Create: `app/Enums/BadgeMilestoneType.php`
- Create: `database/migrations/xxxx_create_badges_table.php`
- Create: `app/Models/Badge.php`
- Create: `database/factories/BadgeFactory.php`
- Create: `database/seeders/BadgeSeeder.php`

### Step 1: Create BadgeMilestoneType Enum

```php
// app/Enums/BadgeMilestoneType.php
<?php

declare(strict_types=1);

namespace App\Enums;

enum BadgeMilestoneType: string
{
    case FirstCheckin = 'first_checkin';
    case FirstChallenge = 'first_challenge';
    case SocialButterfly = 'social_butterfly_10';      // 10 unique verifiers
    case ChallengeMaster = 'challenges_completed_50';
    case EventGuru = 'events_attended_10';
    case PointHunter = 'points_500';
    case Legend = 'points_2000';
    case RewardCollector = 'rewards_won_10';
    case LoyalAttendee = 'events_attended_5';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### Step 2: Create badges migration

Run: `php artisan make:migration create_badges_table --no-interaction`

Migration content:
```php
Schema::create('badges', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->text('description');
    $table->string('icon');
    $table->string('milestone_type')->unique();
    $table->integer('milestone_value');
    $table->timestamps();
});
```

### Step 3: Create Badge Model

```php
// app/Models/Badge.php
- HasFactory, HasUuids
- fillable: name, description, icon, milestone_type, milestone_value
- casts: milestone_type → BadgeMilestoneType, milestone_value → integer
- awards(): HasMany BadgeAward
```

### Step 4: Create BadgeFactory

```php
// database/factories/BadgeFactory.php
- Default: name from faker, description, icon='badge-default', milestone_type=FirstCheckin, milestone_value=1
- States: forMilestone(BadgeMilestoneType $type, int $value)
```

### Step 5: Create BadgeSeeder

Seed 9 system badges matching design doc section 10.1:

| Milestone Type | Name | Description | Icon | Value |
|---|---|---|---|---|
| first_checkin | Ilk Adim | Ilk etkinlige check-in yap | badge-first-checkin | 1 |
| first_challenge | Challenge Baslangic | Ilk challenge'ini tamamla | badge-first-challenge | 1 |
| social_butterfly_10 | Sosyal Kelebek | 10 farkli kisiyle challenge yap | badge-social-butterfly | 10 |
| challenges_completed_50 | Challenge Master | 50 challenge tamamla | badge-challenge-master | 50 |
| events_attended_10 | Etkinlik Gurusu | 10 etkinlige katil | badge-event-guru | 10 |
| points_500 | Puan Avcisi | 500 toplam puan kazan | badge-point-hunter | 500 |
| points_2000 | Efsane | 2000 toplam puan kazan | badge-legend | 2000 |
| rewards_won_10 | Odul Koleksiyoncusu | 10 odul kazan | badge-reward-collector | 10 |
| events_attended_5 | Sadik Katilimci | 5 etkinlige katil | badge-loyal-attendee | 5 |

### Step 6: Run seeder and verify

Run: `php artisan db:seed --class=BadgeSeeder --no-interaction`

### Step 7: Commit

```
feat: add Badge model with BadgeMilestoneType enum and system badge seeder
```

---

## Task 2: BadgeAward Model + Migration + Factory

**Files:**
- Create: `database/migrations/xxxx_create_badge_awards_table.php`
- Create: `app/Models/BadgeAward.php`
- Create: `database/factories/BadgeAwardFactory.php`
- Modify: `app/Models/Profile.php` — add `badgeAwards()` and `badges()` relationships

### Step 1: Create badge_awards migration

Run: `php artisan make:migration create_badge_awards_table --no-interaction`

```php
Schema::create('badge_awards', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('badge_id')->constrained('badges')->cascadeOnDelete();
    $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
    $table->timestamp('awarded_at');
    $table->timestamps();

    $table->unique(['badge_id', 'profile_id']);
});
```

### Step 2: Create BadgeAward Model

```php
// app/Models/BadgeAward.php
- HasFactory, HasUuids
- fillable: badge_id, profile_id, awarded_at
- casts: awarded_at → datetime
- badge(): BelongsTo Badge
- profile(): BelongsTo Profile
```

### Step 3: Create BadgeAwardFactory

```php
// database/factories/BadgeAwardFactory.php
- Default: badge_id from Badge::factory(), profile_id from Profile::factory()->attendee(), awarded_at=now()
```

### Step 4: Add relationships to Profile model

In `app/Models/Profile.php`, add:
```php
public function badgeAwards(): HasMany
{
    return $this->hasMany(BadgeAward::class);
}

public function badges(): BelongsToMany
{
    return $this->belongsToMany(Badge::class, 'badge_awards')
        ->withPivot('awarded_at')
        ->withTimestamps();
}
```

### Step 5: Commit

```
feat: add BadgeAward model with migration, factory, and Profile relationships
```

---

## Task 3: BadgeService + Auto-Award Hooks

**Files:**
- Create: `app/Services/BadgeService.php`
- Modify: `app/Services/CheckinService.php` — add badge check after check-in
- Modify: `app/Services/ChallengeCompletionService.php` — add badge check after verify
- Modify: `app/Services/SpinWheelService.php` — add badge check after win
- Create: `tests/Feature/Api/V1/BadgeAwardingTest.php`

### Step 1: Create BadgeService

```php
// app/Services/BadgeService.php
class BadgeService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Check all milestone conditions and award any earned badges.
     * Returns array of newly awarded badges.
     *
     * @return array<Badge>
     */
    public function checkAndAwardBadges(Profile $profile): array
    {
        if (!$profile->isAttendee() || !$profile->attendeeProfile) {
            return [];
        }

        $awarded = [];
        $existingBadgeTypes = $profile->badgeAwards()
            ->join('badges', 'badges.id', '=', 'badge_awards.badge_id')
            ->pluck('badges.milestone_type')
            ->toArray();

        $badges = Badge::all();

        foreach ($badges as $badge) {
            if (in_array($badge->milestone_type->value, $existingBadgeTypes, true)) {
                continue; // Already has this badge
            }

            if ($this->isMilestoneReached($profile, $badge)) {
                BadgeAward::create([
                    'badge_id' => $badge->id,
                    'profile_id' => $profile->id,
                    'awarded_at' => now(),
                ]);
                $awarded[] = $badge;

                // Send notification
                $this->notificationService->createNotification(
                    recipient: $profile,
                    type: NotificationType::BadgeAwarded,
                    title: 'Badge Earned!',
                    body: "You earned the \"{$badge->name}\" badge!",
                    targetId: $badge->id,
                    targetType: 'badge',
                );
            }
        }

        return $awarded;
    }

    private function isMilestoneReached(Profile $profile, Badge $badge): bool
    {
        $ap = $profile->attendeeProfile;

        return match ($badge->milestone_type) {
            BadgeMilestoneType::FirstCheckin => $ap->total_events_attended >= 1,
            BadgeMilestoneType::FirstChallenge => $ap->total_challenges_completed >= 1,
            BadgeMilestoneType::SocialButterfly => $this->getUniqueVerifierCount($profile) >= $badge->milestone_value,
            BadgeMilestoneType::ChallengeMaster => $ap->total_challenges_completed >= $badge->milestone_value,
            BadgeMilestoneType::EventGuru => $ap->total_events_attended >= $badge->milestone_value,
            BadgeMilestoneType::PointHunter => $ap->total_points >= $badge->milestone_value,
            BadgeMilestoneType::Legend => $ap->total_points >= $badge->milestone_value,
            BadgeMilestoneType::RewardCollector => $this->getRewardsWonCount($profile) >= $badge->milestone_value,
            BadgeMilestoneType::LoyalAttendee => $ap->total_events_attended >= $badge->milestone_value,
        };
    }

    private function getUniqueVerifierCount(Profile $profile): int
    {
        return ChallengeCompletion::query()
            ->where('challenger_profile_id', $profile->id)
            ->where('status', ChallengeCompletionStatus::Verified)
            ->distinct('verifier_profile_id')
            ->count('verifier_profile_id');
    }

    private function getRewardsWonCount(Profile $profile): int
    {
        return RewardClaim::query()
            ->where('profile_id', $profile->id)
            ->count();
    }
}
```

### Step 2: Add BadgeAwarded to NotificationType enum

In `app/Enums/NotificationType.php`, add:
```php
case BadgeAwarded = 'badge_awarded';
case ChallengeVerified = 'challenge_verified';
case RewardWon = 'reward_won';
```

### Step 3: Hook into CheckinService

In `app/Services/CheckinService.php`:
- Inject `BadgeService` via constructor
- After the `increment('total_events_attended')` line, call `$this->badgeService->checkAndAwardBadges($profile)`

### Step 4: Hook into ChallengeCompletionService

In `app/Services/ChallengeCompletionService.php`:
- Inject `BadgeService` via constructor
- After incrementing stats in `verify()`, call `$this->badgeService->checkAndAwardBadges($challengerProfile)`
- Also add notification: `notificationService->createNotification(...)` for ChallengeVerified

### Step 5: Hook into SpinWheelService

In `app/Services/SpinWheelService.php`:
- Inject `BadgeService` via constructor
- After successful win (reward_claim created), call `$this->badgeService->checkAndAwardBadges($profile)`
- Also add notification for RewardWon

### Step 6: Write tests

Create `tests/Feature/Api/V1/BadgeAwardingTest.php` with these tests:
- `test_first_checkin_awards_badge` — check-in → badge awarded
- `test_first_challenge_awards_badge` — verify completion → badge awarded
- `test_events_attended_5_awards_loyal_badge` — 5 check-ins → badge
- `test_challenges_completed_50_awards_master_badge` — set total_challenges_completed=49, verify → badge
- `test_points_500_awards_point_hunter_badge` — set total_points=495, verify 5pt challenge → badge
- `test_rewards_won_10_awards_collector_badge` — create 9 claims, win 10th → badge
- `test_badge_not_awarded_twice` — already has badge, milestone met again → no duplicate
- `test_badge_awards_create_notification` — badge awarded → notification created
- `test_non_attendee_does_not_get_badges` — business user → no badges

### Step 7: Run tests

Run: `php artisan test --compact tests/Feature/Api/V1/BadgeAwardingTest.php`

### Step 8: Commit

```
feat: add BadgeService with auto-awarding hooks in check-in, challenge, and spin services
```

---

## Task 4: Badge API Endpoints + Tests

**Files:**
- Create: `app/Http/Controllers/Api/V1/BadgeController.php`
- Create: `app/Http/Resources/Api/V1/BadgeResource.php`
- Create: `app/Http/Resources/Api/V1/BadgeAwardResource.php`
- Modify: `routes/api.php` — add badge routes
- Create: `tests/Feature/Api/V1/BadgeTest.php`

### Step 1: Create BadgeResource

```php
// app/Http/Resources/Api/V1/BadgeResource.php
return [
    'id' => $this->id,
    'name' => $this->name,
    'description' => $this->description,
    'icon' => $this->icon,
    'milestone_type' => $this->milestone_type->value,
    'milestone_value' => $this->milestone_value,
];
```

### Step 2: Create BadgeAwardResource

```php
// app/Http/Resources/Api/V1/BadgeAwardResource.php
return [
    'id' => $this->id,
    'badge' => new BadgeResource($this->whenLoaded('badge')),
    'awarded_at' => $this->awarded_at?->toIso8601String(),
];
```

### Step 3: Create BadgeController

```php
// app/Http/Controllers/Api/V1/BadgeController.php
class BadgeController extends Controller
{
    /**
     * List all system badges.
     * GET /api/v1/badges
     */
    public function index(): JsonResponse
    {
        $badges = Badge::all();

        return response()->json([
            'success' => true,
            'data' => ['badges' => BadgeResource::collection($badges)],
        ]);
    }

    /**
     * List the authenticated user's awarded badges.
     * GET /api/v1/me/badges
     */
    public function myBadges(Request $request): JsonResponse
    {
        $profile = $request->user();

        $awards = BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['badges' => BadgeAwardResource::collection($awards)],
        ]);
    }
}
```

### Step 4: Add routes

In `routes/api.php`, inside the `auth:sanctum` group, add section:
```php
/*
|--------------------------------------------------------------------------
| Gamification - Badges
|--------------------------------------------------------------------------
*/

// List all system badges
Route::get('badges', [BadgeController::class, 'index'])
    ->name('api.v1.badges.index');

// My awarded badges
Route::get('me/badges', [BadgeController::class, 'myBadges'])
    ->name('api.v1.me.badges');
```

### Step 5: Write tests

Create `tests/Feature/Api/V1/BadgeTest.php`:
- `test_list_all_badges_returns_system_badges` — seed badges, GET /badges → returns all
- `test_my_badges_returns_awarded_badges` — create awards, GET /me/badges → returns awards with badge data
- `test_my_badges_returns_empty_when_none` — GET /me/badges → empty
- `test_badges_include_correct_structure` — assert JSON structure
- `test_unauthenticated_returns_401_for_badges` — 401
- `test_unauthenticated_returns_401_for_my_badges` — 401

### Step 6: Run tests

Run: `php artisan test --compact tests/Feature/Api/V1/BadgeTest.php`

### Step 7: Commit

```
feat: add badge listing and my-badges API endpoints
```

---

## Task 5: Event Discovery Service + Controller + Route + Tests

**Files:**
- Create: `app/Services/EventDiscoveryService.php`
- Create: `app/Http/Controllers/Api/V1/EventDiscoveryController.php`
- Create: `app/Http/Requests/Api/V1/DiscoverEventsRequest.php`
- Modify: `routes/api.php` — add discovery route
- Create: `tests/Feature/Api/V1/EventDiscoveryTest.php`

### Step 1: Create EventDiscoveryService

Uses Haversine formula in SQL for distance-based filtering on PostgreSQL:

```php
// app/Services/EventDiscoveryService.php
class EventDiscoveryService
{
    /**
     * Find active events near a given latitude/longitude within a radius (km).
     *
     * @return LengthAwarePaginator<Event>
     */
    public function discoverNearby(
        float $lat,
        float $lng,
        float $radiusKm = 50.0,
        int $perPage = 10
    ): LengthAwarePaginator {
        // Haversine formula for distance in km
        $haversine = "(
            6371 * acos(
                cos(radians(?)) * cos(radians(location_lat)) *
                cos(radians(location_lng) - radians(?)) +
                sin(radians(?)) * sin(radians(location_lat))
            )
        )";

        return Event::query()
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->where('is_active', true)
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat])
            ->havingRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km')
            ->with(['photos', 'profile'])
            ->paginate($perPage);
    }
}
```

### Step 2: Create DiscoverEventsRequest

```php
// app/Http/Requests/Api/V1/DiscoverEventsRequest.php
public function rules(): array
{
    return [
        'lat' => ['required', 'numeric', 'between:-90,90'],
        'lng' => ['required', 'numeric', 'between:-180,180'],
        'radius' => ['sometimes', 'numeric', 'min:1', 'max:200'],
        'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
    ];
}
```

### Step 3: Create EventDiscoveryController

```php
// app/Http/Controllers/Api/V1/EventDiscoveryController.php
class EventDiscoveryController extends Controller
{
    public function __construct(
        private readonly EventDiscoveryService $discoveryService
    ) {}

    /**
     * Discover nearby active events.
     * GET /api/v1/events/discover?lat=&lng=&radius=&limit=
     */
    public function __invoke(DiscoverEventsRequest $request): JsonResponse
    {
        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');
        $radius = (float) ($request->validated('radius') ?? 50);
        $perPage = (int) ($request->validated('limit') ?? 10);

        $paginator = $this->discoveryService->discoverNearby($lat, $lng, $radius, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => EventResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
```

Note: Use existing `EventResource` if available, or create a simple one.

### Step 4: Add route

In `routes/api.php`, inside `auth:sanctum` group, add before the events CRUD routes:
```php
// Event Discovery
Route::get('events/discover', EventDiscoveryController::class)
    ->name('api.v1.events.discover');
```

**IMPORTANT:** This route MUST be placed BEFORE `Route::get('events/{event}', ...)` to avoid the `{event}` parameter catching "discover" as an ID.

### Step 5: Check if EventResource exists

If `app/Http/Resources/Api/V1/EventResource.php` already exists, use it. If not, create one:
```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'event_date' => $this->event_date?->toDateString(),
    'location_lat' => $this->location_lat,
    'location_lng' => $this->location_lng,
    'address' => $this->address,
    'is_active' => $this->is_active,
    'attendee_count' => $this->attendee_count,
    'distance_km' => $this->distance_km ?? null,
    'photos' => EventPhotoResource::collection($this->whenLoaded('photos')),
    'profile' => new ProfileResource($this->whenLoaded('profile')),
];
```

### Step 6: Write tests

Create `tests/Feature/Api/V1/EventDiscoveryTest.php`:
- `test_discover_returns_nearby_active_events` — create events with lat/lng near a point, request discover → returns nearby
- `test_discover_excludes_inactive_events` — inactive events not returned
- `test_discover_excludes_events_without_location` — events without lat/lng excluded
- `test_discover_excludes_events_outside_radius` — events far away excluded
- `test_discover_orders_by_distance` — closest events first
- `test_discover_respects_limit_parameter` — limit works
- `test_discover_validates_required_parameters` — missing lat/lng → 422
- `test_discover_validates_lat_lng_ranges` — lat=999 → 422
- `test_discover_defaults_radius_to_50km` — no radius param → uses 50km default
- `test_unauthenticated_returns_401` — 401

### Step 7: Run tests

Run: `php artisan test --compact tests/Feature/Api/V1/EventDiscoveryTest.php`

### Step 8: Commit

```
feat: add map-based event discovery with Haversine distance filtering
```

---

## Task 6: Gamification Stats + Game Card API + Tests

**Files:**
- Create: `app/Services/GamificationStatsService.php`
- Create: `app/Http/Controllers/Api/V1/GamificationStatsController.php`
- Modify: `routes/api.php` — add stats routes
- Create: `tests/Feature/Api/V1/GamificationStatsTest.php`

### Step 1: Create GamificationStatsService

```php
// app/Services/GamificationStatsService.php
class GamificationStatsService
{
    /**
     * Get gamification stats for an attendee profile.
     *
     * @return array{total_points: int, total_challenges_completed: int, total_events_attended: int, global_rank: int|null, badges_count: int, rewards_count: int}
     */
    public function getStats(Profile $profile): array
    {
        $ap = $profile->attendeeProfile;

        if (!$ap) {
            return [
                'total_points' => 0,
                'total_challenges_completed' => 0,
                'total_events_attended' => 0,
                'global_rank' => null,
                'badges_count' => 0,
                'rewards_count' => 0,
            ];
        }

        return [
            'total_points' => $ap->total_points,
            'total_challenges_completed' => $ap->total_challenges_completed,
            'total_events_attended' => $ap->total_events_attended,
            'global_rank' => $ap->global_rank,
            'badges_count' => $profile->badgeAwards()->count(),
            'rewards_count' => $profile->rewardClaims()->count(),
        ];
    }

    /**
     * Get the game card view for a profile (public view).
     *
     * @return array{profile: array, stats: array, recent_badges: Collection}
     */
    public function getGameCard(Profile $profile): array
    {
        $stats = $this->getStats($profile);

        $recentBadges = BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->limit(5)
            ->get();

        return [
            'profile' => [
                'id' => $profile->id,
                'email' => $profile->email,
                'avatar_url' => $profile->avatar_url,
                'user_type' => $profile->user_type->value,
            ],
            'stats' => $stats,
            'recent_badges' => $recentBadges,
        ];
    }
}
```

### Step 2: Create GamificationStatsController

```php
// app/Http/Controllers/Api/V1/GamificationStatsController.php
class GamificationStatsController extends Controller
{
    public function __construct(
        private readonly GamificationStatsService $statsService
    ) {}

    /**
     * Get gamification stats for the authenticated user.
     * GET /api/v1/me/gamification-stats
     */
    public function myStats(Request $request): JsonResponse
    {
        $profile = $request->user();
        $stats = $this->statsService->getStats($profile);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get the game card for a profile (public).
     * GET /api/v1/profiles/{profile}/game-card
     */
    public function gameCard(Profile $profile): JsonResponse
    {
        $data = $this->statsService->getGameCard($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $data['profile'],
                'stats' => $data['stats'],
                'recent_badges' => BadgeAwardResource::collection($data['recent_badges']),
            ],
        ]);
    }
}
```

### Step 3: Add routes

In `routes/api.php`, inside `auth:sanctum` group:
```php
/*
|--------------------------------------------------------------------------
| Gamification - Stats & Game Card
|--------------------------------------------------------------------------
*/

// My gamification stats
Route::get('me/gamification-stats', [GamificationStatsController::class, 'myStats'])
    ->name('api.v1.me.gamification-stats');

// Public game card for a profile
Route::get('profiles/{profile}/game-card', [GamificationStatsController::class, 'gameCard'])
    ->name('api.v1.profiles.game-card');
```

### Step 4: Write tests

Create `tests/Feature/Api/V1/GamificationStatsTest.php`:
- `test_my_stats_returns_attendee_stats` — attendee with stats → correct data
- `test_my_stats_returns_zero_for_new_attendee` — fresh attendee → all zeros
- `test_my_stats_returns_zero_for_non_attendee` — business user → zero stats
- `test_my_stats_includes_badges_count` — create awards → count matches
- `test_my_stats_includes_rewards_count` — create claims → count matches
- `test_game_card_returns_public_profile_data` — correct structure
- `test_game_card_includes_recent_badges` — 5 most recent badges
- `test_game_card_limits_to_5_badges` — 7 awards → only 5 returned
- `test_unauthenticated_returns_401_for_my_stats` — 401
- `test_unauthenticated_returns_401_for_game_card` — 401

### Step 5: Run tests

Run: `php artisan test --compact tests/Feature/Api/V1/GamificationStatsTest.php`

### Step 6: Commit

```
feat: add gamification stats and public game card API endpoints
```

---

## Task 7: Gamification Notifications in Existing Services + Tests

**Files:**
- Modify: `app/Enums/NotificationType.php` — add new cases (if not done in Task 3)
- Modify: `app/Services/NotificationService.php` — add gamification notify methods
- Modify: `app/Services/ChallengeCompletionService.php` — add ChallengeVerified notification
- Modify: `app/Services/SpinWheelService.php` — add RewardWon notification
- Create: `tests/Feature/Api/V1/GamificationNotificationTest.php`

### Step 1: Ensure NotificationType has new cases

If not already added in Task 3:
```php
case BadgeAwarded = 'badge_awarded';
case ChallengeVerified = 'challenge_verified';
case RewardWon = 'reward_won';
```

### Step 2: Add gamification notification methods to NotificationService

```php
/**
 * Notify when a challenge is verified (awarded to the challenger).
 */
public function notifyChallengeVerified(ChallengeCompletion $completion): void
{
    $completion->loadMissing(['challenge', 'challenger', 'verifier']);

    $this->createNotification(
        recipient: $completion->challenger,
        type: NotificationType::ChallengeVerified,
        title: 'Challenge Verified!',
        body: "Your \"{$completion->challenge->name}\" challenge was verified. You earned {$completion->points_earned} points!",
        actor: $completion->verifier,
        targetId: $completion->id,
        targetType: 'challenge_completion',
    );
}

/**
 * Notify when a reward is won from spin-the-wheel.
 */
public function notifyRewardWon(RewardClaim $claim): void
{
    $claim->loadMissing(['eventReward', 'profile']);

    $this->createNotification(
        recipient: $claim->profile,
        type: NotificationType::RewardWon,
        title: 'You Won a Reward!',
        body: "You won \"{$claim->eventReward->name}\" from spin-the-wheel!",
        targetId: $claim->id,
        targetType: 'reward_claim',
    );
}
```

### Step 3: Hook into ChallengeCompletionService

In `verify()`, after the DB transaction, add:
```php
$this->notificationService->notifyChallengeVerified($completion);
```

Inject `NotificationService` in constructor.

### Step 4: Hook into SpinWheelService

In `spin()`, after a win (when $claim is not null), add:
```php
$this->notificationService->notifyRewardWon($claim);
```

Inject `NotificationService` in constructor.

### Step 5: Write tests

Create `tests/Feature/Api/V1/GamificationNotificationTest.php`:
- `test_challenge_verification_creates_notification` — verify → notification of type challenge_verified
- `test_reward_won_creates_notification` — spin win → notification of type reward_won
- `test_badge_awarded_creates_notification` — badge award → notification of type badge_awarded (covered by Task 3 tests but verify here too)
- `test_challenge_reject_does_not_create_notification` — reject → no notification
- `test_spin_no_win_does_not_create_notification` — spin miss → no notification

### Step 6: Run tests

Run: `php artisan test --compact tests/Feature/Api/V1/GamificationNotificationTest.php`

### Step 7: Commit

```
feat: add gamification notifications for challenge verification and reward wins
```

---

## Task 8: Run Pint + Full Test Suite

### Step 1: Run Pint

Run: `vendor/bin/pint --dirty`

### Step 2: Run full test suite

Run: `php artisan test --compact`

All tests must pass.

### Step 3: Commit if Pint made changes

```
style: apply Pint formatting
```
