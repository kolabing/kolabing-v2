<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidReferralCodeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidateReferralCodeRequest;
use App\Models\Profile;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService
    ) {}

    public function validateCode(ValidateReferralCodeRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $referralCode = $this->referralService->validateCodeForProfile(
                $profile,
                $request->referralCode(),
            );
        } catch (InvalidReferralCodeException) {
            return $this->invalidReferralResponse();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $referralCode->code,
            ],
        ]);
    }

    private function invalidReferralResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'referral_code' => ['The selected referral code is invalid.'],
            ],
        ], 422);
    }
}
