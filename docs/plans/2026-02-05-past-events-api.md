# Past Events API Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete CRUD API for "Past Events" so business and community users can showcase completed collaborations on their profiles.

**Architecture:** Two new DB tables (`events` + `event_photos`) with a dedicated `EventController`, `EventService`, `EventPolicy`, form requests, and API resources. Photos use the existing `FileUploadService` with a new `EventPhoto` upload type. The `partner_id` references a `profiles` row. Events are publicly viewable (for profile pages) but only owners can CUD.

**Tech Stack:** Laravel 12, PostgreSQL, Sanctum auth, existing `FileUploadService` for photo uploads

---

## Key Decisions (from spec + codebase alignment)

1. **`partner_id` references `profiles` table** (not `users`) - the app uses `profiles` as the main user table with UUID PKs.
2. **`partner_type` stored on events table** - denormalized for quick reads; validated against partner's actual `user_type` on create.
3. **Photos uploaded as `UploadedFile`** (not base64) - consistent with existing gallery photo pattern. The spec shows base64 but the codebase convention uses file uploads.
4. **No thumbnail generation** - the spec mentions thumbnails but the existing codebase has no thumbnail logic. Skip for MVP; `thumbnail_url` column included as nullable for future use.
5. **Pagination** - use Laravel's built-in `simplePaginate` with custom response wrapper to match the spec's pagination format.
6. **Public read access** - any authenticated user can view any user's events (for public profiles). Owner-only for create/update/delete.

---

### Task 1: Create Migration for `events` and `event_photos` Tables

**Files:**
- Create: `database/migrations/2026_02_05_000001_create_events_table.php`
- Create: `database/migrations/2026_02_05_000002_create_event_photos_table.php`

**Step 1: Create events migration**

Run: `php artisan make:migration create_events_table --no-interaction`

Then replace the generated file content with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignUuid('partner_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('partner_type', 20);
            $table->date('event_date');
            $table->unsignedInteger('attendee_count')->default(0);
            $table->timestamps();

            $table->index('profile_id');
            $table->index('partner_id');
            $table->index('event_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

**Step 2: Create event_photos migration**

Run: `php artisan make:migration create_event_photos_table --no-interaction`

Then replace the generated file content with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->text('url');
            $table->text('thumbnail_url')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
    }
};
```

**Step 3: Run migrations**

Run: `php artisan migrate --no-interaction`
Expected: Both tables created successfully.

**Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add events and event_photos migration tables"
```

---

### Task 2: Create Event and EventPhoto Models with Factories

**Files:**
- Create: `app/Models/Event.php`
- Create: `app/Models/EventPhoto.php`
- Create: `database/factories/EventFactory.php`
- Create: `database/factories/EventPhotoFactory.php`
- Modify: `app/Models/Profile.php` (add `events()` relationship)

**Step 1: Create Event model**

Run: `php artisan make:model Event --no-interaction`

Then replace the generated file with:

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
 * @property string $profile_id
 * @property string $name
 * @property string $partner_id
 * @property string $partner_type
 * @property \Illuminate\Support\Carbon $event_date
 * @property int $attendee_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Profile $profile
 * @property-read Profile $partner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EventPhoto> $photos
 */
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'profile_id',
        'name',
        'partner_id',
        'partner_type',
        'event_date',
        'attendee_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendee_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * @return BelongsTo<Profile, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'partner_id');
    }

    /**
     * @return HasMany<EventPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(EventPhoto::class)->orderBy('sort_order');
    }
}
```

**Step 2: Create EventPhoto model**

Run: `php artisan make:model EventPhoto --no-interaction`

Then replace the generated file with:

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
 * @property string $url
 * @property string|null $thumbnail_url
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Event $event
 */
class EventPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\EventPhotoFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'url',
        'thumbnail_url',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
```

**Step 3: Create EventFactory**

Run: `php artisan make:factory EventFactory --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'name' => $this->faker->sentence(3),
            'partner_id' => Profile::factory(),
            'partner_type' => $this->faker->randomElement([UserType::Business->value, UserType::Community->value]),
            'event_date' => $this->faker->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
            'attendee_count' => $this->faker->numberBetween(1, 5000),
        ];
    }

    /**
     * Set the owner profile for this event.
     */
    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => [
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * Set the partner for this event.
     */
    public function withPartner(Profile $partner): static
    {
        return $this->state(fn (): array => [
            'partner_id' => $partner->id,
            'partner_type' => $partner->user_type->value,
        ]);
    }
}
```

**Step 4: Create EventPhotoFactory**

Run: `php artisan make:factory EventPhotoFactory --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPhoto>
 */
class EventPhotoFactory extends Factory
{
    protected $model = EventPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'url' => $this->faker->imageUrl(800, 600),
            'thumbnail_url' => null,
            'sort_order' => $this->faker->numberBetween(0, 4),
        ];
    }

    /**
     * Set the event for this photo.
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => [
            'event_id' => $event->id,
        ]);
    }
}
```

**Step 5: Add `events()` relationship to Profile model**

Open `app/Models/Profile.php` and add the following method alongside the existing relationship methods (near `galleryPhotos()`):

```php
/**
 * @return \Illuminate\Database\Eloquent\Relations\HasMany<Event, $this>
 */
public function events(): HasMany
{
    return $this->hasMany(Event::class, 'profile_id');
}
```

Also add the import at the top if `Event` isn't already imported:
```php
use App\Models\Event;
```

**Step 6: Verify models work**

Run: `php artisan tinker --execute="echo App\Models\Event::query()->count();"` (should output 0)

**Step 7: Commit**

```bash
git add app/Models/Event.php app/Models/EventPhoto.php database/factories/EventFactory.php database/factories/EventPhotoFactory.php app/Models/Profile.php
git commit -m "feat: add Event and EventPhoto models with factories"
```

---

### Task 3: Add `EventPhoto` Case to `FileUploadType` Enum

**Files:**
- Modify: `app/Enums/FileUploadType.php`

**Step 1: Add the new enum case**

Open `app/Enums/FileUploadType.php` and add a new case:

```php
case EventPhoto = 'event_photo';
```

**Step 2: Update all match expressions**

In `getStorageDirectory()`, add:
```php
self::EventPhoto => 'events',
```

In `getMaxFileSize()`, add:
```php
self::EventPhoto => 5 * 1024 * 1024, // 5MB
```

In `getAllowedMimeTypes()`, update the match to include `EventPhoto`:
```php
self::ProfilePhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto => [
```

In `getAllowedExtensions()`, update similarly:
```php
self::ProfilePhoto, self::OpportunityPhoto, self::GalleryPhoto, self::EventPhoto => [
```

**Step 3: Run existing tests to verify no regressions**

Run: `php artisan test --compact --filter=Gallery`
Expected: All gallery tests still pass (no regressions from enum change).

**Step 4: Commit**

```bash
git add app/Enums/FileUploadType.php
git commit -m "feat: add EventPhoto case to FileUploadType enum"
```

---

### Task 4: Create EventPolicy

**Files:**
- Create: `app/Policies/EventPolicy.php`

**Step 1: Create the policy**

Run: `php artisan make:policy EventPolicy --model=Event --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\Profile;

class EventPolicy
{
    /**
     * Any authenticated user can view events (public profiles).
     */
    public function viewAny(Profile $user): bool
    {
        return true;
    }

    /**
     * Any authenticated user can view a single event.
     */
    public function view(Profile $user, Event $event): bool
    {
        return true;
    }

    /**
     * Any authenticated user can create events.
     */
    public function create(Profile $user): bool
    {
        return true;
    }

    /**
     * Only the event owner can update.
     */
    public function update(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }

    /**
     * Only the event owner can delete.
     */
    public function delete(Profile $user, Event $event): bool
    {
        return $user->id === $event->profile_id;
    }
}
```

**Step 2: Register the policy in `AppServiceProvider` or `AuthServiceProvider`**

Check if the app uses auto-discovery for policies (Laravel 12 does by default). The `EventPolicy` will be auto-discovered since it follows the naming convention `Event` -> `EventPolicy`. No manual registration needed.

**Step 3: Commit**

```bash
git add app/Policies/EventPolicy.php
git commit -m "feat: add EventPolicy for authorization"
```

---

### Task 5: Create Form Requests (StoreEventRequest, UpdateEventRequest)

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreEventRequest.php`
- Create: `app/Http/Requests/Api/V1/UpdateEventRequest.php`

**Step 1: Create StoreEventRequest**

Run: `php artisan make:request Api/V1/StoreEventRequest --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'partner_id' => ['required', 'uuid', 'exists:profiles,id'],
            'partner_type' => ['required', 'string', Rule::in([UserType::Business->value, UserType::Community->value])],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Event name is required.',
            'name.min' => 'Event name must be at least 3 characters.',
            'name.max' => 'Event name cannot exceed 100 characters.',
            'partner_id.required' => 'Partner is required.',
            'partner_id.exists' => 'The selected partner does not exist.',
            'partner_type.required' => 'Partner type is required.',
            'partner_type.in' => 'Partner type must be business or community.',
            'date.required' => 'Event date is required.',
            'date.before_or_equal' => 'Event date cannot be in the future.',
            'attendee_count.required' => 'Attendee count is required.',
            'attendee_count.min' => 'Attendee count must be at least 1.',
            'photos.required' => 'At least one photo is required.',
            'photos.max' => 'You can upload a maximum of 5 photos.',
            'photos.*.image' => 'Each photo must be an image file.',
            'photos.*.mimes' => 'Photos must be jpeg, jpg, png, gif, or webp.',
            'photos.*.max' => 'Each photo must not exceed 5MB.',
        ];
    }
}
```

**Step 2: Create UpdateEventRequest**

Run: `php artisan make:request Api/V1/UpdateEventRequest --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
            'partner_id' => ['sometimes', 'uuid', 'exists:profiles,id'],
            'partner_type' => ['sometimes', 'string', Rule::in([UserType::Business->value, UserType::Community->value])],
            'date' => ['sometimes', 'date', 'before_or_equal:today'],
            'attendee_count' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Event name must be at least 3 characters.',
            'name.max' => 'Event name cannot exceed 100 characters.',
            'partner_id.exists' => 'The selected partner does not exist.',
            'partner_type.in' => 'Partner type must be business or community.',
            'date.before_or_equal' => 'Event date cannot be in the future.',
            'attendee_count.min' => 'Attendee count must be at least 1.',
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Requests/Api/V1/StoreEventRequest.php app/Http/Requests/Api/V1/UpdateEventRequest.php
git commit -m "feat: add StoreEventRequest and UpdateEventRequest form requests"
```

---

### Task 6: Create API Resources (EventResource, EventPhotoResource, EventPartnerResource)

**Files:**
- Create: `app/Http/Resources/Api/V1/EventResource.php`
- Create: `app/Http/Resources/Api/V1/EventPhotoResource.php`
- Create: `app/Http/Resources/Api/V1/EventPartnerResource.php`

**Step 1: Create EventPhotoResource**

Run: `php artisan make:resource Api/V1/EventPhotoResource --no-interaction`

Then replace with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EventPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventPhoto
 */
class EventPhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
        ];
    }
}
```

**Step 2: Create EventPartnerResource**

Run: `php artisan make:resource Api/V1/EventPartnerResource --no-interaction`

Then replace with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class EventPartnerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->resource->getExtendedProfile();

        return [
            'id' => $this->id,
            'name' => $extendedProfile?->name,
            'profile_photo' => $extendedProfile?->profile_photo,
            'type' => $this->user_type->value,
        ];
    }
}
```

**Step 3: Create EventResource**

Run: `php artisan make:resource Api/V1/EventResource --no-interaction`

Then replace with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'partner' => new EventPartnerResource($this->whenLoaded('partner')),
            'date' => $this->event_date->toDateString(),
            'attendee_count' => $this->attendee_count,
            'photos' => EventPhotoResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Resources/Api/V1/EventResource.php app/Http/Resources/Api/V1/EventPhotoResource.php app/Http/Resources/Api/V1/EventPartnerResource.php
git commit -m "feat: add Event API resources (EventResource, EventPhotoResource, EventPartnerResource)"
```

---

### Task 7: Create EventService

**Files:**
- Create: `app/Services/EventService.php`

**Step 1: Create the service class**

Run: `php artisan make:class Services/EventService --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * List events for a given profile with pagination.
     */
    public function listForProfile(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return Event::query()
            ->where('profile_id', $profile->id)
            ->with(['partner', 'photos'])
            ->orderByDesc('event_date')
            ->paginate($perPage);
    }

    /**
     * Get a single event with relations loaded.
     */
    public function getWithRelations(Event $event): Event
    {
        return $event->load(['partner', 'photos']);
    }

    /**
     * Create a new event with photos.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function create(Profile $profile, array $data, array $photos): Event
    {
        return DB::transaction(function () use ($profile, $data, $photos): Event {
            $event = Event::query()->create([
                'profile_id' => $profile->id,
                'name' => $data['name'],
                'partner_id' => $data['partner_id'],
                'partner_type' => $data['partner_type'],
                'event_date' => $data['date'],
                'attendee_count' => $data['attendee_count'],
            ]);

            $this->uploadPhotos($event, $photos);

            return $event->load(['partner', 'photos']);
        });
    }

    /**
     * Update an existing event.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Event $event, array $data): Event
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['partner_id'])) {
            $updateData['partner_id'] = $data['partner_id'];
        }
        if (isset($data['partner_type'])) {
            $updateData['partner_type'] = $data['partner_type'];
        }
        if (isset($data['date'])) {
            $updateData['event_date'] = $data['date'];
        }
        if (isset($data['attendee_count'])) {
            $updateData['attendee_count'] = $data['attendee_count'];
        }

        if (! empty($updateData)) {
            $event->update($updateData);
        }

        return $event->load(['partner', 'photos']);
    }

    /**
     * Delete an event and its photos from storage.
     */
    public function delete(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            foreach ($event->photos as $photo) {
                $this->fileUploadService->delete($photo->url);
            }

            $event->delete();
        });
    }

    /**
     * Upload photos for an event.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    private function uploadPhotos(Event $event, array $photos): void
    {
        foreach ($photos as $index => $photo) {
            $url = $this->fileUploadService->uploadFromFile(
                $photo,
                FileUploadType::EventPhoto,
                $event->id
            );

            EventPhoto::query()->create([
                'event_id' => $event->id,
                'url' => $url,
                'sort_order' => $index,
            ]);
        }
    }
}
```

**Step 2: Commit**

```bash
git add app/Services/EventService.php
git commit -m "feat: add EventService with CRUD and photo upload logic"
```

---

### Task 8: Create EventController

**Files:**
- Create: `app/Http/Controllers/Api/V1/EventController.php`

**Step 1: Create the controller**

Run: `php artisan make:controller Api/V1/EventController --api --no-interaction`

Then replace the generated file with:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Models\Profile;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    /**
     * List events for a profile.
     *
     * GET /api/v1/events?profile_id={uuid}
     * Defaults to authenticated user if profile_id not provided.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $authProfile */
        $authProfile = $request->user();

        $profileId = $request->query('profile_id');
        $perPage = min((int) $request->query('limit', '10'), 50);

        if ($profileId) {
            $profile = Profile::query()->findOrFail($profileId);
        } else {
            $profile = $authProfile;
        }

        $paginator = $this->eventService->listForProfile($profile, $perPage);

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

    /**
     * Get a single event.
     *
     * GET /api/v1/events/{event}
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event = $this->eventService->getWithRelations($event);

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Create a new event.
     *
     * POST /api/v1/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorize('create', Event::class);

        /** @var Profile $profile */
        $profile = $request->user();

        $event = $this->eventService->create(
            $profile,
            $request->validated(),
            $request->file('photos')
        );

        return response()->json([
            'success' => true,
            'message' => __('Event created successfully.'),
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * Update an event.
     *
     * PUT /api/v1/events/{event}
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event = $this->eventService->update($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Event updated successfully.'),
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Delete an event.
     *
     * DELETE /api/v1/events/{event}
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'message' => __('Event deleted successfully.'),
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/V1/EventController.php
git commit -m "feat: add EventController with full CRUD"
```

---

### Task 9: Register API Routes

**Files:**
- Modify: `routes/api.php`

**Step 1: Add the import at the top of `routes/api.php`**

```php
use App\Http\Controllers\Api\V1\EventController;
```

**Step 2: Add event routes inside the `auth:sanctum` middleware group**

Add the following block after the Gallery section (around line 175), before the Public Profile section:

```php
/*
|--------------------------------------------------------------------------
| Events (Past Events)
|--------------------------------------------------------------------------
*/

// List events (own or by profile_id query param)
Route::get('events', [EventController::class, 'index'])
    ->name('api.v1.events.index');

// Get single event
Route::get('events/{event}', [EventController::class, 'show'])
    ->name('api.v1.events.show');

// Create event
Route::post('events', [EventController::class, 'store'])
    ->name('api.v1.events.store');

// Update event
Route::put('events/{event}', [EventController::class, 'update'])
    ->name('api.v1.events.update');

// Delete event
Route::delete('events/{event}', [EventController::class, 'destroy'])
    ->name('api.v1.events.destroy');
```

**Step 3: Verify routes are registered**

Run: `php artisan route:list --path=events`
Expected: 5 routes listed (GET index, GET show, POST store, PUT update, DELETE destroy).

**Step 4: Commit**

```bash
git add routes/api.php
git commit -m "feat: register event API routes"
```

---

### Task 10: Write Feature Tests

**Files:**
- Create: `tests/Feature/Api/V1/EventTest.php`

**Step 1: Create the test file**

Run: `php artisan make:test Api/V1/EventTest --phpunit --no-interaction`

Then replace the generated file with the comprehensive test suite:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | List Events (GET /api/v1/events)
    |--------------------------------------------------------------------------
    */

    public function test_list_events_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/events');

        $response->assertStatus(401);
    }

    public function test_list_own_events(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        Event::factory()->count(3)->forProfile($profile)->withPartner($partner)->create();

        // Another user's events (should not appear)
        $other = Profile::factory()->business()->create();
        Event::factory()->count(2)->forProfile($other)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.events')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events' => [
                        '*' => ['id', 'name', 'partner', 'date', 'attendee_count', 'photos', 'created_at'],
                    ],
                    'pagination' => ['current_page', 'total_pages', 'total_count', 'per_page'],
                ],
            ]);
    }

    public function test_list_events_for_another_profile(): void
    {
        $viewer = Profile::factory()->business()->create();
        $target = Profile::factory()->community()->create();
        $partner = Profile::factory()->business()->create();
        Event::factory()->count(2)->forProfile($target)->withPartner($partner)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/events?profile_id={$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.events');
    }

    public function test_list_events_respects_pagination_limit(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        Event::factory()->count(5)->forProfile($profile)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.pagination.total_count', 5)
            ->assertJsonPath('data.pagination.per_page', 2);
    }

    public function test_list_events_max_limit_is_50(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events?limit=100');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    public function test_list_events_returns_empty_when_no_events(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_list_events_ordered_by_date_descending(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        Event::factory()->forProfile($profile)->withPartner($partner)->create(['event_date' => '2025-01-01', 'name' => 'Oldest']);
        Event::factory()->forProfile($profile)->withPartner($partner)->create(['event_date' => '2025-06-15', 'name' => 'Middle']);
        Event::factory()->forProfile($profile)->withPartner($partner)->create(['event_date' => '2025-12-01', 'name' => 'Newest']);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events');

        $response->assertStatus(200);
        $events = $response->json('data.events');
        $this->assertEquals('Newest', $events[0]['name']);
        $this->assertEquals('Middle', $events[1]['name']);
        $this->assertEquals('Oldest', $events[2]['name']);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Single Event (GET /api/v1/events/{id})
    |--------------------------------------------------------------------------
    */

    public function test_show_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_show_event_returns_full_details(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create();
        EventPhoto::factory()->count(2)->forEvent($event)->create();

        $response = $this->actingAs($profile)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id', 'name', 'partner' => ['id', 'name', 'profile_photo', 'type'],
                    'date', 'attendee_count',
                    'photos' => ['*' => ['id', 'url', 'thumbnail_url']],
                    'created_at', 'updated_at',
                ],
            ]);
    }

    public function test_any_authenticated_user_can_view_event(): void
    {
        $owner = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $viewer = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_show_event_returns_404_for_invalid_id(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/events/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Event (POST /api/v1/events)
    |--------------------------------------------------------------------------
    */

    public function test_create_event_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/events');

        $response->assertStatus(401);
    }

    public function test_create_event_with_valid_data(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Summer Music Festival',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-08-15',
                'attendee_count' => 1250,
                'photos' => [
                    UploadedFile::fake()->image('photo1.jpg', 800, 600),
                    UploadedFile::fake()->image('photo2.jpg', 800, 600),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Summer Music Festival')
            ->assertJsonPath('data.attendee_count', 1250)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'id', 'name', 'partner', 'date', 'attendee_count', 'photos', 'created_at', 'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('events', [
            'profile_id' => $profile->id,
            'name' => 'Summer Music Festival',
            'partner_id' => $partner->id,
            'attendee_count' => 1250,
        ]);

        $this->assertDatabaseCount('event_photos', 2);
    }

    public function test_create_event_validates_required_fields(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'partner_id', 'partner_type', 'date', 'attendee_count', 'photos']);
    }

    public function test_create_event_validates_name_min_length(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'AB',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-08-15',
                'attendee_count' => 10,
                'photos' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_event_rejects_future_date(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Future Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2099-01-01',
                'attendee_count' => 10,
                'photos' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_create_event_rejects_invalid_partner_id(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => '00000000-0000-0000-0000-000000000000',
                'partner_type' => 'community',
                'date' => '2025-01-01',
                'attendee_count' => 10,
                'photos' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_id']);
    }

    public function test_create_event_rejects_invalid_partner_type(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => $partner->id,
                'partner_type' => 'invalid',
                'date' => '2025-01-01',
                'attendee_count' => 10,
                'photos' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_type']);
    }

    public function test_create_event_rejects_more_than_5_photos(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = UploadedFile::fake()->image("photo{$i}.jpg", 800, 600);
        }

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-01-01',
                'attendee_count' => 10,
                'photos' => $photos,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos']);
    }

    public function test_create_event_rejects_non_image_photos(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-01-01',
                'attendee_count' => 10,
                'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photos.0']);
    }

    public function test_create_event_rejects_zero_attendee_count(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Test Event',
                'partner_id' => $partner->id,
                'partner_type' => 'community',
                'date' => '2025-01-01',
                'attendee_count' => 0,
                'photos' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['attendee_count']);
    }

    public function test_community_user_can_create_event(): void
    {
        $profile = Profile::factory()->community()->create();
        $partner = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/events', [
                'name' => 'Community Meetup',
                'partner_id' => $partner->id,
                'partner_type' => 'business',
                'date' => '2025-05-01',
                'attendee_count' => 50,
                'photos' => [UploadedFile::fake()->image('photo.jpg', 800, 600)],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Event (PUT /api/v1/events/{id})
    |--------------------------------------------------------------------------
    */

    public function test_update_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->putJson("/api/v1/events/{$event->id}", ['name' => 'Updated']);

        $response->assertStatus(401);
    }

    public function test_owner_can_update_event(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'Updated Festival Name',
                'attendee_count' => 2000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Festival Name')
            ->assertJsonPath('data.attendee_count', 2000);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'name' => 'Updated Festival Name',
            'attendee_count' => 2000,
        ]);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $nonOwner = Profile::factory()->community()->create();

        $response = $this->actingAs($nonOwner)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertStatus(403);
    }

    public function test_update_event_validates_fields(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create();

        $response = $this->actingAs($profile)
            ->putJson("/api/v1/events/{$event->id}", [
                'name' => 'AB',
                'attendee_count' => 0,
                'date' => '2099-01-01',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'attendee_count', 'date']);
    }

    public function test_update_event_partial_update(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create([
            'name' => 'Original Name',
            'attendee_count' => 100,
        ]);

        $response = $this->actingAs($profile)
            ->putJson("/api/v1/events/{$event->id}", [
                'attendee_count' => 200,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Original Name')
            ->assertJsonPath('data.attendee_count', 200);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Event (DELETE /api/v1/events/{id})
    |--------------------------------------------------------------------------
    */

    public function test_delete_event_requires_authentication(): void
    {
        $event = Event::factory()->create();

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(401);
    }

    public function test_owner_can_delete_event(): void
    {
        $profile = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($profile)->withPartner($partner)->create();
        EventPhoto::factory()->count(2)->forEvent($event)->create();

        $response = $this->actingAs($profile)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
        $this->assertDatabaseCount('event_photos', 0);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = Profile::factory()->business()->create();
        $partner = Profile::factory()->community()->create();
        $event = Event::factory()->forProfile($owner)->withPartner($partner)->create();

        $nonOwner = Profile::factory()->community()->create();

        $response = $this->actingAs($nonOwner)
            ->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_delete_event_returns_404_for_invalid_id(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->deleteJson('/api/v1/events/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }
}
```

**Step 2: Run all event tests**

Run: `php artisan test --compact tests/Feature/Api/V1/EventTest.php`
Expected: All tests pass.

**Step 3: Commit**

```bash
git add tests/Feature/Api/V1/EventTest.php
git commit -m "feat: add comprehensive EventTest feature tests"
```

---

### Task 11: Run Full Test Suite and Fix Pint

**Step 1: Run Pint to fix code style**

Run: `vendor/bin/pint --dirty`

**Step 2: Commit any Pint fixes**

```bash
git add -A
git commit -m "style: fix code formatting with Pint"
```

**Step 3: Run the full test suite**

Run: `php artisan test --compact`
Expected: All tests pass, no regressions.

---

## Summary of Files Created/Modified

**New files (13):**
- `database/migrations/2026_02_05_000001_create_events_table.php`
- `database/migrations/2026_02_05_000002_create_event_photos_table.php`
- `app/Models/Event.php`
- `app/Models/EventPhoto.php`
- `database/factories/EventFactory.php`
- `database/factories/EventPhotoFactory.php`
- `app/Policies/EventPolicy.php`
- `app/Http/Requests/Api/V1/StoreEventRequest.php`
- `app/Http/Requests/Api/V1/UpdateEventRequest.php`
- `app/Http/Resources/Api/V1/EventResource.php`
- `app/Http/Resources/Api/V1/EventPhotoResource.php`
- `app/Http/Resources/Api/V1/EventPartnerResource.php`
- `app/Http/Controllers/Api/V1/EventController.php`
- `app/Services/EventService.php`
- `tests/Feature/Api/V1/EventTest.php`

**Modified files (3):**
- `app/Enums/FileUploadType.php` (add `EventPhoto` case)
- `app/Models/Profile.php` (add `events()` relationship)
- `routes/api.php` (add event routes)
