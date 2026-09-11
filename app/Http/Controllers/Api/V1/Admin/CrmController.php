<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\CrmAccountResource;
use App\Models\CrmAccount;
use App\Services\CrmQueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Token-authenticated (Sanctum, maintainer-only) read access to the CRM — the API
 * counterpart to Admin\CrmController for callers that can't hold a browser session
 * (agents, scripts). Read-only by construction: no write route exists here.
 *
 * GET /api/v1/admin/crm?type=business|community|ambassador&owner=&status=&city=&q=&per_page=
 */
class CrmController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = in_array($request->query('type'), CrmAccount::TYPES, true)
            ? $request->query('type') : 'business';

        $query = CrmQueryFilters::apply(
            CrmAccount::query()->where('type', $type),
            $type,
            $request->only(['owner', 'status', 'city', 'q']),
        );

        $perPage = min(max($request->integer('per_page', 50), 1), 200);
        $accounts = $query->orderByDesc('score')->orderBy('name')->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => CrmAccountResource::collection($accounts->items())->resolve(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'total' => $accounts->total(),
                'per_page' => $accounts->perPage(),
            ],
        ]);
    }
}
