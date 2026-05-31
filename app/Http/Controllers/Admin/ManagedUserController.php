<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManagedUserRequest;
use App\Http\Requests\Admin\UpdateManagedUserRequest;
use App\Models\Profile;
use App\Services\Admin\ManagedProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ManagedUserController extends Controller
{
    public function __construct(
        private readonly ManagedProfileService $managedProfileService
    ) {}

    public function index(): View
    {
        $profiles = Profile::query()
            ->with(['businessProfile', 'communityProfile', 'attendeeProfile', 'subscription'])
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
        $profile->loadMissing(['businessProfile', 'communityProfile', 'attendeeProfile', 'subscription']);

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
}
