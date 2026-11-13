<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Showtime;
use App\Services\BookingHoldService;
use App\Services\BookingService;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromoController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter') === 'upcoming' ? 'upcoming' : 'active';
        $query = $filter === 'upcoming' ? Promotion::upcoming() : Promotion::available();
        $promotions = $query->where('is_member_gift', false)->orderBy('start_date')->orderBy('end_date')->paginate(12)->withQueryString();
        $counts = ['active' => Promotion::available()->where('is_member_gift', false)->count(), 'upcoming' => Promotion::upcoming()->where('is_member_gift', false)->count()];

        return view('frontend.promotions', compact('promotions', 'counts', 'filter'));
    }

    public function select(Request $request, Promotion $promotion, PromotionService $promotions): RedirectResponse|JsonResponse
    {
        $promotion = $promotions->findAvailable($promotion->code);
        session()->put('promotion.preferred_code', $promotion->code);

        if (! auth()->check()) {
            session()->put('url.intended', route('movies.index'));

            if ($request->expectsJson()) {
                return response()->json([
                    'requires_login' => true,
                    'message' => 'Hãy đăng nhập để áp dụng ưu đãi này.',
                ]);
            }

            return redirect()->route('login')->with('status', 'Hãy đăng nhập để áp dụng ưu đãi này.');
        }

        return redirect()->route('movies.index')->with('success', 'Đã chọn mã '.$promotion->code.'. Mã sẽ được áp dụng ở bước thanh toán.');
    }

    public function applyPromotion(Request $request, BookingHoldService $holds, BookingService $booking, PromotionService $promotions): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50'], 'showtime_id' => ['required', 'integer', 'exists:showtimes,id']]);
        $showtime = Showtime::findOrFail($data['showtime_id']);
        $hold = $holds->active($showtime->id);
        if (! $hold) return response()->json(['success' => false, 'message' => 'Thời gian giữ ghế đã hết. Vui lòng chọn lại ghế.'], 422);
        $total = $booking->calculateTicketTotal($showtime, collect($hold['seats'])) + max(0, (float) session('booking.combo_total', 0));
        $promotion = $promotions->findAvailable($data['code']);
        session()->put('booking.promo_code', $promotion->code);

        return response()->json(['success' => true, 'code' => $promotion->code, 'name' => $promotion->name,
            'discount' => $promotion->discountFor($total), 'total' => max(0, $total - $promotion->discountFor($total)), 'message' => 'Áp dụng mã thành công.']);
    }

    public function remove(Request $request, BookingHoldService $holds, BookingService $booking, \App\Services\MembershipService $membership): JsonResponse
    {
        $data = $request->validate(['showtime_id'=>['nullable','integer','exists:showtimes,id']]);
        $discount = 0;
        if (! empty($data['showtime_id']) && $hold = $holds->active((int) $data['showtime_id'])) {
            $showtime = Showtime::findOrFail($data['showtime_id']);
            $total = $booking->calculateTicketTotal($showtime, collect($hold['seats'])) + max(0,(float) session('booking.combo_total',0));
            $discount = $membership->discount($request->user(), $total);
        }
        session()->forget(['booking.promo_code', 'promotion.preferred_code']);

        return response()->json(['success' => true, 'discount'=>$discount]);
    }
}
