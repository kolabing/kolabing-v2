# Community Members + Customisable Tiers (NF-6, Phase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans or subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give a Community Leader (the `community` user type) a member roster and a per-community, leader-defined tier (status ladder) system, reusing the live gamification track for auto-assignment, without ever touching the business paywall.

**Architecture:** Three new tables (`communities`, `community_tiers`, `community_members`) + a nullable `events.community_id` linkage. Service-layer logic only (no DB triggers). A `CommunityPolicy` gates mutation to owner-or-`can_manage`. A new free-tier cap (`config('communities.max_free_communities', 1)`) — a NEW gate, never `hasActiveSubscription()`. Auto-assignment runs in a nightly command plus an on-check-in hook. The existing leaderboard endpoint gains an optional `community_id` scope.

**Tech Stack:** Laravel 12, PHP 8.4, Sanctum, PostgreSQL (prod) / sqlite `:memory:` (tests, `LazilyRefreshDatabase`), PHPUnit.

**Wire contract:** App Dart models in `kolabing-app/lib/features/community/models/`. Resources MUST serialise field-for-field to the §0.4 shapes in the backend prompt (snake_case keys, nested `tier`+`profile`).

---

## Locked decisions (from spec §1)
- **D1** tier ⟂ admin: `can_manage` is a separate boolean; never couple top tier to admin.
- **D2** multi-community: a member belongs to many communities, one tier per community (tier on the membership row).
- **D3** tier carries a flexible `permissions` JSON; stored + returned in Phase 1, enforcement later.
- **D4** wire value stays `attendee`; "Community Member" is an app label only. Do NOT touch the `UserType` enum.
- **D5** one community free per leader; 2nd+ → `422 community_limit_reached`. NEW gate; never reuse the business paywall.
- Join model: `open` (self-join + invite) | `invite_only` (leader/can_manage add only). Default `open`.
- Cash-out: tiers carry status + non-cash perks only. Do NOT wire tiers into wallets/withdrawals.

## Linkage decision (spec §0.6) — DOCUMENT IN PR
- Add nullable `events.community_id` FK → `communities` (nullOnDelete). The **`events_attended`** rule counts `event_checkins` for the member joined to events where `events.community_id = {community}`. The **chapter leaderboard** is scoped by **membership** (active `community_members` of the community) — i.e. "the global leaderboard filtered to one community", which is what the member surface shows. Organiser's events are NOT silently assumed to be the community's events; the explicit `events.community_id` link is the source of truth for the rule.

## XP source decision
- `xp_threshold` rule reads XP as `SUM(point_ledger.points)` for the member profile (point_ledger is append-only source of truth, per spec §0.6). When NF-5 `GET /gamification/config` ships, swap the threshold *source of truth* there; do not hardcode point values.

## Verified conventions to mirror
- Migrations: `$table->uuid('id')->primary()`; FKs `$table->foreignUuid('x')->constrained('table')->cascadeOnDelete()` (or `->nullOnDelete()` when nullable). String columns for enums (cast in model). No DB triggers.
- Models: `use HasFactory, HasUuids;` `$fillable` list; `protected function casts(): array`; typed relationships with PHPDoc generics.
- Enums: `app/Enums`, string-backed, PascalCase cases / snake_case values, `public static function values(): array` helper.
- Controllers: constructor-inject service; return `response()->json(['success' => true, 'data' => ...], $status)`; 201 on create, 403 unauthorized, 422 validation/domain.
- Resources: `@mixin Model`; `toArray(Request)`; timestamps `->toIso8601String()`, dates `->toDateString()`; nested `Resource::collection($this->whenLoaded('rel'))`.
- Policies: first arg `Profile $user`; register in `app/Providers/AppServiceProvider.php::registerPolicies()` with `Gate::policy(...)`.
- Auth in code/tests: `$request->user()` is a `Profile`; tests use `$this->actingAs($profile)` + `getJson/postJson`.
- Tests: `use Illuminate\Foundation\Testing\LazilyRefreshDatabase;` (NOT DatabaseTransactions — match siblings), factories with states (`Profile::factory()->community()->create()`).
- Routes: `Route::prefix('v1')` → `->middleware(['auth:sanctum','log_auth_token_first_use','touch_profile_activity'])` group.
- `vendor/bin/pint --dirty` before finalising. Run tests with `php artisan test --compact --filter=...`.

---

## Task 1: Enums

**Files:**
- Create: `app/Enums/CommunityType.php` — `Greek=greek, Fitness=fitness, Running=running, Business=business, Other=other`
- Create: `app/Enums/TierAssignmentRule.php` — `Manual=manual, XpThreshold=xp_threshold, Tenure=tenure, EventsAttended=events_attended`
- Create: `app/Enums/JoinPolicy.php` — `Open=open, InviteOnly=invite_only`
- Create: `app/Enums/CommunityMemberStatus.php` — `Active=active, Inactive=inactive, Removed=removed`
- Test: `tests/Unit/Enums/CommunityEnumsTest.php`

Each enum is `enum X: string` with a `public static function values(): array { return array_column(self::cases(), 'value'); }`. `TierAssignmentRule` also gets `public function requiresThreshold(): bool { return $this !== self::Manual; }`.

- [ ] Write `tests/Unit/Enums/CommunityEnumsTest.php` asserting `values()` content for each enum and `TierAssignmentRule::Manual->requiresThreshold() === false`, `XpThreshold->requiresThreshold() === true`.
- [ ] Run `php artisan test --compact --filter=CommunityEnumsTest` → FAIL (enums missing).
- [ ] Create the four enums.
- [ ] Re-run → PASS. Commit.

## Task 2: Migrations

**Files:**
- Create: `database/migrations/2026_06_03_000001_create_communities_table.php`
- Create: `database/migrations/2026_06_03_000002_create_community_tiers_table.php`
- Create: `database/migrations/2026_06_03_000003_create_community_members_table.php`
- Create: `database/migrations/2026_06_03_000004_add_community_id_to_events_table.php`

`communities`: `uuid id pk`; `foreignUuid owner_profile_id constrained('profiles') cascadeOnDelete`; `foreignUuid community_profile_id nullable constrained('community_profiles') nullOnDelete`; `string name`; `string slug unique`; `string('type',20)`; `text description nullable`; `string avatar_url nullable`; `boolean is_primary default true`; `string('join_policy',20) default 'open'`; `timestamps`; `softDeletes`. Index `owner_profile_id`.

`community_tiers`: `uuid id pk`; `foreignUuid community_id constrained('communities') cascadeOnDelete`; `string name`; `integer rank`; `string('color',9) nullable`; `string('assignment_rule',20) default 'manual'`; `integer threshold nullable`; `json permissions nullable`; `boolean is_default default false`; `timestamps`. Index `community_id`.

`community_members`: `uuid id pk`; `foreignUuid community_id constrained('communities') cascadeOnDelete`; `foreignUuid profile_id constrained('profiles') cascadeOnDelete`; `foreignUuid tier_id nullable constrained('community_tiers') nullOnDelete`; `boolean can_manage default false`; `string('status',20) default 'active'`; `timestamp joined_at`; `timestamp tier_assigned_at nullable`; `timestamps`. `unique(['community_id','profile_id'])`; index `profile_id`.

`events.community_id`: `foreignUuid community_id nullable after('profile_id') constrained('communities') nullOnDelete`. (sqlite-portable: plain add-column, no ALTER of existing column.)

- [ ] Write all four migrations.
- [ ] Run `php artisan migrate:fresh` (test sqlite is auto-built per test run; also run real migrate to catch pg issues) → no errors.
- [ ] Commit.

## Task 3: Models + relationships + factories

**Files:**
- Create: `app/Models/Community.php`, `app/Models/CommunityTier.php`, `app/Models/CommunityMember.php`
- Create: `database/factories/CommunityFactory.php`, `CommunityTierFactory.php`, `CommunityMemberFactory.php`
- Modify: `app/Models/Profile.php` (add `ownedCommunities(): HasMany`, `communityMemberships(): HasMany`)
- Modify: `app/Models/Event.php` (add `community_id` to `$fillable`, add `community(): BelongsTo`)
- Test: `tests/Unit/Models/CommunityRelationshipsTest.php`

`Community`: `HasFactory, HasUuids, SoftDeletes`. `$fillable`: owner_profile_id, community_profile_id, name, slug, type, description, avatar_url, is_primary, join_policy. casts: `type => CommunityType::class`, `join_policy => JoinPolicy::class`, `is_primary => 'boolean'`. Relations: `owner(): BelongsTo Profile` (FK owner_profile_id), `communityProfile(): BelongsTo`, `tiers(): HasMany CommunityTier`, `members(): HasMany CommunityMember`, `defaultTier(): HasOne CommunityTier` (where is_default true). Helper `memberCount(): int` = active members count.

`CommunityTier`: casts `assignment_rule => TierAssignmentRule::class`, `threshold => 'integer'`, `permissions => 'array'`, `is_default => 'boolean'`, `rank => 'integer'`. Relations `community(): BelongsTo`, `members(): HasMany CommunityMember` (FK tier_id).

`CommunityMember`: casts `can_manage => 'boolean'`, `status => CommunityMemberStatus::class`, `joined_at => 'datetime'`, `tier_assigned_at => 'datetime'`. Relations `community(): BelongsTo`, `profile(): BelongsTo`, `tier(): BelongsTo CommunityTier`.

Factories: `CommunityFactory` default `is_primary=true, type=other, join_policy=open, slug=Str::slug(name).'-'.Str::random(6)`, state `forOwner(Profile)`, `inviteOnly()`. `CommunityTierFactory` default `rank=1, assignment_rule=manual, is_default=false`, states `defaultTier()`, `xpThreshold(int)`, `tenure(int)`, `eventsAttended(int)`. `CommunityMemberFactory` default `status=active, joined_at=now()`, state `forCommunity(Community)`, `manager()`.

- [ ] Write `CommunityRelationshipsTest`: create community via factory `forOwner`, assert `owner`, `tiers`, `members`, `defaultTier`; create member, assert `community`/`profile`/`tier`; assert `Profile::ownedCommunities` and `communityMemberships` resolve; assert `Event` `community` relation.
- [ ] Run → FAIL. Implement models + factories + Profile/Event edits.
- [ ] Re-run → PASS. Commit.

## Task 4: Config + domain exception

**Files:**
- Create: `config/communities.php` → `['max_free_communities' => env('COMMUNITIES_MAX_FREE', 1)]`
- Create: `app/Exceptions/CommunityLimitReachedException.php` (extends `\DomainException`)

- [ ] Create both. (Covered by Task 6 tests.) Commit with Task 6.

## Task 5: CommunityPolicy

**Files:**
- Create: `app/Policies/CommunityPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php` (register `Gate::policy(Community::class, CommunityPolicy::class)`)
- Test: `tests/Feature/Api/V1/CommunityPolicyTest.php` (or unit) — covered via endpoint tests too.

`manage(Profile $user, Community $community): bool` = `$user->id === $community->owner_profile_id || $community->members()->where('profile_id',$user->id)->where('can_manage',true)->where('status',CommunityMemberStatus::Active->value)->exists()`. Also `view` = true (any auth user may view a community/its public roster — Phase 1: allow). NEVER reference subscription.

- [ ] Write policy test: owner can manage; can_manage active member can manage; plain member cannot; outsider cannot.
- [ ] FAIL → implement + register → PASS. Commit.

## Task 6: CommunityService — create with cap + default tier

**Files:**
- Create: `app/Services/CommunityService.php`
- Test: `tests/Feature/Api/V1/CommunityCreateTest.php`

`create(Profile $owner, array $data): Community` in a `DB::transaction`: enforce cap — if `Community::where('owner_profile_id',$owner->id)->count() >= config('communities.max_free_communities')` throw `CommunityLimitReachedException`. Create community (`is_primary` = (count==0)). Auto-create default tier: name `'Member'`, rank 1, assignment_rule `manual`, is_default true, permissions `{view:[],chat_channels:[],perks:[],capabilities:[]}`. Return `$community->load('tiers')`.

- [ ] Tests: community user creates 1 → ok, default tier exists (is_default, manual); 2nd create → `CommunityLimitReachedException`; default tier auto-created with the empty permissions blob.
- [ ] FAIL → implement service + Task 4 files → PASS. Commit.

## Task 7: Tier CRUD service + default invariant

**Files:**
- Create: `app/Services/CommunityTierService.php`
- Test: `tests/Feature/Api/V1/CommunityTierCrudTest.php`

Methods: `create(Community,$data): CommunityTier` (if `is_default` requested, unset other defaults in a tx); `update(CommunityTier,$data)` (same default handling); `delete(CommunityTier)` — refuse (`\DomainException 'cannot_delete_default_tier'`) if `is_default` and it's the only/last default, unless `$data` promotes another. Validate threshold required when `assignment_rule !== manual`. Guarantee exactly one default per community always.

- [ ] Tests: create manual/xp/tenure/events tiers; setting a new default unsets old; deleting default rejected; deleting non-default ok; threshold required for non-manual.
- [ ] FAIL → implement → PASS. Commit.

## Task 8: Roster service — join + invite + manage

**Files:**
- Create: `app/Services/CommunityMemberService.php`
- Test: `tests/Feature/Api/V1/CommunityMemberRosterTest.php`

`join(Community,$profile): CommunityMember` — only if `join_policy === Open` else throw `\DomainException 'invite_only'`; idempotent on unique; new members land in `defaultTier`. `addMember(Community,$profileId): CommunityMember` (leader/can_manage path) — same default-tier landing, allowed regardless of join_policy. `updateMember(CommunityMember,$data)` — set `tier_id` (manual promote → stamp `tier_assigned_at`), `can_manage`, `status`. `remove(CommunityMember)` — set status `removed` (soft) per spec status enum. `roster(Community,$perPage)` paginated with `tier` + `profile` eager-loaded.

- [ ] Tests: open self-join lands in default tier; invite_only self-join throws; leader add works on invite_only; can_manage independent of tier; manual tier set stamps tier_assigned_at; unique (community,profile) enforced.
- [ ] FAIL → implement → PASS. Commit.

## Task 9: TierAssignmentService — rule evaluation + hook

**Files:**
- Create: `app/Services/TierAssignmentService.php`
- Test: `tests/Unit/Services/TierAssignmentServiceTest.php`

`evaluateMember(CommunityMember): void` — skip if status !== active. Gather the community's tiers ordered by rank DESC, excluding `manual` tiers. Pick the highest-rank tier whose rule is satisfied:
- `xp_threshold`: `SUM(point_ledger.points where profile_id=member)` ≥ threshold.
- `tenure`: `now()->diffInDays(joined_at)` ≥ threshold.
- `events_attended`: `EventCheckin::where(profile_id)->whereIn(event_id, Event::where('community_id',$community->id)->select('id'))->count()` ≥ threshold.
Only promote if the chosen tier's rank > current tier rank (never demote, never overwrite a manual assignment — i.e. if member's current tier is_default-or-manual we may still promote upward via auto rules; but never move a member OFF an auto tier down, and never auto-touch a member the leader manually placed on a `manual` tier above default). Set `tier_id` + `tier_assigned_at` only when changed.
`evaluateCommunity(Community): int` — loop active members, return count changed.

Rule for "manual never auto-overwritten": if the member's current `tier->assignment_rule === Manual` and it is not the default tier, skip (leader-set). The default tier is the floor and may be auto-promoted away from.

- [ ] Tests (one per rule): member meeting xp threshold promoted; tenure days; events_attended counts only this community's events (create event with community_id, check-ins; a check-in on another community's event does NOT count); manual tier member untouched; highest satisfied rank wins when multiple satisfied; no demotion.
- [ ] FAIL → implement → PASS. Commit.

## Task 10: Resources

**Files:**
- Create: `app/Http/Resources/Api/V1/CommunityResource.php`, `CommunityTierResource.php`, `CommunityMemberResource.php`
- Test: `tests/Feature/Api/V1/CommunityResourceShapeTest.php`

Shapes EXACTLY per spec §0.4:
- `CommunityResource`: id, owner_profile_id, community_profile_id, name, slug, type (string value), description, avatar_url, is_primary, join_policy (string value), member_count (active count), created_at/updated_at iso8601.
- `CommunityTierResource`: id, community_id, name, rank, color, assignment_rule (value), threshold, permissions (object with view/chat_channels/perks/capabilities defaulting to `[]`), is_default, created_at/updated_at.
- `CommunityMemberResource`: id, community_id, profile_id, `tier` => `new CommunityTierResource($this->whenLoaded('tier'))` (nested), `tier_id`, can_manage, status (value), joined_at iso8601, tier_assigned_at iso8601|null, `profile` => `{name, avatar_url}` (from member's profile extended display name + avatar), created_at/updated_at.

- [ ] Tests assert each key present + correct types/enum string values + nested tier/profile present.
- [ ] FAIL → implement → PASS. Commit.

## Task 11: Form Requests

**Files:**
- Create: `StoreCommunityRequest`, `UpdateCommunityRequest`, `StoreCommunityTierRequest`, `UpdateCommunityTierRequest`, `StoreCommunityMemberRequest`, `UpdateCommunityMemberRequest` under `app/Http/Requests/Api/V1/`

Array-syntax rules, `authorize(): true` (policy handles auth), custom `messages()`. Key rules:
- Store community: name required string 2..100; type `Rule::in(CommunityType::values())`; description nullable string; avatar_url nullable url; join_policy nullable `Rule::in(JoinPolicy::values())`; community_profile_id nullable uuid exists.
- Tier store: name required; rank required integer min:1; color nullable hex regex; assignment_rule `Rule::in(TierAssignmentRule::values())`; threshold `nullable|integer|min:0` + `required` when rule != manual (use `Rule::requiredIf`); permissions nullable array.
- Member store: profile_id required uuid exists profiles. Member update: tier_id nullable uuid exists community_tiers; can_manage nullable boolean; status nullable `Rule::in(CommunityMemberStatus::values())`.

- [ ] Created + exercised by Task 12 endpoint tests. Commit with Task 12.

## Task 12: Controllers

**Files:**
- Create: `CommunityController`, `CommunityTierController`, `CommunityMemberController` under `app/Http/Controllers/Api/V1/`
- Test: `tests/Feature/Api/V1/CommunityEndpointsTest.php`

`CommunityController`: `store` (create — catch `CommunityLimitReachedException` → 422 `{success:false, error:'community_limit_reached', message:...}`), `index` = `GET /me/communities` (owned), `show`, `update` (authorize 'manage'). `myMemberships` = `GET /me/memberships` (communities the auth profile is an active member of + their tier).
`CommunityTierController`: `index`, `store`, `update`, `destroy` (authorize 'manage' on parent community; destroy catches `cannot_delete_default_tier` → 422).
`CommunityMemberController`: `index` (roster paginated), `store` (invite/add, authorize manage), `update`, `destroy`, `join` (POST /communities/{c}/join — uses service join, catches `invite_only` → 403).

All return `{success:true,data:...}`; 201 on store.

- [ ] Endpoint tests: full journey — community user creates community (201, default tier in payload); 2nd → 422 community_limit_reached; GET /me/communities; tier CRUD via HTTP; roster GET; POST members (invite) by owner; POST /join on open community by an attendee; POST /join on invite_only → 403; PATCH member tier/can_manage/status; GET /me/memberships returns tier. Assert **no** 402/paywall ever appears and a community/attendee path never calls hasActiveSubscription.
- [ ] FAIL → implement controllers + Task 11 requests + routes (Task 13) → PASS. Commit.

## Task 13: Routes

**Files:** Modify `routes/api.php` inside the `auth:sanctum` v1 group.

```
Route::get('me/communities', [CommunityController::class, 'index']);
Route::get('me/memberships', [CommunityController::class, 'myMemberships']);
Route::post('communities', [CommunityController::class, 'store']);
Route::get('communities/{community}', [CommunityController::class, 'show']);
Route::patch('communities/{community}', [CommunityController::class, 'update']);
Route::get('communities/{community}/tiers', [CommunityTierController::class, 'index']);
Route::post('communities/{community}/tiers', [CommunityTierController::class, 'store']);
Route::patch('tiers/{tier}', [CommunityTierController::class, 'update']);
Route::delete('tiers/{tier}', [CommunityTierController::class, 'destroy']);
Route::get('communities/{community}/members', [CommunityMemberController::class, 'index']);
Route::post('communities/{community}/members', [CommunityMemberController::class, 'store']);
Route::patch('communities/{community}/members/{member}', [CommunityMemberController::class, 'update']);
Route::delete('communities/{community}/members/{member}', [CommunityMemberController::class, 'destroy']);
Route::post('communities/{community}/join', [CommunityController::class, 'join']);
```
(`{member}` binds `CommunityMember`; scope check member belongs to community in controller.)

- [ ] Covered by Task 12 tests. Commit with Task 12.

## Task 14: Chapter-scoped leaderboard

**Files:**
- Modify: `app/Services/LeaderboardService.php` (add `getCommunityLeaderboard(Community,$limit)`, `getMyCommunityRank(Community,$profile)`)
- Modify: `app/Http/Controllers/Api/V1/LeaderboardController.php` (`globalLeaderboard` reads optional `community_id`; when present, resolve `Community` and delegate to community methods)
- Test: `tests/Feature/Api/V1/LeaderboardTest.php` (extend)

`getCommunityLeaderboard`: same shape as global but `AttendeeProfile` filtered to `profile_id IN (active community_members of community)`. `getMyCommunityRank`: null if not an active member, else rank within that filtered set.

- [ ] Test: two attendees, both members of community A, one also higher global — community leaderboard for A ranks only members; a non-member with high points excluded; `community_id` of unknown id → empty/404 (choose: validate exists → 404).
- [ ] FAIL → implement → PASS. Commit.

## Task 15: Auto-assignment command + check-in hook + schedule

**Files:**
- Create: `app/Console/Commands/EvaluateCommunityTiers.php` (signature `app:evaluate-community-tiers {--dry-run}`)
- Modify: `routes/console.php` (`Schedule::command('app:evaluate-community-tiers')->dailyAt('02:00');`)
- Modify: `app/Services/CheckinService.php` (after attendee check-in increment, evaluate tiers for the member's active memberships of communities owning that event — call `TierAssignmentService` for memberships in communities where `events.community_id` matches, plus any membership using xp/events rules)
- Test: `tests/Feature/Api/V1/CommunityTierEvaluationTest.php`

Command: loop all communities, call `TierAssignmentService::evaluateCommunity`; report counts; `--dry-run` reports without writing.
Check-in hook: after `total_events_attended` increment, for each active `CommunityMember` of `$profile`, call `evaluateMember` (cheap; promotes on event/xp rules immediately). Wrap so a hook failure never breaks check-in (try/catch, log).

- [ ] Tests: command promotes eligible members and is idempotent; `--dry-run` writes nothing; checking into a community-linked event promotes the member to the events_attended tier in that community without a cron run.
- [ ] FAIL → implement → PASS. Commit.

## Task 16: ROLES docs update + mirror

**Files:**
- Modify: `docs/ROLES-AND-PERMISSIONS.md` — add Attendee/Community-Member column already exists in §5 matrix; add NEW **§9 Community Members & customisable tiers** (roster, tiers, cap, join policy, can_manage⟂tier, auto-assignment, event linkage, "never paywalled"). Bump *Last updated* to 2026-06-03.
- Modify: `docs/ROLES-BACKEND-DB-MAP.md` — add a NEW section mapping the three tables + `events.community_id`, endpoints, `CommunityPolicy`, the cap config (NOT the business paywall), the command + hook; add a hot-spot note that the cap is a NEW non-subscription gate; tick/add checklist items. Bump date.
- Mirror BOTH files into `../kolabing-app/docs/` (keep identical).
- Modify `CLAUDE.md` MUST-READ block to mention the new community-members/tiers surface.

- [ ] Update both docs + CLAUDE.md, copy both into kolabing-app/docs, bump dates. Commit.

## Final verification
- [ ] `vendor/bin/pint --dirty`
- [ ] `php artisan test --compact` (full suite green; no regressions in paywall/gamification tests)
- [ ] Grep guard: no new file under community/tier/member paths references `hasActiveSubscription`/`SubscriptionRequired`.

## Self-review notes
- Spec coverage: cap (T6/T12), policy (T5), each rule (T9), join policy (T8/T12), event linkage (T2/T9/T14), resource shapes (T10), docs (T16) — all mapped.
- No business-paywall code path is touched; cap is its own config + exception.
- Type consistency: `assignment_rule` (TierAssignmentRule), `join_policy` (JoinPolicy), `status` (CommunityMemberStatus) used identically across migrations/models/requests/resources.
