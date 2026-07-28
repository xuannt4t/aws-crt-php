<?php

namespace App\Http\Controllers;

use App\Actions\User\DeleteUserAction;
use App\Actions\User\UpdateProfileAction;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return Redirect::route('profile.edit')->with('success', 'Đã cập nhật hồ sơ cá nhân.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, DeleteUserAction $action): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $action->execute($user, $user);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/')->with('success', 'Đã xóa tài khoản.');
    }
}
