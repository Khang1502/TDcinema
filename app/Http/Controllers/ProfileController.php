<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $userId = $request->user()->id;
        $year = now()->year;

        $yearSpent = \App\Models\Payment::where('status', 'paid')
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $userId))
            ->whereYear('paid_at', $year)
            ->sum('amount');

        $tier2 = 4000000;
        $progress = $tier2 > 0 ? min(1, $yearSpent / $tier2) : 0;

        return view('profile.edit', [
            'user' => $request->user(),
            'yearSpent' => $yearSpent,
            'tier2' => $tier2,
            'progress' => $progress,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
