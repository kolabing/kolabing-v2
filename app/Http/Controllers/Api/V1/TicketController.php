<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Profile;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LogicException;

/**
 * Tickets: the holder's wallet, and the doorkeeper's scanner.
 *
 * Two audiences, two very different authorisation rules, which is why they are
 * separate actions rather than one polymorphic endpoint:
 *  - {@see index()} and {@see show()} answer "what am I holding" — the holder.
 *  - {@see admit()} answers "let this person in" — the host, about someone else.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    /**
     * GET /api/v1/me/tickets — what the caller is holding.
     *
     * Upcoming by default; `?past=1` for the ones already used, because a ticket is
     * also a record of having been somewhere.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $upcomingOnly = ! $request->boolean('past');

        return response()->json([
            'success' => true,
            'data' => TicketResource::collection(
                $this->tickets->forProfile($profile, $upcomingOnly)
            ),
        ]);
    }

    /**
     * GET /api/v1/tickets/{code} — one ticket.
     *
     * Readable by the holder (their own ticket) and by the event's host (who has to
     * be able to look up a code someone reads out when a QR will not scan). Anyone
     * else gets a 404 rather than a 403: whether a code exists is itself information.
     */
    public function show(Request $request, string $code): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $ticket = $this->tickets->findByCode($code);

        $mayRead = $ticket !== null
            && ($ticket->profile_id === $profile->id || $ticket->event?->isHostedBy($profile));

        if (! $mayRead) {
            return response()->json([
                'success' => false,
                'message' => __('That ticket does not exist.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new TicketResource($ticket),
        ]);
    }

    /**
     * POST /api/v1/tickets/{code}/admit — the host lets the holder in.
     *
     * 409 for "already admitted", because that is not a client mistake: at a busy
     * door the same ticket gets scanned twice all the time, and the useful answer is
     * "yes, they are already in" rather than an error the doorkeeper must interpret.
     */
    public function admit(Request $request, string $code): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $checkin = $this->tickets->admit($code, $profile);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 404);
        } catch (LogicException $exception) {
            $alreadyIn = str_contains($exception->getMessage(), 'already checked in');

            return response()->json([
                'success' => false,
                'message' => $alreadyIn
                    ? __('This ticket has already been used.')
                    : $exception->getMessage(),
            ], $alreadyIn ? 409 : 403);
        }

        $ticket = $this->tickets->findByCode($code);

        return response()->json([
            'success' => true,
            'message' => __('Admitted.'),
            'data' => [
                'checked_in_at' => $checkin->checked_in_at?->toIso8601String(),
                'ticket' => $ticket !== null ? new TicketResource($ticket) : null,
            ],
        ]);
    }
}
