<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function notifications(Request $request): View
    {
        $upcomingTickets = Ticket::with(['showtime.movie', 'showtime.room.cinema', 'seat'])
            ->where('user_id', $request->user()->id)
            ->whereHas('showtime', fn ($query) => $query->where('start_time', '>=', now()))
            ->whereNotIn('status', ['cancelled'])
            ->latest('booked_at')
            ->take(6)
            ->get();

        return view('customer.account.section', [
            'section' => 'notifications',
            'upcomingTickets' => $upcomingTickets,
            'readAt' => session('account.notifications_read_at'),
        ]);
    }

    public function markNotificationsRead(): RedirectResponse
    {
        session()->put('account.notifications_read_at', now()->toIso8601String());

        return redirect()->route('customer.notifications')
            ->with('status', 'notifications-read');
    }

    public function gifts(): View
    {
        $promotions = Promotion::available()->eligibleFor(auth()->user())
            ->where('is_member_gift', true)
            ->orderBy('sort_order')
            ->orderBy('end_date')
            ->get();

        return view('customer.account.section', [
            'section' => 'gifts',
            'promotions' => $promotions,
        ]);
    }

    public function policy(Request $request, \App\Services\MembershipService $membership): View
    {
        return view('frontend.membership', $membership->pageData($request->user()));
    }
}
