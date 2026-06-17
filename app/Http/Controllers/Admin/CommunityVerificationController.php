<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Admin\CommunityVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin community verification dashboard. Communities submit proof "channels",
 * a maintainer manually verifies or rejects them, and businesses see a Verified
 * tick. Mirrors the maintainer subscription grant/revoke pattern. All state is
 * on community_profiles (see CommunityVerificationService).
 */
class CommunityVerificationController extends Controller
{
    public function __construct(
        private readonly CommunityVerificationService $verificationService
    ) {}

    /**
     * List communities with their verification status, filterable by
     * ?filter=pending|flagged|verified|rejected|unverified.
     */
    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', '');

        $query = Profile::query()
            ->where('user_type', UserType::Community->value)
            ->whereHas('communityProfile')
            ->with('communityProfile');

        $query->whereHas('communityProfile', function ($q) use ($filter): void {
            match ($filter) {
                'pending' => $q->where('verification_status', VerificationStatus::Pending->value),
                'verified' => $q->where('verification_status', VerificationStatus::Verified->value),
                'rejected' => $q->where('verification_status', VerificationStatus::Rejected->value),
                'unverified' => $q->where('verification_status', VerificationStatus::Unverified->value),
                'flagged' => $q->whereNotNull('verification_flagged_at'),
                default => $q,
            };
        });

        $profiles = $query->latest()->paginate(20)->withQueryString();

        return view('admin.community-verification.index', [
            'profiles' => $profiles,
            'filter' => $filter,
        ]);
    }

    /**
     * Verify the community owned by the given profile.
     */
    public function verify(Profile $profile): RedirectResponse
    {
        $communityProfile = $profile->communityProfile;
        abort_if($communityProfile === null, 422, 'This profile has no community profile.');

        $this->verificationService->verify($communityProfile, $this->adminId());

        return redirect()->back()->with('status', __('Community verified.'));
    }

    /**
     * Reject the community's verification with a reason.
     */
    public function reject(Request $request, Profile $profile): RedirectResponse
    {
        $communityProfile = $profile->communityProfile;
        abort_if($communityProfile === null, 422, 'This profile has no community profile.');

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $this->verificationService->reject(
            $communityProfile,
            $validated['rejection_reason'],
            $this->adminId()
        );

        return redirect()->back()->with('status', __('Community verification rejected.'));
    }

    /**
     * The acting admin's id (auth:admin guard, users table). Stored as a string
     * for audit on community_profiles.verified_by.
     */
    private function adminId(): ?string
    {
        $admin = auth('admin')->user();

        return $admin?->getAuthIdentifier() !== null
            ? (string) $admin->getAuthIdentifier()
            : null;
    }
}
