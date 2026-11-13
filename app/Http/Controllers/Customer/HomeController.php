<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Banner;
use App\Models\CinemaCornerItem;
use App\Models\CinemaCornerSetting;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\PromotionDisplaySetting;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Services\TicketExpiryService;

class HomeController extends Controller
{
    public function index()
    {
        $nowShowing = Movie::where('status', 'now_showing')->orderByDesc('release_date')->take(8)->get();
        $upcoming   = Movie::where('status', 'upcoming')->orderBy('release_date')->take(8)->get();
        $imax       = Movie::imax()->orderByDesc('release_date')->take(8)->get();
        $banners = Banner::with('cinema')->forPlacement('home')->get();
        $cornerSettings = CinemaCornerSetting::current();
        $cornerItems = CinemaCornerItem::with('movie')->withCount('likes')->published()
            ->orderBy('sort_order')->orderBy('id')->get();
        $cornerTabs = collect(CinemaCornerItem::CATEGORIES)->map(function ($label, $category) use ($cornerItems, $cornerSettings) {
            $items = $cornerItems->where('category', $category);
            $featuredId = $category === 'blog' ? $cornerSettings->blog_featured_item_id : $cornerSettings->featured_item_id;
            $featured = $items->firstWhere('id', $featuredId) ?? $items->first();
            return ['label' => $label, 'featured' => $featured, 'articles' => $items->reject(fn ($item) => $item->id === $featured?->id)->take($cornerSettings->list_limit)];
        });
        $cornerFeatured = $cornerTabs['review']['featured'];
        $cornerArticles = $cornerTabs['review']['articles'];
        $cornerLikedIds = auth()->check() ? \App\Models\CinemaCornerLike::where('user_id', auth()->id())->pluck('cinema_corner_item_id')->all() : [];

        // Hero: ưu tiên banner_path, fallback poster
        $heroMovie = Movie::whereNotNull('banner_path')->first() ?? $nowShowing->first() ?? $upcoming->first();

        $promotions = Promotion::available()->eligibleFor(auth()->user())
            ->where('is_member_gift', false)
            ->where('show_on_home',true)
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->get();
        $promotionDisplay = PromotionDisplaySetting::current();

        return view('frontend.home', [
            'nowShowing' => $nowShowing,
            'upcoming'   => $upcoming,
            'imax'       => $imax,
            'banners'    => $banners,
            'heroMovie'  => $heroMovie,
            'promotions' => $promotions,
            'promotionDisplay' => $promotionDisplay,
            'websiteSettings' => \App\Models\HeaderSetting::current(),
            'cornerSettings' => $cornerSettings,
            'cornerFeatured' => $cornerFeatured,
            'cornerArticles' => $cornerArticles,
            'cornerTabs' => $cornerTabs,
            'cornerLikedIds' => $cornerLikedIds,
        ]);
    }

    /**
     * Lịch sử vé của khách (role customer).
     */
    public function ticketHistory(TicketExpiryService $expiry)
    {
        $userId = Auth::id();
        $expiry->expireOverdue(userId: $userId);
        $bookings = Ticket::query()
            ->where('user_id', $userId)
            ->selectRaw('MIN(id) as first_ticket_id, qr_code, showtime_id, MAX(created_at) as latest_at')
            ->groupBy('qr_code', 'showtime_id')
            ->groupByRaw("CASE WHEN qr_code IS NULL OR qr_code = '' THEN id ELSE 0 END")
            ->orderByDesc('latest_at')
            ->orderByDesc('first_ticket_id')
            ->paginate(5);

        $bookings->getCollection()->transform(function ($booking) use ($userId) {
            $tickets = Ticket::with(['showtime.movie', 'showtime.room.cinema', 'seat', 'payment', 'foodOrders.items'])
                ->where('user_id', $userId)
                ->where('showtime_id', $booking->showtime_id)
                ->when($booking->qr_code, fn ($query) => $query->where('qr_code', $booking->qr_code),
                    fn ($query) => $query->whereKey($booking->first_ticket_id))
                ->orderBy('id')
                ->get();
            $booking->setRelation('tickets', $tickets);
            $code = $booking->qr_code ?: 'TICKET-'.$booking->first_ticket_id;
            $booking->checkin_code = $code;
            $booking->qr_data_uri = 'data:image/svg+xml;base64,'.base64_encode(
                QrCode::format('svg')->encoding('UTF-8')->size(220)->margin(1)->generate($code)
            );
            return $booking;
        });

        // Tổng chi tiêu (payment paid của user)
        $totalSpent = Payment::where('status', 'paid')
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $userId))
            ->sum('amount');

        return view('customer.tickets.index', compact('bookings', 'totalSpent'));
    }
}
