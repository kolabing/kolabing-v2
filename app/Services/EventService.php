<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Enums\FileUploadType;
use App\Enums\NotificationType;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\EventSignup;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventService
{
    /**
     * Maximum number of photos an event gallery may hold in total. Each
     * POST /events/{event}/photos request may add up to 5 (StoreEventPhotosRequest),
     * but the gallery never grows past this cap.
     */
    public const int MAX_EVENT_PHOTOS = 20;

    public function __construct(
        private readonly FileUploadService $fileUploadService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * List events for a given profile with pagination.
     */
    public function listForProfile(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return Event::query()
            ->where('profile_id', $profile->id)
            ->with(['photos'])
            ->orderByDesc('event_date')
            ->paginate($perPage);
    }

    /**
     * Filtered event list (NF-CHAT Phase 3 §6.1) — one events lifecycle feeds the
     * upcoming tab, community Details (past + gallery) and the profile showcase.
     *
     * @param  array{community_id?: ?string, profile_id?: ?string, attendee_profile_id?: ?string, time?: ?string}  $filters
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        // COALESCE(ends_at, starts_at, event_date) = the event's effective time.
        $effective = 'COALESCE(ends_at, starts_at, event_date)';

        $query = Event::query()->with(['photos']);

        if (! empty($filters['community_id'])) {
            $query->where('community_id', $filters['community_id']);
        }

        if (! empty($filters['profile_id'])) {
            $query->where('profile_id', $filters['profile_id']);
        }

        if (! empty($filters['attendee_profile_id'])) {
            $attendeeId = $filters['attendee_profile_id'];
            $query->where(function ($q) use ($attendeeId): void {
                $q->whereHas('checkins', fn ($c) => $c->where('profile_id', $attendeeId))
                    ->orWhereHas('signups', fn ($s) => $s->where('profile_id', $attendeeId)
                        ->where('status', '!=', 'cancelled'));
            });
        }

        if (! empty($filters['follower_profile_id'])) {
            $followerId = $filters['follower_profile_id'];
            // Only a community's events can be followed — an event with no
            // community has no one to follow.
            $query->whereHas(
                'community.followers',
                fn ($f) => $f->where('profile_id', $followerId)
            );
        }

        $time = $filters['time'] ?? null;
        if ($time === 'upcoming') {
            $query->whereRaw("$effective >= ?", [now()])
                ->orderByRaw('COALESCE(starts_at, event_date) asc');
        } elseif ($time === 'past') {
            $query->whereRaw("$effective < ?", [now()])
                ->orderByRaw('COALESCE(starts_at, event_date) desc');
        } else {
            $query->orderByDesc('event_date');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a single event with relations loaded.
     */
    public function getWithRelations(Event $event): Event
    {
        return $event->load(['photos']);
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
            // Upcoming-event mode (Phase 3) is keyed on `starts_at`; otherwise this
            // is the legacy retroactive past-showcase create.
            $event = empty($data['starts_at'])
                ? Event::query()->create([
                    'profile_id' => $profile->id,
                    'name' => $data['name'],
                    'partner_name' => $data['partner_name'],
                    'partner_type' => $data['partner_type'],
                    'event_date' => $data['date'],
                    'attendee_count' => $data['attendee_count'],
                    'visibility' => EventVisibility::Members->value,
                ])
                : $this->buildUpcoming($profile, $data);

            if ($photos !== []) {
                $this->uploadPhotos($event, $photos);
            }

            return $event->load(['photos']);
        });
    }

    /**
     * Create an upcoming community event (sign-up/waitlist/chat apply). Defaults
     * partner + event_date from the community/start so the legacy NOT NULL columns
     * stay satisfied.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildUpcoming(Profile $profile, array $data): Event
    {
        $community = Community::query()->find($data['community_id']);
        $startsAt = Carbon::parse($data['starts_at']);

        return Event::query()->create([
            'profile_id' => $profile->id,
            'community_id' => $data['community_id'],
            'city_id' => $data['city_id'] ?? null,
            'collaboration_id' => $data['collaboration_id'] ?? null,
            'name' => $data['name'],
            'partner_name' => $community?->name ?? $data['name'],
            'partner_type' => UserType::Community->value,
            'event_date' => $startsAt->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
            'location' => $data['location'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'tier_gate' => $data['tier_gate'] ?? null,
            'visibility' => $data['visibility'] ?? EventVisibility::Members->value,
            'attendee_count' => 0,
        ]);
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
        if (isset($data['partner_name'])) {
            $updateData['partner_name'] = $data['partner_name'];
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

        // Upcoming-event fields (NF-16 edit). starts_at also syncs event_date.
        if (isset($data['starts_at'])) {
            $startsAt = Carbon::parse($data['starts_at']);
            $updateData['starts_at'] = $startsAt;
            $updateData['event_date'] = $startsAt->toDateString();
        }
        if (array_key_exists('ends_at', $data)) {
            $updateData['ends_at'] = $data['ends_at'] !== null ? Carbon::parse($data['ends_at']) : null;
        }
        foreach (['city_id', 'location', 'capacity', 'tier_gate', 'visibility'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (! empty($updateData)) {
            $event->update($updateData);
        }

        return $event->load(['photos']);
    }

    /**
     * Add photos to an existing event (gallery grows). Total capped at
     * self::MAX_EVENT_PHOTOS (per-request count is capped at 5 in the request).
     *
     * @param  array<int, UploadedFile>  $photos
     *
     * @throws \DomainException 'photo_limit_reached'
     */
    public function addPhotos(Event $event, array $photos): Event
    {
        $existing = $event->photos()->count();

        if ($existing + count($photos) > self::MAX_EVENT_PHOTOS) {
            throw new \DomainException('photo_limit_reached');
        }

        return DB::transaction(function () use ($event, $photos, $existing): Event {
            foreach (array_values($photos) as $i => $photo) {
                $url = $this->fileUploadService->uploadFromFile(
                    $photo,
                    FileUploadType::EventPhoto,
                    $event->id
                );

                EventPhoto::query()->create([
                    'event_id' => $event->id,
                    'url' => $url,
                    'sort_order' => $existing + $i,
                ]);
            }

            return $event->load(['photos']);
        });
    }

    /**
     * Remove a single photo from an event (file + row).
     */
    public function deletePhoto(EventPhoto $photo): void
    {
        DB::transaction(function () use ($photo): void {
            $this->fileUploadService->delete($photo->url);
            $photo->delete();
        });
    }

    /**
     * Delete an event and its photos from storage. Anyone holding a live sign-up
     * (going or waitlisted) is notified the event was cancelled, before the rows
     * cascade away.
     */
    public function delete(Event $event): void
    {
        $event->load('photos');

        $recipientIds = EventSignup::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                EventSignupStatus::Going->value,
                EventSignupStatus::Waitlisted->value,
            ])
            ->pluck('profile_id')
            ->all();

        DB::transaction(function () use ($event): void {
            foreach ($event->photos as $photo) {
                $this->fileUploadService->delete($photo->url);
            }

            $event->delete();
        });

        if ($recipientIds !== []) {
            $this->notificationService->recordNotifications(
                recipientIds: $recipientIds,
                type: NotificationType::EventCancelled,
                title: __('Event cancelled'),
                body: __('":event" has been cancelled by the organiser.', ['event' => $event->name]),
                actorProfileId: $event->profile_id,
                targetId: $event->community_id,
                targetType: 'community',
            );
        }
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
