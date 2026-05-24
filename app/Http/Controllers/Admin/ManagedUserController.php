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
            ->with(['businessProfile', 'communityProfile', 'attendeeProfile'])
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
        $profile->loadMissing(['businessProfile', 'communityProfile', 'attendeeProfile']);

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
}
