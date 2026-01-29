<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
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

        // Transform upcoming collaborations for the response
        $upcomingCollaborations = $data['upcoming_collaborations']->map(function ($collaboration) use ($profile) {
            return [
                'id' => $collaboration->id,
                'status' => $collaboration->status->value,
                'scheduled_date' => $collaboration->scheduled_date?->toDateString(),
                'opportunity' => [
                    'id' => $collaboration->collabOpportunity->id,
                    'title' => $collaboration->collabOpportunity->title,
                    'categories' => $collaboration->collabOpportunity->categories,
                ],
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
