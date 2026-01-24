<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GoogleLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly GoogleAuthService $googleAuthService,
        private readonly AuthService $authService,
        private readonly ProfileService $profileService
    ) {}

    /**
     * Authenticate or register a user via Google OAuth.
     *
     * POST /api/v1/auth/google
     */
    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Verify the Google ID token
        $googleUserData = $this->googleAuthService->verifyIdToken($validated['id_token']);

        if (! $googleUserData) {
            return response()->json([
                'success' => false,
                'message' => __('Invalid Google ID token'),
                'errors' => [
                    'id_token' => [__('The provided Google ID token is invalid or expired')],
                ],
            ], 400);
        }

        // Authenticate or register the user
        $result = $this->authService->authenticateWithGoogle(
            $googleUserData,
            $request->getUserType()
        );

        // Check for errors
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => __('User type mismatch'),
                'errors' => [
                    'user_type' => [$result['error']],
                ],
            ], $result['code']);
        }

        $message = $result['is_new_user']
            ? __('Registration successful')
            : __('Login successful');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'is_new_user' => $result['is_new_user'],
                'user' => new UserResource($result['profile']),
            ],
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $profile = $this->profileService->getAuthenticatedUser($profile);

        return response()->json([
            'success' => true,
            'data' => new UserResource($profile),
        ]);
    }

    /**
     * Logout the authenticated user.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->authService->logout($profile);

        return response()->json([
            'success' => true,
            'message' => __('Logged out successfully'),
        ]);
    }
}
