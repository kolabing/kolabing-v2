<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Models\Profile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserType
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $userType): Response
    {
        /** @var Profile|null $user */
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('Unauthenticated'),
                'errors' => [
                    'auth' => [__('Authentication token is invalid or expired')],
                ],
            ], 401);
        }

        $requiredType = UserType::tryFrom($userType);

        if (! $requiredType || $user->user_type !== $requiredType) {
            $typeLabel = $userType === 'business' ? 'business' : 'community';

            return response()->json([
                'success' => false,
                'message' => __('Access denied'),
                'errors' => [
                    'user_type' => [__("This endpoint is only accessible to {$typeLabel} users")],
                ],
            ], 403);
        }

        return $next($request);
    }
}
