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
