<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\CheckinService;
use App\Services\CollaborationHappeningService;
use App\Support\CheckinLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollaborationQrCodeController extends Controller
{
    public function __construct(
        private readonly CheckinService $checkinService,
        private readonly CollaborationHappeningService $happenings,
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
            /*
             * One place builds a happening: CollaborationHappeningService. This used
             * to create the Event inline, which had two consequences worth naming.
             * It set `partner_name` from `$profile->display_name` — an attribute that
             * does not exist on Profile — so every generated event was hosted with
             * "Partner". And it left `visibility` at the `members` default with no
             * `community_id`, which is exactly the combination EventSignupService
             * refuses, so nobody could ever sign up to a Kolab's happening.
             */
            $event = $this->happenings->ensureFor($collaboration);

            if ($event === null) {
                throw new \LogicException('A cancelled collaboration has no door.');
            }

            // One place mints tokens, so every event gets the typable code and the
            // expiry window too. Re-minting also reopens a door that has closed.
            if (! $event->checkin_token || $event->checkin_token_expires_at?->isPast()) {
                $this->checkinService->generateCheckinToken($event);
            }

            /*
             * This used to build url("/api/v1/events/{id}/checkin?token=…") — a route
             * that does not exist (check-in is POST /api/v1/checkin with the token in
             * the body), so a phone scanning the QR got a 404. It also put the secret
             * in a query string, where it lands in logs and browser history. The QR
             * now carries the panel page that performs the check-in.
             */
            $qrCodeUrl = CheckinLink::urlFor($event->refresh());

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
