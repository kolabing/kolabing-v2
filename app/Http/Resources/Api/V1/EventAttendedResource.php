<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EventCheckin;
use App\Services\AttendeeProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single attended-event row (an EventCheckin joined to its Event + community)
 * for the attendee events-attended history.
 *
 * @mixin EventCheckin
 */
class EventAttendedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EventCheckin $checkin */
        $checkin = $this->resource;

        return app(AttendeeProfileService::class)->formatCheckin($checkin);
    }
}
