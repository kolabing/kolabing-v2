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
            $event = Event::query()->create([
                'profile_id' => $profile->id,
                'name' => $data['name'],
                'partner_name' => $data['partner_name'],
                'partner_type' => $data['partner_type'],
                'event_date' => $data['date'],
                'attendee_count' => $data['attendee_count'],
            ]);

            $this->uploadPhotos($event, $photos);

            return $event->load(['photos']);
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

        if (! empty($updateData)) {
            $event->update($updateData);
        }

        return $event->load(['photos']);
    }

    /**
     * Delete an event and its photos from storage.
     */
    public function delete(Event $event): void
    {
        $event->load('photos');

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
