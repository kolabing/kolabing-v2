<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    /**
     * TEMPORARY diagnostic logging (2026-09-14) -- Daniel reported /admin/login
     * silently bouncing him back to the same page with no visible error, a
     * symptom none of these three branches should produce. Every log() call
     * below is tagged [admin-login-diag] for a quick log-viewer search; no
     * password is ever logged. Remove once the real branch/cause is confirmed
     * from a live attempt -- see kolabing-v2 issue #270's follow-ups.
     */
    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $sessionIdBefore = $request->session()->getId();

        Log::info('[admin-login-diag] attempt started', [
            'email' => $credentials['email'] ?? null,
            'session_id_before' => $sessionIdBefore,
            'session_driver' => config('session.driver'),
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            Log::info('[admin-login-diag] branch=invalid_credentials', [
                'email' => $credentials['email'] ?? null,
            ]);

            return back()
                ->withErrors(['email' => __('Invalid credentials')])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $sessionIdAfterRegenerate = $request->session()->getId();

        Log::info('[admin-login-diag] credentials accepted, session regenerated', [
            'session_id_before' => $sessionIdBefore,
            'session_id_after_regenerate' => $sessionIdAfterRegenerate,
            'ids_differ' => $sessionIdBefore !== $sessionIdAfterRegenerate,
        ]);

        $user = Auth::guard('admin')->user();

        if (! $user instanceof User || ! $user->isMaintainer()) {
            Log::info('[admin-login-diag] branch=not_a_maintainer', [
                'user_id' => $user?->id,
                'user_class' => $user === null ? null : $user::class,
                'is_maintainer' => $user instanceof User ? $user->isMaintainer() : null,
            ]);

            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => __('This account is not allowed to access the admin panel.')])
                ->onlyInput('email');
        }

        Log::info('[admin-login-diag] branch=success, redirecting to dashboard', [
            'user_id' => $user->id,
            'session_id_final' => $request->session()->getId(),
        ]);

        return redirect()->intended(route('admin.users.index'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
