# Kolabing AdminLTE Panel Design

## Objective

Replace the current custom Blade admin UI with a stable, ready-made Blade admin shell based on `jeroennoten/laravel-adminlte`, while preserving the existing `/admin` maintainer-only access model and the current `profiles` CRUD behavior.

This phase is intentionally narrow:

- Keep `/admin` as the panel root.
- Keep `users` table based maintainer login.
- Keep application user management mapped to `profiles`.
- Introduce a permanent left sidebar and a conventional admin layout.
- Deliver a usable `User Management` area for CRUD work.

## Chosen Approach

### Option A: Keep custom Blade layout and restyle manually

- Lowest dependency count.
- Highest design and maintenance effort.
- Easy to drift into one-off admin UI instead of a stable panel system.

### Option B: Use `Laravel-AdminLTE`

- Ready Blade layout with sidebar, header, content wrapper, auth-friendly structure, and menu configuration.
- Fits the repo's Laravel + Blade setup without introducing a frontend SPA.
- Best match for "use a ready admin template for now".

### Option C: Move admin to a richer panel framework like Filament

- Faster CRUD in the long run.
- Much larger structural shift than needed right now.
- More opinionated than this phase requires.

### Recommendation

Use `Laravel-AdminLTE` now. It gives the project a fixed admin shell quickly, keeps risk low, and does not force a rewrite of the existing admin auth and CRUD behavior.

## Information Architecture

### Sidebar Menu

The sidebar should be fixed and persistent on all authenticated admin pages.

Initial menu:

- Dashboard
  - Placeholder page for future admin metrics.
- User Management
  - Users
  - Create User

The menu structure should be defined centrally so more sections can be added later without rewriting each view.

## Layout Direction

### Global Shell

- AdminLTE default shell with:
  - top navbar
  - left sidebar
  - content header
  - content area
- Kolabing branding in the sidebar/header.
- Clean, neutral admin styling instead of the current custom serif marketing look.

### Page Pattern

Authenticated admin pages should share one consistent frame:

- page title
- optional breadcrumb
- primary action button in the page header when relevant
- card-based content sections
- table/listing views inside AdminLTE cards
- forms split into logical sections instead of one long flat block

## Screen Design

### 1. Admin Login

- Keep route at `/admin/login`.
- Move the page into the AdminLTE authentication look if practical.
- Keep same behavior:
  - email + password
  - non-maintainer accounts denied

### 2. Users Index

Purpose: operational user lookup and quick access to edit.

Visible content:

- page title: `Users`
- primary CTA: `Create User`
- table columns:
  - display name
  - email
  - user type
  - phone number
  - verification status
  - edit action

Optional if straightforward during implementation:

- search by name/email
- simple filter by `user_type`

No bulk actions in this phase.

### 3. Create User

Purpose: allow maintainers to create new application users from admin.

Fields:

- user type
- email
- password
- phone number
- display name
- about
- instagram
- website
- tiktok for community users only
- email verified toggle

Behavior:

- business creates `profile`, `business_profile`, and inactive subscription as today
- community creates `profile` and `community_profile`
- attendee creates `profile` and `attendee_profile`

### 4. Edit User

Purpose: allow maintainers to update an existing application user.

Rules:

- user type remains read-only in this phase
- password update remains optional
- same secondary profile fields can be edited
- preserve current detail-profile-specific handling

## Data and Behavior Rules

### Authentication

- Continue using the `admin` guard backed by `users`.
- Continue requiring `is_maintainer = true`.
- Do not merge maintainer auth into the app's `profiles` auth model.

### Managed Records

- Admin panel manages `profiles`, not `users`.
- `users` remain only for admin/maintainer login.

### CRUD Boundaries

In scope:

- list users
- create users
- edit users

Out of scope for this phase:

- delete users
- role/permission management beyond maintainer gate
- audit logs
- bulk import/export
- advanced filtering
- dashboard metrics implementation

## Template Integration Strategy

### Package Setup

- Add `jeroennoten/laravel-adminlte`.
- Publish only the configuration/assets needed for the panel.
- Configure app title, branding, menu, and route mapping through package config instead of scattering layout values in views.

### View Migration

- Replace the current custom admin layout with an AdminLTE-based layout.
- Keep existing controllers and service layer where possible.
- Rebuild current pages on top of AdminLTE blade sections/components.

### Navigation

- Sidebar links should point to:
  - `/admin`
  - `/admin/users`
  - `/admin/users/create`

`/admin` should land on a placeholder dashboard page instead of redirecting straight to users after the redesign, so the panel has a true home screen.

## Implementation Notes

### Controller Structure

Keep the current split:

- `Admin\AuthController`
- `Admin\ManagedUserController`

Add only what is needed for the dashboard placeholder and search/filter inputs if implemented.

### Form Reuse

Keep a shared form partial for create/edit to avoid divergence.

### Risk Notes

- AdminLTE installation can touch published config and view structure; keep the change localized to admin pages.
- Existing unrelated worktree changes must not be mixed into this effort.
- The remote database already contains an unrelated `notification_reminders` migration side effect from earlier work; this redesign should not add any new schema changes beyond what the admin panel truly needs.

## Success Criteria

This design is complete when:

- `/admin` opens a fixed admin shell with a left sidebar.
- the panel no longer uses the current custom serif marketing-style layout.
- `Users` listing is inside the ready admin template.
- `Create User` and `Edit User` use the same admin template and remain operational.
- maintainer-only access still works.
- the package choice does not force an SPA or API rewrite.
