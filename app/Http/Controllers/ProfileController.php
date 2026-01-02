<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PasswordUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile.
     */
    public function show(Request $request): Response
    {
        $user = $request->user()->load('role');

        return Inertia::render('Profile/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Update the user's profile.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(PasswordUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        // Invalidate all other sessions for security
        Auth::logoutOtherDevices($request->validated('current_password'));

        return back()->with('success', 'Password updated successfully.');
    }
}
