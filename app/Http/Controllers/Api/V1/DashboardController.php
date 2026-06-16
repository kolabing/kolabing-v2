<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\DashboardService;
use App\Services\LegacyOpportunityBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly LegacyOpportunityBridgeService $legacyBridge,
    ) {}

    /**
     * Get dashboard stats for the authenticated user.
     *
     * GET /api/v1/me/dashboard
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $data = $profile->isBusiness()
            ? $this->dashboardService->getBusinessDashboard($profile)
            : $this->dashboardService->getCommunityDashboard($profile);

        // Transform upcoming collaborations for the response.
        // Phase 2: source the embedded opportunity from the related KOLAB when
        // present (mapped through the bridge so `categories` keeps its shape),
        // falling back to the legacy collabOpportunity. Both are null-safe so a
        // collaboration missing either relation no longer fatals the endpoint
        // (this was the "Unable to load dashboard data" parse symptom).
        $upcomingCollaborations = $data['upcoming_collaborations']->map(function ($collaboration) use ($profile) {
            $opportunity = $this->resolveOpportunitySummary($collaboration);

            return [
                'id' => $collaboration->id,
                'status' => $collaboration->status->value,
                'scheduled_date' => $collaboration->scheduled_date?->toDateString(),
                'opportunity' => $opportunity,
                'partner' => $this->getPartnerInfo($collaboration, $profile),
            ];
        });

        $data['upcoming_collaborations'] = $upcomingCollaborations;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Build the embedded opportunity summary for an upcoming collaboration.
     *
     * Prefers the related kolab (Phase 2 source of truth), mapped through the
     * bridge so `categories` is derived consistently; falls back to the legacy
     * collabOpportunity. Always returns a stable shape (never null props), so the
     * app's dashboard parser never hits a missing/typed-wrong field.
     *
     * @return array{id: string|null, title: string|null, categories: array<int, string>|null}
     */
    private function resolveOpportunitySummary(mixed $collaboration): array
    {
        $kolab = $collaboration->kolab;

        if ($kolab !== null) {
            $opportunity = $this->legacyBridge->makeCompatibilityOpportunity($kolab);

            return [
                'id' => $opportunity->id,
                'title' => $opportunity->title,
                'categories' => $opportunity->categories ?? [],
            ];
        }

        $legacy = $collaboration->collabOpportunity;

        return [
            'id' => $legacy?->id,
            'title' => $legacy?->title,
            'categories' => $legacy?->categories ?? [],
        ];
    }

    /**
     * Get the partner info (the other participant) for a collaboration.
     *
     * @return array{id: string, name: string|null, user_type: string}
     */
    private function getPartnerInfo(mixed $collaboration, Profile $profile): array
    {
        if ($collaboration->creator_profile_id === $profile->id) {
            // Current user is creator, partner is applicant
            $partner = $collaboration->applicantProfile;
            $name = $partner?->communityProfile?->name ?? $partner?->businessProfile?->name;
        } else {
            // Current user is applicant, partner is creator
            $partner = $collaboration->creatorProfile;
            $name = $partner?->businessProfile?->name ?? $partner?->communityProfile?->name;
        }

        return [
            'id' => $partner?->id,
            'name' => $name,
            'user_type' => $partner?->user_type?->value,
        ];
    }
}
