<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Services\BookingService;
use App\Services\BookingHoldService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingHoldService $bookingHoldService
    ) {
    }

    /**
     * Trang chọn ghế/checkout (placeholder).
     */
    public function checkout(Showtime $showtime): View
    {
        abort_unless($showtime->status === 'active' && $showtime->movie?->status === 'now_showing', 404, 'Suất chiếu chưa mở hoặc đã đóng.');
        $tz = 'Asia/Ho_Chi_Minh';
        $showtime->load(['movie', 'room.cinema']);

        if ((int) session('booking.quick_selection.showtime_id') === $showtime->id) {
            session()->forget('booking.quick_selection');
        }

        $soldSeatIds = Ticket::where('showtime_id', $showtime->id)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('seat_id')
            ->all();

        $seats = Seat::where('room_id', $showtime->room_id)
            ->orderBy('seat_row')
            ->orderBy('seat_number')
            ->get();

        $activeHold = $this->bookingHoldService->active($showtime->id);
        $seatCodes = $seats->map(fn ($seat) => $seat->seat_row . $seat->seat_number);
        $heldSeatCodes = $this->bookingHoldService->heldByOther($showtime->id, $seatCodes)->all();

        $seatRows = $seats->groupBy('seat_row')->map(function ($row) use ($soldSeatIds, $heldSeatCodes, $showtime) {
            return $row->map(function ($seat) use ($soldSeatIds, $heldSeatCodes, $showtime) {
                $status = $seat->status ? 'available' : 'inactive';
                if (in_array($seat->id, $soldSeatIds, true)) {
                    $status = 'sold';
                } elseif (in_array($seat->seat_row . $seat->seat_number, $heldSeatCodes, true)) {
                    $status = 'held';
                }
                return [
                    'id'     => $seat->id,
                    'code'   => $seat->seat_row . $seat->seat_number,
                    'row'    => $seat->seat_row,
                    'number' => $seat->seat_number,
                    'type'   => $seat->seat_type,
                    'status' => $status,
                    'price'  => (float) ($showtime->base_price ?? 0) + (float) ($seat->extra_price ?? 0),
                ];
            })->values();
        })->sortKeys();

        $dateLabel = Carbon::parse($showtime->start_time)->setTimezone($tz)->locale('vi')->translatedFormat('d/m/Y');

        $switchShowtimes = Showtime::with('room')
            ->where('movie_id', $showtime->movie_id)
            ->when($showtime->room?->cinema_id, function ($q, $cinemaId) {
                $q->whereHas('room', function ($qr) use ($cinemaId) {
                    $qr->where('cinema_id', $cinemaId);
                });
            })
            ->whereDate('start_time', Carbon::parse($showtime->start_time)->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(function ($st) use ($showtime, $tz) {
                return [
                    'id'         => $st->id,
                    'time'       => Carbon::parse($st->start_time)->setTimezone($tz)->format('H:i'),
                    'date'       => Carbon::parse($st->start_time)->toDateString(),
                    'start_time' => $st->start_time,
                    'room'       => $st->room->name ?? null,
                    'room_id'    => $st->room_id,
                    'cinema_id'  => $st->room->cinema_id ?? null,
                    'active'     => $st->id === $showtime->id,
                ];
            });

        $maxSelectable = (int) config('booking_limits.seats_per_order');

        return view('frontend.booking.checkout', [
            'showtime'        => $showtime,
            'seatRows'        => $seatRows,
            'switchShowtimes' => $switchShowtimes,
            'dateLabel'       => $dateLabel,
            'maxSelectable'   => $maxSelectable,
            'initialSelected' => collect($activeHold['seats'] ?? []),
            'holdExpiresAt'   => $activeHold['expires_at'] ?? null,
        ]);
    }

    public function hold(Request $request, Showtime $showtime): JsonResponse
    {
        abort_unless($showtime->status === 'active' && $showtime->movie?->status === 'now_showing', 404, 'Suất chiếu chưa mở hoặc đã đóng.');
        $maxSeats = (int) config('booking_limits.seats_per_order');
        $data = $request->validate([
            'seats' => ['required', 'array', 'min:1', 'max:'.$maxSeats],
            'seats.*' => ['required', 'string', 'distinct', 'max:8'],
        ], [
            'seats.max' => 'Chỉ được chọn tối đa '.$maxSeats.' ghế cho mỗi giao dịch.',
        ]);

        $requested = $this->bookingService->parseSeatCodes(implode(',', $data['seats']));
        $availableCodes = Seat::where('room_id', $showtime->room_id)
            ->where('status', true)
            ->whereIn(DB::raw("CONCAT(seat_row, seat_number)"), $requested->all())
            ->get()
            ->reject(fn ($seat) => Ticket::where('showtime_id', $showtime->id)
                ->where('seat_id', $seat->id)
                ->whereNotIn('status', ['cancelled'])
                ->exists())
            ->map(fn ($seat) => $seat->seat_row . $seat->seat_number)
            ->values();

        if ($availableCodes->count() !== $requested->unique()->count()) {
            return response()->json(['message' => 'Một hoặc nhiều ghế không còn khả dụng.'], 422);
        }

        $hold = $this->bookingHoldService->start((int) $request->user()->id, $showtime->id, $requested);

        return response()->json([
            'redirect' => route('booking.combo', $showtime),
            'expires_at' => $hold['expires_at'],
        ]);
    }

    /**
     * Bước 3: Chọn combo / sản phẩm.
     */
    public function combo(Showtime $showtime): ViewContract|RedirectResponse
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $showtime->load(['movie', 'room.cinema']);

        // Combos/đồ ăn đang hoạt động
        $combos = FoodItem::where('status', 'available')
            ->orderBy('created_at', 'desc')
            ->get();

        $hold = $this->bookingHoldService->active($showtime->id);
        $selectedSeats = collect($hold['seats'] ?? []);

        // Không có ghế -> quay lại bước chọn ghế
        if ($selectedSeats->isEmpty()) {
            return redirect()->route('booking.checkout', $showtime)
                ->with('error', 'Phiên giữ ghế đã hết. Vui lòng chọn lại ghế.');
        }

        return view('frontend.booking.combo', [
            'showtime'      => $showtime,
            'combos'        => $combos,
            'holdExpiresAt' => $hold['expires_at'] ?? null,
            'selectedSeats' => $selectedSeats,
            'startTime'     => Carbon::parse($showtime->start_time)->setTimezone($tz)->format('H:i'),
            'startDate'     => Carbon::parse($showtime->start_time)->setTimezone($tz)->translatedFormat('l, d/m/Y'),
        ]);
    }

    /**
     * Trả về danh sách rạp/ngày/suất cho booking bar theo phim.
     */
    public function bookingOptions(Request $request): JsonResponse
    {
        $movieId = $request->query('movie_id');
        if (!$movieId) {
            return response()->json(['message' => 'movie_id is required'], 422);
        }

        $today = Carbon::today();
        $showtimes = Showtime::with(['room.cinema'])
            ->where('movie_id', $movieId)
            ->where('start_time', '>=', $today)
            ->where(function ($q) {
                $q->where('status', 1)
                    ->orWhere('status', 'active')
                    ->orWhereNull('status');
            })
            ->orderBy('start_time')
            ->get();

        $cinemaData = [];
        foreach ($showtimes as $showtime) {
            $cinema = $showtime->room?->cinema;
            if (!$cinema) {
                continue;
            }
            $cid = $cinema->id;
            if (!isset($cinemaData[$cid])) {
                $cinemaData[$cid] = [
                    'id'      => $cid,
                    'name'    => $cinema->name,
                    'address' => $cinema->address,
                    'dates'   => [],
                ];
            }

            $dateKey = Carbon::parse($showtime->start_time)->toDateString();
            if (!isset($cinemaData[$cid]['dates'][$dateKey])) {
                $cinemaData[$cid]['dates'][$dateKey] = [
                    'date'      => $dateKey,
                    'label'     => Carbon::parse($showtime->start_time)->locale('vi')->translatedFormat('d/m'),
                    'showtimes' => [],
                ];
            }

            $cinemaData[$cid]['dates'][$dateKey]['showtimes'][] = [
                'id'   => $showtime->id,
                'time' => Carbon::parse($showtime->start_time)->format('H:i'),
                'room' => $showtime->room->name ?? 'Phòng',
            ];
        }

        // Sort dates and convert to list
        $cinemas = collect($cinemaData)->map(function ($cinema) {
            $cinema['dates'] = collect($cinema['dates'])
                ->sortBy('date')
                ->values()
                ->toArray();
            return $cinema;
        })->values()->toArray();

        return response()->json([
            'cinemas' => $cinemas,
        ]);
    }

    /**
     * Lưu đủ lựa chọn của thanh đặt vé nhanh trước khi khách đăng nhập.
     */
    public function storeQuickIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'cinema_id' => ['required', 'integer', 'exists:cinemas,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'showtime_id' => ['required', 'integer', 'exists:showtimes,id'],
        ]);

        $showtime = Showtime::with('room')->findOrFail($data['showtime_id']);
        $showtimeDate = Carbon::parse($showtime->start_time)->toDateString();
        $isActive = in_array($showtime->status, [1, '1', 'active', null], true);

        if (
            (int) $showtime->movie_id !== (int) $data['movie_id']
            || (int) $showtime->room?->cinema_id !== (int) $data['cinema_id']
            || $showtimeDate !== $data['date']
            || !$isActive
            || $showtime->movie?->status !== 'now_showing'
            || Carbon::parse($showtime->start_time)->isBefore(Carbon::today())
        ) {
            throw ValidationException::withMessages([
                'showtime_id' => 'Lựa chọn đặt vé không còn hợp lệ. Vui lòng chọn lại suất chiếu.',
            ]);
        }

        $selection = [
            'movie_id' => (int) $data['movie_id'],
            'cinema_id' => (int) $data['cinema_id'],
            'date' => $data['date'],
            'showtime_id' => (int) $data['showtime_id'],
        ];
        $checkoutUrl = route('booking.checkout', $showtime);

        $request->session()->put('booking.quick_selection', $selection);
        $request->session()->put('url.intended', $checkoutUrl);

        return response()->json([
            'selection' => $selection,
            'redirect' => $checkoutUrl,
        ]);
    }
}
