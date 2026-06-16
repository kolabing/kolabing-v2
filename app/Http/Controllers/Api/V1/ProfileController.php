<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\FileUploadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\CommunityPublicProfileResource;
use App\Http\Resources\Api\V1\PublicCollaborationResource;
use App\Http\Resources\Api\V1\PublicProfileResource;
use App\Http\Resources\Api\V1\PublicProfileReviewResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\FileUploadService;
use App\Services\FriendshipService;
use App\Services\HandleService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly FileUploadService $fileUploadService,
        private readonly HandleService $handleService,
        private readonly FriendshipService $friendshipService,
    ) {}

    /**
     * Look up a single profile's public card by exact email OR exact @handle.
     * Reused by friend add-by-identifier and the leader member-add flow.
     *
     * GET /api/v1/profiles/lookup?q=<email-or-@handle>
     */
    public function lookup(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        $q = trim($validated['q']);

        $target = str_contains($q, '@') && str_contains($q, '.') && ! str_starts_with($q, '@')
            ? Profile::query()->where('email', mb_strtolower($q))->first()
            : $this->handleService->resolve($q);

        if ($target === null) {
            return response()->json([
                'success' => false,
                'error' => 'profile_not_found',
                'message' => __('No Kolabing account found for that identifier.'),
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $target->id,
                'name' => $target->getExtendedProfile()?->name ?? $target->name,
                'handle' => $target->handle,
                'avatar_url' => $target->avatar_url,
                'user_type' => $target->user_type->value,
                'friend_status' => $this->friendshipService->statusFor($viewer, $target),
            ],
        ]);
    }

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

        // Get base profile data (phone_number, analytics_opt_out).
        $profileData = $request->getProfileData();

        // Attendees have no extended profile: their identity (name, avatar, city)
        // lives on the base `profiles` record. Merge attendee fields into the base
        // profile update and write the photo to profiles.avatar_url.
        if ($profile->isAttendee()) {
            $profileData = array_merge($profileData, $request->getAttendeeProfileData());

            if ($request->hasFile('profile_photo')) {
                if ($profile->avatar_url) {
                    $this->fileUploadService->delete($profile->avatar_url);
                }

                $profileData['avatar_url'] = $this->fileUploadService->uploadFromFile(
                    $request->file('profile_photo'),
                    FileUploadType::ProfilePhoto,
                    $profile->id
                );
            }

            $profile = $this->profileService->updateProfile($profile, $profileData, []);

            return response()->json([
                'success' => true,
                'message' => __('Profile updated successfully'),
                'data' => new UserResource($profile),
            ]);
        }

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
