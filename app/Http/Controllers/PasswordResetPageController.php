<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PasswordResetPageController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Render the password reset form. The token and email arrive as query
     * parameters from the reset link emailed to the user.
     */
    public function show(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => __('The password must be at least 8 characters'),
            'password.confirmed' => __('The password confirmation does not match'),
        ]);

        try {
            $this->authService->resetPassword([
                'token' => $validated['token'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()
                ->withErrors(['email' => $e->getMessage()])
                ->withInput($request->only('email', 'token'));
        }

        return redirect()
            ->route('password.reset')
            ->with('status', __('Password has been reset successfully.'));
    }
}
