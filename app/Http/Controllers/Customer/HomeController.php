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
    public function ticketHistory()
    {
        $userId = Auth::id();
        $tickets = Ticket::with(['showtime.movie', 'showtime.room.cinema', 'seat', 'payment'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(5);

        // Tổng chi tiêu (payment paid của user)
        $totalSpent = Payment::where('status', 'paid')
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $userId))
            ->sum('amount');

        return view('customer.tickets.index', compact('tickets', 'totalSpent'));
    }
}
