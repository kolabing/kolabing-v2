<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\EventCreatorEntitlementRequiredException;
use App\Exceptions\MultiKolabEventPublishValidationException;
use App\Http\Controllers\Api\V1\Concerns\MapsMultiKolabExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MultiKolab\AddMultiKolabRoleRequest;
use App\Http\Requests\Api\V1\MultiKolab\CancelMultiKolabEventRequest;
use App\Http\Requests\Api\V1\MultiKolab\CreateMultiKolabEventRequest;
use App\Http\Requests\Api\V1\MultiKolab\UpdateMultiKolabEventRequest;
use App\Http\Requests\Api\V1\MultiKolab\UpdateMultiKolabRoleRequest;
use App\Http\Resources\Api\V1\MultiKolab\MultiKolabEventResource;
use App\Http\Resources\Api\V1\MultiKolab\MultiKolabEventSummaryResource;
use App\Http\Resources\Api\V1\MultiKolab\MultiKolabRoleResource;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\MultiKolabEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MultiKolabEventController extends Controller
{
    use MapsMultiKolabExceptions;

    public function __construct(
        private readonly MultiKolabEventService $eventService,
    ) {}

    /**
     * GET /api/v1/me/organizer-entitlement
     */
    public function entitlement(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $entitlement = $profile->organizerEntitlements()
            ->where('capability', \App\Enums\OrganizerCapability::EventCreator)
            ->whereNull('revoked_at')
            ->latest('granted_at')
            ->first();

        $hasEntitlement = $profile->hasEventCreatorEntitlement();

        return response()->json([
            'success' => true,
            'data' => [
                'has_event_creator_entitlement' => $hasEntitlement,
                'capability' => 'event_creator',
                'granted_at' => $hasEntitlement ? $entitlement?->granted_at?->toIso8601String() : null,
                'expires_at' => $hasEntitlement ? $entitlement?->expires_at?->toIso8601String() : null,
                'source' => $hasEntitlement ? $entitlement?->source : null,
            ],
        ]);
    }

    /**
     * Explore listing — published (recruiting) events only, filterable.
     *
     * GET /api/v1/multi-kolab-events
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = MultiKolabEvent::query()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile'])
            ->withCount([
                'roles',
                'roles as open_roles_count' => fn ($q) => $q->where('status', MultiKolabRoleStatus::Open),
                'roles as filled_roles_count' => fn ($q) => $q->where('status', MultiKolabRoleStatus::Filled),
            ])
            ->where('status', $request->query('status', MultiKolabEventStatus::Recruiting->value));

        if ($city = $request->query('city')) {
            $query->where('city', $city);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($eligible = $request->query('eligible_account_type')) {
            $query->where('eligible_account_type', $eligible);
        }

        $events = $query->latest('published_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MultiKolabEventSummaryResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * The authenticated profile's own events, any status.
     *
     * GET /api/v1/multi-kolab-events/me
     */
    public function myEvents(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $perPage = min((int) $request->query('per_page', 15), 100);

        $events = MultiKolabEvent::query()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile'])
            ->withCount([
                'roles',
                'roles as open_roles_count' => fn ($q) => $q->where('status', MultiKolabRoleStatus::Open),
                'roles as filled_roles_count' => fn ($q) => $q->where('status', MultiKolabRoleStatus::Filled),
            ])
            ->where('creator_profile_id', $profile->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => MultiKolabEventSummaryResource::collection($events),
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/multi-kolab-events
     */
    public function store(CreateMultiKolabEventRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $event = $this->eventService->createDraft($profile, $request->validated());
        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ], 201);
    }

    /**
     * GET /api/v1/multi-kolab-events/{event}
     */
    public function show(Request $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $event)) {
            return $this->notOwnerResponse();
        }

        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        $event->viewer_application = MultiKolabRoleApplication::query()
            ->whereIn('multi_kolab_role_id', $event->roles->pluck('id'))
            ->where('applicant_profile_id', $profile->id)
            ->first();

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ]);
    }

    /**
     * PATCH /api/v1/multi-kolab-events/{event}
     */
    public function update(UpdateMultiKolabEventRequest $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return $this->notOwnerResponse();
        }

        try {
            $event = $this->eventService->update($event, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ]);
    }

    /**
     * POST /api/v1/multi-kolab-events/{event}/roles
     */
    public function storeRole(AddMultiKolabRoleRequest $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return $this->notOwnerResponse();
        }

        try {
            $role = $this->eventService->addRole($event, $request->validated());
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        return response()->json([
            'success' => true,
            'data' => new MultiKolabRoleResource($role),
        ], 201);
    }

    /**
     * PATCH /api/v1/multi-kolab-roles/{role}
     */
    public function updateRole(UpdateMultiKolabRoleRequest $request, MultiKolabRole $role): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $role->loadMissing('event');

        if ($profile->cannot('update', $role->event)) {
            return $this->notOwnerResponse();
        }

        try {
            $role = $this->eventService->updateRole($role, $request->validated());
        } catch (\App\Exceptions\RoleCapacityExceededException $e) {
            return $this->roleCapacityExceededResponse($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        return response()->json([
            'success' => true,
            'data' => new MultiKolabRoleResource($role),
        ]);
    }

    /**
     * DELETE /api/v1/multi-kolab-roles/{role}
     */
    public function destroyRole(Request $request, MultiKolabRole $role): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $role->loadMissing('event');

        if ($profile->cannot('update', $role->event)) {
            return $this->notOwnerResponse();
        }

        try {
            $this->eventService->removeRole($role);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v1/multi-kolab-events/{event}/publish
     */
    public function publish(Request $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return $this->notOwnerResponse();
        }

        try {
            $event = $this->eventService->publish($event, $profile);
        } catch (EventCreatorEntitlementRequiredException $e) {
            return $this->entitlementRequiredResponse($e);
        } catch (MultiKolabEventPublishValidationException $e) {
            return $this->publishValidationResponse($e);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ]);
    }

    /**
     * POST /api/v1/multi-kolab-events/{event}/confirm
     */
    public function confirm(Request $request, MultiKolabEvent $event): JsonResponse
    {
        return $this->transition($request, $event, fn (MultiKolabEvent $e, Profile $p) => $this->eventService->confirm($e, $p));
    }

    /**
     * POST /api/v1/multi-kolab-events/{event}/complete
     */
    public function complete(Request $request, MultiKolabEvent $event): JsonResponse
    {
        return $this->transition($request, $event, fn (MultiKolabEvent $e, Profile $p) => $this->eventService->complete($e, $p));
    }

    /**
     * POST /api/v1/multi-kolab-events/{event}/cancel
     */
    public function cancel(CancelMultiKolabEventRequest $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('cancel', $event)) {
            return $this->notOwnerResponse();
        }

        try {
            $event = $this->eventService->cancel($event, $profile, $request->validated()['reason']);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ]);
    }

    /**
     * GET /api/v1/multi-kolab-events/{event}/dashboard
     *
     * Per-role application counts computed via one grouped query — never
     * per-role queries in a loop.
     */
    public function dashboard(Request $request, MultiKolabEvent $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return $this->notOwnerResponse();
        }

        $roles = $event->roles()->get();
        $roleIds = $roles->pluck('id');

        $counts = MultiKolabRoleApplication::query()
            ->whereIn('multi_kolab_role_id', $roleIds)
            ->selectRaw('multi_kolab_role_id, status, count(*) as aggregate')
            ->groupBy('multi_kolab_role_id', 'status')
            ->get()
            ->groupBy('multi_kolab_role_id');

        $roleData = $roles->map(function (MultiKolabRole $role) use ($counts) {
            $rows = $counts->get($role->id, collect());
            // 'status' is cast to MultiKolabRoleApplicationStatus by the
            // model even on this raw-select query, so key by ->value
            // explicitly rather than plucking the enum object as a key.
            $byStatus = $rows->mapWithKeys(fn ($row) => [
                (is_object($row->status) ? $row->status->value : $row->status) => $row->aggregate,
            ]);

            return [
                'role_id' => $role->id,
                'title' => $role->title,
                'positions_needed' => $role->positions_needed,
                'positions_filled' => $role->positions_filled,
                'status' => $role->status->value,
                'application_counts' => [
                    'pending' => (int) ($byStatus['pending'] ?? 0),
                    'shortlisted' => (int) ($byStatus['shortlisted'] ?? 0),
                    'accepted' => (int) ($byStatus['accepted'] ?? 0),
                    'declined' => (int) ($byStatus['declined'] ?? 0),
                    'withdrawn' => (int) ($byStatus['withdrawn'] ?? 0),
                ],
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'event_id' => $event->id,
                'status' => $event->status->value,
                'role_counts' => [
                    'total' => $roles->count(),
                    'open' => $roles->where('status', MultiKolabRoleStatus::Open)->count(),
                    'filled' => $roles->where('status', MultiKolabRoleStatus::Filled)->count(),
                ],
                'roles' => $roleData,
            ],
        ]);
    }

    /**
     * @param  \Closure(MultiKolabEvent, Profile): MultiKolabEvent  $action
     */
    private function transition(Request $request, MultiKolabEvent $event, \Closure $action): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return $this->notOwnerResponse();
        }

        try {
            $event = $action($event, $profile);
        } catch (InvalidArgumentException $e) {
            return $this->invalidArgumentResponse($e);
        }

        $event->load(['creatorProfile.businessProfile', 'creatorProfile.communityProfile', 'roles']);

        return response()->json([
            'success' => true,
            'data' => new MultiKolabEventResource($event),
        ]);
    }

    private function notOwnerResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to perform this action.'),
            'errors' => ['owner' => ['not_owner']],
        ], 403);
    }
}
