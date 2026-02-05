<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\GoogleLoginRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterAttendeeRequest;
use App\Http\Requests\Api\V1\RegisterBusinessRequest;
use App\Http\Requests\Api\V1\RegisterCommunityRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profile;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

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

    /**
     * Register a new business user with email and password.
     *
     * POST /api/v1/auth/register/business
     */
    public function registerBusiness(RegisterBusinessRequest $request): JsonResponse
    {
        $result = $this->authService->registerBusiness(
            $request->getProfileData(),
            $request->getBusinessProfileData()
        );

        return response()->json([
            'success' => true,
            'message' => __('Registration successful'),
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'is_new_user' => $result['is_new_user'],
                'user' => new UserResource($result['profile']),
            ],
        ], 201);
    }

    /**
     * Register a new community user with email and password.
     *
     * POST /api/v1/auth/register/community
     */
    public function registerCommunity(RegisterCommunityRequest $request): JsonResponse
    {
        $result = $this->authService->registerCommunity(
            $request->getProfileData(),
            $request->getCommunityProfileData()
        );

        return response()->json([
            'success' => true,
            'message' => __('Registration successful'),
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'is_new_user' => $result['is_new_user'],
                'user' => new UserResource($result['profile']),
            ],
        ], 201);
    }

    /**
     * Register a new attendee user with email and password.
     *
     * POST /api/v1/auth/register/attendee
     */
    public function registerAttendee(RegisterAttendeeRequest $request): JsonResponse
    {
        $result = $this->authService->registerAttendee(
            $request->only(['email', 'password'])
        );

        return response()->json([
            'success' => true,
            'message' => __('Registration successful'),
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'is_new_user' => $result['is_new_user'],
                'user' => new UserResource($result['profile']),
            ],
        ], 201);
    }

    /**
     * Login a user with email and password.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->getEmail(),
            $request->getPassword()
        );

        // Check for errors
        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'errors' => [
                    'credentials' => [$result['error']],
                ],
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => __('Login successful'),
            'data' => [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'user' => new UserResource($result['profile']),
            ],
        ]);
    }

    /**
     * Send password reset link.
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->sendPasswordResetLink($request->validated('email'));

            return response()->json([
                'success' => true,
                'message' => __('Password reset link sent to your email.'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reset password with token.
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                $request->only(['token', 'email', 'password', 'password_confirmation'])
            );

            return response()->json([
                'success' => true,
                'message' => __('Password has been reset successfully.'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
