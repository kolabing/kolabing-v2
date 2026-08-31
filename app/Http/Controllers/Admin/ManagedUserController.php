<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkProfileActiveRequest;
use App\Http\Requests\Admin\StoreManagedUserRequest;
use App\Http\Requests\Admin\UpdateManagedUserRequest;
use App\Models\Profile;
use App\Models\Scopes\ActiveProfileScope;
use App\Services\Admin\ManagedProfileService;
use App\Services\OrganizerEntitlementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ManagedUserController extends Controller
{
    public function __construct(
        private readonly ManagedProfileService $managedProfileService,
        private readonly OrganizerEntitlementService $organizerEntitlementService,
    ) {}

    public function index(): View
    {
        // Deliberately unfiltered: an admin that cannot see a switched-off account
        // cannot switch it back on. The sub-profile relations carry ActiveProfileScope,
        // so they are loaded without it or the name column would go blank (#254).
        $profiles = Profile::query()
            ->with([
                'businessProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
                'communityProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
                'attendeeProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
                'subscription',
            ])
            ->latest()
            ->paginate(20);

        return view('admin.users.index', [
            'profiles' => $profiles,
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'userTypes' => UserType::cases(),
        ]);
    }

    public function store(StoreManagedUserRequest $request): RedirectResponse
    {
        $profile = $this->managedProfileService->create($request->validated());

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('User created successfully.'));
    }

    public function edit(Profile $profile): View
    {
        $profile->loadMissing([
            'businessProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'communityProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'attendeeProfile' => fn ($q) => $q->withoutGlobalScope(ActiveProfileScope::class),
            'subscription',
        ]);

        return view('admin.users.edit', [
            'profile' => $profile,
        ]);
    }

    public function update(UpdateManagedUserRequest $request, Profile $profile): RedirectResponse
    {
        $profile = $this->managedProfileService->update($profile, $request->validated());

        return redirect()->route('admin.users.edit', $profile)
            ->with('status', __('User updated successfully.'));
    }

    public function destroy(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->delete($profile);

        return redirect()->route('admin.users.index')
            ->with('status', __('User deleted.'));
    }

    /**
     * The global active/passive switch (#254). Not a delete: reversible, and the
     * account's data is untouched. Deactivating also revokes its tokens, so a
     * signed-in phone stops working immediately rather than at token expiry.
     */
    public function deactivate(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->deactivate($profile);

        return redirect()->back()
            ->with('status', __('Account deactivated. It is now hidden from the app and cannot sign in.'));
    }

    public function activate(Profile $profile): RedirectResponse
    {
        $this->managedProfileService->activate($profile);

        return redirect()->back()
            ->with('status', __('Account activated.'));
    }

    /**
     * Switch a selection off in one action (#256). The message reports how many
     * rows actually changed, not how many were ticked — an admin who re-selects
     * accounts that were already passive should see that nothing happened.
     */
    public function bulkDeactivate(BulkProfileActiveRequest $request): RedirectResponse
    {
        $changed = $this->managedProfileService->deactivateMany($request->profileIds());

        return redirect()->back()->with('status', trans_choice(
            '{0}No accounts changed — they were already deactivated.|{1}1 account deactivated. It is now hidden from the app and cannot sign in.|[2,*]:count accounts deactivated. They are now hidden from the app and cannot sign in.',
            $changed,
            ['count' => $changed],
        ));
    }

    public function bulkActivate(BulkProfileActiveRequest $request): RedirectResponse
    {
        $changed = $this->managedProfileService->activateMany($request->profileIds());

        return redirect()->back()->with('status', trans_choice(
            '{0}No accounts changed — they were already active.|{1}1 account activated.|[2,*]:count accounts activated.',
            $changed,
            ['count' => $changed],
        ));
    }

    public function grantSubscription(Profile $profile): RedirectResponse
    {
        abort_unless($profile->isBusiness(), 422, 'Only business users can receive a subscription.');

        $this->managedProfileService->grantSubscription($profile);

        return redirect()->back()
            ->with('status', __('Subscription granted for 12 months.'));
    }

    public function revokeSubscription(Profile $profile): RedirectResponse
    {
        abort_unless($profile->isBusiness(), 422, 'Only business users have a subscription.');

        $this->managedProfileService->revokeSubscription($profile);

        return redirect()->back()
            ->with('status', __('Subscription revoked.'));
    }

    /**
     * Grant the Multi-Kolab Event Creator capability. Unlike the subscription
     * grant above, both Business and Community profiles are eligible — this
     * is an independent capability, not the business paywall.
     */
    public function grantEventCreatorEntitlement(Profile $profile): RedirectResponse
    {
        $this->organizerEntitlementService->grant($profile);

        return redirect()->back()
            ->with('status', __('Event Creator access granted for 12 months.'));
    }

    public function revokeEventCreatorEntitlement(Profile $profile): RedirectResponse
    {
        $this->organizerEntitlementService->revoke($profile);

        return redirect()->back()
            ->with('status', __('Event Creator access revoked.'));
    }
}
