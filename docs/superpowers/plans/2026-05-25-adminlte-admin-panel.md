# AdminLTE Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current custom `/admin` Blade UI with a fixed Laravel-AdminLTE shell that keeps maintainer auth and preserves working user CRUD over `profiles`.

**Architecture:** Keep the existing admin controllers, guard, middleware, and profile service logic, but move page rendering onto `jeroennoten/laravel-adminlte`. Configure the sidebar and top navigation centrally in `config/adminlte.php`, add a real `/admin` dashboard placeholder, and rebuild login and user CRUD views on the package layout.

**Tech Stack:** Laravel 12, Blade, jeroennoten/laravel-adminlte, existing admin auth middleware and profile CRUD service

---

## File Structure

**Create:**

- `config/adminlte.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `resources/views/admin/dashboard.blade.php`

**Modify:**

- `composer.json`
- `composer.lock`
- `routes/web.php`
- `app/Http/Controllers/Admin/AuthController.php`
- `app/Http/Controllers/Admin/ManagedUserController.php`
- `resources/views/admin/auth/login.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/admin/users/create.blade.php`
- `resources/views/admin/users/edit.blade.php`
- `resources/views/admin/users/form.blade.php`

**No new automated admin tests:**

- The user explicitly requested not to add tests for admin work.
- Verification for this plan uses route, syntax, view, and package checks instead.

### Task 1: Install and Publish AdminLTE

**Files:**

- Modify: `composer.json`
- Modify: `composer.lock`
- Create: `config/adminlte.php`

- [ ] **Step 1: Require the package**

Run:

```bash
composer require jeroennoten/laravel-adminlte
```

Expected:

- Composer adds `jeroennoten/laravel-adminlte`
- `composer.json` and `composer.lock` change

- [ ] **Step 2: Inspect the package install command**

Run:

```bash
php artisan help adminlte:install
```

Expected:

- Command exists
- Output shows install options

- [ ] **Step 3: Publish package resources**

Run:

```bash
php artisan adminlte:install --only=config --only=assets --force
```

Expected:

- `config/adminlte.php` is created
- `public/vendor/adminlte` assets are published

- [ ] **Step 4: Configure panel identity and menu**

Set in `config/adminlte.php`:

```php
'title' => 'Kolabing Admin',
'title_prefix' => '',
'title_postfix' => '',
'use_ico_only' => false,
'use_full_favicon' => false,
'dashboard_url' => 'admin',
'logout_url' => 'admin/logout',
'login_url' => 'admin/login',
'register_url' => false,
'password_reset_url' => false,
'menu' => [
    [
        'text' => 'Dashboard',
        'route' => 'admin.dashboard',
        'icon' => 'fas fa-gauge-high',
    ],
    [
        'header' => 'USER MANAGEMENT',
    ],
    [
        'text' => 'Users',
        'route' => 'admin.users.index',
        'icon' => 'fas fa-users',
    ],
    [
        'text' => 'Create User',
        'route' => 'admin.users.create',
        'icon' => 'fas fa-user-plus',
    ],
]
```

- [ ] **Step 5: Verify the package is active**

Run:

```bash
php artisan adminlte:status
```

Expected:

- Output confirms installed resources

### Task 2: Add the Real `/admin` Shell and Dashboard

**Files:**

- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Create: `resources/views/admin/dashboard.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add a dashboard controller**

Create `app/Http/Controllers/Admin/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
```

- [ ] **Step 2: Replace the redirect-only `/admin` route**

Update `routes/web.php` so authenticated admin routes include:

```php
Route::middleware(['auth:admin', 'maintainer'])->prefix('admin')->as('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

    Route::get('/users', [ManagedUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [ManagedUserController::class, 'create'])->name('users.create');
    Route::post('/users', [ManagedUserController::class, 'store'])->name('users.store');
    Route::get('/users/{profile}/edit', [ManagedUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{profile}', [ManagedUserController::class, 'update'])->name('users.update');
});
```

- [ ] **Step 3: Add a dashboard placeholder page**

Create `resources/views/admin/dashboard.blade.php`:

```blade
@extends('adminlte::page')

@section('title', 'Dashboard')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Dashboard</h1>
            <p class="text-muted mb-0">Maintainer home for Kolabing operations.</p>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <x-adminlte-card title="Admin Panel" theme="lightblue" icon="fas fa-shield-halved">
                <p class="mb-0">This dashboard is intentionally simple for now. User management is live in the left sidebar.</p>
            </x-adminlte-card>
        </div>
    </div>
@stop
```

- [ ] **Step 4: Verify route registration**

Run:

```bash
php artisan route:list --path=admin
```

Expected:

- `/admin`
- `/admin/login`
- `/admin/users`
- `/admin/users/create`
- `/admin/users/{profile}/edit`

### Task 3: Move Login to the AdminLTE Shell

**Files:**

- Modify: `app/Http/Controllers/Admin/AuthController.php`
- Modify: `resources/views/admin/auth/login.blade.php`

- [ ] **Step 1: Keep controller behavior, clean redirects to named admin routes**

Update redirect targets in `app/Http/Controllers/Admin/AuthController.php`:

```php
return redirect()->intended(route('admin.users.index'));
```

and

```php
return redirect()->route('login');
```

- [ ] **Step 2: Rebuild the login page on AdminLTE**

Replace `resources/views/admin/auth/login.blade.php` with:

```blade
@extends('adminlte::auth.auth-page', ['auth_type' => 'login'])

@section('auth_header', 'Kolabing Admin')
@section('auth_body')
    <form action="{{ url('/admin/login') }}" method="post">
        @csrf
        <x-adminlte-input name="email" type="email" label="Email" placeholder="admin@kolabing.com" value="{{ old('email') }}" fgroup-class="mb-3" enable-old-support/>
        <x-adminlte-input name="password" type="password" label="Password" placeholder="Password" fgroup-class="mb-3"/>
        <x-adminlte-input-switch name="remember" label="Remember this device" data-on-text="Yes" data-off-text="No" value="1" :checked="old('remember')"/>
        <x-adminlte-button type="submit" theme="primary" label="Login" class="w-100 mt-3"/>
    </form>
@stop
```

- [ ] **Step 3: Verify view compilation**

Run:

```bash
php artisan view:clear && php artisan view:cache
```

Expected:

- Views compile without Blade errors

### Task 4: Move User CRUD Pages to AdminLTE

**Files:**

- Modify: `app/Http/Controllers/Admin/ManagedUserController.php`
- Modify: `resources/views/admin/users/index.blade.php`
- Modify: `resources/views/admin/users/create.blade.php`
- Modify: `resources/views/admin/users/edit.blade.php`
- Modify: `resources/views/admin/users/form.blade.php`

- [ ] **Step 1: Return named admin views without the old custom layout assumptions**

Keep controller methods returning:

```php
return view('admin.users.index', [
    'profiles' => $profiles,
]);
```

and:

```php
return view('admin.users.create', [
    'userTypes' => UserType::cases(),
]);
```

and:

```php
return view('admin.users.edit', [
    'profile' => $profile,
]);
```

- [ ] **Step 2: Rebuild the users index page**

Use `adminlte::page` and an AdminLTE card + table structure in `resources/views/admin/users/index.blade.php`.

Table fields:

```blade
name, email, type, phone, verified, edit action
```

Primary CTA:

```blade
href="{{ route('admin.users.create') }}"
```

- [ ] **Step 3: Rebuild the shared form partial**

Use AdminLTE form components in `resources/views/admin/users/form.blade.php`:

```blade
<x-adminlte-input ... />
<x-adminlte-select ... />
<x-adminlte-textarea ... />
<x-adminlte-input-switch ... />
```

Keep behavior:

- create page shows user type select
- edit page shows user type read-only
- community-only TikTok field remains conditional

- [ ] **Step 4: Rebuild create and edit screens**

Wrap both pages in `@extends('adminlte::page')`, use `x-adminlte-card`, and wire buttons to:

```blade
route('admin.users.store')
route('admin.users.update', $profile)
route('admin.users.index')
```

- [ ] **Step 5: Verify syntax and routes**

Run:

```bash
php -l app/Http/Controllers/Admin/ManagedUserController.php
php -l app/Http/Controllers/Admin/AuthController.php
php artisan route:list --path=admin
php artisan view:cache
```

Expected:

- No PHP syntax errors
- Admin routes still present
- Views cache successfully

### Task 5: Final Package and Build Verification

**Files:**

- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: any touched admin file from prior tasks

- [ ] **Step 1: Verify AdminLTE package registration**

Run:

```bash
composer show jeroennoten/laravel-adminlte
```

Expected:

- Installed package info prints successfully

- [ ] **Step 2: Verify the frontend asset pipeline still builds**

Run:

```bash
npm run build
```

Expected:

- Vite build exits 0

- [ ] **Step 3: Review changed files**

Run:

```bash
git status --short
git diff -- app/Http/Controllers/Admin app/Http/Requests/Admin app/Services/Admin routes/web.php config/adminlte.php composer.json composer.lock resources/views/admin
```

Expected:

- Only the AdminLTE redesign scope is included

- [ ] **Step 4: Commit the redesign**

Run:

```bash
git add composer.json composer.lock config/adminlte.php routes/web.php app/Http/Controllers/Admin/DashboardController.php app/Http/Controllers/Admin/AuthController.php app/Http/Controllers/Admin/ManagedUserController.php resources/views/admin/dashboard.blade.php resources/views/admin/auth/login.blade.php resources/views/admin/users/index.blade.php resources/views/admin/users/create.blade.php resources/views/admin/users/edit.blade.php resources/views/admin/users/form.blade.php
git commit -m "feat: redesign admin panel with adminlte"
```
