# Kolabs Canonical Migration Plan

## Problem

Business users publish listings through the canonical `kolabs` flow, but parts of the API still read or write `collab_opportunities`. This splits the product concept into two sources of truth and can hide published Kolabs from community discovery/application flows.

## Current Findings

- `kolabs` is the newer canonical table and is already used by `KolabController`, `KolabService`, admin Kolab flows, and discovery service code.
- `collab_opportunities` is still used by `OpportunityController`, `OpportunityService`, `ApplicationController`, `ApplicationService`, `ApplicationPolicy`, `ApplicationResource`, and several relationships/resources.
- The connected database has `applications.kolab_id` and `collaborations.kolab_id`, but the repository migrations do not. The repo schema must be brought back in sync before tests can reliably validate the new canonical flow.
- `applications.collab_opportunity_id` and `collaborations.collab_opportunity_id` are still legacy foreign keys. Current application creation relies on `LegacyOpportunityBridgeService` to create/find a compatibility `collab_opportunities` row for a Kolab.
- `/api/v1/discovery/opportunities` already uses Kolab-backed discovery, but `/api/v1/opportunities` and the application endpoints still depend on legacy opportunity models.

## Target State

- `kolabs` is the only source of truth for listing discovery, publish, apply, and collaboration creation.
- New writes never create `collab_opportunities` rows.
- Existing legacy rows can be migrated into equivalent `kolabs` rows by an artisan command.
- `applications.kolab_id` and `collaborations.kolab_id` are populated and used by code.
- Legacy `collab_opportunities` remains only as a migration input until it can be dropped.

## Implementation Steps

### 1. Add Schema Migration

Create a guarded migration that makes the canonical links part of repository history:

- Add `applications.kolab_id` if missing.
- Add `collaborations.kolab_id` if missing.
- Add indexes and foreign keys to `kolabs.id` where missing.
- Make legacy `collab_opportunity_id` nullable on `applications` and `collaborations`, or otherwise stop code from requiring it before legacy columns are removed.
- Add a canonical uniqueness guard for applications, preferably `unique(kolab_id, applicant_profile_id)`.

The migration should use `Schema::hasColumn()` guards because the current connected database already has some of this shape.

### 2. Add Backfill Command

Create `app/Console/Commands/MigrateLegacyOpportunitiesToKolabs.php` with a signature like:

```bash
php artisan kolabs:migrate-legacy-opportunities --dry-run
php artisan kolabs:migrate-legacy-opportunities
```

Command behavior:

- Iterate all `collab_opportunities` rows.
- For each legacy row, create or update a `kolabs` row.
- Preserve the legacy UUID as the Kolab UUID when possible so old references stay resolvable during the migration window.
- Map shared fields directly: creator, title, description, status, city, availability, selected time, recurring days, direct recipient, offer headline, base offer, negotiation triggers, timestamps, and `published_at`.
- Map legacy business/community fields into Kolab intent fields:
  - Business-created opportunities become business Kolabs.
  - Community-created opportunities become community-seeking Kolabs.
  - `business_offer`, `community_deliverables`, `categories`, `venue_mode`, `address`, and `offer_photo` are converted into the closest Kolab fields.
- Fill required Kolab fields deterministically if legacy data is incomplete, and report those rows in command output.
- Backfill `applications.kolab_id` from each application’s `collab_opportunity_id`.
- Backfill `collaborations.kolab_id` from each collaboration’s `application.kolab_id` or `collab_opportunity_id`.
- Print totals: legacy rows scanned, Kolabs created, Kolabs updated, applications linked, collaborations linked, rows skipped, and missing-field fallbacks.
- `--dry-run` must not write.

### 3. Move Application Flow To Kolabs

Update the application path so it no longer needs `LegacyOpportunityBridgeService`:

- `ApplicationController::store()` resolves the route ID to `Kolab`.
- `ApplicationController::forOpportunity()` lists applications by `kolab_id`.
- `ApplicationService::apply()` accepts a `Kolab`, validates published/direct-recipient rules against Kolab fields, and creates `Application` with `kolab_id`.
- `ApplicationService::accept()` and collaboration creation load/use `application.kolab`.
- `Collaboration::create()` writes `kolab_id`.
- `ApplicationPolicy` uses `Kolab` for create/view/accept/decline checks.
- `ApplicationResource` exposes `kolab_id` and Kolab summary data. Keep `opportunity` as an API alias only if current clients still expect the key.

### 4. Make Opportunity Endpoints Canonical Or Closed

Since this project is not live and backward compatibility is not required:

- Stop using `OpportunityService` for new product behavior.
- Make read endpoints under `/api/v1/opportunities` return canonical Kolabs or remove them after Flutter is updated.
- Make legacy write endpoints under `/api/v1/opportunities` return `410 Gone` or route them to the Kolab controller only if the client still calls them.
- Keep `/api/v1/kolabs` as the primary create/publish/update/close API.

The immediate bug fix should ensure a Kolab published through `/api/v1/kolabs/{id}/publish` appears in community-facing discovery/list endpoints.

### 5. Award Kolab Publish XP In One Place

Add the publish XP award only in `KolabService::publish()`:

- Use the existing `GamificationWalletService` and `PointEventType::FirstKolabBonus`.
- Make the award idempotent by using the Kolab ID as the reference.
- Do not award XP from legacy opportunity publish paths.

### 6. Remove Runtime Legacy Bridge Usage

After application and discovery paths are canonical:

- Remove `LegacyOpportunityBridgeService` from runtime controllers/services.
- Leave it deleted or only replace it with migration-command-only mapping logic.
- Search the codebase for `CollabOpportunity`, `collab_opportunity_id`, `OpportunityService`, and `LegacyOpportunityBridgeService`; runtime hits should be gone or explicitly isolated to migration/deprecation tests.

### 7. Tests

Add or update focused tests without `RefreshDatabase`:

- A business creates and publishes a Kolab through `/api/v1/kolabs`, then a community can see it through discovery/listing endpoints.
- A community can apply to that published Kolab and `applications.kolab_id` is set.
- Accepting the application creates a collaboration with `collaborations.kolab_id`.
- Direct-recipient Kolabs are only visible/applicable to the selected community.
- Legacy migration command dry-run reports intended changes without writing.
- Legacy migration command creates canonical Kolabs and backfills `applications.kolab_id` / `collaborations.kolab_id`.
- Publishing a Kolab awards `FirstKolabBonus` once.

### 8. Verification

Run targeted suites first:

```bash
php artisan test tests/Feature/Api/V1/Kolab*
php artisan test tests/Feature/Api/V1/DiscoveryOpportunityControllerTest.php
php artisan test tests/Feature/Api/V1/Application*
php artisan test tests/Feature/Api/V1/Collaboration*
php artisan test tests/Feature/Console
```

Then run the full suite:

```bash
composer test
```

Manual/database verification queries after the migration command:

- Count applications with `kolab_id is null`.
- Count collaborations with `kolab_id is null`.
- Count published `collab_opportunities` without an equivalent `kolabs` row.
- Confirm a newly published Kolab appears in the community discovery endpoint response.

## Rollout Order

1. Schema migration and model relationships.
2. Migration command with dry-run support.
3. Backfill tests for the command.
4. Application/collaboration flow switch to `kolab_id`.
5. Opportunity endpoint canonicalization/removal.
6. Kolab publish XP idempotency.
7. Targeted endpoint tests and full test run.

