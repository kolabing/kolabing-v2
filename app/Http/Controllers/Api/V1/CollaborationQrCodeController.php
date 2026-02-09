<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollaborationQrCodeController extends Controller
{
    public function __construct(
        private readonly CheckinService $checkinService
    ) {}

    /**
     * Generate a QR code for a collaboration's event.
     *
     * POST /api/v1/collaborations/{collaboration}/qr-code
     */
    public function store(Request $request, Collaboration $collaboration): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $collaboration)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $result = DB::transaction(function () use ($collaboration): array {
            $event = $collaboration->event;

            if (! $event) {
                $collaboration->loadMissing(['collabOpportunity', 'applicantProfile']);

                $event = Event::create([
                    'profile_id' => $collaboration->creator_profile_id,
                    'name' => $collaboration->collabOpportunity?->title ?? 'Collaboration Event',
                    'partner_name' => $collaboration->applicantProfile?->display_name ?? 'Partner',
                    'partner_type' => $collaboration->applicantProfile?->user_type?->value ?? 'community',
                    'event_date' => $collaboration->scheduled_date ?? now(),
                    'is_active' => true,
                    'checkin_token' => Str::random(64),
                ]);

                $collaboration->update(['event_id' => $event->id]);
            }

            if (! $event->checkin_token) {
                $this->checkinService->generateCheckinToken($event);
                $event->refresh();
            }

            $qrCodeUrl = url("/api/v1/events/{$event->id}/checkin?token={$event->checkin_token}");

            $collaboration->update(['qr_code_url' => $qrCodeUrl]);

            return [
                'event_id' => $event->id,
                'qr_code_url' => $qrCodeUrl,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
