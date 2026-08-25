<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\EventCheckin;
use App\Models\EventSignup;
use App\Services\TicketService;
use App\Support\TicketLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EventSignup
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->event;

        return [
            'id' => $this->id,
            'code' => $this->ticket_code,
            'status' => $this->status->value,
            'issued_at' => $this->ticket_issued_at?->toIso8601String(),
            /*
             * The QR travels with the ticket rather than behind a second request. The
             * door is exactly where the network is worst, so a ticket that needs
             * another round trip to become scannable is a ticket that fails in a
             * basement bar. It is an inline SVG — no image host, no CDN, nothing to
             * be blocked.
             */
            'qr_svg' => app(TicketService::class)->qrSvg($this->resource),
            'admit_url' => TicketLink::admitUrl($this->resource),
            'used_at' => $this->usedAt(),
            /*
             * Who is being let in. The doorkeeper needs it — the whole point of a
             * ticket is that the person scanning is not the person authenticated —
             * and only the holder and the host can read this resource at all.
             */
            'holder_name' => $this->profile?->name
                ?? $this->profile?->businessProfile?->name
                ?? $this->profile?->communityProfile?->name,
            'event' => $event === null ? null : [
                'id' => $event->id,
                'name' => $event->name,
                'host_name' => $event->partner_name,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'event_date' => $event->event_date?->toDateString(),
                'address' => $event->address,
                'location' => $event->location,
            ],
        ];
    }

    /**
     * When the holder was admitted, if they have been.
     *
     * Read from `event_checkins` rather than a column on the ticket, because that is
     * where attendance lives however someone got in — the host scanning a ticket and
     * the attendee scanning the host's door QR both write there, and a ticket that
     * disagreed with the attendance list would be worse than one that says nothing.
     */
    private function usedAt(): ?string
    {
        $checkin = EventCheckin::query()
            ->where('event_id', $this->event_id)
            ->where('profile_id', $this->profile_id)
            ->first();

        return $checkin?->checked_in_at?->toIso8601String();
    }
}
