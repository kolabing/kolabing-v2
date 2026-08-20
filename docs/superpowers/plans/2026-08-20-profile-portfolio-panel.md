# Profile & Portfolio Panel (BE-NF-35) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give a business or community a web surface to manage the portfolio their public profile shows — the profile gallery, their past events and each event's photos — and make the 173 already-uploaded event photographs that were invisible actually render.

**Architecture:** No new table and no migration. Three small self-scoped endpoints close the "cannot reorder / cannot re-caption" gap; one service change merges the two past-event stores into the public `past_events` block; `/account` becomes a tabbed section following the Community Hub's `community-nav.blade.php` pattern.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL (SQLite in tests), PHPUnit 11, Blade, Alpine 3 (self-hosted), Tailwind (CDN-configured in the webapp layout), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-08-20-profile-portfolio-panel-design.md` (revision 2)

---

## Non-negotiable rules for every task

1. **Never run migrations against the default env** — the local `.env` points at the **production** database. Every DB-touching command below passes `DB_CONNECTION=sqlite DB_DATABASE=:memory:` explicitly, exactly as written.
2. **Tests use `LazilyRefreshDatabase`** (not `RefreshDatabase`, not `DatabaseTransactions`).
3. **`Profile` is the authenticatable model**, not `User`.
4. Response envelope is `{"success": true, "data": …}`; errors add `"error": "<snake_code>"` and a translated `"message"`.
5. **Run `vendor/bin/pint` before every commit.**
6. **No `window.confirm` / `alert` / `prompt`** in any Blade file — a browser modal blocks the page and any automation driving it. Use inline confirm state.
7. Nothing in this feature may call `Profile::hasActiveSubscription()`. The portfolio is free for both roles.

---

## File Structure

**New backend files**

| File | Responsibility |
|---|---|
| `app/Http/Requests/Api/V1/UpdateGalleryPhotoRequest.php` | caption edit validation |
| `app/Http/Requests/Api/V1/ReorderGalleryRequest.php` | `ids[]` for the gallery |
| `app/Http/Requests/Api/V1/ReorderEventPhotosRequest.php` | `ids[]` for one event |
| `app/Services/PhotoOrderingService.php` | **the one reorder rule**, shared by gallery and event photos — supplied ids first in the given order, everything else after, foreign ids ignored |

**Modified backend files**

| File | Change |
|---|---|
| `app/Http/Controllers/Api/V1/GalleryController.php` | add `update()` + `reorder()` |
| `app/Http/Controllers/Api/V1/EventPhotoController.php` | add `reorder()` |
| `app/Services/ProfileService.php` | `buildCommunityPastEvents()` merges both stores |
| `app/Http/Resources/Api/V1/PublicProfileResource.php` | emit `gallery`, `past_events`, `past_events_count` |
| `app/Http/Controllers/Api/V1/ProfileController.php` | hydrate `publicProfile()` for business/community only |
| `routes/api.php` | three new routes |

**New frontend files**

| File | Responsibility |
|---|---|
| `resources/views/webapp/partials/account-nav.blade.php` | tab strip for the Profile section |
| `resources/views/webapp/account-gallery.blade.php` | Gallery tab |
| `resources/views/webapp/account-events.blade.php` | Past events tab + per-event photo manager |
| `resources/views/webapp/account-preview.blade.php` | Preview tab |
| `resources/views/webapp/account-settings.blade.php` | Settings tab (notification prefs, moved) |

**Modified frontend files:** `resources/views/webapp/account.blade.php` (Details only), `resources/views/webapp/kolab-form.blade.php` (past-events repeater), `resources/views/webapp/partials/sidebar.blade.php` (label), `routes/web.php`, `lang/{en,es,ca}/webapp.php`.

---

## Task 1: The shared reorder rule

Both reorder endpoints need the same semantics. Write it once.

**Files:**
- Create: `app/Services/PhotoOrderingService.php`
- Test: `tests/Unit/Services/PhotoOrderingServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PhotoOrderingService;
use PHPUnit\Framework\TestCase;

class PhotoOrderingServiceTest extends TestCase
{
    private function order(array $requested, array $owned): array
    {
        return (new PhotoOrderingService)->resolve($requested, $owned);
    }

    public function test_it_orders_by_the_requested_sequence(): void
    {
        $this->assertSame(['c', 'a', 'b'], $this->order(['c', 'a', 'b'], ['a', 'b', 'c']));
    }

    public function test_ids_that_are_not_owned_are_ignored(): void
    {
        // A caller must never reorder someone else's photo by guessing an id.
        $this->assertSame(['b', 'a'], $this->order(['b', 'intruder', 'a'], ['a', 'b']));
    }

    public function test_owned_ids_missing_from_the_request_keep_their_relative_order_at_the_end(): void
    {
        // A partial list must never make a photo disappear from the gallery.
        $this->assertSame(['c', 'a', 'b', 'd'], $this->order(['c'], ['a', 'b', 'c', 'd']));
    }

    public function test_duplicate_ids_in_the_request_are_collapsed(): void
    {
        $this->assertSame(['b', 'a'], $this->order(['b', 'b', 'a'], ['a', 'b']));
    }

    public function test_an_empty_request_leaves_the_existing_order_untouched(): void
    {
        $this->assertSame(['a', 'b'], $this->order([], ['a', 'b']));
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Unit/Services/PhotoOrderingServiceTest.php
```

Expected: FAIL — class does not exist.

- [ ] **Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The one reorder rule, shared by the profile gallery and an event's photos.
 *
 * Two properties matter and both are load-bearing:
 *  - ids the caller does not own are ignored, never written, so a guessed id
 *    cannot touch someone else's row;
 *  - owned ids the caller omitted keep their relative order *after* the supplied
 *    ones, so a partial list can never make a photo vanish from the grid.
 */
class PhotoOrderingService
{
    /**
     * @param  array<int, string>  $requestedIds  the client's desired order
     * @param  array<int, string>  $ownedIds      the caller's ids, in current order
     * @return array<int, string>  the full id list in its new order
     */
    public function resolve(array $requestedIds, array $ownedIds): array
    {
        $owned = array_values($ownedIds);
        $ownedLookup = array_flip($owned);

        $ordered = [];
        foreach ($requestedIds as $id) {
            if (! is_string($id) || ! array_key_exists($id, $ownedLookup)) {
                continue;
            }
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        foreach ($owned as $id) {
            if (! in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Unit/Services/PhotoOrderingServiceTest.php
```

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint
git add app/Services/PhotoOrderingService.php tests/Unit/Services/PhotoOrderingServiceTest.php
git commit -m "feat(media): shared reorder rule for gallery and event photos

Foreign ids are ignored and omitted ids keep their relative order at the end,
so a partial or malicious list can neither hide a photo nor touch another
profile's row.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Gallery caption edit + reorder (D2)

**Files:**
- Create: `app/Http/Requests/Api/V1/UpdateGalleryPhotoRequest.php`, `app/Http/Requests/Api/V1/ReorderGalleryRequest.php`
- Modify: `app/Http/Controllers/Api/V1/GalleryController.php`, `routes/api.php`
- Test: `tests/Feature/Api/V1/GalleryManageTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GalleryManageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function photoFor(Profile $profile, int $order, ?string $caption = null): ProfileGalleryPhoto
    {
        return ProfileGalleryPhoto::query()->create([
            'profile_id' => $profile->id,
            'url' => "https://example.com/{$order}.jpg",
            'caption' => $caption,
            'sort_order' => $order,
        ]);
    }

    public function test_a_caption_can_be_set_and_cleared(): void
    {
        $profile = Profile::factory()->community()->create();
        $photo = $this->photoFor($profile, 0);

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => 'Opening night'])
            ->assertOk()
            ->assertJsonPath('data.caption', 'Opening night');

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => null])
            ->assertOk()
            ->assertJsonPath('data.caption', null);
    }

    public function test_a_caption_longer_than_the_column_is_rejected(): void
    {
        $profile = Profile::factory()->community()->create();
        $photo = $this->photoFor($profile, 0);

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => str_repeat('x', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caption');
    }

    public function test_a_stranger_cannot_edit_a_caption(): void
    {
        $owner = Profile::factory()->community()->create();
        $photo = $this->photoFor($owner, 0);

        $this->actingAs(Profile::factory()->community()->create())
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => 'mine now'])
            ->assertForbidden();

        $this->assertNull($photo->fresh()->caption);
    }

    public function test_reorder_writes_sort_order_and_returns_the_full_gallery(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);
        $c = $this->photoFor($profile, 2);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_reorder_ignores_ids_that_belong_to_someone_else(): void
    {
        $profile = Profile::factory()->community()->create();
        $mine = $this->photoFor($profile, 0);
        $theirs = $this->photoFor(Profile::factory()->community()->create(), 0);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$theirs->id, $mine->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$mine->id], $ids);
        $this->assertSame(0, $theirs->fresh()->sort_order);
    }

    public function test_photos_omitted_from_the_request_keep_their_relative_order_at_the_end(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);
        $c = $this->photoFor($profile, 2);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$c->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_the_gallery_index_returns_photos_in_sort_order(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);

        $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(
            [$b->id, $a->id],
            $this->actingAs($profile)->getJson('/api/v1/me/gallery')->assertOk()->json('data.*.id'),
        );
    }

    public function test_an_empty_id_list_is_rejected(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
```

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/GalleryManageTest.php
```

Expected: FAIL — both routes 404/405.

- [ ] **Step 3: Write the two form requests**

`app/Http/Requests/Api/V1/UpdateGalleryPhotoRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryPhotoRequest extends FormRequest
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
        // Matches profile_gallery_photos.caption (string 500, nullable).
        return [
            'caption' => ['present', 'nullable', 'string', 'max:500'],
        ];
    }
}
```

`app/Http/Requests/Api/V1/ReorderGalleryRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReorderGalleryRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => ['required', 'uuid'],
        ];
    }
}
```

- [ ] **Step 4: Add the two controller actions**

In `app/Http/Controllers/Api/V1/GalleryController.php`, inject the ordering service and add the actions. Add these imports: `App\Http\Requests\Api\V1\ReorderGalleryRequest`, `App\Http\Requests\Api\V1\UpdateGalleryPhotoRequest`, `App\Services\PhotoOrderingService`, `Illuminate\Support\Facades\DB`.

```php
    /**
     * PATCH /api/v1/me/gallery/{photo} — edit a caption.
     */
    public function update(UpdateGalleryPhotoRequest $request, ProfileGalleryPhoto $photo): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($photo->profile_id !== $profile->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to edit this photo.'),
            ], 403);
        }

        $photo->update(['caption' => $request->validated('caption')]);

        return response()->json([
            'success' => true,
            'data' => new GalleryPhotoResource($photo->fresh()),
        ]);
    }

    /**
     * PUT /api/v1/me/gallery/order — set the display order.
     *
     * Returns the caller's FULL ordered gallery so the client never has to infer
     * where the omitted photos landed.
     */
    public function reorder(ReorderGalleryRequest $request, PhotoOrderingService $ordering): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $owned = $profile->galleryPhotos()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->pluck('id')
            ->all();

        $ordered = $ordering->resolve($request->validated('ids'), $owned);

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $id) {
                ProfileGalleryPhoto::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => GalleryPhotoResource::collection(
                $profile->galleryPhotos()->orderBy('sort_order')->orderByDesc('created_at')->get()
            ),
        ]);
    }
```

- [ ] **Step 5: Register the routes**

In `routes/api.php`, next to the existing gallery routes. **`order` must be registered before `{photo}`** is irrelevant here (different verbs), but keep them grouped:

```php
        Route::put('me/gallery/order', [GalleryController::class, 'reorder'])
            ->name('api.v1.me.gallery.reorder');
        Route::patch('me/gallery/{photo}', [GalleryController::class, 'update'])
            ->name('api.v1.me.gallery.update');
```

- [ ] **Step 6: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/GalleryManageTest.php
```

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint
git add app/Http/Requests/Api/V1/UpdateGalleryPhotoRequest.php app/Http/Requests/Api/V1/ReorderGalleryRequest.php app/Http/Controllers/Api/V1/GalleryController.php routes/api.php tests/Feature/Api/V1/GalleryManageTest.php
git commit -m "feat(gallery): caption edit and reorder endpoints

profile_gallery_photos has carried caption and sort_order since it was created,
but no endpoint wrote either after upload — the gallery could be added to and
deleted from, never arranged.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Event photo reorder (D2)

**Files:**
- Create: `app/Http/Requests/Api/V1/ReorderEventPhotosRequest.php`
- Modify: `app/Http/Controllers/Api/V1/EventPhotoController.php`, `routes/api.php`
- Test: `tests/Feature/Api/V1/EventPhotoOrderTest.php`

- [ ] **Step 1: Write the failing test**

Cases, each its own method:

1. `test_the_event_creator_can_reorder_photos` — three photos, reversed order, assert returned id order and the persisted `sort_order` values 0/1/2.
2. `test_photos_from_another_event_are_ignored` — an id from a second event is dropped from the result and its `sort_order` is unchanged.
3. `test_omitted_photos_keep_their_relative_order_at_the_end` — send one id, assert the rest follow in their previous order.
4. `test_a_community_manager_can_reorder` — an attendee with `can_manage` on the event's community (event has `community_id`) gets 200.
5. `test_a_stranger_cannot_reorder` — 403, and no `sort_order` changed.
6. `test_an_empty_id_list_is_rejected` — 422 on `ids`.

Build fixtures with `Event::factory()` and `EventPhoto::query()->create(['event_id' => …, 'url' => …, 'sort_order' => …])`.

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/EventPhotoOrderTest.php
```

- [ ] **Step 3: Write the request**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReorderEventPhotosRequest extends FormRequest
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
        // EventService::MAX_EVENT_PHOTOS is 20.
        return [
            'ids' => ['required', 'array', 'min:1', 'max:20'],
            'ids.*' => ['required', 'uuid'],
        ];
    }
}
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/Api/V1/EventPhotoController.php` — it already has `canManageEvent()` and `forbidden()`; reuse both.

```php
    /**
     * PUT /api/v1/events/{event}/photos/order — set the display order
     * (creator / community can_manage).
     */
    public function reorder(
        ReorderEventPhotosRequest $request,
        Event $event,
        PhotoOrderingService $ordering,
    ): JsonResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->canManageEvent($profile, $event)) {
            return $this->forbidden();
        }

        $owned = $event->photos()->pluck('id')->all();
        $ordered = $ordering->resolve($request->validated('ids'), $owned);

        DB::transaction(function () use ($ordered): void {
            foreach ($ordered as $index => $id) {
                EventPhoto::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => EventPhotoResource::collection($event->photos()->get()),
        ]);
    }
```

Add imports: `App\Http\Requests\Api\V1\ReorderEventPhotosRequest`, `App\Http\Resources\Api\V1\EventPhotoResource`, `App\Services\PhotoOrderingService`, `Illuminate\Support\Facades\DB`. `Event::photos()` already orders by `sort_order`, so the response is correct without a second sort.

- [ ] **Step 5: Register the route**

```php
        Route::put('events/{event}/photos/order', [EventPhotoController::class, 'reorder'])
            ->name('api.v1.events.photos.reorder');
```

Register it **before** `events/{event}/photos/{photo}` is not required (different verb + literal segment), but place it directly above the existing photo routes for readability.

- [ ] **Step 6: Run, then commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/EventPhotoOrderTest.php
vendor/bin/pint
git add app/Http/Requests/Api/V1/ReorderEventPhotosRequest.php app/Http/Controllers/Api/V1/EventPhotoController.php routes/api.php tests/Feature/Api/V1/EventPhotoOrderTest.php
git commit -m "feat(events): reorder an event's photos

event_photos.sort_order was only ever written at insert, so the first photo
uploaded was permanently the cover.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Merge both past-event stores (D3 — the highest-value change)

Prod holds **60 past events with 173 photos** in the `events` table and **7 kolabs**
with `past_events` JSON. The public profile reads only the second. This makes the
first visible.

**Files:**
- Modify: `app/Services/ProfileService.php`
- Test: `tests/Feature/Api/V1/PastEventsMergeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PastEventsMergeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function pastEvents(Profile $profile): array
    {
        return app(ProfileService::class)
            ->getPublicProfileDetail($profile)
            ->getAttribute('community_public_past_events');
    }

    private function eventFor(Profile $profile, string $name, string $date, int $photos = 0): Event
    {
        $event = Event::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'event_date' => $date,
            'attendee_count' => 42,
        ]);

        for ($i = 0; $i < $photos; $i++) {
            EventPhoto::query()->create([
                'event_id' => $event->id,
                'url' => "https://example.com/{$name}-{$i}.jpg",
                'sort_order' => $i,
            ]);
        }

        return $event;
    }

    public function test_events_table_rows_now_appear_in_the_public_past_events(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Rooftop Session', '2026-05-01', photos: 2);

        $events = $this->pastEvents($profile);

        $this->assertCount(1, $events);
        $this->assertSame('event', $events[0]['source']);
        $this->assertSame('Rooftop Session', $events[0]['name']);
        $this->assertSame(42, $events[0]['attendee_count']);
        $this->assertNull($events[0]['source_kolab_id']);
        $this->assertNotNull($events[0]['source_event_id']);
        $this->assertCount(2, $events[0]['media']);
    }

    public function test_kolab_sourced_entries_still_appear_with_their_original_keys(): void
    {
        $profile = Profile::factory()->community()->create();
        Kolab::factory()->create([
            'creator_profile_id' => $profile->id,
            'status' => 'published',
            'past_events' => [[
                'name' => 'Winter Market',
                'date' => '2026-01-10',
                'partner_name' => 'Cafe Nord',
                'photos' => ['https://example.com/winter.jpg'],
            ]],
        ]);

        $events = $this->pastEvents($profile);

        $this->assertSame('kolab', $events[0]['source']);
        $this->assertSame('Winter Market', $events[0]['name']);
        $this->assertSame('Cafe Nord', $events[0]['partner_name']);
        $this->assertNull($events[0]['source_event_id']);
        $this->assertNull($events[0]['attendee_count']);
        $this->assertNotNull($events[0]['source_kolab_id']);
    }

    public function test_both_sources_are_returned_newest_first(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Older Event', '2026-02-01');
        Kolab::factory()->create([
            'creator_profile_id' => $profile->id,
            'status' => 'published',
            'past_events' => [['name' => 'Newer Kolab Entry', 'date' => '2026-06-01']],
        ]);

        $names = array_column($this->pastEvents($profile), 'name');

        $this->assertSame(['Newer Kolab Entry', 'Older Event'], $names);
    }

    public function test_entries_without_a_date_sort_last(): void
    {
        $profile = Profile::factory()->community()->create();
        Kolab::factory()->create([
            'creator_profile_id' => $profile->id,
            'status' => 'published',
            'past_events' => [['name' => 'Undated', 'date' => null]],
        ]);
        $this->eventFor($profile, 'Dated', '2026-03-01');

        $names = array_column($this->pastEvents($profile), 'name');

        $this->assertSame(['Dated', 'Undated'], $names);
    }

    public function test_the_same_name_and_date_dedupes_to_the_event_sourced_copy(): void
    {
        // A leader who logged the evening in both places must not see it twice.
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Launch Party', '2026-04-04', photos: 1);
        Kolab::factory()->create([
            'creator_profile_id' => $profile->id,
            'status' => 'published',
            'past_events' => [['name' => 'launch party', 'date' => '2026-04-04']],
        ]);

        $events = $this->pastEvents($profile);

        $this->assertCount(1, $events);
        $this->assertSame('event', $events[0]['source']);
    }

    public function test_upcoming_events_are_not_included(): void
    {
        $profile = Profile::factory()->community()->create();
        Event::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Next Month',
            'event_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame([], $this->pastEvents($profile));
    }

    public function test_another_profiles_events_are_not_included(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor(Profile::factory()->community()->create(), 'Not Mine', '2026-05-01');

        $this->assertSame([], $this->pastEvents($profile));
    }

    public function test_past_events_count_follows_the_merged_list(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'A', '2026-05-01');
        $this->eventFor($profile, 'B', '2026-05-02');

        $stats = app(ProfileService::class)
            ->getPublicProfileDetail($profile)
            ->getAttribute('community_public_stats');

        $this->assertSame(2, $stats['past_events_count']);
    }

    public function test_the_merge_costs_the_same_number_of_queries_at_any_size(): void
    {
        $small = Profile::factory()->community()->create();
        $this->eventFor($small, 'S1', '2026-05-01', photos: 1);

        $large = Profile::factory()->community()->create();
        for ($i = 0; $i < 15; $i++) {
            $this->eventFor($large, "L{$i}", '2026-05-01', photos: 2);
        }

        $count = function (Profile $profile): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(ProfileService::class)->getPublicProfileDetail($profile);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($small), $count($large));
    }
}
```

> If `Kolab::factory()` has no `past_events` in its definition it is still settable —
> the column is on the table. Check `KolabStatus` for the exact published value and use
> the enum rather than the string if the factory expects one.

- [ ] **Step 2: Run it and watch it fail**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/PastEventsMergeTest.php
```

Expected: FAIL — event-sourced entries absent, `source` key missing.

- [ ] **Step 3: Replace `buildCommunityPastEvents()`**

In `app/Services/ProfileService.php`, replace the whole method and add two private helpers below it. Add `use App\Models\Event;` and `use App\Models\EventPhoto;` if absent.

```php
    /**
     * The public "past events" block, merged from the two stores that hold it.
     *
     * `kolabs.past_events` is a free-form JSON array any creator writes. The
     * `events` table holds real retrospective rows with their own photo store.
     * The public profile historically read only the first, which left every
     * event-table photograph invisible; both are returned here, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCommunityPastEvents(Profile $profile): array
    {
        $merged = array_merge(
            $this->pastEventsFromKolabs($profile),
            $this->pastEventsFromEvents($profile),
        );

        // Same evening logged in both places: keep the event-sourced copy, which
        // carries attendee_count and a real photo store.
        $deduped = [];
        foreach ($merged as $item) {
            $key = mb_strtolower(trim((string) $item['name'])).'|'.(string) $item['date'];

            if (! isset($deduped[$key]) || $item['source'] === 'event') {
                $deduped[$key] = $item;
            }
        }

        $items = array_values($deduped);

        // Newest first; undated entries sort last so a malformed Kolab entry can
        // never take the top slot.
        usort($items, function (array $a, array $b): int {
            if ($a['date'] === null && $b['date'] === null) {
                return 0;
            }
            if ($a['date'] === null) {
                return 1;
            }
            if ($b['date'] === null) {
                return -1;
            }

            return strcmp((string) $b['date'], (string) $a['date']);
        });

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pastEventsFromKolabs(Profile $profile): array
    {
        /** @var Collection<int, Kolab> $kolabs */
        $kolabs = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->whereIn('status', [KolabStatus::Published, KolabStatus::Closed])
            ->orderByDesc('published_at')
            ->get(['id', 'past_events']);

        return $kolabs
            ->flatMap(function (Kolab $kolab): array {
                if (! is_array($kolab->past_events)) {
                    return [];
                }

                return array_map(function (mixed $event) use ($kolab): ?array {
                    if (! is_array($event)) {
                        return null;
                    }

                    return [
                        'source' => 'kolab',
                        'source_kolab_id' => $kolab->id,
                        'source_event_id' => null,
                        'name' => isset($event['name']) && is_string($event['name']) ? $event['name'] : null,
                        'date' => isset($event['date']) && is_string($event['date']) ? $event['date'] : null,
                        'partner_name' => isset($event['partner_name']) && is_string($event['partner_name']) ? $event['partner_name'] : null,
                        'attendee_count' => null,
                        'media' => $this->normalizeMediaCollection($event['media'] ?? $event['photos'] ?? []),
                    ];
                }, $kolab->past_events);
            })
            ->filter(fn (?array $event): bool => $event !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pastEventsFromEvents(Profile $profile): array
    {
        return Event::query()
            ->where('profile_id', $profile->id)
            ->whereDate('event_date', '<', now()->toDateString())
            ->with('photos')
            ->orderByDesc('event_date')
            ->get()
            ->map(fn (Event $event): array => [
                'source' => 'event',
                'source_kolab_id' => null,
                'source_event_id' => $event->id,
                'name' => $event->name,
                'date' => $event->event_date?->toDateString(),
                'partner_name' => $event->partner_name,
                'attendee_count' => $event->attendee_count,
                'media' => $this->normalizeMediaCollection(
                    $event->photos->pluck('url')->all()
                ),
            ])
            ->values()
            ->all();
    }
```

> `Event::photos()` already orders by `sort_order`, so the reorder from Task 3 is what
> decides the public cover image. `with('photos')` keeps this two queries regardless of
> event count — that is what the query-count test locks.
>
> Check whether `events.partner_name` exists as a column; the schema shows
> `partner_id`/`partner_type` on the original table and `partner_name` was added by
> `2026_02_05_223815_change_partner_id_to_partner_name_in_events_table.php`. If the
> attribute is absent, emit `null` rather than inventing a join.

- [ ] **Step 4: Run the test — expect PASS**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/PastEventsMergeTest.php
```

- [ ] **Step 5: Run every profile test — this changes a public payload**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact --filter="Profile|PublicProfile|Community"
```

Any test asserting the exact `past_events` shape needs the three new keys added. Do not
delete such an assertion — extend it.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint
git add app/Services/ProfileService.php tests/Feature/Api/V1/PastEventsMergeTest.php
git commit -m "fix(profiles): surface the past events that were invisible

The public profile's past_events read only kolabs.past_events — 7 kolabs in
prod. The events table holds 60 past events and 173 photographs (business
26/76, community 34/97) that no public surface has ever rendered. Both stores
are merged now, newest first, deduped on name+date in favour of the
event-sourced copy, with additive source / source_event_id / attendee_count
keys. Two queries regardless of event count, locked by a query-count test.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: The light public profile gains the portfolio

**Files:**
- Modify: `app/Http/Resources/Api/V1/PublicProfileResource.php`, `app/Http/Controllers/Api/V1/ProfileController.php`
- Test: `tests/Feature/Api/V1/PublicProfilePortfolioTest.php`

- [ ] **Step 1: Write the failing test**

Cases:

1. `test_a_community_profile_emits_the_portfolio` — `GET /profiles/{id}` returns `gallery`, `past_events`, `past_events_count`.
2. `test_a_business_profile_emits_the_portfolio` — same for a business.
3. `test_an_attendee_profile_still_returns_200_without_a_portfolio` — **the guard that matters**: `getPublicProfileDetail()` throws `ModelNotFoundException` for attendees, so calling it unguarded turns every attendee profile into a 404. Assert `200`, assert the response has no `gallery` key, and assert the existing keys (`handle`, `display_name`) are intact.
4. `test_the_gallery_is_returned_in_sort_order` — reorder, then read the public profile.

- [ ] **Step 2: Guard and hydrate in the controller**

In `ProfileController@publicProfile` (around line 197), before building the resource:

```php
        // Attendees have no portfolio surface, and getPublicProfileDetail()
        // throws ModelNotFoundException for them — calling it unguarded would
        // turn every attendee profile into a 404.
        if ($profile->isBusiness() || $profile->isCommunity()) {
            $profile = $this->profileService->getPublicProfileDetail($profile);
        }
```

- [ ] **Step 3: Emit the block from the resource**

In `PublicProfileResource::toArray()`, append:

```php
            // Present only for business/community, which are the only types the
            // controller hydrates. Attendees keep their original payload.
            ...$this->portfolioFields(),
```

and add:

```php
    /**
     * @return array<string, mixed>
     */
    private function portfolioFields(): array
    {
        if (! array_key_exists('community_public_past_events', $this->resource->getAttributes())) {
            return [];
        }

        $pastEvents = $this->getAttribute('community_public_past_events') ?? [];

        return [
            'gallery' => $this->getAttribute('community_public_gallery') ?? [],
            'past_events' => $pastEvents,
            'past_events_count' => count($pastEvents),
        ];
    }
```

- [ ] **Step 4: Run, then commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/Api/V1/PublicProfilePortfolioTest.php
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact --filter=Profile
vendor/bin/pint
git add app/Http/Resources/Api/V1/PublicProfileResource.php app/Http/Controllers/Api/V1/ProfileController.php tests/Feature/Api/V1/PublicProfilePortfolioTest.php
git commit -m "feat(profiles): the light public profile carries the portfolio

GET /profiles/{id} now emits gallery, past_events and past_events_count for
business and community profiles. Attendees are explicitly excluded:
getPublicProfileDetail() throws for them, so an unguarded call would 404 every
attendee profile. PublicProfileResource is instantiated once, for a single
profile, never in a collection — there is no list-payload cost.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: `/account` becomes a tabbed section

Pure restructuring — no behaviour change. Do it before adding tabs, so each later task
adds one file instead of editing a growing page.

**Files:**
- Create: `resources/views/webapp/partials/account-nav.blade.php`, `resources/views/webapp/account-settings.blade.php`
- Modify: `resources/views/webapp/account.blade.php`, `routes/web.php`
- Test: `tests/Feature/WebApp/WebAppRoutesTest.php`

- [ ] **Step 1: Write the tab strip partial**

`account-nav.blade.php` — copy the structure of `community-nav.blade.php` exactly
(same pill markup, same `$base` prefixing, same `kb-scroll`), with:

```php
    $tabs = [
        'details'  => ['/account', __('webapp.account.tabs.details')],
        'gallery'  => ['/account/gallery', __('webapp.account.tabs.gallery')],
        'events'   => ['/account/events', __('webapp.account.tabs.events')],
        'preview'  => ['/account/preview', __('webapp.account.tabs.preview')],
        'settings' => ['/account/settings', __('webapp.account.tabs.settings')],
    ];
    $current = $accountActive ?? 'details';
```

No `x-show` gate: every authenticated profile reaches every tab. The heading above the
strip is `__('webapp.account.title')`.

- [ ] **Step 2: Split the page**

Move the notification-preferences block out of `account.blade.php` into
`account-settings.blade.php` **verbatim** — same markup, same Alpine methods, same API
calls. `account.blade.php` keeps only the profile form. Both pages `@include` the nav
partial with their own `accountActive`.

- [ ] **Step 3: Register the routes**

In `routes/web.php`, inside `$webappRoutes` (so root + `/es` + `/ca` all get them),
next to the existing `/account`:

```php
    Route::view('/account/gallery', 'webapp.account-gallery');
    Route::view('/account/events', 'webapp.account-events');
    Route::view('/account/preview', 'webapp.account-preview');
    Route::view('/account/settings', 'webapp.account-settings');
```

Create the three not-yet-written views as one-line stubs that include the nav partial,
so the routes resolve now and Tasks 7–9 fill them in.

- [ ] **Step 4: Extend the route test**

```php
    public function test_account_section_tabs_render(): void
    {
        foreach (['', '/gallery', '/events', '/preview', '/settings'] as $path) {
            $this->get('http://'.$this->host().'/account'.$path)->assertOk();
        }
    }

    public function test_account_section_tabs_render_under_the_locale_prefixes(): void
    {
        foreach (['es', 'ca'] as $locale) {
            $this->get('http://'.$this->host().'/'.$locale.'/account/gallery')->assertOk();
        }
    }
```

The existing `test_no_file_in_public_shadows_a_webapp_route` already covers `account`.

- [ ] **Step 5: Run, then commit**

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact tests/Feature/WebApp
vendor/bin/pint
git add resources/views/webapp routes/web.php tests/Feature/WebApp/WebAppRoutesTest.php
git commit -m "refactor(webapp): /account becomes a tabbed Profile section

No behaviour change: the profile form and notification preferences move
unaltered into Details and Settings, and three stub tabs are registered for
the gallery, past events and preview.

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Gallery tab (D1)

**Files:** `resources/views/webapp/account-gallery.blade.php`

- [ ] **Step 1: The Alpine component**

```js
function accountGalleryPage() {
    return {
        t: (key, params) => window.t(key, params),
        loading: true, busy: false, error: '',
        photos: [],
        max: 20,
        confirmDelete: null,
        dragFrom: null,
        editingId: null, editingCaption: '',

        get remaining() { return Math.max(0, this.max - this.photos.length); },

        async init() {
            await this.loadShell();
            await this.load();
        },

        async load() {
            this.loading = true;
            const res = await window.kb.api('/me/gallery');
            this.loading = false;
            this.photos = res.ok ? window.kb.rows(res) : [];
            this.confirmDelete = null;
        },

        /**
         * The endpoint caps a request at 5 files but the gallery allows 20, so a
         * larger drop is sent in sequential chunks rather than rejected or
         * silently truncated.
         */
        async upload(event) {
            const files = Array.from(event.target.files || []).slice(0, this.remaining);
            event.target.value = '';
            if (!files.length) return;

            this.busy = true; this.error = '';
            const failed = [];

            for (let i = 0; i < files.length; i += 5) {
                const fd = new FormData();
                files.slice(i, i + 5).forEach(f => fd.append('photos[]', f));
                const res = await window.kb.upload('/me/gallery', fd);
                if (!res.ok) failed.push(window.kb.errorText(res, t('account.gallery.upload_error')));
            }

            this.busy = false;
            if (failed.length) this.error = failed.join('\n');
            await this.load();
        },

        async saveCaption(photo) {
            const res = await window.kb.api('/me/gallery/' + photo.id, {
                method: 'PATCH', body: { caption: this.editingCaption.trim() || null },
            });
            this.editingId = null;
            if (res.ok) await this.load();
            else this.error = window.kb.errorText(res, window.t('account.gallery.save_error'));
        },

        async remove(photo) {
            this.confirmDelete = null;
            const res = await window.kb.api('/me/gallery/' + photo.id, { method: 'DELETE' });
            if (res.ok) await this.load();
            else this.error = window.kb.errorText(res, window.t('account.gallery.delete_error'));
        },

        /* Drag to reorder — optimistic, then persisted. */
        onDrop(index) {
            if (this.dragFrom === null || this.dragFrom === index) return;
            const moved = this.photos.splice(this.dragFrom, 1)[0];
            this.photos.splice(index, 0, moved);
            this.dragFrom = null;
            this.persistOrder();
        },

        async persistOrder() {
            const res = await window.kb.api('/me/gallery/order', {
                method: 'PUT', body: { ids: this.photos.map(p => p.id) },
            });
            if (res.ok) this.photos = window.kb.rows(res);
            else { this.error = window.kb.errorText(res, window.t('account.gallery.order_error')); await this.load(); }
        },
    };
}
```

- [ ] **Step 2: The markup**

Responsive grid (`grid-cols-2 sm:grid-cols-3 lg:grid-cols-4`) of cards, each
`draggable="true"` with `@dragstart="dragFrom = index"`, `@dragover.prevent`,
`@drop="onDrop(index)"`. Each card: the image (`object-cover aspect-square rounded-2xl`),
a caption line that swaps to an input on click, and a delete control using the inline
confirm pattern from `community-members.blade.php` (never `window.confirm`). Header
carries the "N/20" counter and the file input (`multiple`, `accept="image/*"`,
disabled at `remaining === 0`). Empty state: a short line plus the upload button as the
primary CTA.

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint
git add resources/views/webapp/account-gallery.blade.php
git commit -m "feat(webapp): gallery tab — upload, caption, drag-to-reorder, delete

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Past events tab (D1)

**Files:** `resources/views/webapp/account-events.blade.php`

- [ ] **Step 1: List + create**

- Load `GET /events?time=past&profile_id=<me.id>&limit=50`, newest first.
- "Log a past event" form → `POST /events` as **multipart** (`kb.upload`), fields
  `name`, `partner_name`, `partner_type`, `date`, `attendee_count`, `photos[]`.
  The retrospective branch **requires 1–5 photo files**, so the submit button stays
  disabled until at least one file is chosen — the form must not let someone submit
  into a guaranteed 422.
- `partner_type` options come from the same vocabulary `StoreEventRequest` validates;
  read that request and mirror its allowed values rather than inventing labels.

- [ ] **Step 2: Per-event editor**

An expandable row per event:
- Text fields → `PUT /events/{id}` (`UpdateEventRequest` accepts `name`,
  `partner_name`, `partner_type`, `date`, `attendee_count`).
- Photo manager: grid of `event.photos`; add via `POST /events/{id}/photos` chunked at
  5 with a 20 total cap; delete via `DELETE /events/{id}/photos/{photo}`;
  drag-to-reorder via `PUT /events/{id}/photos/order`. Reuse the same chunking and
  drag handlers as Task 7 — copy them into this component rather than sharing a global,
  matching how the other pages keep their state local.
- Delete the event via `DELETE /events/{id}` behind the inline confirm.

- [ ] **Step 3: A line of copy that prevents a support ticket**

Above the list: "These appear on your public profile." The whole point of Task 4 is
that this is now true — say it.

- [ ] **Step 4: Commit**

```bash
vendor/bin/pint
git add resources/views/webapp/account-events.blade.php
git commit -m "feat(webapp): past events tab with a per-event photo manager

Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Preview tab

**Files:** `resources/views/webapp/account-preview.blade.php`

- [ ] Load `GET /profiles/{me.id}/public-profile` and render it read-only: avatar, name,
  type, about, socials, the `gallery` grid, `past_events` (name · date · partner ·
  attendee count · media thumbnails), `past_collaborations`, and `public_stats`.
- [ ] A "Copy public link" button using `public_url` from the payload, with the same
  clipboard fallback as `community-settings.blade.php`.
- [ ] Empty state when the portfolio is bare: point at the Gallery and Past events tabs.
- [ ] Commit: `feat(webapp): preview tab — the public profile as others see it`

---

## Task 10: Past-events editor on the Kolab form

`UpdateKolabRequest` already accepts `past_events` (`name`, `date`, `partner_name`,
`photos[≤3 urls]`). Only the web form never sends it.

**Files:** `resources/views/webapp/kolab-form.blade.php`

- [ ] Add a repeater section (edit mode only, since it goes out on `PUT /kolabs/{id}`):
  rows of name / date / partner name, each with up to 3 photos uploaded via
  `kb.uploadFile(file, 'kolabs')` and stored as URLs.
- [ ] Include `past_events` in the `PUT` payload, prefilled from the loaded Kolab.
- [ ] Commit: `feat(webapp): edit a Kolab's past events from the web form`

---

## Task 11: i18n — es/ca to 100%

**Files:** `lang/{en,es,ca}/webapp.php`

- [ ] Add an `account.tabs.*` block and `account.gallery.*`, `account.events.*`,
  `account.preview.*` blocks covering every string in Tasks 6–10.
- [ ] **Before editing, confirm there is exactly one top-level `'account'` key per file:**

```bash
for l in en es ca; do echo -n "$l: "; grep -c "^    'account' => \[" lang/$l/webapp.php; done
```

Expected `1` for each. A duplicated top-level key silently overrides the earlier block —
that bug was introduced by a rebase in BE-NF-34 and cost real debugging time.

- [ ] Verify parity:

```bash
php -r '
$f=function($a,$p="") use(&$f){$o=[];foreach($a as $k=>$v){$o=array_merge($o,is_array($v)?$f($v,"$p$k."):["$p$k"]);}return $o;};
$en=$f(require "lang/en/webapp.php");
foreach(["es","ca"] as $n){$m=array_diff($en,$f(require "lang/$n/webapp.php")); echo $n.": ".(count($m)?implode(", ",$m):"complete")."\n";}'
```

Expected: `es: complete` / `ca: complete`.

- [ ] Extend `WebAppRoutesTest` with one Spanish and one Catalan string assertion on
  `/es/account/gallery` and `/ca/account/gallery`.
- [ ] Commit: `feat(webapp): es/ca localisation for the Profile section`

---

## Task 12: Docs, full suite, PR

- [ ] **`docs/ROLES-AND-PERMISSIONS.md`** — a short section: what a business or
  community may publish about past work, that both roles have it, and that it is free.
  Bump *Last updated*.
- [ ] **`docs/ROLES-BACKEND-DB-MAP.md`** — the three new endpoints; the two-store merge
  with its item shape, ordering, and dedup rule; the light-profile portfolio and the
  attendee guard; the gallery ordering guarantee. Bump *Last updated*.
- [ ] **`BACKLOG.md`** — BE-NF-35 entry. Bump *Last updated*. **Confirm the id is still
  free first** — ids get claimed by parallel sessions:

```bash
git fetch -q origin && grep -o "BE-NF-[0-9]*" BACKLOG.md | sort -u -V | tail -3
```

- [ ] **Full suite + pint:**

```bash
vendor/bin/pint
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact
```

Paste the real counts into the PR. Do not claim green without the output.

- [ ] **PR** using `.github/pull_request_template.md`, every section filled. The
  **Mobile impact** section must state:

> `past_events` items gain `source`, `source_event_id` and `attendee_count`, and the
> array now also contains rows from the `events` table — in prod that is 60 more events
> and 173 more photographs across business and community profiles. Existing keys
> (`source_kolab_id`, `name`, `date`, `partner_name`, `media`) are unchanged, so a
> client that ignores the new keys keeps working; a client that assumed
> `source_kolab_id` is always present must now handle `null`. `GET /profiles/{id}` gains
> `gallery`, `past_events`, `past_events_count` for business/community profiles
> (attendees unchanged). Three new endpoints — `PATCH /me/gallery/{photo}`,
> `PUT /me/gallery/order`, `PUT /events/{event}/photos/order` — are available to adopt.
> kolabing-app ticket: `<link>`.

---

## Build order

Tasks are strictly sequential: Task 1's service is used by Tasks 2 and 3; Task 4's merge
is what makes Task 8's "these appear on your public profile" true; Task 6 creates the
files Tasks 7–9 fill in.

## Self-review notes

- **Spec coverage:** §5.1→T4, §5.2→T5, §5.3→T2, §5.4→T3, §5.5→(no task by design —
  it lists what must not change), §6 table→T6–T9, §6 Kolab repeater→T10, §7→each task's
  test step, §8→T12.
- **Beyond the spec:** Task 1 extracts `PhotoOrderingService` so the reorder rule is
  written and tested once rather than duplicated across two controllers.
- **Naming consistency:** `PhotoOrderingService::resolve(array $requestedIds, array $ownedIds): array`,
  `buildCommunityPastEvents()` / `pastEventsFromKolabs()` / `pastEventsFromEvents()`,
  `portfolioFields()` — used identically wherever they appear.
- **Known unknowns flagged inline rather than guessed:** whether `events.partner_name`
  is present as an attribute (Task 4 Step 3), and the exact `partner_type` vocabulary
  (Task 8 Step 1). Both say "read the source" instead of inventing a value.
