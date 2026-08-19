<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateRoleApplicationException;
use App\Exceptions\MultiKolabApplicationRejectedException;
use App\Exceptions\MultiKolabNotOrganizerException;
use App\Exceptions\RoleCapacityExceededException;
use App\Http\Controllers\Api\V1\Concerns\MapsMultiKolabExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MultiKolab\CreateMultiKolabRoleApplicationRequest;
use App\Http\Requests\Api\V1\MultiKolab\WithdrawMultiKolabRoleApplicationRequest;
use App\Http\Resources\Api\V1\MultiKolab\MultiKolabRoleApplicationResource;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\MultiKolabRoleApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MultiKolabRoleApplicationController extends Controller
{
    use MapsMultiKolabExceptions;

    public function __construct(
        private readonly MultiKolabRoleApplicationService $applicationService,
    ) {}

    /**
     * Organizer-only review list for a role. Never eager-loads the
     * applicant's full profile — the frozen resource shape only needs
     * `applicant_profile_id`/`_type`, so there is nothing here that could
     * N+1 per row.
     *
     * GET /api/v1/multi-kolab-roles/{role}/applications
     */
    public function forRole(Request $request, MultiKolabRole $role): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('viewAnyForRole', [MultiKolabRoleApplication::class, $role])) {
            return $this->notOwnerResponse();
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);

        $query = MultiKolabRoleApplication::query()->where('multi_kolab_role_id', $role->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $applications = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MultiKolabRoleApplicationResource::collection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/multi-kolab-roles/{role}/applications
     */
    public function store(CreateMultiKolabRoleApplicationRequest $request, MultiKolabRole $role): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', [MultiKolabRoleApplication::class, $role])) {
            return $this->notOwnerResponse('You cannot apply to your own event.');
        }

        try {
            $application = $this->applicationService->apply($role, $profile, $request->validated());
        } catch (DuplicateRoleApplicationException $e) {
            return $this->duplicateApplicationResponse($e);
        } catch (MultiKolabApplicationRejectedException $e) {
            return $this->applicationRejectedResponse($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        return response()->json([
            'success' => true,
            'data' => new MultiKolabRoleApplicationResource($application),
        ], 201);
    }

    /**
     * POST /api/v1/multi-kolab-role-applications/{application}/shortlist
     */
    public function shortlist(Request $request, MultiKolabRoleApplication $application): JsonResponse
    {
        return $this->act($request, $application, 'shortlist', fn (MultiKolabRoleApplication $a, Profile $p) => $this->applicationService->shortlist($a, $p));
    }

    /**
     * POST /api/v1/multi-kolab-role-applications/{application}/decline
     */
    public function decline(Request $request, MultiKolabRoleApplication $application): JsonResponse
    {
        return $this->act($request, $application, 'decline', fn (MultiKolabRoleApplication $a, Profile $p) => $this->applicationService->decline($a, $p));
    }

    /**
     * POST /api/v1/multi-kolab-role-applications/{application}/withdraw
     */
    public function withdraw(WithdrawMultiKolabRoleApplicationRequest $request, MultiKolabRoleApplication $application): JsonResponse
    {
        return $this->act(
            $request,
            $application,
            'withdraw',
            fn (MultiKolabRoleApplication $a, Profile $p) => $this->applicationService->withdraw($a, $p, $request->validated()['reason'] ?? null),
        );
    }

    /**
     * POST /api/v1/multi-kolab-role-applications/{application}/accept
     */
    public function accept(Request $request, MultiKolabRoleApplication $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('accept', $application)) {
            return $this->notOwnerResponse();
        }

        try {
            $kolab = $this->applicationService->accept($application, $profile);
        } catch (RoleCapacityExceededException $e) {
            return $this->roleCapacityExceededResponse($e);
        } catch (MultiKolabNotOrganizerException $e) {
            return $this->notOrganizerResponse($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        $application->refresh();
        $collaboration = $kolab->collaborations()->latest()->first();

        return response()->json([
            'success' => true,
            'data' => [
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status->value,
                    'kolab_id' => $application->kolab_id,
                ],
                'kolab' => [
                    'id' => $kolab->id,
                    'status' => $kolab->status->value,
                    'collaboration_id' => $collaboration?->id,
                ],
                'collaboration' => $collaboration !== null ? [
                    'id' => $collaboration->id,
                    'status' => $collaboration->status->value,
                    'application_id' => $collaboration->application_id,
                ] : null,
            ],
        ]);
    }

    /**
     * @param  \Closure(MultiKolabRoleApplication, Profile): MultiKolabRoleApplication  $action
     */
    private function act(Request $request, MultiKolabRoleApplication $application, string $ability, \Closure $action): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot($ability, $application)) {
            return $this->notOwnerResponse();
        }

        try {
            $application = $action($application, $profile);
        } catch (MultiKolabNotOrganizerException $e) {
            return $this->notOrganizerResponse($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        return response()->json([
            'success' => true,
            'data' => new MultiKolabRoleApplicationResource($application),
        ]);
    }

    private function notOwnerResponse(?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ?? __('You are not authorized to perform this action.'),
            'errors' => ['owner' => ['not_owner']],
        ], 403);
    }
}
