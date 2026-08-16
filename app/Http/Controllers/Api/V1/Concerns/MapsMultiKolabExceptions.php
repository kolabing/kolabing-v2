<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\DuplicateRoleApplicationException;
use App\Exceptions\EventCreatorEntitlementRequiredException;
use App\Exceptions\MultiKolabApplicationRejectedException;
use App\Exceptions\MultiKolabEventPublishValidationException;
use App\Exceptions\RoleCapacityExceededException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Maps the service-layer exceptions thrown by MultiKolabEventService /
 * MultiKolabRoleApplicationService to the HTTP status + `errors` shape
 * frozen in the API contract §10. Shared between both Multi-Kolab
 * controllers to keep each controller thin (delegates business logic to the
 * service, only translates outcomes to HTTP).
 */
trait MapsMultiKolabExceptions
{
    protected function entitlementRequiredResponse(EventCreatorEntitlementRequiredException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['entitlement' => ['event_creator_required']],
        ], 403);
    }

    protected function publishValidationResponse(MultiKolabEventPublishValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => $e->errors(),
        ], 422);
    }

    protected function duplicateApplicationResponse(DuplicateRoleApplicationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['application' => ['duplicate_application']],
        ], 409);
    }

    protected function roleCapacityExceededResponse(RoleCapacityExceededException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => ['role' => ['role_capacity_exceeded']],
        ], 409);
    }

    /**
     * Stable, machine-readable codes for the reachable role-application
     * rejection paths (ineligibility, event/role not accepting applications,
     * applying to your own event) — added in the Phase 5 hardening pass so
     * Flutter never has to match on the localized message.
     */
    protected function applicationRejectedResponse(MultiKolabApplicationRejectedException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => [$e->field() => [$e->code()]],
        ], 422);
    }

    /**
     * Fallback for the base InvalidArgumentException the services throw for
     * every other business-rule rejection (lifecycle transitions, role
     * removal with an accepted application, ownership on the service side,
     * eligibility, etc). Classifies the message into the contract's known
     * error codes where possible; otherwise a generic 422.
     */
    protected function invalidArgumentResponse(InvalidArgumentException $e): JsonResponse
    {
        $message = $e->getMessage();

        if (str_contains($message, 'accepted application and cannot be removed')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['role' => ['role_has_accepted_application']],
            ], 422);
        }

        if (str_contains($message, 'Cannot ')
            || str_contains($message, 'can no longer be edited')
            || str_contains($message, 'cannot be cancelled')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['status' => ['invalid_transition']],
            ], 422);
        }

        if (str_contains($message, 'organizer may')) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['owner' => ['not_owner']],
            ], 403);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['base' => [$message]],
        ], 422);
    }
}
