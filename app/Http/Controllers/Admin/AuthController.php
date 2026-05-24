<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => __('Invalid credentials')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = Auth::guard('admin')->user();

        if (! $user instanceof User || ! $user->isMaintainer()) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => __('This account is not allowed to access the admin panel.')])
                ->onlyInput('email');
        }

        return redirect()->intended('/admin/users');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
