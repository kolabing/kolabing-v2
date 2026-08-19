# Community Members & Tiers Web Panel (BE-NF-29) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give a Community Leader a working web panel (`app.kolabing.com/community`) to manage members — a findable, sortable, engagement-enriched roster with two working inlets for new members (email invitations + a live `/c/{slug}` join page) — plus the tier/economy levers that move that data.

**Architecture:** All backend work is additive on the existing NF-6 tables (`communities`, `community_tiers`, `community_members`, `community_points`, `community_point_ledger`, `event_checkins`) plus **one** new table (`community_invitations`). Roster metrics are resolved with LEFT-JOINed aggregate subqueries so a page costs O(1) queries, not O(N) — the pattern `CommunityResource` already established with its preloaded-attribute fast path. The frontend is Blade + Alpine + the existing inline `window.kb` client on the app host; no npm/Vite change.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL (SQLite in tests), PHPUnit 11, Blade, Alpine 3 (self-hosted), Tailwind (CDN-configured in the webapp layout), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-08-19-community-members-web-panel-design.md`

---

## Non-negotiable rules for every task

1. **§8.4 — NEVER paywall this surface.** No file you touch may call `Profile::hasActiveSubscription()`, `->hasActiveSubscription()`, or any subscription gate. Authorization is `CommunityPolicy@manage` (owner OR active member with `can_manage`) and nothing else.
2. **Tests use `LazilyRefreshDatabase`** (not `RefreshDatabase`, not `DatabaseTransactions`). See `tests/Feature/Api/V1/CommunityMemberRosterTest.php`.
3. **`Profile` is the authenticatable model**, not `User`. Authorization reads `$profile->cannot('manage', $community)`.
4. **Never run migrations against the default env** — the local `.env` points at the production database. Every artisan/test command in this plan that touches the DB must pass `DB_CONNECTION=sqlite DB_DATABASE=:memory:` explicitly, exactly as written.
5. **Run `vendor/bin/pint` before every commit.**
6. Response envelope is `{"success": true, "data": …}`. Errors add `"error": "<snake_code>"` and a translated `"message"`.

---

## File Structure

**New backend files**

| File | Responsibility |
|---|---|
| `database/migrations/2026_08_19_000001_create_community_invitations_table.php` | the one new table |
| `app/Enums/CommunityInvitationStatus.php` | `pending` \| `accepted` \| `revoked` \| `expired` |
| `app/Models/CommunityInvitation.php` | model + relations + `isClaimable()` |
| `database/factories/CommunityInvitationFactory.php` | test factory + states |
| `app/Services/CommunityRosterQuery.php` | **the roster query object** — filters, sorts, metric joins. Extracted so `CommunityMemberService` does not grow a 200-line method. |
| `app/Services/CommunityStatsService.php` | the aggregate figures behind `GET /stats` |
| `app/Services/CommunityInvitationService.php` | invitation lifecycle + claim-on-register |
| `app/Http/Controllers/Api/V1/CommunityStatsController.php` | `GET /communities/{c}/stats` |
| `app/Http/Controllers/Api/V1/CommunityInvitationController.php` | invitation index/store/resend/revoke/accept |
| `app/Http/Requests/Api/V1/StoreCommunityInvitationRequest.php` | `email` \| `emails[]` + `tier_id` |
| `app/Http/Requests/Api/V1/BulkUpdateCommunityMembersRequest.php` | bulk roster edit |
| `app/Http/Resources/Api/V1/CommunityInvitationResource.php` | invitation payload |
| `app/Mail/CommunityInvitationMail.php` + `resources/views/mail/community-invitation.blade.php` | queued invite email |
| `app/Http/Controllers/CommunityJoinPageController.php` | public `/c/{slug}` landing (web, not api) |
| `resources/views/pages/community-join.blade.php` | the landing page |

**Modified backend files**

| File | Change |
|---|---|
| `app/Services/CommunityMemberService.php` | `roster()` delegates to `CommunityRosterQuery`; add `bulkUpdate()` |
| `app/Http/Controllers/Api/V1/CommunityMemberController.php` | `index()` passes filters; add `show()` + `bulkUpdate()` |
| `app/Http/Resources/Api/V1/CommunityMemberResource.php` | emit `email`, `handle`, metrics; fix display-name fallback |
| `routes/api.php` | new routes in the NF-6 block |
| `routes/web.php` | `/c/{slug}` + the 7 `/community/*` webapp routes |
| `config/communities.php` | `invitation_ttl_days` |
| `app/Services/OnboardingService.php` | claim-on-register hook |

**New frontend files**

| File | Responsibility |
|---|---|
| `resources/views/webapp/community.blade.php` | Overview |
| `resources/views/webapp/community-members.blade.php` | Roster (the workspace) |
| `resources/views/webapp/community-requests.blade.php` | Join requests + invitations |
| `resources/views/webapp/community-tiers.blade.php` | Tier editor |
| `resources/views/webapp/community-economy.blade.php` | Goals / Rewards / Badges |
| `resources/views/webapp/community-leaderboard.blade.php` | Points leaderboard |
| `resources/views/webapp/community-settings.blade.php` | Community settings + invite link |
| `resources/views/webapp/partials/community-nav.blade.php` | tab strip + community switcher |
| `resources/views/webapp/partials/community-modals.blade.php` | add-member, invite, member drawer, tier form |

---

## The five defects this plan fixes

Referenced by id throughout. Each has a task that closes it.

- **D1** Roster has no search/filter/sort — `CommunityMemberService::roster()` is `orderBy('created_at')->paginate()`. → Task 2
- **D2** `roster()` never filters `status`, so soft-removed members render as members. → Task 2
- **D3** `POST /communities/{id}/members` 404s for anyone without a Kolabing account. → Tasks 6–10
- **D4** `Community::inviteUrl()` emits `/c/{slug}` but **no such route exists** — every invite link ever shared is dead. → Task 13
- **D5** `CommunityMemberResource::profileDisplayName()` reads `$extended->name`, but `attendee_profiles` **has no `name` column** — and every community member is an attendee. So the roster renders email prefixes ("volkanoluc") instead of real names, even though `profiles.name` exists. → Task 1

---

## Task 1: Fix the roster display name (D5)

`profiles.name` and `profiles.handle` were added in `2026_06_10_040000_add_name_to_profiles_table.php` / `..._070000_add_handle_...`, but `CommunityMemberResource` never reads them. Fix it first — every later screenshot depends on names being real.

**Files:**
- Modify: `app/Http/Resources/Api/V1/CommunityMemberResource.php`
- Test: `tests/Feature/Api/V1/CommunityMemberResourceNameTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/V1/CommunityMemberResourceNameTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityMemberResourceNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roster_uses_the_profile_name_for_an_attendee_member(): void
    {
        // attendee_profiles has no `name` column, so the old resource fell back
        // to the email prefix for every community member. profiles.name is the
        // real source.
        $community = Community::factory()->create();
        $member = Profile::factory()->attendee()->create([
            'name' => 'Volkan Oluc',
            'handle' => 'volkan',
            'email' => 'volkanoluc@example.com',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
        ]);

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.profile.name', 'Volkan Oluc')
            ->assertJsonPath('data.members.0.profile.handle', 'volkan');
    }

    public function test_display_name_falls_back_to_the_email_prefix_when_nothing_is_set(): void
    {
        $community = Community::factory()->create();
        $member = Profile::factory()->attendee()->create([
            'name' => null,
            'handle' => null,
            'email' => 'nameless@example.com',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
        ]);

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.profile.name', 'nameless');
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityMemberResourceNameTest.php
```

Expected: FAIL — first test gets `volkanoluc`, and `profile.handle` is missing.

- [ ] **Step 3: Fix the resource**

In `app/Http/Resources/Api/V1/CommunityMemberResource.php`, replace the `profile` block and `profileDisplayName()`:

```php
            'profile' => $this->whenLoaded('profile', fn () => [
                'name' => $this->profileDisplayName(),
                'handle' => $this->profile?->handle,
                'email' => $this->profile?->email,
                'avatar_url' => $this->profileAvatarUrl(),
            ]),
```

```php
    /**
     * profiles.name is the canonical display name (set at onboarding for every
     * user type). attendee_profiles carries no name column at all, so the old
     * extended-profile-first order rendered every community member as their
     * email prefix. Extended names stay as a fallback for business/community
     * profiles created before profiles.name existed.
     */
    private function profileDisplayName(): ?string
    {
        if (filled($this->profile?->name)) {
            return $this->profile->name;
        }

        $extended = $this->profile?->getExtendedProfile();

        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return $this->profile ? Str::before($this->profile->email, '@') : null;
    }
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityMemberResourceNameTest.php
```

- [ ] **Step 5: Check nothing else asserted the old behaviour**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact --filter=Community
```

Expected: all green. If a test asserted an email-prefix name, update it — the old value was a bug.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint
git add app/Http/Resources/Api/V1/CommunityMemberResource.php tests/Feature/Api/V1/CommunityMemberResourceNameTest.php
git commit -m "fix(communities): roster showed email prefixes instead of member names

attendee_profiles has no name column, so profileDisplayName() — which read
the extended profile first — fell through to Str::before(email, '@') for
every community member. profiles.name is the canonical display name; read
it first and emit handle + email alongside it for the web roster.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Roster query object — search, filters, sort (D1 + D2)

`CommunityMemberService::roster()` is `->orderBy('created_at')->paginate()`. Everything the panel needs goes into a dedicated query object so the service does not grow a 200-line method.

**Files:**
- Create: `app/Services/CommunityRosterQuery.php`
- Modify: `app/Services/CommunityMemberService.php`
- Modify: `app/Http/Controllers/Api/V1/CommunityMemberController.php`
- Test: `tests/Feature/Api/V1/CommunityRosterFilterTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/V1/CommunityRosterFilterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityRosterFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        $this->community = Community::factory()->create();
    }

    private function member(array $profile = [], array $membership = []): CommunityMember
    {
        $p = Profile::factory()->attendee()->create($profile);

        return CommunityMember::factory()->create(array_merge([
            'community_id' => $this->community->id,
            'profile_id' => $p->id,
        ], $membership));
    }

    private function roster(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/members".$query);
    }

    public function test_removed_members_are_excluded_by_default(): void
    {
        $this->member(['name' => 'Stays']);
        $this->member(['name' => 'Gone'], ['status' => CommunityMemberStatus::Removed->value]);

        $res = $this->roster()->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Stays', $res->json('data.members.0.profile.name'));
    }

    public function test_status_all_restores_removed_members(): void
    {
        $this->member(['name' => 'Stays']);
        $this->member(['name' => 'Gone'], ['status' => CommunityMemberStatus::Removed->value]);

        $this->assertSame(2, $this->roster('?status=all')->assertOk()->json('data.pagination.total_count'));
    }

    public function test_status_filter_selects_one_status(): void
    {
        $this->member(['name' => 'Active One']);
        $this->member(['name' => 'Inactive One'], ['status' => CommunityMemberStatus::Inactive->value]);

        $res = $this->roster('?status=inactive')->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Inactive One', $res->json('data.members.0.profile.name'));
    }

    public function test_search_matches_name_email_and_handle(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'handle' => 'ada']);
        $this->member(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'handle' => 'grace']);

        $this->assertSame(1, $this->roster('?search=lovelace')->assertOk()->json('data.pagination.total_count'));
        $this->assertSame(1, $this->roster('?search=grace@example')->assertOk()->json('data.pagination.total_count'));
        // A leading @ is stripped so pasting a handle works.
        $this->assertSame(1, $this->roster('?search=@ada')->assertOk()->json('data.pagination.total_count'));
        $this->assertSame(0, $this->roster('?search=nobody')->assertOk()->json('data.pagination.total_count'));
    }

    public function test_tier_filter_and_the_none_bucket(): void
    {
        $tier = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Exec', 'rank' => 3]);
        $this->member(['name' => 'Tiered'], ['tier_id' => $tier->id]);
        $this->member(['name' => 'Untiered'], ['tier_id' => null]);

        $this->assertSame('Tiered', $this->roster("?tier_id={$tier->id}")->assertOk()->json('data.members.0.profile.name'));
        $this->assertSame('Untiered', $this->roster('?tier_id=none')->assertOk()->json('data.members.0.profile.name'));
    }

    public function test_can_manage_filter(): void
    {
        $this->member(['name' => 'Manager'], ['can_manage' => true]);
        $this->member(['name' => 'Plain'], ['can_manage' => false]);

        $res = $this->roster('?can_manage=1')->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Manager', $res->json('data.members.0.profile.name'));
    }

    public function test_sort_by_name_ascending(): void
    {
        $this->member(['name' => 'Zoe']);
        $this->member(['name' => 'Alice']);

        $names = collect($this->roster('?sort=name')->assertOk()->json('data.members'))
            ->pluck('profile.name')->all();

        $this->assertSame(['Alice', 'Zoe'], $names);
    }

    public function test_sort_by_tier_defaults_to_highest_rank_first(): void
    {
        $low = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Pledge', 'rank' => 1]);
        $high = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Exec', 'rank' => 5]);
        $this->member(['name' => 'Low'], ['tier_id' => $low->id]);
        $this->member(['name' => 'High'], ['tier_id' => $high->id]);

        $names = collect($this->roster('?sort=tier')->assertOk()->json('data.members'))
            ->pluck('profile.name')->all();

        $this->assertSame(['High', 'Low'], $names);
    }

    public function test_an_unknown_sort_key_falls_back_to_joined_at_instead_of_erroring(): void
    {
        $this->member(['name' => 'Only']);

        $this->roster('?sort=drop%20table')->assertOk();
    }

    public function test_limit_is_capped_at_100(): void
    {
        $this->member();

        $this->assertSame(100, $this->roster('?limit=5000')->assertOk()->json('data.pagination.per_page'));
    }

    public function test_a_non_manager_cannot_read_the_roster_filters(): void
    {
        $this->member();
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$this->community->id}/members?search=x")
            ->assertForbidden();
    }
}
```

> **Note on the last test:** `CommunityMemberController@index` currently has **no** manage gate — `CommunityPolicy@view` returns `true` for everyone. The roster now carries member emails, so it must be gated. This test drives that change.

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityRosterFilterTest.php
```

Expected: most cases FAIL (filters ignored, removed members present, 200 instead of 403).

- [ ] **Step 3: Create the query object**

Create `app/Services/CommunityRosterQuery.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The community roster query — search, filters, sort, and each member's
 * engagement metrics.
 *
 * Metrics are resolved with LEFT-JOINed aggregate subqueries so one page costs
 * a fixed number of queries regardless of member count (BACKLOG BE-NF-15 flags
 * the O(N)-per-row pattern this class exists to avoid). Grouped aggregates are
 * the documented exception to the "prefer Model::query() over DB::" rule —
 * Eloquent cannot express them without a correlated subquery per row.
 */
class CommunityRosterQuery
{
    /** Accepted sort keys → the column they order by. */
    private const SORTS = [
        'joined_at' => 'community_members.joined_at',
        'name' => 'display_name_value',
        'points' => 'points_value',
        'events_attended' => 'events_attended_value',
        'last_active_at' => 'last_active_value',
        'tier' => 'tier_rank_value',
    ];

    /** Metric sorts read best highest-first. */
    private const DESC_BY_DEFAULT = ['points', 'events_attended', 'last_active_at', 'tier'];

    /**
     * @param  array{search?: string|null, status?: string|null, tier_id?: string|null, can_manage?: bool|null, sort?: string|null, direction?: string|null}  $filters
     */
    public function paginate(Community $community, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->base($community);

        $this->applyStatus($query, $filters['status'] ?? null);
        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyTier($query, $filters['tier_id'] ?? null);
        $this->applyCanManage($query, $filters['can_manage'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * Base query: the membership rows plus every column the filters, the sorts
     * and the resource need, joined once.
     */
    public function base(Community $community): Builder
    {
        $points = DB::table('community_points')
            ->select('profile_id', 'points')
            ->where('community_id', $community->id);

        $checkins = DB::table('event_checkins')
            ->join('events', 'events.id', '=', 'event_checkins.event_id')
            ->where('events.community_id', $community->id)
            ->groupBy('event_checkins.profile_id')
            ->select('event_checkins.profile_id', DB::raw('COUNT(*) as events_attended'));

        $activity = DB::table('community_point_ledger')
            ->where('community_id', $community->id)
            ->groupBy('profile_id')
            ->select('profile_id', DB::raw('MAX(created_at) as last_ledger_at'));

        return $community->members()
            ->join('profiles', 'profiles.id', '=', 'community_members.profile_id')
            ->leftJoin('business_profiles', 'business_profiles.profile_id', '=', 'profiles.id')
            ->leftJoin('community_profiles', 'community_profiles.profile_id', '=', 'profiles.id')
            ->leftJoin('community_tiers', 'community_tiers.id', '=', 'community_members.tier_id')
            ->leftJoinSub($points, 'cp', 'cp.profile_id', '=', 'community_members.profile_id')
            ->leftJoinSub($checkins, 'ec', 'ec.profile_id', '=', 'community_members.profile_id')
            ->leftJoinSub($activity, 'al', 'al.profile_id', '=', 'community_members.profile_id')
            ->with([
                'tier',
                'profile.attendeeProfile',
                'profile.businessProfile',
                'profile.communityProfile',
            ])
            ->select('community_members.*')
            ->selectRaw('COALESCE(cp.points, 0) as points_value')
            ->selectRaw('COALESCE(ec.events_attended, 0) as events_attended_value')
            ->selectRaw('COALESCE(al.last_ledger_at, community_members.joined_at) as last_active_value')
            ->selectRaw('COALESCE(community_tiers.rank, 0) as tier_rank_value')
            ->selectRaw(
                "COALESCE(NULLIF(profiles.name, ''), NULLIF(business_profiles.name, ''), "
                ."NULLIF(community_profiles.name, ''), profiles.email) as display_name_value"
            );
    }

    /**
     * A soft-removed member is not a member (D2). The default set is
     * active + inactive; ?status=all restores the pre-fix behaviour.
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === 'all') {
            return;
        }

        if ($status !== null && in_array($status, CommunityMemberStatus::values(), true)) {
            $query->where('community_members.status', $status);

            return;
        }

        $query->whereIn('community_members.status', [
            CommunityMemberStatus::Active->value,
            CommunityMemberStatus::Inactive->value,
        ]);
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return;
        }

        // A pasted @handle searches the handle without its marker.
        $needle = '%'.mb_strtolower(ltrim($search, '@')).'%';

        $query->where(function (Builder $inner) use ($needle): void {
            $inner->whereRaw('LOWER(profiles.email) LIKE ?', [$needle])
                ->orWhereRaw("LOWER(COALESCE(profiles.handle, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(profiles.name, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(business_profiles.name, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(community_profiles.name, '')) LIKE ?", [$needle]);
        });
    }

    private function applyTier(Builder $query, ?string $tierId): void
    {
        if ($tierId === null || $tierId === '') {
            return;
        }

        if ($tierId === 'none') {
            $query->whereNull('community_members.tier_id');

            return;
        }

        $query->where('community_members.tier_id', $tierId);
    }

    private function applyCanManage(Builder $query, ?bool $canManage): void
    {
        if ($canManage === null) {
            return;
        }

        $query->where('community_members.can_manage', $canManage);
    }

    /**
     * An unknown sort key falls back to joined_at rather than reaching the
     * database — the key is never interpolated, only looked up in self::SORTS.
     */
    private function applySort(Builder $query, ?string $sort, ?string $direction): void
    {
        $key = array_key_exists((string) $sort, self::SORTS) ? (string) $sort : 'joined_at';

        $direction = in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : (in_array($key, self::DESC_BY_DEFAULT, true) ? 'desc' : 'asc');

        $query->orderBy(self::SORTS[$key], $direction)
            // Stable pagination: ties must not reshuffle between pages.
            ->orderBy('community_members.id');
    }
}
```

- [ ] **Step 4: Point the service at it**

In `app/Services/CommunityMemberService.php`, add the dependency and replace `roster()`:

```php
    public function __construct(
        private readonly MissionService $missionService,
        private readonly CommunityRosterQuery $rosterQuery,
    ) {}
```

```php
    /**
     * Paginated roster with nested tier + profile and per-member engagement
     * metrics, filtered/sorted by the caller. See CommunityRosterQuery.
     *
     * @param  array<string, mixed>  $filters
     */
    public function roster(Community $community, int $perPage = 25, array $filters = []): LengthAwarePaginator
    {
        return $this->rosterQuery->paginate($community, $filters, $perPage);
    }
```

Keep the `use Illuminate\Contracts\Pagination\LengthAwarePaginator;` import that is already there.

- [ ] **Step 5: Parse the filters in the controller and gate it**

In `app/Http/Controllers/Api/V1/CommunityMemberController.php`, replace `index()`:

```php
    /**
     * GET /api/v1/communities/{community}/members
     *
     * Paginated roster with nested tier + profile + engagement metrics.
     * Manage-gated: the payload carries member emails, so it is not a public
     * roster. Filters: search, status, tier_id, can_manage, sort, direction.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $perPage = min(max((int) $request->query('limit', '25'), 1), 100);

        $canManage = $request->query('can_manage');

        $paginator = $this->memberService->roster($community, $perPage, [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'tier_id' => $request->query('tier_id'),
            'can_manage' => $canManage === null ? null : filter_var($canManage, FILTER_VALIDATE_BOOL),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'members' => CommunityMemberResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
```

- [ ] **Step 6: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityRosterFilterTest.php
```

- [ ] **Step 7: Run every community test — the gate is a behaviour change**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact --filter=Community
```

Expected: green. If an existing test read the roster as a non-manager and expected 200, change it to act as the owner — the roster now carries emails and must be manage-gated.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint
git add app/Services/CommunityRosterQuery.php app/Services/CommunityMemberService.php app/Http/Controllers/Api/V1/CommunityMemberController.php tests/Feature/Api/V1/CommunityRosterFilterTest.php
git commit -m "feat(communities): searchable, filterable, sortable member roster

GET /communities/{id}/members was orderBy(created_at)->paginate() with no
search, filter or sort, and it never filtered status — so soft-removed
members rendered as members. Adds CommunityRosterQuery: search (name/email/
handle), status (default active+inactive, ?status=all opts back in), tier
(incl. a 'none' bucket), can_manage, and six sort keys, all resolved in one
query via left-joined aggregate subqueries. The roster is now manage-gated:
it carries member emails.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Emit the engagement metrics, and prove there is no N+1

The joins from Task 2 already put `points_value`, `events_attended_value`, `last_active_value` on each model. Now surface them — and lock the query count with a test, because this is exactly the regression BE-NF-15 exists to prevent.

**Files:**
- Modify: `app/Http/Resources/Api/V1/CommunityMemberResource.php`
- Test: `tests/Feature/Api/V1/CommunityRosterMetricsTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/V1/CommunityRosterMetricsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityRosterMetricsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roster_reports_points_events_attended_last_active_and_tenure(): void
    {
        $community = Community::factory()->create();
        $profile = Profile::factory()->attendee()->create(['name' => 'Ada']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'joined_at' => now()->subDays(10),
        ]);

        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'points' => 340,
        ]);

        // Two check-ins on THIS community's events, one on someone else's —
        // only the community's own events count (ROLES §8.6).
        $ours = Event::factory()->create(['community_id' => $community->id]);
        $alsoOurs = Event::factory()->create(['community_id' => $community->id]);
        $theirs = Event::factory()->create(['community_id' => null]);

        foreach ([$ours, $alsoOurs, $theirs] as $event) {
            EventCheckin::query()->create([
                'event_id' => $event->id,
                'profile_id' => $profile->id,
                'checked_in_at' => now()->subDays(2),
            ]);
        }

        CommunityPointLedger::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'points' => 10,
            'source' => 'event_check_in',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $row = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->json('data.members.0');

        $this->assertSame(340, $row['points']);
        $this->assertSame(2, $row['events_attended']);
        $this->assertSame(10, $row['tenure_days']);
        $this->assertNotNull($row['last_active_at']);
    }

    public function test_a_member_with_no_activity_reports_zeroes_and_falls_back_to_joined_at(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subDays(3),
        ]);

        $row = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->json('data.members.0');

        $this->assertSame(0, $row['points']);
        $this->assertSame(0, $row['events_attended']);
        $this->assertSame(3, $row['tenure_days']);
        $this->assertSame(
            $member->joined_at->toIso8601String(),
            $row['last_active_at'],
        );
    }

    public function test_the_roster_costs_the_same_number_of_queries_at_any_size(): void
    {
        // BACKLOG BE-NF-15: list endpoints in this codebase have a habit of
        // going O(N) per row. Lock it: 3 members and 30 members must cost the
        // same number of queries.
        $small = Community::factory()->create();
        CommunityMember::factory()->count(3)->create(['community_id' => $small->id]);

        $large = Community::factory()->create();
        CommunityMember::factory()->count(30)->create(['community_id' => $large->id]);

        $count = function (Community $community): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($community->owner)
                ->getJson("/api/v1/communities/{$community->id}/members?limit=100")
                ->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($small), $count($large));
    }
}
```

> If `EventCheckin` / `CommunityPoints` / `CommunityPointLedger` have factories, use them instead of `::query()->create()`. Check with `ls database/factories | grep -i "checkin\|point"` — `EventCheckinFactory` exists; the points models may not have one, hence the explicit creates above.

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityRosterMetricsTest.php
```

Expected: FAIL — `points`, `events_attended`, `tenure_days`, `last_active_at` are not in the payload.

- [ ] **Step 3: Emit the metrics from the resource**

In `app/Http/Resources/Api/V1/CommunityMemberResource.php`, add `use Illuminate\Support\Carbon;` and spread the metric block into `toArray()` right after `'tier_assigned_at'`:

```php
            'tier_assigned_at' => $this->tier_assigned_at?->toIso8601String(),
            ...$this->engagementFields(),
```

Then add these methods:

```php
    /**
     * Per-member engagement, present only when the caller resolved it (the web
     * roster does, via CommunityRosterQuery). Callers that did not preload get
     * the original lean payload and pay nothing extra — the same
     * preloaded-attribute fast path CommunityResource uses.
     *
     * @return array<string, mixed>
     */
    private function engagementFields(): array
    {
        if (! array_key_exists('points_value', $this->resource->getAttributes())) {
            return [];
        }

        $raw = $this->resource->getAttributes();

        return [
            'points' => (int) ($raw['points_value'] ?? 0),
            'events_attended' => (int) ($raw['events_attended_value'] ?? 0),
            'last_active_at' => $this->lastActiveAt($raw['last_active_value'] ?? null),
            'tenure_days' => $this->joined_at ? (int) $this->joined_at->diffInDays(now()) : null,
        ];
    }

    private function lastActiveAt(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return $raw instanceof \DateTimeInterface
            ? Carbon::instance($raw)->toIso8601String()
            : Carbon::parse((string) $raw)->toIso8601String();
    }
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityRosterMetricsTest.php
```

If the query-count case fails, the cause is almost always eager-loading that was dropped — confirm the `->with([...])` in `CommunityRosterQuery::base()` is intact.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint
git add app/Http/Resources/Api/V1/CommunityMemberResource.php tests/Feature/Api/V1/CommunityRosterMetricsTest.php
git commit -m "feat(communities): per-member engagement on the roster payload

points, events_attended, last_active_at and tenure_days, emitted only when
the caller resolved them so existing callers keep the lean payload and its
cost. Locked with a query-count test: 3 members and 30 members must cost the
same number of queries (BACKLOG BE-NF-15).

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Community stats endpoint

One request behind the Overview health strip. Every figure is a single aggregate — no per-member loop.

**Files:**
- Create: `app/Services/CommunityStatsService.php`
- Create: `app/Http/Controllers/Api/V1/CommunityStatsController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CommunityStatsTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/V1/CommunityStatsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityStatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_counts_split_by_status_and_recency(): void
    {
        $community = Community::factory()->create();

        CommunityMember::factory()->count(2)->create([
            'community_id' => $community->id,
            'joined_at' => now()->subMonths(4),
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subDays(2),
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'status' => CommunityMemberStatus::Inactive->value,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'status' => CommunityMemberStatus::Removed->value,
        ]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $this->assertSame(5, $data['members']['total']);
        $this->assertSame(3, $data['members']['active']);
        $this->assertSame(1, $data['members']['inactive']);
        $this->assertSame(1, $data['members']['removed']);
        $this->assertSame(1, $data['members']['new_this_month']);
    }

    public function test_dormant_counts_active_members_with_no_ledger_activity_in_30_days(): void
    {
        $community = Community::factory()->create();

        $recent = CommunityMember::factory()->create(['community_id' => $community->id]);
        $stale = CommunityMember::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id]); // never active

        CommunityPointLedger::query()->create([
            'community_id' => $community->id,
            'profile_id' => $recent->profile_id,
            'points' => 5,
            'source' => 'event_check_in',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);
        CommunityPointLedger::query()->create([
            'community_id' => $community->id,
            'profile_id' => $stale->profile_id,
            'points' => 5,
            'source' => 'event_check_in',
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['members']['dormant_30d']);
    }

    public function test_attendance_rate_is_zero_when_the_community_ran_no_events(): void
    {
        $community = Community::factory()->create();
        CommunityMember::factory()->count(3)->create(['community_id' => $community->id]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        // Never divide by zero, and never report a misleading 100%.
        $this->assertSame(0, $data['engagement']['events_30d']);
        $this->assertSame(0.0, $data['engagement']['attendance_rate_30d']);
    }

    public function test_tier_distribution_and_top_members(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Exec', 'rank' => 3]);

        $star = Profile::factory()->attendee()->create(['name' => 'Star']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $star->id,
            'tier_id' => $tier->id,
        ]);
        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $star->id,
            'points' => 980,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'tier_id' => null]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $exec = collect($data['tiers'])->firstWhere('name', 'Exec');
        $this->assertSame(1, $exec['member_count']);
        $this->assertSame('Star', $data['top_members'][0]['name']);
        $this->assertSame(980, $data['top_members'][0]['points']);
    }

    public function test_stats_is_manage_gated(): void
    {
        $community = Community::factory()->create();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertForbidden();
    }

    public function test_a_leader_with_no_subscription_gets_stats(): void
    {
        // ROLES §8.4 — this surface is NEVER paywalled.
        $community = Community::factory()->create();

        $this->assertFalse($community->owner->hasActiveSubscription());

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityStatsTest.php
```

Expected: FAIL — route does not exist (404).

- [ ] **Step 3: Write the service**

Create `app/Services/CommunityStatsService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinRequestStatus;
use App\Models\Community;
use Illuminate\Support\Facades\DB;

/**
 * The aggregate figures behind GET /communities/{community}/stats — the
 * Community Hub's health strip. Deliberately a fixed set of aggregates, not a
 * general analytics engine: every figure is one grouped query, none of them
 * iterate members.
 */
class CommunityStatsService
{
    private const DORMANT_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function forCommunity(Community $community): array
    {
        $window = now()->subDays(self::DORMANT_DAYS);

        return [
            'members' => $this->members($community, $window),
            'pending' => $this->pending($community),
            'tiers' => $this->tiers($community),
            'engagement' => $this->engagement($community, $window),
            'top_members' => $this->topMembers($community),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function members(Community $community, \DateTimeInterface $window): array
    {
        $byStatus = DB::table('community_members')
            ->where('community_id', $community->id)
            ->groupBy('status')
            ->pluck(DB::raw('COUNT(*)'), 'status');

        $active = (int) ($byStatus[CommunityMemberStatus::Active->value] ?? 0);
        $inactive = (int) ($byStatus[CommunityMemberStatus::Inactive->value] ?? 0);
        $removed = (int) ($byStatus[CommunityMemberStatus::Removed->value] ?? 0);

        $newThisMonth = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('joined_at', '>=', now()->startOfMonth())
            ->count();

        // Dormant: active members with no community_point_ledger row in the
        // window. The ledger is written on check-in, goal completion, challenge
        // verification and redemption, so it is the activity spine.
        $dormant = DB::table('community_members')
            ->where('community_members.community_id', $community->id)
            ->where('community_members.status', CommunityMemberStatus::Active->value)
            ->whereNotExists(function ($sub) use ($community, $window): void {
                $sub->select(DB::raw(1))
                    ->from('community_point_ledger')
                    ->whereColumn('community_point_ledger.profile_id', 'community_members.profile_id')
                    ->where('community_point_ledger.community_id', $community->id)
                    ->where('community_point_ledger.created_at', '>=', $window);
            })
            ->count();

        return [
            'total' => $active + $inactive + $removed,
            'active' => $active,
            'inactive' => $inactive,
            'removed' => $removed,
            'new_this_month' => $newThisMonth,
            'dormant_30d' => $dormant,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function pending(Community $community): array
    {
        return [
            'join_requests' => DB::table('community_join_requests')
                ->where('community_id', $community->id)
                ->where('status', JoinRequestStatus::Pending->value)
                ->count(),
            'invitations' => DB::table('community_invitations')
                ->where('community_id', $community->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tiers(Community $community): array
    {
        $counts = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->whereNotNull('tier_id')
            ->groupBy('tier_id')
            ->pluck(DB::raw('COUNT(*)'), 'tier_id');

        return $community->tiers()
            ->orderByDesc('rank')
            ->get()
            ->map(fn ($tier): array => [
                'tier_id' => $tier->id,
                'name' => $tier->name,
                'color' => $tier->color,
                'rank' => $tier->rank,
                'member_count' => (int) ($counts[$tier->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function engagement(Community $community, \DateTimeInterface $window): array
    {
        $pointsIssued = (int) DB::table('community_point_ledger')
            ->where('community_id', $community->id)
            ->where('created_at', '>=', $window)
            ->where('points', '>', 0)
            ->sum('points');

        $eventIds = DB::table('events')
            ->where('community_id', $community->id)
            ->where('event_date', '>=', $window)
            ->pluck('id');

        $checkins = $eventIds->isEmpty() ? 0 : DB::table('event_checkins')
            ->whereIn('event_id', $eventIds)
            ->count();

        $distinctAttendees = $eventIds->isEmpty() ? 0 : DB::table('event_checkins')
            ->whereIn('event_id', $eventIds)
            ->distinct()
            ->count('profile_id');

        $activeMembers = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->count();

        // No events in the window, or no members: report 0, never divide by
        // zero and never imply 100% attendance at nothing.
        $rate = ($eventIds->isEmpty() || $activeMembers === 0)
            ? 0.0
            : round($distinctAttendees / $activeMembers, 2);

        return [
            'points_issued_30d' => $pointsIssued,
            'checkins_30d' => $checkins,
            'events_30d' => $eventIds->count(),
            'attendance_rate_30d' => $rate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topMembers(Community $community): array
    {
        return DB::table('community_points')
            ->join('profiles', 'profiles.id', '=', 'community_points.profile_id')
            ->leftJoin('community_members', function ($join) use ($community): void {
                $join->on('community_members.profile_id', '=', 'community_points.profile_id')
                    ->where('community_members.community_id', '=', $community->id);
            })
            ->where('community_points.community_id', $community->id)
            ->where('community_members.status', CommunityMemberStatus::Active->value)
            ->orderByDesc('community_points.points')
            ->limit(5)
            ->get([
                'profiles.id as profile_id',
                'profiles.name as name',
                'profiles.email as email',
                'profiles.avatar_url as avatar_url',
                'community_points.points as points',
            ])
            ->map(fn ($row): array => [
                'profile_id' => $row->profile_id,
                'name' => $row->name ?: \Illuminate\Support\Str::before($row->email, '@'),
                'avatar_url' => $row->avatar_url,
                'points' => (int) $row->points,
            ])
            ->all();
    }
}
```

> `pending()` reads `community_invitations`, which Task 6 creates. Write this method now but **run Task 6 before Task 4's test can pass**, or temporarily guard it. Simpler: **do Task 6 first if you are executing out of order.** The plan's build order (§ bottom) runs invitations before stats for exactly this reason — see the note there.

- [ ] **Step 4: Write the controller**

Create `app/Http/Controllers/Api/V1/CommunityStatsController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityStatsController extends Controller
{
    public function __construct(private readonly CommunityStatsService $stats) {}

    /**
     * GET /api/v1/communities/{community}/stats — the Hub health strip.
     * Manage-gated (owner / can_manage). NEVER subscription-gated (ROLES §8.4).
     */
    public function show(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this community.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->stats->forCommunity($community),
        ]);
    }
}
```

- [ ] **Step 5: Register the route**

In `routes/api.php`, in the NF-6 block right after the members routes (around line 502):

```php
        Route::get('communities/{community}/stats', [CommunityStatsController::class, 'show'])
            ->name('api.v1.communities.stats');
```

Add the import at the top with the other `Api\V1` controller imports:

```php
use App\Http\Controllers\Api\V1\CommunityStatsController;
```

- [ ] **Step 6: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityStatsTest.php
```

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint
git add app/Services/CommunityStatsService.php app/Http/Controllers/Api/V1/CommunityStatsController.php routes/api.php tests/Feature/Api/V1/CommunityStatsTest.php
git commit -m "feat(communities): GET /communities/{id}/stats for the hub health strip

Member counts by status, new-this-month, 30-day dormancy, tier distribution,
pending join requests + invitations, 30-day points/check-ins/attendance rate,
and the top five members by points. Fixed set of aggregates, one grouped query
each; attendance rate is 0 when the window held no events rather than dividing
by zero. Manage-gated, never subscription-gated (ROLES §8.4).

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Invitations — schema, enum, model, factory (D3, part 1)

**Files:**
- Create: `database/migrations/2026_08_19_000001_create_community_invitations_table.php`
- Create: `app/Enums/CommunityInvitationStatus.php`
- Create: `app/Models/CommunityInvitation.php`
- Create: `database/factories/CommunityInvitationFactory.php`
- Modify: `config/communities.php`
- Test: `tests/Unit/Models/CommunityInvitationTest.php` (create)

- [ ] **Step 1: Write the migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending email invitations to a community (NF-6 / BE-NF-29).
 *
 * POST /communities/{id}/members only works for people who already hold a
 * Kolabing account — it 404s otherwise. A leader's real roster lives in a
 * spreadsheet, so this table lets them invite an email address: the row waits
 * as `pending` until that person registers (anywhere), at which point the
 * signup hook claims it and creates the membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('tier_id')->nullable()->constrained('community_tiers')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignUuid('invited_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            // pending | accepted | revoked | expired
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('accepted_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            // A partial unique index on (community_id, email) WHERE pending is
            // not portable to SQLite, so uniqueness is enforced in the service
            // (upsert on the pending row) and these indexes serve the reads.
            $table->index(['community_id', 'status']);
            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_invitations');
    }
};
```

- [ ] **Step 2: Write the enum**

`app/Enums/CommunityInvitationStatus.php` — mirror the shape of `app/Enums/CommunityMemberStatus.php` (it has a `values(): array` helper; copy that method exactly):

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 3: Write the model**

`app/Models/CommunityInvitation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommunityInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityInvitation extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'community_id',
        'email',
        'tier_id',
        'token',
        'invited_by_profile_id',
        'status',
        'expires_at',
        'accepted_at',
        'accepted_profile_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommunityInvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(CommunityTier::class, 'tier_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'invited_by_profile_id');
    }

    /** Still redeemable: pending and inside its window. */
    public function isClaimable(): bool
    {
        return $this->status === CommunityInvitationStatus::Pending
            && $this->expires_at->isFuture();
    }
}
```

- [ ] **Step 4: Write the factory**

`database/factories/CommunityInvitationFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityInvitationStatus;
use App\Models\Community;
use App\Models\CommunityInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CommunityInvitation>
 */
class CommunityInvitationFactory extends Factory
{
    protected $model = CommunityInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'email' => fake()->unique()->safeEmail(),
            'tier_id' => null,
            'token' => Str::random(64),
            'invited_by_profile_id' => null,
            'status' => CommunityInvitationStatus::Pending->value,
            'expires_at' => now()->addDays(30),
            'accepted_at' => null,
            'accepted_profile_id' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => CommunityInvitationStatus::Revoked->value]);
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (): array => ['community_id' => $community->id]);
    }
}
```

- [ ] **Step 5: Add the TTL config**

Append to the array in `config/communities.php`:

```php
    /*
    |--------------------------------------------------------------------------
    | Invitation lifetime
    |--------------------------------------------------------------------------
    |
    | How long a pending community_invitations row stays claimable. A leader can
    | always resend, which refreshes the window.
    |
    */
    'invitation_ttl_days' => env('COMMUNITIES_INVITATION_TTL_DAYS', 30),
```

- [ ] **Step 6: Test the model**

`tests/Unit/Models/CommunityInvitationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\CommunityInvitationStatus;
use App\Models\CommunityInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_fresh_pending_invitation_is_claimable(): void
    {
        $this->assertTrue(CommunityInvitation::factory()->create()->isClaimable());
    }

    public function test_an_expired_invitation_is_not_claimable(): void
    {
        $this->assertFalse(CommunityInvitation::factory()->expired()->create()->isClaimable());
    }

    public function test_a_revoked_invitation_is_not_claimable(): void
    {
        $invitation = CommunityInvitation::factory()->revoked()->create();

        $this->assertSame(CommunityInvitationStatus::Revoked, $invitation->status);
        $this->assertFalse($invitation->isClaimable());
    }
}
```

- [ ] **Step 7: Run it**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Unit/Models/CommunityInvitationTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint
git add database/migrations/2026_08_19_000001_create_community_invitations_table.php app/Enums/CommunityInvitationStatus.php app/Models/CommunityInvitation.php database/factories/CommunityInvitationFactory.php config/communities.php tests/Unit/Models/CommunityInvitationTest.php
git commit -m "feat(communities): community_invitations table, model and factory

Backing store for inviting someone who has no Kolabing account yet. The row
waits as pending until they register anywhere, then the signup hook claims it.
Uniqueness per (community, pending email) is enforced in the service rather
than a partial index, which SQLite (the test driver) cannot express.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Invitation service + endpoints (D3, part 2)

**Files:**
- Create: `app/Services/CommunityInvitationService.php`
- Create: `app/Http/Requests/Api/V1/StoreCommunityInvitationRequest.php`
- Create: `app/Http/Resources/Api/V1/CommunityInvitationResource.php`
- Create: `app/Http/Controllers/Api/V1/CommunityInvitationController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CommunityInvitationEndpointsTest.php` (create)

- [ ] **Step 1: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityInvitationStatus;
use App\Enums\CommunityMemberStatus;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\Profile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Pending email invitations to a community.
 *
 * Deliberately a separate resource from POST /communities/{id}/members: turning
 * that endpoint's 404 into a 201 would silently change the contract the mobile
 * client is written against.
 */
class CommunityInvitationService
{
    public function __construct(
        private readonly CommunityMemberService $memberService,
    ) {}

    /**
     * Invite one email. Idempotent: re-inviting the same address re-uses the
     * pending row and refreshes its window, mirroring
     * CommunityMemberService::upsertMember.
     *
     * @return array{status: 'invited'|'already_member', invitation: CommunityInvitation|null}
     */
    public function invite(Community $community, string $email, ?string $tierId, ?Profile $invitedBy): array
    {
        $email = mb_strtolower(trim($email));

        if ($this->isActiveMember($community, $email)) {
            return ['status' => 'already_member', 'invitation' => null];
        }

        $invitation = CommunityInvitation::query()
            ->where('community_id', $community->id)
            ->where('email', $email)
            ->where('status', CommunityInvitationStatus::Pending->value)
            ->first();

        $ttl = (int) config('communities.invitation_ttl_days', 30);

        if ($invitation) {
            $invitation->update([
                'tier_id' => $tierId ?? $invitation->tier_id,
                'expires_at' => now()->addDays($ttl),
            ]);
        } else {
            $invitation = CommunityInvitation::query()->create([
                'community_id' => $community->id,
                'email' => $email,
                'tier_id' => $tierId,
                'token' => Str::random(64),
                'invited_by_profile_id' => $invitedBy?->id,
                'status' => CommunityInvitationStatus::Pending->value,
                'expires_at' => now()->addDays($ttl),
            ]);
        }

        $this->sendSafely($invitation);

        return ['status' => 'invited', 'invitation' => $invitation->fresh()];
    }

    /**
     * Redeem a token. The token IS the authorization (same model as
     * Community::inviteUrlWithToken), so the caller's email need not match —
     * whoever redeemed it is recorded in accepted_profile_id.
     *
     * @throws \DomainException 'not_claimable'
     */
    public function accept(CommunityInvitation $invitation, Profile $profile): CommunityInvitation
    {
        if (! $invitation->isClaimable()) {
            throw new \DomainException('not_claimable');
        }

        $invitation->loadMissing('community');

        $this->memberService->addMember(
            $invitation->community,
            $profile->id,
            $invitation->tier_id,
        );

        $invitation->update([
            'status' => CommunityInvitationStatus::Accepted->value,
            'accepted_at' => now(),
            'accepted_profile_id' => $profile->id,
        ]);

        return $invitation->fresh();
    }

    public function revoke(CommunityInvitation $invitation): CommunityInvitation
    {
        $invitation->update(['status' => CommunityInvitationStatus::Revoked->value]);

        return $invitation->fresh();
    }

    public function resend(CommunityInvitation $invitation): CommunityInvitation
    {
        $invitation->update([
            'status' => CommunityInvitationStatus::Pending->value,
            'expires_at' => now()->addDays((int) config('communities.invitation_ttl_days', 30)),
        ]);

        $invitation = $invitation->fresh();
        $this->sendSafely($invitation);

        return $invitation;
    }

    /**
     * Claim every pending invitation addressed to a freshly-registered profile.
     * Guarded: a failure here must never break signup — the same contract as
     * OnboardingService::autoJoinCommunities and the mission hooks.
     */
    public function claimForSafely(Profile $profile): void
    {
        try {
            CommunityInvitation::query()
                ->with('community')
                ->where('email', mb_strtolower($profile->email))
                ->where('status', CommunityInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->get()
                ->each(function (CommunityInvitation $invitation) use ($profile): void {
                    if ($invitation->community === null) {
                        return;
                    }

                    $this->accept($invitation, $profile);
                });
        } catch (\Throwable $e) {
            Log::warning('Community invitation claim failed', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function isActiveMember(Community $community, string $email): bool
    {
        $profileId = Profile::query()->where('email', $email)->value('id');

        if ($profileId === null) {
            return false;
        }

        return $community->members()
            ->where('profile_id', $profileId)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    /** Mail is queued; a mail failure must not lose the invitation row. */
    private function sendSafely(CommunityInvitation $invitation): void
    {
        try {
            Mail::to($invitation->email)->send(new CommunityInvitationMail($invitation));
        } catch (\Throwable $e) {
            Log::warning('Community invitation mail failed', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: Write the form request**

`app/Http/Requests/Api/V1/StoreCommunityInvitationRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Accept a single `email` as a one-item `emails` list. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email')) && ! $this->has('emails')) {
            $this->merge(['emails' => [$this->input('email')]]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // The panel pastes a list — the cheap half of bulk, without a CSV parser.
            'emails' => ['required', 'array', 'min:1', 'max:50'],
            'emails.*' => ['required', 'email', 'max:255'],
            'tier_id' => ['nullable', 'uuid', 'exists:community_tiers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'emails.required' => 'At least one email address is required.',
            'emails.max' => 'You can invite at most 50 people at a time.',
        ];
    }
}
```

- [ ] **Step 3: Write the resource**

`app/Http/Resources/Api/V1/CommunityInvitationResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityInvitation
 */
class CommunityInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'email' => $this->email,
            'tier_id' => $this->tier_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier
                ? new CommunityTierResource($this->tier)
                : null),
            'status' => $this->status->value,
            'is_claimable' => $this->isClaimable(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

> The token is deliberately **not** in the payload — it is a bearer credential and belongs only in the email.

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/Api/V1/CommunityInvitationController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityInvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityInvitationRequest;
use App\Http\Resources\Api\V1\CommunityInvitationResource;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\Profile;
use App\Services\CommunityInvitationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityInvitationController extends Controller
{
    public function __construct(private readonly CommunityInvitationService $invitations) {}

    /** GET /api/v1/communities/{community}/invitations?status=pending|all */
    public function index(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $query = $community->invitations()->with('tier')->latest();

        if ($request->query('status') !== 'all') {
            $query->where('status', CommunityInvitationStatus::Pending->value);
        }

        return response()->json([
            'success' => true,
            'data' => CommunityInvitationResource::collection($query->get()),
        ]);
    }

    /** POST /api/v1/communities/{community}/invitations */
    public function store(StoreCommunityInvitationRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        $tierId = $data['tier_id'] ?? null;

        if ($tierId !== null && ! $community->tiers()->whereKey($tierId)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        // Per-row results so the panel can say "8 sent, 2 already members".
        $results = [];

        foreach ($data['emails'] as $email) {
            $outcome = $this->invitations->invite($community, $email, $tierId, $profile);

            $results[] = [
                'email' => mb_strtolower(trim($email)),
                'status' => $outcome['status'],
                'invitation' => $outcome['invitation']
                    ? new CommunityInvitationResource($outcome['invitation'])
                    : null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'results' => $results,
                'invited' => count(array_filter($results, fn ($r): bool => $r['status'] === 'invited')),
                'already_members' => count(array_filter($results, fn ($r): bool => $r['status'] === 'already_member')),
            ],
        ], 201);
    }

    /** POST /api/v1/invitations/{invitation}/resend */
    public function resend(Request $request, CommunityInvitation $invitation): JsonResponse
    {
        if ($guard = $this->guardManage($request, $invitation)) {
            return $guard;
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($this->invitations->resend($invitation)),
        ]);
    }

    /** DELETE /api/v1/invitations/{invitation} — revoke. */
    public function destroy(Request $request, CommunityInvitation $invitation): JsonResponse
    {
        if ($guard = $this->guardManage($request, $invitation)) {
            return $guard;
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($this->invitations->revoke($invitation)),
        ]);
    }

    /**
     * POST /api/v1/invitations/accept/{token} — the invitee redeems it.
     * Auth required; the token is the authorization.
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $invitation = CommunityInvitation::query()->where('token', $token)->first();

        if ($invitation === null) {
            return response()->json([
                'success' => false,
                'error' => 'invitation_not_found',
                'message' => __('This invitation link is not valid.'),
            ], 404);
        }

        try {
            $invitation = $this->invitations->accept($invitation, $profile);
        } catch (DomainException) {
            return response()->json([
                'success' => false,
                'error' => 'invitation_not_claimable',
                'message' => __('This invitation has expired or has already been used.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityInvitationResource($invitation),
        ]);
    }

    private function guardManage(Request $request, CommunityInvitation $invitation): ?JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $invitation->loadMissing('community');

        if ($invitation->community === null) {
            return response()->json(['success' => false, 'message' => __('Invitation not found.')], 404);
        }

        return $profile->cannot('manage', $invitation->community) ? $this->forbidden() : null;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }
}
```

- [ ] **Step 5: Add the `invitations()` relation to `Community`**

In `app/Models/Community.php`, alongside `joinRequests()`:

```php
    public function invitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class);
    }
```

- [ ] **Step 6: Register the routes**

In `routes/api.php`, after the members routes:

```php
        Route::get('communities/{community}/invitations', [CommunityInvitationController::class, 'index'])
            ->name('api.v1.communities.invitations.index');
        Route::post('communities/{community}/invitations', [CommunityInvitationController::class, 'store'])
            ->name('api.v1.communities.invitations.store');
        Route::post('invitations/{invitation}/resend', [CommunityInvitationController::class, 'resend'])
            ->name('api.v1.invitations.resend');
        Route::delete('invitations/{invitation}', [CommunityInvitationController::class, 'destroy'])
            ->name('api.v1.invitations.destroy');
        Route::post('invitations/accept/{token}', [CommunityInvitationController::class, 'accept'])
            ->name('api.v1.invitations.accept');
```

Plus the import: `use App\Http\Controllers\Api\V1\CommunityInvitationController;`

- [ ] **Step 7: Write the endpoint test**

`tests/Feature/Api/V1/CommunityInvitationEndpointsTest.php` — cases (write each as its own method, using `Mail::fake()` in `setUp`):

1. `test_a_manager_invites_a_single_email` — 201, `data.invited === 1`, row is `pending`.
2. `test_a_manager_invites_a_pasted_list` — `emails` array of 3 → `data.invited === 3`.
3. `test_re_inviting_the_same_email_reuses_the_pending_row_and_refreshes_the_window` — invite twice, assert `CommunityInvitation::count() === 1` and `expires_at` moved.
4. `test_inviting_an_existing_active_member_reports_already_member` — `data.already_members === 1`, no row created.
5. `test_a_tier_from_another_community_is_rejected` — 422 `tier_not_in_community`.
6. `test_more_than_fifty_emails_is_rejected` — 422 on `emails`.
7. `test_index_lists_pending_by_default_and_all_on_request`.
8. `test_the_token_is_never_in_the_payload` — `assertJsonMissing(['token' => …])`.
9. `test_resend_refreshes_the_window_and_sends_again` — `Mail::assertSent(CommunityInvitationMail::class, 2)`.
10. `test_revoke_marks_it_revoked_and_it_stops_being_claimable`.
11. `test_accept_makes_the_caller_a_member_on_the_invited_tier`.
12. `test_accept_is_422_for_an_expired_invitation` and `..._for_a_revoked_invitation`.
13. `test_accept_is_404_for_an_unknown_token`.
14. `test_accept_is_idempotent_for_someone_already_a_member` — second accept 422 `invitation_not_claimable`, membership unchanged, still exactly one member row.
15. `test_a_non_manager_cannot_invite_list_resend_or_revoke` — 403 on each.
16. `test_a_can_manage_member_can_invite` — the `can_manage` attendee path (ROLES §8.3 D1).

- [ ] **Step 8: Run, then commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityInvitationEndpointsTest.php
vendor/bin/pint
git add app/Services/CommunityInvitationService.php app/Http/Requests/Api/V1/StoreCommunityInvitationRequest.php app/Http/Resources/Api/V1/CommunityInvitationResource.php app/Http/Controllers/Api/V1/CommunityInvitationController.php app/Models/Community.php routes/api.php tests/Feature/Api/V1/CommunityInvitationEndpointsTest.php
git commit -m "feat(communities): invite members by email, before they have an account

POST /communities/{id}/members 404s for anyone without a Kolabing account, so
a leader could not get their real roster in. Adds a separate invitations
resource (index/store/resend/revoke/accept) rather than changing that
endpoint's contract. Accepts a pasted list of up to 50 addresses and returns
per-row results. The token is a bearer credential and never leaves the email.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: The invitation email

**Files:**
- Create: `app/Mail/CommunityInvitationMail.php`
- Create: `resources/views/mail/community-invitation.blade.php`
- Test: `tests/Feature/Api/V1/CommunityInvitationMailTest.php` (create)

Follow `app/Mail/ModerationAlertMail.php` exactly: `extends Mailable implements ShouldQueue`, `use Queueable, SerializesModels`, promoted constructor properties, `envelope()` + `content(markdown: …)`.

- [ ] **Step 1: Write the mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\CommunityInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You've been invited to join <community>". Queued so a slow SMTP never
 * blocks the leader's invite request.
 */
class CommunityInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly CommunityInvitation $invitation) {}

    public function envelope(): Envelope
    {
        $this->invitation->loadMissing('community');

        return new Envelope(
            subject: __('You have been invited to join :community', [
                'community' => $this->invitation->community?->name ?? 'a Kolabing community',
            ]),
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['community', 'invitedBy']);

        $community = $this->invitation->community;

        return new Content(
            markdown: 'mail.community-invitation',
            with: [
                'communityName' => $community?->name ?? 'a Kolabing community',
                'inviterName' => $this->invitation->invitedBy?->name,
                // The landing page created in Task 11 resolves the slug and the
                // ?i= token together.
                'joinUrl' => rtrim((string) config('communities.invite_base_url'), '/')
                    .'/'.($community?->slug ?? '').'?i='.$this->invitation->token,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
```

- [ ] **Step 2: Write the view**

`resources/views/mail/community-invitation.blade.php`:

```blade
<x-mail::message>
# {{ __('You have been invited to join :community', ['community' => $communityName]) }}

@if ($inviterName)
{{ __(':inviter invited you to join :community on Kolabing.', ['inviter' => $inviterName, 'community' => $communityName]) }}
@else
{{ __('You have been invited to join :community on Kolabing.', ['community' => $communityName]) }}
@endif

<x-mail::button :url="$joinUrl">
{{ __('View the community') }}
</x-mail::button>

{{ __('This invitation is valid until :date.', ['date' => $expiresAt->toFormattedDateString()]) }}

{{ __('If you were not expecting this, you can ignore this email.') }}

{{ __('Thanks') }},<br>
{{ config('app.name') }}
</x-mail::message>
```

- [ ] **Step 3: Test it**

```php
public function test_the_invitation_email_carries_the_join_link_with_the_token(): void
{
    Mail::fake();

    $community = Community::factory()->create(['name' => 'Run Club']);
    $this->actingAs($community->owner)
        ->postJson("/api/v1/communities/{$community->id}/invitations", ['email' => 'new@example.com'])
        ->assertCreated();

    $invitation = CommunityInvitation::query()->firstOrFail();

    Mail::assertQueued(CommunityInvitationMail::class, function (CommunityInvitationMail $mail) use ($invitation): bool {
        return $mail->hasTo('new@example.com')
            && $mail->invitation->is($invitation);
    });
}
```

> `Mail::fake()` + `ShouldQueue` means the assertion is `assertQueued`, not `assertSent`. If a case in Task 6 used `assertSent`, switch it to `assertQueued`.

- [ ] **Step 4: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityInvitationMailTest.php
vendor/bin/pint
git add app/Mail/CommunityInvitationMail.php resources/views/mail/community-invitation.blade.php tests/Feature/Api/V1/CommunityInvitationMailTest.php
git commit -m "feat(communities): queued invitation email with the tokenised join link

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Claim pending invitations on register

This is what makes "no attendee registration on the web" safe — the invitation waits.

**Files:**
- Modify: `app/Services/OnboardingService.php`
- Test: `tests/Feature/Api/V1/CommunityInvitationClaimOnRegisterTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
public function test_a_pending_invitation_becomes_a_membership_when_that_email_registers(): void
{
    $community = Community::factory()->create();
    $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Pledge', 'rank' => 1]);
    $invitation = CommunityInvitation::factory()->forCommunity($community)->create([
        'email' => 'invitee@example.com',
        'tier_id' => $tier->id,
    ]);

    // The profile is created by the normal signup path; the hook runs inside it.
    $profile = Profile::factory()->attendee()->create(['email' => 'invitee@example.com']);
    app(CommunityInvitationService::class)->claimForSafely($profile);

    $this->assertDatabaseHas('community_members', [
        'community_id' => $community->id,
        'profile_id' => $profile->id,
        'tier_id' => $tier->id,
        'status' => CommunityMemberStatus::Active->value,
    ]);
    $this->assertSame(
        CommunityInvitationStatus::Accepted,
        $invitation->fresh()->status,
    );
}

public function test_an_expired_invitation_is_not_claimed(): void
{
    $community = Community::factory()->create();
    CommunityInvitation::factory()->forCommunity($community)->expired()->create(['email' => 'late@example.com']);

    $profile = Profile::factory()->attendee()->create(['email' => 'late@example.com']);
    app(CommunityInvitationService::class)->claimForSafely($profile);

    $this->assertDatabaseMissing('community_members', ['profile_id' => $profile->id]);
}

public function test_a_failure_inside_the_claim_hook_never_breaks_signup(): void
{
    // A community that has been hard-deleted out from under the invitation.
    $community = Community::factory()->create();
    $invitation = CommunityInvitation::factory()->forCommunity($community)->create(['email' => 'x@example.com']);
    Community::query()->whereKey($community->id)->delete();

    $profile = Profile::factory()->attendee()->create(['email' => 'x@example.com']);

    // Must not throw.
    app(CommunityInvitationService::class)->claimForSafely($profile);

    $this->assertTrue(true);
}
```

- [ ] **Step 2: Wire the hook into onboarding**

In `app/Services/OnboardingService.php`, add `CommunityInvitationService` to the constructor alongside `CommunityMemberService`, and call it from the attendee onboarding path immediately before `autoJoinCommunities()`:

```php
        // Pending email invitations addressed to this person become real
        // memberships now that they have an account. Guarded — the same
        // never-break-signup contract as autoJoinCommunities below.
        $this->communityInvitationService->claimForSafely($profile);

        $this->autoJoinCommunities($profile, $data['community_ids'] ?? []);
```

> Locate the exact call site first: `grep -n "autoJoinCommunities" app/Services/OnboardingService.php`. Insert directly above the call at line ~128.

- [ ] **Step 3: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityInvitationClaimOnRegisterTest.php
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact --filter=Onboarding
vendor/bin/pint
git add app/Services/OnboardingService.php tests/Feature/Api/V1/CommunityInvitationClaimOnRegisterTest.php
git commit -m "feat(communities): claim pending invitations when the invitee registers

Guarded like autoJoinCommunities — a failure here must never break signup.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Member detail endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CommunityMemberController.php` (add `show()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CommunityMemberDetailTest.php` (create)

- [ ] **Step 1: Add the route** (before the `PATCH`/`DELETE` on the same path):

```php
        Route::get('communities/{community}/members/{member}', [CommunityMemberController::class, 'show'])
            ->name('api.v1.communities.members.show');
```

- [ ] **Step 2: Add `show()` to the controller**

```php
    /**
     * GET /api/v1/communities/{community}/members/{member} — the roster drawer.
     * Member + metrics + a capped activity timeline. Not paginated: a drawer,
     * not a page.
     */
    public function show(Request $request, Community $community, CommunityMember $member): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        if ($member->community_id !== $community->id) {
            return $this->notFound();
        }

        $member = $this->rosterQuery->base($community)
            ->where('community_members.id', $member->id)
            ->firstOrFail();

        $activity = CommunityPointLedger::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $member->profile_id)
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (CommunityPointLedger $row): array => [
                'id' => $row->id,
                'points' => $row->points,
                'source' => $row->source,
                'description' => $row->description,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => new CommunityMemberResource($member),
                'activity' => $activity,
            ],
        ]);
    }
```

Inject `CommunityRosterQuery $rosterQuery` into the controller constructor and import `CommunityPointLedger`.

- [ ] **Step 3: Test** — cases: payload carries metrics + activity; activity capped at 25 with the newest first; a member from another community 404s; a non-manager 403s; an unsubscribed leader gets 200 (§8.4).

- [ ] **Step 4: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityMemberDetailTest.php
vendor/bin/pint
git add app/Http/Controllers/Api/V1/CommunityMemberController.php routes/api.php tests/Feature/Api/V1/CommunityMemberDetailTest.php
git commit -m "feat(communities): member detail endpoint behind the roster drawer

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Bulk roster actions

**Files:**
- Create: `app/Http/Requests/Api/V1/BulkUpdateCommunityMembersRequest.php`
- Modify: `app/Services/CommunityMemberService.php` (add `bulkUpdate()`)
- Modify: `app/Http/Controllers/Api/V1/CommunityMemberController.php` (add `bulkUpdate()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/CommunityBulkMemberUpdateTest.php` (create)

- [ ] **Step 1: The request**

```php
    public function rules(): array
    {
        return [
            'member_ids' => ['required', 'array', 'min:1', 'max:100'],
            'member_ids.*' => ['required', 'uuid'],
            'tier_id' => ['nullable', 'uuid', 'exists:community_tiers,id'],
            'can_manage' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(CommunityMemberStatus::values())],
        ];
    }
```

- [ ] **Step 2: The service method**

```php
    /**
     * Apply one change set to many memberships. Every id is verified to belong
     * to $community first, so a caller can never write across communities.
     *
     * @param  array<int, string>  $memberIds
     * @param  array<string, mixed>  $data
     * @return array{updated: int, skipped: int}
     */
    public function bulkUpdate(Community $community, array $memberIds, array $data): array
    {
        $members = $community->members()->whereIn('id', $memberIds)->get();

        DB::transaction(function () use ($members, $data): void {
            foreach ($members as $member) {
                $this->updateMember($member, $data);
            }
        });

        return [
            'updated' => $members->count(),
            'skipped' => count(array_unique($memberIds)) - $members->count(),
        ];
    }
```

- [ ] **Step 3: The route + controller action**

```php
        Route::patch('communities/{community}/members', [CommunityMemberController::class, 'bulkUpdate'])
            ->name('api.v1.communities.members.bulk-update');
```

Register it **before** `communities/{community}/members/{member}` is irrelevant (different segment count), but keep it grouped with the other member routes.

```php
    /** PATCH /api/v1/communities/{community}/members — bulk tier / can_manage / status. */
    public function bulkUpdate(BulkUpdateCommunityMembersRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $data = $request->validated();
        $memberIds = $data['member_ids'];
        unset($data['member_ids']);

        if (array_key_exists('tier_id', $data) && $data['tier_id'] !== null
            && ! $community->tiers()->whereKey($data['tier_id'])->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'tier_not_in_community',
                'message' => __('That tier does not belong to this community.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $this->memberService->bulkUpdate($community, $memberIds, $data),
        ]);
    }
```

- [ ] **Step 4: Test** — cases: bulk tier assign moves every row and stamps `tier_assigned_at`; bulk status; ids from another community are counted in `skipped` and **not** written; >100 ids is 422; a tier from another community is 422; non-manager 403.

- [ ] **Step 5: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityBulkMemberUpdateTest.php
vendor/bin/pint
git add -A app/Http/Requests/Api/V1/BulkUpdateCommunityMembersRequest.php app/Services/CommunityMemberService.php app/Http/Controllers/Api/V1/CommunityMemberController.php routes/api.php tests/Feature/Api/V1/CommunityBulkMemberUpdateTest.php
git commit -m "feat(communities): bulk tier/status/can_manage updates on the roster

Cross-community ids are skipped, never written.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 11: The `/c/{slug}` join landing page (D4)

`Community::inviteUrl()` has always emitted `https://kolabing.com/c/{slug}`, and that route has never existed — every invite link ever shared is dead.

**Files:**
- Create: `app/Http/Controllers/CommunityJoinPageController.php`
- Create: `resources/views/pages/community-join.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Web/CommunityJoinPageTest.php` (create)

- [ ] **Step 1: Write the failing test**

Cases:
1. `test_the_join_page_renders_for_a_known_slug` — 200, sees the name, description, active member count.
2. `test_an_unknown_slug_is_404`.
3. `test_the_tier_ladder_renders_in_rank_order` — highest rank first.
4. `test_upcoming_community_events_render_and_other_communities_events_do_not`.
5. `test_an_invite_only_community_is_noindex` — `assertSee('noindex', false)`; an open one is not.
6. `test_the_page_carries_the_invitation_token_through_to_the_cta` — request `?i=abc`, assert the token reaches the Alpine state.
7. `test_a_removed_member_is_not_counted_in_the_member_count`.

- [ ] **Step 2: The controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Models\Community;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public landing page for a community's shareable join link.
 *
 * config('communities.invite_base_url') has always pointed at /c/{slug} and
 * Community::inviteUrl() has always emitted it, but the route never existed —
 * so every invite link ever shared 404'd. This is that route.
 */
class CommunityJoinPageController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $community = Community::query()
            ->where('slug', $slug)
            ->with(['communityProfile', 'tiers' => fn ($q) => $q->orderByDesc('rank')])
            ->firstOrFail();

        $memberCount = $community->members()
            ->where('status', CommunityMemberStatus::Active->value)
            ->count();

        $events = $community->hasMany(\App\Models\Event::class)
            ->getQuery()
            ->where('community_id', $community->id)
            ->where('event_date', '>=', now()->toDateString())
            ->where('visibility', 'public')
            ->orderBy('event_date')
            ->limit(5)
            ->get(['id', 'name', 'event_date']);

        return view('pages.community-join', [
            'community' => $community,
            'memberCount' => $memberCount,
            'events' => $events,
            'isInviteOnly' => $community->join_policy === JoinPolicy::InviteOnly,
            // ?invite= pre-authorises an invite_only join; ?i= carries an
            // email invitation token.
            'inviteToken' => $request->query('invite'),
            'invitationToken' => $request->query('i'),
        ]);
    }
}
```

> Simplify the `$events` lookup to `\App\Models\Event::query()->where('community_id', $community->id)…` — the relation hop above is noise. Use the direct query.

- [ ] **Step 3: The view**

`resources/views/pages/community-join.blade.php` uses `<x-layouts.marketing-page>` with `:title`, `:description`, `:canonical`. Structure:

- Hero: avatar (`$community->avatar_url ?: $community->communityProfile?->profile_photo`), name, type label, member count.
- Description paragraph.
- Tier ladder: one pill per tier, `background` from `$tier->color`, rank order (highest first).
- Upcoming events list.
- CTA card, Alpine-driven — reads the same `kolabing_token` localStorage key the web app writes:

```blade
<div x-data="joinCta()" x-cloak>
    <template x-if="!signedIn">
        <div>
            <a :href="loginUrl" class="…">{{ __('Sign in to join') }}</a>
            <p class="…">{{ __('New to Kolabing? Get the app to create your account.') }}</p>
        </div>
    </template>
    <template x-if="signedIn">
        <button type="button" @click="join()" :disabled="busy" class="…" x-text="ctaLabel"></button>
    </template>
    <p x-show="error" x-text="error" class="…"></p>
</div>

<script>
function joinCta() {
    return {
        busy: false,
        error: '',
        signedIn: !!localStorage.getItem('kolabing_token'),
        communityId: @json($community->id),
        inviteOnly: @json($isInviteOnly),
        invitationToken: @json($invitationToken),
        get loginUrl() {
            return @json(rtrim(config('webapp.url'), '/')) + '/login?next=' + encodeURIComponent(location.pathname + location.search);
        },
        get ctaLabel() {
            if (this.invitationToken) return @json(__('Accept invitation'));
            return this.inviteOnly ? @json(__('Request to join')) : @json(__('Join'));
        },
        async join() {
            this.busy = true; this.error = '';
            const token = localStorage.getItem('kolabing_token');
            const path = this.invitationToken
                ? '/invitations/accept/' + this.invitationToken
                : (this.inviteOnly
                    ? '/communities/' + this.communityId + '/join-requests'
                    : '/communities/' + this.communityId + '/join');
            const res = await fetch(@json(rtrim(config('app.url'), '/')) + '/api/v1' + path, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            });
            this.busy = false;
            if (res.ok) { location.href = @json(rtrim(config('webapp.url'), '/')) + '/community'; return; }
            const j = await res.json().catch(() => null);
            this.error = j?.message || @json(__('Something went wrong. Please try again.'));
        },
    };
}
</script>
```

Add `<meta name="robots" content="noindex">` when `$isInviteOnly` — the marketing layout emits an index directive by default, so override it via a slot or a conditional meta in the page head section (check how `subscription.blade.php` in the webapp does its noindex and follow the same mechanism the marketing layout supports).

- [ ] **Step 4: The route**

In `routes/web.php`, with the other public marketing routes (after the `home` route):

```php
// Public landing for a community's shareable join link. config('communities.
// invite_base_url') has always pointed here; the route did not exist until now.
Route::get('/c/{slug}', [CommunityJoinPageController::class, 'show'])
    ->name('communities.join-page');
```

- [ ] **Step 5: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Web/CommunityJoinPageTest.php
vendor/bin/pint
git add app/Http/Controllers/CommunityJoinPageController.php resources/views/pages/community-join.blade.php routes/web.php tests/Feature/Web/CommunityJoinPageTest.php
git commit -m "fix(communities): /c/{slug} join page — every invite link was 404ing

config('communities.invite_base_url') is https://kolabing.com/c and
Community::inviteUrl() has always emitted /c/{slug}, but no such route
existed, so every shareable join link ever handed out was dead. Adds the
public landing page: community identity, tier ladder, upcoming public
events, and a CTA that joins, requests to join, or accepts an email
invitation depending on the policy and the token in the URL.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 12: Web app shell — routes, `kb.rows()` fix, nav entry, community switcher

**Files:**
- Modify: `resources/views/webapp/layout.blade.php`
- Modify: `resources/views/webapp/partials/sidebar.blade.php`
- Create: `resources/views/webapp/partials/community-nav.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/WebApp/WebAppRoutesTest.php` (extend), `tests/Feature/WebApp/KbRowsTest.php` (create)

- [ ] **Step 1: Fix `kb.rows()`**

`GET /communities/{id}/members` returns rows at `data.members`. `kb.rows()` reads `data` and `data.data` only, so it silently returns `[]` — **the exact bug BE-NF-21 shipped and had to fix**. In `resources/views/webapp/layout.blade.php`, replace `rows(res)`:

```js
            /**
             * The list rows out of a response. ResourceCollection-backed endpoints
             * (kolabs, applications, collaborations, discovery) nest under data.data,
             * plain ones (notifications, lookups) put the array at data, and a few
             * envelope-style endpoints name the key (the community roster returns
             * data.members alongside data.pagination). Read all three so a caller
             * never silently renders an empty list.
             */
            rows(res, key = null) {
                const d = res?.json?.data;
                if (Array.isArray(d)) return d;
                if (Array.isArray(d?.data)) return d.data;
                if (key && Array.isArray(d?.[key])) return d[key];
                if (d && typeof d === 'object') {
                    const first = Object.values(d).find(v => Array.isArray(v));
                    if (first) return first;
                }
                return [];
            },
```

- [ ] **Step 2: Add `canManageCommunity` to `kbShell()`**

In the `kbShell()` return object in `layout.blade.php`, add state + a loader that runs alongside the existing `/auth/me` + unread-count fetch:

```js
                communities: [],
                get canManageCommunity() { return this.communities.length > 0; },
                get activeCommunity() {
                    const saved = localStorage.getItem('kolabing_active_community');
                    return this.communities.find(c => c.id === saved) || this.communities[0] || null;
                },
                setActiveCommunity(id) {
                    localStorage.setItem('kolabing_active_community', id);
                    location.reload();
                },
                /**
                 * Communities this profile may administer: the ones they own, plus
                 * the ones where their membership carries can_manage. Gated on the
                 * grant, NOT on user_type — managers are attendee accounts
                 * (ROLES §8.1 / §8.3 D1).
                 */
                async loadManagedCommunities() {
                    if (!window.kb.token) return;
                    const [owned, memberships] = await Promise.all([
                        window.kb.api('/me/communities'),
                        window.kb.api('/me/memberships'),
                    ]);
                    const mine = window.kb.rows(owned);
                    const managed = window.kb.rows(memberships)
                        .filter(m => m.can_manage && m.community)
                        .map(m => m.community);
                    const byId = {};
                    [...mine, ...managed].forEach(c => { if (c && c.id) byId[c.id] = c; });
                    this.communities = Object.values(byId);
                },
```

Call `await this.loadManagedCommunities();` from the shell's existing init, after `/auth/me` resolves.

> Verify the `/me/memberships` payload shape first — `CommunityController@myMemberships` may return community rows directly rather than membership rows with a nested `community`. Run:
> `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan tinker` is not appropriate here (prod env risk); instead read `app/Http/Controllers/Api/V1/CommunityController.php@myMemberships` and adapt the mapping to what it actually returns.

- [ ] **Step 3: Add the sidebar entry**

In `resources/views/webapp/partials/sidebar.blade.php`, after the `$items` loop and before the Plan link, in **both** the mobile nav and the desktop nav:

```blade
        <a href="{{ $base }}/community" x-show="canManageCommunity" x-cloak
           class="flex items-center gap-3 px-3 py-[11px] rounded-xl text-sm transition {{ $activeKey === 'community' ? 'bg-primary-tint font-bold text-ink' : 'font-medium text-body hover:bg-cream-low' }}">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            {{ __('webapp.nav.community') }}
            <span class="ml-auto min-w-[20px] h-5 px-1.5 rounded-pill bg-ink text-primary text-[11px] font-bold flex items-center justify-center"
                  x-show="communityPending > 0" x-text="communityPending" x-cloak></span>
        </a>
```

Add `communityPending: 0` to `kbShell()` and set it from `/communities/{id}/stats` (`pending.join_requests + pending.invitations`) when an active community exists.

- [ ] **Step 4: Register the 7 routes**

In `routes/web.php`, inside `$webappRoutes` (so they get both the root and the `/es` `/ca` registrations):

```php
    // Community Hub — the members & tiers surface (BE-NF-29). Order matters:
    // every literal sits under /community, no catch-all segment.
    Route::view('/community', 'webapp.community');
    Route::view('/community/members', 'webapp.community-members');
    Route::view('/community/requests', 'webapp.community-requests');
    Route::view('/community/tiers', 'webapp.community-tiers');
    Route::view('/community/economy', 'webapp.community-economy');
    Route::view('/community/leaderboard', 'webapp.community-leaderboard');
    Route::view('/community/settings', 'webapp.community-settings');
```

- [ ] **Step 5: Write the tab strip partial**

`resources/views/webapp/partials/community-nav.blade.php` — a horizontal pill strip (Overview / Members / Requests / Tiers / Economy / Leaderboard / Settings) using `$communityActive` to mark the current tab, plus the community switcher (`<select>` bound to `setActiveCommunity($event.target.value)`, hidden when `communities.length < 2`). Follow the tab markup already in `resources/views/webapp/kolabs.blade.php`.

- [ ] **Step 6: Extend the route test**

In `tests/Feature/WebApp/WebAppRoutesTest.php`:

```php
    public function test_community_hub_pages_render_on_the_app_host(): void
    {
        foreach (['', '/members', '/requests', '/tiers', '/economy', '/leaderboard', '/settings'] as $path) {
            $this->get('http://'.$this->host().'/community'.$path)
                ->assertOk()
                ->assertSee('noindex', false);
        }
    }

    public function test_community_hub_pages_render_under_the_locale_prefixes(): void
    {
        foreach (['es', 'ca'] as $locale) {
            $this->get('http://'.$this->host().'/'.$locale.'/community/members')->assertOk();
        }
    }
```

- [ ] **Step 7: Test `kb.rows()` knows about `data.members`**

`tests/Feature/WebApp/KbRowsTest.php` — assert the layout ships the fixed helper so the regression cannot silently return:

```php
    public function test_the_api_client_reads_keyed_list_envelopes(): void
    {
        // The community roster returns rows at data.members. kb.rows() used to
        // read only data / data.data and silently returned [] (BE-NF-21).
        $this->get('http://'.config('webapp.host').'/community/members')
            ->assertOk()
            ->assertSee('rows(res, key = null)', false)
            ->assertSee("Object.values(d).find(v => Array.isArray(v))", false);
    }
```

- [ ] **Step 8: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/WebApp
vendor/bin/pint
git add resources/views/webapp/layout.blade.php resources/views/webapp/partials/sidebar.blade.php resources/views/webapp/partials/community-nav.blade.php routes/web.php tests/Feature/WebApp
git commit -m "feat(webapp): Community Hub shell — routes, nav entry, switcher, rows() fix

kb.rows() read only data / data.data, so the roster's data.members envelope
silently rendered as an empty list — the same class of bug BE-NF-21 shipped.
The nav entry is gated on owning or can_manage-ing a community, never on
user_type: managers are attendee accounts (ROLES §8.1 / §8.3 D1).

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 13: Roster page — the workspace

**Files:** Create `resources/views/webapp/community-members.blade.php`, `resources/views/webapp/partials/community-modals.blade.php`

This is the page that matters most. Build it before the Overview.

- [ ] **Step 1: The Alpine component**

```js
function communityMembers() {
    return window.kbMerge(kbShell(), {
        loading: true,
        members: [],
        tiers: [],
        pagination: { current_page: 1, total_pages: 1, total_count: 0, per_page: 25 },
        filters: { search: '', status: '', tier_id: '', can_manage: '', sort: 'joined_at', direction: 'asc' },
        selected: [],
        drawer: null,          // the member currently open in the detail drawer
        drawerActivity: [],
        searchTimer: null,

        get communityId() { return this.activeCommunity?.id || null; },
        get allSelected() { return this.members.length > 0 && this.selected.length === this.members.length; },

        async initPage() {
            await this.loadManagedCommunities();
            if (!this.communityId) { this.loading = false; return; }
            await Promise.all([this.loadTiers(), this.load()]);
        },

        /** Debounced so typing does not fire a request per keystroke. */
        onSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => { this.pagination.current_page = 1; this.load(); }, 300);
        },

        query(page = 1) {
            const p = new URLSearchParams();
            Object.entries(this.filters).forEach(([k, v]) => { if (v !== '' && v !== null) p.set(k, v); });
            p.set('page', page);
            p.set('limit', this.pagination.per_page);
            return p.toString();
        },

        async load(page = 1) {
            this.loading = true;
            const res = await window.kb.api(`/communities/${this.communityId}/members?${this.query(page)}`);
            this.loading = false;
            if (!res.ok) { this.members = []; return; }
            this.members = window.kb.rows(res, 'members');
            this.pagination = res.json?.data?.pagination || this.pagination;
            this.selected = [];
        },

        async loadTiers() {
            const res = await window.kb.api(`/communities/${this.communityId}/tiers`);
            this.tiers = window.kb.rows(res);
        },

        sortBy(key) {
            if (this.filters.sort === key) {
                this.filters.direction = this.filters.direction === 'asc' ? 'desc' : 'asc';
            } else {
                this.filters.sort = key;
                this.filters.direction = ['points', 'events_attended', 'last_active_at', 'tier'].includes(key) ? 'desc' : 'asc';
            }
            this.load();
        },

        toggleAll() { this.selected = this.allSelected ? [] : this.members.map(m => m.id); },

        async setTier(member, tierId) {
            const res = await window.kb.api(`/communities/${this.communityId}/members/${member.id}`, {
                method: 'PATCH', body: { tier_id: tierId || null },
            });
            if (res.ok) await this.load(this.pagination.current_page);
        },

        async toggleManager(member) {
            const res = await window.kb.api(`/communities/${this.communityId}/members/${member.id}`, {
                method: 'PATCH', body: { can_manage: !member.can_manage },
            });
            if (res.ok) await this.load(this.pagination.current_page);
        },

        async removeMember(member) {
            // No window.confirm — a browser modal would block the page. Use the
            // in-page confirm state on the row instead.
            const res = await window.kb.api(`/communities/${this.communityId}/members/${member.id}`, { method: 'DELETE' });
            if (res.ok) await this.load(this.pagination.current_page);
        },

        async bulkTier(tierId) {
            const res = await window.kb.api(`/communities/${this.communityId}/members`, {
                method: 'PATCH', body: { member_ids: this.selected, tier_id: tierId || null },
            });
            if (res.ok) await this.load(this.pagination.current_page);
        },

        async openDrawer(member) {
            this.drawer = member; this.drawerActivity = [];
            const res = await window.kb.api(`/communities/${this.communityId}/members/${member.id}`);
            if (res.ok) {
                this.drawer = res.json.data.member;
                this.drawerActivity = res.json.data.activity || [];
            }
        },
    });
}
```

- [ ] **Step 2: The markup**

Copy the card/table/pill conventions from `resources/views/webapp/kolabs.blade.php`. Structure:

- `@include('webapp.partials.sidebar', ['active' => 'community'])`
- `@include('webapp.partials.community-nav', ['communityActive' => 'members'])`
- Toolbar: search input (`@input="onSearch()"`), status `<select>`, tier `<select>` (options from `tiers` + a "No tier" option with value `none`), a Managers-only toggle, and two buttons — **Add member** and **Invite by email**.
- Bulk bar (`x-show="selected.length"`): "N selected", a tier `<select>` → `bulkTier`, and Remove.
- Table columns: checkbox · avatar+name+handle · tier chip (colour from `tier.color`) · points · events · last active (`kbDateShort`) · joined (`kbDate`) · row menu. Every metric header is a `<button @click="sortBy('points')">` with an arrow when active.
- Mobile: the same rows as stacked cards (`md:hidden`), matching how `kolabs.blade.php` degrades.
- Empty state: "No members yet — invite your first members" with the Invite button as the primary CTA.
- Pagination: prev/next + "page X of Y".
- Detail drawer: right-hand panel with avatar, name, email, tier, the four metrics, and the activity timeline (points, source label, date).

**No `window.confirm` / `alert` / `prompt` anywhere** — they block the page and the browser automation session.

- [ ] **Step 3: The modals partial**

`community-modals.blade.php`:
- **Add member**: one input (email or @handle) + tier select → `POST /communities/{id}/members` with `{identifier, tier_id}`. On `404 profile_not_found`, do not show a dead end — swap to "No Kolabing account for that address. Send an email invitation instead?" with a button that submits the same address to the invitations endpoint. **This is the D3 fix as the user experiences it.**
- **Invite by email**: a `<textarea>` (one address per line, split on newline/comma, trimmed, max 50) + tier select → `POST /communities/{id}/invitations`, then render the per-row result summary ("8 invited · 2 already members").

- [ ] **Step 4: Verify in the browser, then commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/WebApp
vendor/bin/pint
git add resources/views/webapp/community-members.blade.php resources/views/webapp/partials/community-modals.blade.php
git commit -m "feat(webapp): community roster page — search, filters, sort, bulk, drawer

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 14: Overview page

**Files:** Create `resources/views/webapp/community.blade.php`

- [ ] Fetch `/communities/{id}/stats` once on init.
- [ ] Health strip: five stat cards — Members (total, `active` as the subline), New this month, Dormant 30d, Pending (join_requests + invitations, links to `/community/requests`), Attendance 30d (as a percentage).
- [ ] Tier distribution: a single horizontal stacked bar, one segment per tier coloured by `tier.color`, with a legend showing `name — member_count`. Falls back to an "Add your first tier" CTA when `tiers` is empty.
- [ ] Top members: five rows (avatar, name, points), linking into the roster.
- [ ] Quick actions row: Invite members · Add member · New tier · Copy invite link.
- [ ] Empty state when the profile manages no community at all: explain that communities are created in the app / via `POST /communities`, and link to `/community/settings`.
- [ ] Commit: `feat(webapp): community hub overview with the health strip`

---

## Task 15: Requests & invitations page

**Files:** Create `resources/views/webapp/community-requests.blade.php`

- [ ] Two tabs. **Join requests**: `GET /communities/{id}/join-requests`, each row → Approve (`POST /join-requests/{id}/approve`) / Decline. **Invitations**: `GET /communities/{id}/invitations?status=all`, each row shows email, tier, status chip, expiry → Resend / Revoke for pending ones.
- [ ] Both lists refresh after every action, and the sidebar badge (`communityPending`) is recomputed.
- [ ] Empty states for both tabs.
- [ ] Commit: `feat(webapp): join-request queue and pending-invitation management`

---

## Task 16: Tier editor

**Files:** Create `resources/views/webapp/community-tiers.blade.php`

- [ ] List tiers rank-descending with a colour swatch, the rule label, the threshold, a "Default" chip, and the member count (from `/stats`).
- [ ] Create/edit form (inline panel, not a modal): name, rank (number), colour (`<input type="color">`), `assignment_rule` (`manual` / `xp_threshold` / `tenure` / `events_attended`), threshold (shown only when the rule is not `manual`, and required then — mirror `StoreCommunityTierRequest`), `is_default` checkbox, and four tag inputs for `permissions.view` / `chat_channels` / `perks` / `capabilities`.
- [ ] Copy explaining §8.3 D1 in one line: "A tier is a status ladder. Managing rights are granted separately, per member, on the roster."
- [ ] Delete with an in-page confirm (never `window.confirm`).
- [ ] Commit: `feat(webapp): community tier editor with rules, colours and permissions`

---

## Task 17: Economy page (goals / rewards / badges)

**Files:** Create `resources/views/webapp/community-economy.blade.php`

- [ ] Three tabs against the existing endpoints: goals (`title`, `earn_type`, `target`, `reward_points`, `is_active`), rewards (`title`, `description`, `cost_points`, `stock`, `is_active`), badges (`title`, `icon`, `criteria_type`, `criteria_value`, `is_active`).
- [ ] Each tab: list + inline create/edit form + delete. Read the enum vocabularies from `app/Enums/CommunityGoalEarnType.php` and `app/Enums/CommunityBadgeCriteriaType.php` and hardcode the option lists to match — do not invent values.
- [ ] Commit: `feat(webapp): community economy — goals, rewards and badges CRUD`

---

## Task 18: Leaderboard + Settings

**Files:** Create `resources/views/webapp/community-leaderboard.blade.php`, `resources/views/webapp/community-settings.blade.php`

- [ ] **Leaderboard:** `GET /communities/{id}/leaderboard` → rank, avatar, name, tier chip, badge count, points. Top three get a subtle highlight.
- [ ] **Settings:** `PATCH /communities/{id}` for name / type / description / `join_policy`; avatar via `kb.uploadFile(file, 'communities')` then `PATCH` with the returned URL. Invite link block: the canonical `invite_url` with a copy button, plus (for `invite_only`) the token link from `GET /communities/{id}/invite`.
- [ ] The settings page surfaces `422 community_limit_reached` honestly if a create is ever attempted — do not hide the cap.
- [ ] Commit: `feat(webapp): community leaderboard and settings`

---

## Task 19: i18n — es/ca to 100%

**Files:** Modify `lang/en/webapp.php`, `lang/es/webapp.php`, `lang/ca/webapp.php`

- [ ] Add a `community.*` block covering every string in Tasks 12–18 plus `nav.community`. Mirror the structure of the existing `subscription.*` block.
- [ ] Every Blade string goes through `__('webapp.community.…')`; every JS string through `window.t('community.…')`.
- [ ] Test: extend `tests/Feature/WebApp/WebAppRoutesTest.php` to assert `/es/community/members` and `/ca/community/members` render localised copy (assert one known Spanish and one known Catalan string).
- [ ] Verify parity — every key present in all three files:

```bash
php -r '$en=require "lang/en/webapp.php"; $es=require "lang/es/webapp.php"; $ca=require "lang/ca/webapp.php"; $f=function($a,$p="") use(&$f){$o=[];foreach($a as $k=>$v){$o=array_merge($o,is_array($v)?$f($v,"$p$k."):["$p$k"]);}return $o;}; $e=$f($en); foreach(["es"=>$es,"ca"=>$ca] as $n=>$l){$m=array_diff($e,$f($l)); echo $n.": ".(count($m)?implode(", ",$m):"complete")."\n";}'
```

Expected: `es: complete` / `ca: complete`.

- [ ] Commit: `feat(webapp): es/ca localisation for the Community Hub`

---

## Task 20: The §8.4 guard test

**Files:** Create `tests/Feature/Api/V1/CommunityNeverPaywalledTest.php`

ROLES §6 names paywalling a community as the single most-repeated regression in this codebase. This test is not optional.

- [ ] **Step 1: Write it**

```php
    /**
     * ROLES §8.4 — the community members & tiers surface is NEVER paywalled.
     * Every endpoint in BE-NF-29, exercised by an owner with no subscription
     * and by a can_manage attendee with no subscription.
     */
    public function test_no_endpoint_in_the_community_hub_is_subscription_gated(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $manager->id,
            'can_manage' => true,
        ]);

        $this->assertFalse($community->owner->hasActiveSubscription());
        $this->assertFalse($manager->hasActiveSubscription());

        foreach ([$community->owner, $manager] as $actor) {
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/members")->assertOk();
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/members/{$member->id}")->assertOk();
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/stats")->assertOk();
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/invitations")->assertOk();
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/tiers")->assertOk();
            $this->actingAs($actor)->getJson("/api/v1/communities/{$community->id}/join-requests")->assertOk();
            $this->actingAs($actor)
                ->postJson("/api/v1/communities/{$community->id}/invitations", ['email' => uniqid().'@example.com'])
                ->assertCreated();
            $this->actingAs($actor)
                ->patchJson("/api/v1/communities/{$community->id}/members/{$member->id}", ['tier_id' => $tier->id])
                ->assertOk();
        }
    }
```

- [ ] **Step 2: Grep for the forbidden call in everything this feature touched**

```bash
grep -rn "hasActiveSubscription" app/Services/CommunityRosterQuery.php app/Services/CommunityStatsService.php app/Services/CommunityInvitationService.php app/Http/Controllers/Api/V1/CommunityStatsController.php app/Http/Controllers/Api/V1/CommunityInvitationController.php app/Http/Controllers/Api/V1/CommunityMemberController.php app/Http/Controllers/CommunityJoinPageController.php
```

Expected: **no output**.

- [ ] **Step 3: Run and commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/CommunityNeverPaywalledTest.php
git add tests/Feature/Api/V1/CommunityNeverPaywalledTest.php
git commit -m "test(communities): assert the members surface is never paywalled (ROLES §8.4)

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 21: Docs, full suite, PR

**Files:** `docs/ROLES-AND-PERMISSIONS.md`, `docs/ROLES-BACKEND-DB-MAP.md`, `BACKLOG.md`

- [ ] **Step 1: ROLES §8** — add **§8.7 Managing members on the web**: the Hub's seven surfaces, the owner-or-`can_manage` access model (explicitly *not* `user_type`), email invitations + claim-on-register, the `/c/{slug}` landing page, and a restatement that none of it is paywalled. Bump *Last updated* at the top of the file.

- [ ] **Step 2: Backend map §12** — add: the `community_invitations` table (every column), the new endpoints with their auth and error codes, the roster's new query params, **the roster default-status behaviour change** (removed members now excluded; `?status=all` opts back in), the roster's new manage gate, the claim-on-register hook and its guard contract, and the `/c/{slug}` route. Bump *Last updated*.

- [ ] **Step 3: `BACKLOG.md`** — add BE-NF-29 under *Incomplete Features* with what shipped and what is pending (prod migrate, `COMMUNITIES_INVITATION_TTL_DAYS`, queue worker for the invitation mail). Bump *Last updated*.

- [ ] **Step 4: Full suite + pint**

```bash
vendor/bin/pint
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
```

Paste the real counts into the PR. Do not claim green without this output.

- [ ] **Step 5: Branch + PR**

```bash
git checkout -b feat/community-members-web-panel   # if not already on it
git push -u origin feat/community-members-web-panel
gh pr create --fill
```

Fill **every** section of `.github/pull_request_template.md`. The **Mobile impact** section must say, explicitly:

> `GET /communities/{id}/members` gains optional query params (`search`, `status`, `tier_id`, `can_manage`, `sort`, `direction`) and additive response fields (`points`, `events_attended`, `last_active_at`, `tenure_days`, `profile.handle`, `profile.email`). **Two behaviour changes:** the default result set now excludes `status=removed` (pass `?status=all` for the old set), and the endpoint is now manage-gated (owner / `can_manage`) because it carries member emails. Mobile has no Community tab yet, so nothing breaks today — kolabing-app ticket: `<link>`. A new invitations resource (`/communities/{id}/invitations`, `/invitations/*`) is available for mobile to adopt later.

Tick the *Docs & rules updated* boxes and paste the test counts into *Testing*.

---

## Build order

Run tasks in numeric order **with one exception**: `CommunityStatsService::pending()` reads `community_invitations`, so **Task 5 (the migration) must run before Task 4's test can pass.** Either run Task 5 first, or run Task 4 through Step 3 and return to it after Task 5.

Everything else is strictly sequential — each task's tests depend on the previous task's code.

## Self-review notes

- **Spec coverage:** every section of the spec maps to a task — §4.1→T2/T3, §4.2→T4, §4.3→T5/T6/T7/T8, §4.4→T9, §4.5→T10, §4.6→T11, §4.7→T20, §5→T12–T18, §5.3→T19, §7→each task's test step + T20, §8→T21. §6 ("what we do NOT build") has no task by design.
- **Extra beyond the spec:** Task 1 (D5, the display-name bug) and the `kb.rows()` fix in Task 12 were found while planning; both are prerequisites for the roster rendering correctly.
- **Naming consistency:** `CommunityRosterQuery::paginate()` / `::base()`, `CommunityMemberService::roster($community, $perPage, $filters)`, `CommunityInvitationService::invite()/accept()/revoke()/resend()/claimForSafely()`, `CommunityStatsService::forCommunity()` — used identically everywhere they appear.
