<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\AttendeePublicProfileResource;
use App\Http\Resources\Api\V1\CommunityPublicProfileResource;
use App\Http\Resources\Api\V1\EventAttendedResource;
use App\Http\Resources\Api\V1\PublicCollaborationResource;
use App\Http\Resources\Api\V1\PublicProfileResource;
use App\Http\Resources\Api\V1\PublicProfileReviewResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\AttendeeProfileService;
use App\Services\FileUploadService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly FileUploadService $fileUploadService,
        private readonly AttendeeProfileService $attendeeProfileService,
    ) {}

    /**
     * Get the authenticated user's full profile with subscription.
     *
     * GET /api/v1/me/profile
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $profile = $this->profileService->getFullProfile($profile);

        return response()->json([
            'success' => true,
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * PUT /api/v1/me/profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        // Get profile data based on user type
        $profileData = $request->getProfileData();

        $extendedProfileData = $profile->isBusiness()
            ? $request->getBusinessProfileData()
            : $request->getCommunityProfileData();

        // Handle profile photo file upload
        if ($request->hasFile('profile_photo')) {
            $extendedProfile = $profile->isBusiness()
                ? $profile->businessProfile
                : $profile->communityProfile;

            // Delete old photo if exists
            if ($extendedProfile?->profile_photo) {
                $this->fileUploadService->delete($extendedProfile->profile_photo);
            }

            $url = $this->fileUploadService->uploadFromFile(
                $request->file('profile_photo'),
                FileUploadType::ProfilePhoto,
                $profile->id
            );

            $extendedProfileData['profile_photo'] = $url;
        }

        $profile = $this->profileService->updateProfile(
            $profile,
            $profileData,
            $extendedProfileData
        );

        return response()->json([
            'success' => true,
            'message' => __('Profile updated successfully'),
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Get a public profile by ID.
     *
     * GET /api/v1/profiles/{profile}
     */
    public function publicProfile(Profile $profile): JsonResponse
    {
        $this->profileService->loadProfileRelationships($profile);

        return response()->json([
            'success' => true,
            'data' => new PublicProfileResource($profile),
        ]);
    }

    /**
     * Get a safe public-facing community profile.
     *
     * GET /api/v1/communities/{community}/public-profile
     */
    public function communityPublicProfile(Profile $community): JsonResponse
    {
        $community = $this->profileService->getCommunityPublicProfile($community);

        return response()->json([
            'success' => true,
            'data' => new CommunityPublicProfileResource($community),
        ]);
    }

    /**
     * Get completed collaborations for a profile.
     *
     * GET /api/v1/profiles/{profile}/collaborations
     */
    public function profileCollaborations(Request $request, Profile $profile): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 100);

        $collaborations = $this->profileService->getCompletedCollaborations($profile, $perPage);

        $data = $collaborations->through(
            fn ($collaboration) => (new PublicCollaborationResource($collaboration))->forProfile($profile)
        );

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $collaborations->currentPage(),
                'last_page' => $collaborations->lastPage(),
                'per_page' => $collaborations->perPage(),
                'total' => $collaborations->total(),
            ],
        ]);
    }

    /**
     * Get received reviews for a profile.
     *
     * GET /api/v1/profiles/{profile}/reviews
     */
    public function profileReviews(Request $request, Profile $profile): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 10), 100);

        $reviews = $this->profileService->getReceivedReviews($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => PublicProfileReviewResource::collection($reviews->getCollection())->resolve(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Get the attendee public-profile aggregate (identity, gamification,
     * communities, events attended, optional friends count).
     *
     * Read-only and never gated on the business paywall (attendees are free,
     * see docs/ROLES-AND-PERMISSIONS.md §7).
     *
     * GET /api/v1/profiles/{profile}/attendee
     */
    public function attendeeProfile(Profile $profile): JsonResponse
    {
        $aggregate = $this->attendeeProfileService->buildPublicProfile($profile);

        return response()->json([
            'success' => true,
            'data' => new AttendeePublicProfileResource($aggregate),
        ]);
    }

    /**
     * Paginated history of the events the authenticated attendee has attended
     * (check-ins joined to events + community).
     *
     * GET /api/v1/me/events-attended
     */
    public function myEventsAttended(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $checkins = $this->attendeeProfileService->paginateEventsAttended($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => EventAttendedResource::collection($checkins->getCollection())->resolve(),
            'meta' => [
                'current_page' => $checkins->currentPage(),
                'last_page' => $checkins->lastPage(),
                'per_page' => $checkins->perPage(),
                'total' => $checkins->total(),
            ],
        ]);
    }

    /**
     * Soft delete the authenticated user's account.
     *
     * DELETE /api/v1/me/account
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->profileService->deleteProfile($profile);

        return response()->json([
            'success' => true,
            'message' => __('Account deleted successfully'),
        ]);
    }
}
