# Kolabing Launch Fixes Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the Laravel API in line with the Flutter launch-fix contract for auth refresh, kolab drafts/publish/media, upload responses, application acceptance, business categories, venue echoing, and the public email banner asset.

**Architecture:** Keep the existing Laravel layering intact: thin controllers, service-level orchestration, resource-based response shaping, and request validation as the contract gate. Add refresh-token support on top of Sanctum without replacing the current auth stack, and use compatibility fields where the old mobile/web payloads still depend on single-value business types or string-only media fields.

**Tech Stack:** Laravel 12, Sanctum personal access tokens, Eloquent models/resources, PHPUnit feature tests, database factories/seeders, public static assets.

---

## File Map

**Auth and tokens**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Http/Resources/Api/V1/UserResource.php`
- Create: `app/Http/Requests/Api/V1/RefreshTokenRequest.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`

**Seeded account compatibility**
- Modify: `database/seeders/RealisticDataSeeder.php`
- Possibly modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`

**Kolabs, publish gating, and media contracts**
- Modify: `app/Http/Controllers/Api/V1/KolabController.php`
- Modify: `app/Services/KolabService.php`
- Modify: `app/Http/Resources/Api/V1/KolabResource.php`
- Modify: `app/Http/Controllers/Api/V1/UploadController.php`
- Modify: `app/Services/FileUploadService.php`
- Modify: `app/Enums/FileUploadType.php`
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateKolabRequest.php`
- Test: `tests/Feature/Api/V1/KolabPublishCloseTest.php`
- Test: `tests/Feature/Api/V1/KolabBrowseTest.php`
- Create or modify: `tests/Feature/Api/V1/KolabCrudTest.php`
- Create: `tests/Feature/Api/V1/UploadControllerTest.php`

**Application acceptance**
- Modify: `app/Policies/ApplicationPolicy.php`
- Modify: `app/Services/ApplicationService.php`
- Modify: `app/Http/Controllers/Api/V1/ApplicationController.php`
- Modify: `app/Http/Resources/Api/V1/ApplicationResource.php`
- Test: `tests/Feature/Api/V1/ApplicationAcceptTest.php`

**Business categories and venue echoing**
- Create: `database/migrations/2026_04_28_000000_add_categories_to_business_profiles_table.php`
- Modify: `app/Models/BusinessProfile.php`
- Modify: `app/Http/Requests/Api/V1/RegisterBusinessRequest.php`
- Modify: `app/Http/Requests/Api/V1/BusinessOnboardingRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateProfileRequest.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Services/OnboardingService.php`
- Modify: `app/Services/ProfileService.php`
- Modify: `app/Http/Resources/Api/V1/BusinessProfileResource.php`
- Modify: `app/Http/Resources/Api/V1/PublicProfileResource.php`
- Modify: `app/Http/Resources/Api/V1/ProfileSummaryResource.php`
- Modify: `database/factories/BusinessProfileFactory.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`
- Test: `tests/Feature/Api/V1/OnboardingControllerTest.php`
- Test: `tests/Feature/Api/V1/ProfileControllerTest.php`

**Public banner asset**
- Create directory if missing: `public/community/kolabing/marketing/brand/`
- Copy or create: `public/community/kolabing/marketing/brand/logo-wordmark-banner-dark.png`

### Task 1: Add refresh-token auth flow

**Files:**
- Create: `app/Http/Requests/Api/V1/RefreshTokenRequest.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Http/Resources/Api/V1/UserResource.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`

- [ ] **Step 1: Write the failing auth refresh tests**

```php
public function test_login_returns_access_and_refresh_tokens(): void
{
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'validuser@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'token',
                'token_type',
                'refresh_token',
                'refresh_token_expires_at',
                'user',
            ],
        ]);
}

public function test_refresh_returns_new_access_token_and_user_payload(): void
{
    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'validuser@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refresh_token' => $login->json('data.refresh_token'),
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.user_type', 'business');
}
```

- [ ] **Step 2: Run the auth refresh tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php --filter=refresh`
Expected: FAIL because `POST /api/v1/auth/refresh` and `refresh_token` are not implemented.

- [ ] **Step 3: Implement request validation and route**

```php
Route::post('auth/refresh', [AuthController::class, 'refresh'])
    ->name('api.v1.auth.refresh');
```

```php
return [
    'refresh_token' => ['required', 'string'],
];
```

- [ ] **Step 4: Implement token-pair issuance and refresh handling in `AuthService`**

```php
private function issueTokenPair(Profile $profile): array
{
    $accessToken = $profile->createToken(
        name: 'mobile-access',
        abilities: [$profile->user_type->value],
        expiresAt: now()->addDays(30)
    );

    $refreshToken = $profile->createToken(
        name: 'mobile-refresh',
        abilities: ['refresh'],
        expiresAt: now()->addDays(90)
    );

    return [
        'token' => $accessToken->plainTextToken,
        'refresh_token' => $refreshToken->plainTextToken,
        'refresh_token_expires_at' => now()->addDays(90)->toIso8601String(),
    ];
}
```

- [ ] **Step 5: Make login, register, Google, and Apple responses reuse the token-pair contract**

```php
'data' => [
    ...$result['tokens'],
    'token_type' => 'Bearer',
    'user' => new UserResource($result['profile']),
]
```

- [ ] **Step 6: Run the focused auth tests**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php`
Expected: PASS for login/register/me/refresh coverage with no refresh regressions.

### Task 2: Make seeded accounts usable for launch QA

**Files:**
- Modify: `database/seeders/RealisticDataSeeder.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`

- [ ] **Step 1: Write a seeded-account compatibility test**

```php
public function test_seeded_profile_has_password_credentials_for_login_flow(): void
{
    $this->seed(\Database\Seeders\RealisticDataSeeder::class);

    $profile = \App\Models\Profile::query()->firstOrFail();

    $this->assertNotNull($profile->password);
    $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $profile->password));
}
```

- [ ] **Step 2: Run the seeded-account test to verify it fails**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php --filter=seeded_profile_has_password`
Expected: FAIL because seeded profiles do not currently store a password.

- [ ] **Step 3: Add a deterministic password and test-user-friendly setup inside the seeder**

```php
'password' => 'password123',
'email_verified_at' => now(),
'is_test_user' => true,
```

- [ ] **Step 4: Re-run the focused seeded-account test**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php --filter=seeded_profile_has_password`
Expected: PASS.

### Task 3: Align kolab publish, draft listing, show payloads, and upload contracts

**Files:**
- Modify: `app/Http/Controllers/Api/V1/KolabController.php`
- Modify: `app/Services/KolabService.php`
- Modify: `app/Http/Resources/Api/V1/KolabResource.php`
- Modify: `app/Http/Controllers/Api/V1/UploadController.php`
- Modify: `app/Services/FileUploadService.php`
- Modify: `app/Enums/FileUploadType.php`
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateKolabRequest.php`
- Test: `tests/Feature/Api/V1/KolabPublishCloseTest.php`
- Test: `tests/Feature/Api/V1/KolabBrowseTest.php`
- Test: `tests/Feature/Api/V1/KolabCrudTest.php`
- Test: `tests/Feature/Api/V1/UploadControllerTest.php`

- [ ] **Step 1: Write failing tests for publish `402` payload, draft listing, editable show payload, and canonical upload response**

```php
$response->assertStatus(402)
    ->assertJsonPath('requires_subscription', true)
    ->assertJsonPath('code', 'subscription_required');

$response->assertJsonPath('meta.total', 3);
$response->assertJsonPath('data.url', fn (string $url) => str_contains($url, '/profiles/'));
$response->assertJsonPath('data.type', 'photo');
```

- [ ] **Step 2: Run the kolab and upload tests to verify they fail for the expected reasons**

Run: `php artisan test tests/Feature/Api/V1/KolabPublishCloseTest.php tests/Feature/Api/V1/KolabBrowseTest.php tests/Feature/Api/V1/KolabCrudTest.php tests/Feature/Api/V1/UploadControllerTest.php`
Expected: FAIL on missing `code`, incomplete upload payload shape, and past-event media normalization.

- [ ] **Step 3: Add a reusable subscription-required response contract**

```php
return response()->json([
    'success' => false,
    'message' => $e->getMessage(),
    'requires_subscription' => true,
    'code' => 'subscription_required',
], 402);
```

- [ ] **Step 4: Normalize kolab media and past events for edit-safe payloads**

```php
'media' => collect($this->media ?? [])->map(fn (array $item) => [
    'url' => $item['url'],
    'type' => $item['type'],
    'thumbnail_url' => $item['thumbnail_url'] ?? null,
])->values()->all(),

'past_events' => collect($this->past_events ?? [])->map(fn (array $event) => [
    ...$event,
    'media' => collect($event['media'] ?? $event['photos'] ?? [])->map(/* normalize */)->values()->all(),
])->values()->all(),
```

- [ ] **Step 5: Make the generic upload endpoint return the canonical response shape**

```php
return response()->json([
    'success' => true,
    'data' => [
        'url' => $url,
        'type' => $isVideo ? 'video' : 'photo',
        'thumbnail_url' => null,
    ],
], 201);
```

- [ ] **Step 6: Re-run the focused kolab and upload suite**

Run: `php artisan test tests/Feature/Api/V1/KolabPublishCloseTest.php tests/Feature/Api/V1/KolabBrowseTest.php tests/Feature/Api/V1/KolabCrudTest.php tests/Feature/Api/V1/UploadControllerTest.php`
Expected: PASS.

### Task 4: Make application acceptance idempotent and immediate

**Files:**
- Modify: `app/Policies/ApplicationPolicy.php`
- Modify: `app/Services/ApplicationService.php`
- Modify: `app/Http/Controllers/Api/V1/ApplicationController.php`
- Modify: `app/Http/Resources/Api/V1/ApplicationResource.php`
- Test: `tests/Feature/Api/V1/ApplicationAcceptTest.php`

- [ ] **Step 1: Write failing idempotent-accept tests**

```php
public function test_accept_endpoint_is_idempotent(): void
{
    $first = $this->actingAs($business)->postJson("/api/v1/applications/{$application->id}/accept");
    $second = $this->actingAs($business)->postJson("/api/v1/applications/{$application->id}/accept");

    $first->assertOk();
    $second->assertOk()
        ->assertJsonPath('data.application.status', 'accepted')
        ->assertJsonPath('data.collaboration.application_id', $application->id);
}
```

- [ ] **Step 2: Run the accept tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/ApplicationAcceptTest.php`
Expected: FAIL because repeat accepts currently hit policy/service rejection.

- [ ] **Step 3: Teach the policy/service to return the existing accepted state**

```php
if ($application->isAccepted() && $application->collaboration) {
    return [
        'application' => $application->fresh(['collaboration', 'applicantProfile', 'collabOpportunity.creatorProfile']),
        'collaboration' => $application->collaboration->fresh(),
    ];
}
```

- [ ] **Step 4: Re-run the accept suite**

Run: `php artisan test tests/Feature/Api/V1/ApplicationAcceptTest.php`
Expected: PASS.

### Task 5: Support multi-category business profiles and keep venue fields stable

**Files:**
- Create: `database/migrations/2026_04_28_000000_add_categories_to_business_profiles_table.php`
- Modify: `app/Models/BusinessProfile.php`
- Modify: `app/Http/Requests/Api/V1/RegisterBusinessRequest.php`
- Modify: `app/Http/Requests/Api/V1/BusinessOnboardingRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateProfileRequest.php`
- Modify: `app/Services/AuthService.php`
- Modify: `app/Services/OnboardingService.php`
- Modify: `app/Services/ProfileService.php`
- Modify: `app/Http/Resources/Api/V1/BusinessProfileResource.php`
- Modify: `app/Http/Resources/Api/V1/PublicProfileResource.php`
- Modify: `app/Http/Resources/Api/V1/ProfileSummaryResource.php`
- Modify: `database/factories/BusinessProfileFactory.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`
- Test: `tests/Feature/Api/V1/OnboardingControllerTest.php`
- Test: `tests/Feature/Api/V1/ProfileControllerTest.php`

- [ ] **Step 1: Write failing tests for ordered category arrays and unchanged venue echoing**

```php
$response->assertJsonPath('data.user.business_profile.categories', ['cafe', 'coworking', 'other']);
$response->assertJsonPath('data.user.business_profile.primary_venue.formatted_address', 'Carrer de Mallorca 1, Barcelona');
$response->assertJsonPath('data.user.business_profile.primary_venue.place_id', 'google-place-id');
```

- [ ] **Step 2: Run the business profile tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php tests/Feature/Api/V1/ProfileControllerTest.php`
Expected: FAIL on missing `categories` array support.

- [ ] **Step 3: Add a `categories` JSON column and compatibility mapping**

```php
$table->json('categories')->nullable()->after('business_type');
```

```php
'business_type' => $categories[0] ?? null,
'categories' => $categories,
```

- [ ] **Step 4: Update validators to accept up to three categories**

```php
'categories' => ['required', 'array', 'min:1', 'max:3'],
'categories.*' => ['string', 'in:'.implode(',', self::BUSINESS_TYPES)],
```

- [ ] **Step 5: Re-run the business profile suite**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php tests/Feature/Api/V1/ProfileControllerTest.php`
Expected: PASS.

### Task 6: Publish the email banner asset path

**Files:**
- Create: `public/community/kolabing/marketing/brand/logo-wordmark-banner-dark.png`

- [ ] **Step 1: Check whether an existing brand PNG can be reused**

Run: `file public/brand/uploaded-logo-2.png public/brand/uploaded-logo-3.png`
Expected: One of the existing PNGs is suitable or can be published as a temporary launch-safe asset.

- [ ] **Step 2: Place the banner PNG at the stable public path**

```bash
mkdir -p public/community/kolabing/marketing/brand
cp public/brand/uploaded-logo-2.png public/community/kolabing/marketing/brand/logo-wordmark-banner-dark.png
```

- [ ] **Step 3: Verify the file exists at the expected path**

Run: `file public/community/kolabing/marketing/brand/logo-wordmark-banner-dark.png`
Expected: PNG image data reported.

## Verification

- [ ] Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php`
- [ ] Run: `php artisan test tests/Feature/Api/V1/KolabPublishCloseTest.php tests/Feature/Api/V1/KolabBrowseTest.php tests/Feature/Api/V1/KolabCrudTest.php`
- [ ] Run: `php artisan test tests/Feature/Api/V1/ProfileControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php`
- [ ] Run: `php artisan test tests/Feature/Api/V1/EventTest.php`
- [ ] Run: `php artisan test tests/Feature/Api/V1/ApplicationAcceptTest.php`

## Self-Review

- Spec coverage: B1, B2, B3, B4, C2, C3, C4, C5, C6, D1, and G1 are each mapped to a task above.
- Placeholder scan: No `TODO`/`TBD` markers remain.
- Type consistency: Use `categories` for ordered business lists, keep legacy `business_type` as compatibility-first derived data, and use canonical media items with `url`, `type`, `thumbnail_url`.
