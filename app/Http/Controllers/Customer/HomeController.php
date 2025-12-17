<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View as ViewContract;

class HomeController extends Controller
{
    public function index()
    {
        $nowShowing = Movie::where('status', 'now_showing')->orderByDesc('release_date')->take(8)->get();
        $upcoming   = Movie::where('status', 'upcoming')->orderBy('release_date')->take(8)->get();
        $imax       = Movie::where('status', 'imax')->orderByDesc('release_date')->take(8)->get();

        // Hero: ưu tiên banner_path, fallback poster
        $heroMovie = Movie::whereNotNull('banner_path')->first() ?? $nowShowing->first() ?? $upcoming->first();

        $today = Carbon::today();
        $promotions = Promotion::where('status', 'active')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->take(8)
            ->get();

        return view('frontend.home', [
            'nowShowing' => $nowShowing,
            'upcoming'   => $upcoming,
            'imax'       => $imax,
            'heroMovie'  => $heroMovie,
            'promotions' => $promotions,
        ]);
    }

    /**
     * Trang chọn ghế/checkout (placeholder).
     */
    public function checkout(Showtime $showtime): View
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $showtime->load(['movie', 'room.cinema']);

        $soldSeatIds = Ticket::where('showtime_id', $showtime->id)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('seat_id')
            ->all();

        $seats = Seat::where('room_id', $showtime->room_id)
            ->orderBy('seat_row')
            ->orderBy('seat_number')
            ->get();

        $seatRows = $seats->groupBy('seat_row')->map(function ($row) use ($soldSeatIds, $showtime) {
            return $row->map(function ($seat) use ($soldSeatIds, $showtime) {
                $status = $seat->status ? 'available' : 'inactive';
                if (in_array($seat->id, $soldSeatIds)) {
                    $status = 'sold';
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

        $maxSelectable = 8;

        return view('frontend.booking.checkout', [
            'showtime'        => $showtime,
            'seatRows'        => $seatRows,
            'switchShowtimes' => $switchShowtimes,
            'dateLabel'       => $dateLabel,
            'maxSelectable'   => $maxSelectable,
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
        $combos = \App\Models\FoodItem::where(function ($q) {
            $q->where('status', 1)->orWhereNull('status');
        })
            ->orderBy('created_at', 'desc')
            ->get();

        // Thời gian giữ ghế (phút) - có thể lấy từ config sau
        $holdMinutes = 10;

        // Danh sách ghế (tạm lấy từ query seats=G1,G2)
        $seatParam = request('seats', session('booking.seats', ''));
        $selectedSeats = collect(explode(',', $seatParam))
            ->filter()
            ->values();

        // Không có ghế -> quay lại bước chọn ghế
        if ($selectedSeats->isEmpty()) {
            return back()->with('error', 'Vui lòng chọn ghế trước khi thanh toán.');
        }

        return view('frontend.booking.combo', [
            'showtime'      => $showtime,
            'combos'        => $combos,
            'holdMinutes'   => $holdMinutes,
            'selectedSeats' => $selectedSeats,
            'startTime'     => Carbon::parse($showtime->start_time)->setTimezone($tz)->format('H:i'),
            'startDate'     => Carbon::parse($showtime->start_time)->setTimezone($tz)->translatedFormat('l, d/m/Y'),
        ]);
    }

    /**
     * Bước 4: Thanh toán.
     */
    public function payment(Showtime $showtime): ViewContract|RedirectResponse
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $showtime->load(['movie', 'room.cinema']);

        // Ưu tiên lấy từ query, nếu không có dùng session (tránh bị redirect khi reload trang thanh toán)
        $selectedSeats = collect(explode(',', request('seats', session('booking.seats', ''))))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        // Không có ghế -> quay lại bước chọn ghế
        if ($selectedSeats->isEmpty()) {
            return redirect()->route('booking.checkout', $showtime);
        }

        $ticketPrice = (float) ($showtime->base_price ?? 0);
        $ticketTotal = $selectedSeats->count() * $ticketPrice;
        $comboTotal  = max(0, (float) request('combo_total', session('booking.combo_total', 0)));
        $holdMinutes = 10;

        // Lưu state tạm vào session để dùng cho bước thanh toán
        session()->put('booking.seats', $selectedSeats->implode(','));
        session()->put('booking.combo_total', $comboTotal);

        // Khuyến mãi khả dụng (chung)
        $today = Carbon::today();
        $availablePromos = Promotion::where(function ($q) {
                $q->where('status', 1)->orWhere('status', 'active')->orWhereNull('status');
            })
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->where(function ($q) {
                $q->whereNull('usage_limit')->orWhere('usage_limit', '>', 0);
            })
            ->orderBy('end_date')
            ->take(10)
            ->get();

        // mock phương thức thanh toán
        $paymentMethods = [
            ['key' => 'onepay', 'label' => 'OnePay – Visa, Master, JCB, ... / ATM / QR Ngân hàng / Apple Pay'],
            ['key' => 'shopeepay', 'label' => 'Ví ShopeePay – Giảm đến 20% tối đa 50K'],
            ['key' => 'momo', 'label' => 'Ví Điện Tử MoMo'],
            ['key' => 'zalopay', 'label' => 'Zalopay – Nhập mã GIAMSAU – Giảm 50% tối đa 40K'],
            ['key' => 'payoo', 'label' => 'HSBC/Payoo – ATM/VISA/MASTERC/QR CODE – Giảm 50K cho đơn từ 100K'],
            ['key' => 'fundiin', 'label' => 'Trả sau Fundiin'],
        ];

        return view('frontend.booking.payment', [
            'showtime'       => $showtime,
            'selectedSeats'  => $selectedSeats,
            'ticketPrice'    => $ticketPrice,
            'ticketTotal'    => $ticketTotal,
            'comboTotal'     => $comboTotal,
            'holdMinutes'    => $holdMinutes,
            'paymentMethods' => $paymentMethods,
            'availablePromos'=> $availablePromos,
            'startTime'      => Carbon::parse($showtime->start_time)->setTimezone($tz)->format('H:i'),
            'startDate'      => Carbon::parse($showtime->start_time)->setTimezone($tz)->translatedFormat('l, d/m/Y'),
        ]);
    }

    /**
     * Tạo giao dịch MoMo (sandbox/demo).
     */
    public function momoPayment(Request $request, Showtime $showtime): RedirectResponse
    {
        $seatParam = $request->input('seats', session('booking.seats', ''));
        $seats = collect(explode(',', $seatParam))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();
        if ($seats->isEmpty()) {
            return back()->with('error', 'Vui lòng chọn ghế.');
        }

        $ticketPrice = (float) ($showtime->base_price ?? 0);
        $ticketTotal = $seats->count() * $ticketPrice;
        $comboTotal  = max(0, (float) $request->input('combo_total', session('booking.combo_total', 0)));
        $amount      = $ticketTotal + $comboTotal;

        // Áp dụng mã KM nếu có (chỉ kiểm tra, không trừ lượt lần nữa)
        $code = strtoupper(trim($request->input('promo_code', '')));
        if ($code) {
            $today = Carbon::today();
            $promo = Promotion::where('code', $code)
                ->where(function ($q) {
                    $q->where('status', 1)->orWhere('status', 'active')->orWhereNull('status');
                })
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->where(function ($q) {
                    $q->whereNull('usage_limit')->orWhere('usage_limit', '>', 0);
                })
                ->first();
            if ($promo) {
                $discount = $promo->type === 'percent'
                    ? $amount * ($promo->value / 100)
                    : $promo->value;
                $amount = max(0, $amount - $discount);
            }
        }

        if ($amount <= 0) {
            return redirect()->route('booking.payment', $showtime)->with('error', 'Số tiền không hợp lệ.');
        }

        // Nếu hết thời gian giữ ghế (client), có thể kiểm tra thêm theo session ở đây

        // Config MoMo
        $endpoint    = config('services.momo.endpoint', env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'));
        $partnerCode = config('services.momo.partner_code', env('MOMO_PARTNER_CODE', ''));
        $accessKey   = config('services.momo.access_key', env('MOMO_ACCESS_KEY', ''));
        $secretKey   = config('services.momo.secret_key', env('MOMO_SECRET_KEY', ''));
        $redirectUrl = route('booking.payment.return');
        $ipnUrl      = route('booking.payment.ipn');

        $orderId  = $partnerCode . time();
        $requestId= $orderId;
        $orderInfo= 'Thanh toan ve phim ' . ($showtime->movie->title ?? 'TD Cinema');
        $requestType = 'captureWallet';
        $extraData = http_build_query([
            'showtime_id' => $showtime->id,
            'seats'       => $seats->implode(','),
            'combo_total' => $comboTotal,
            'promo_code'  => $code,
        ]);

        $rawHash = "accessKey={$accessKey}&amount={$amount}&extraData={$extraData}&ipnUrl={$ipnUrl}&orderId={$orderId}&orderInfo={$orderInfo}&partnerCode={$partnerCode}&redirectUrl={$redirectUrl}&requestId={$requestId}&requestType={$requestType}";
        $signature = hash_hmac("sha256", $rawHash, $secretKey);

        $payload = [
            'partnerCode' => $partnerCode,
            'partnerName' => 'TD Cinema',
            'storeId'     => 'TD Cinema',
            'requestId'   => $requestId,
            'amount'      => $amount,
            'orderId'     => $orderId,
            'orderInfo'   => $orderInfo,
            'redirectUrl' => $redirectUrl,
            'ipnUrl'      => $ipnUrl,
            'lang'        => 'vi',
            'extraData'   => $extraData,
            'requestType' => $requestType,
            'signature'   => $signature,
        ];

        try {
            // Dev note: MoMo sandbox có thể gặp lỗi SSL (self-signed) trên môi trường local Windows.
            // Nếu đã cấu hình cacert cho PHP, có thể bỏ verify=false.
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->withOptions(['verify' => false])
                ->post($endpoint, $payload)
                ->json();
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Không kết nối được MoMo: '.$e->getMessage());
        }

        if (isset($response['payUrl'])) {
            return redirect()->away($response['payUrl']);
        }

        return redirect()->route('booking.payment', $showtime)->with('error', $response['message'] ?? 'Tạo giao dịch thất bại.');
    }

    public function momoReturn(Request $request): ViewContract
    {
        // TODO: xác thực chữ ký và cập nhật trạng thái đơn
        $resultCode = $request->input('resultCode');
        $message    = $request->input('message', 'Thanh toán thất bại.');
        $success    = $resultCode == '0';

        // Lấy thông tin bổ sung (extraData chứa showtime_id, seats, combo_total, promo_code)
        $extraData = [];
        parse_str($request->input('extraData', ''), $extraData);

        $showtime   = null;
        $selected   = collect();
        $ticketTotal= 0;
        $comboTotal = (float) ($extraData['combo_total'] ?? session('booking.combo_total', 0));

        if (!empty($extraData['showtime_id'])) {
            $showtime = Showtime::with(['movie', 'room.cinema'])->find($extraData['showtime_id']);
        }

        $selected = collect(explode(',', $extraData['seats'] ?? session('booking.seats', '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        if ($showtime) {
            $ticketPrice = (float) ($showtime->base_price ?? 0);
            $ticketTotal = $selected->count() * $ticketPrice;
        }

        $grandTotal = $ticketTotal + $comboTotal;

        return view('frontend.booking.confirm', [
            'success'      => $success,
            'message'      => $message,
            'showtime'     => $showtime,
            'selectedSeats'=> $selected,
            'ticketTotal'  => $ticketTotal,
            'comboTotal'   => $comboTotal,
            'grandTotal'   => $grandTotal,
        ]);
    }

    public function momoIpn(Request $request): JsonResponse
    {
        // TODO: xác thực chữ ký, cập nhật order (invoice) sang paid/failed
        return response()->json(['message' => 'ok']);
    }

    /**
     * Xử lý thanh toán theo phương thức được chọn.
     * - momo: chuyển sang flow MoMo thật.
     * - khác: fake success (dùng cho demo/đồ án).
     */
    public function processPayment(Request $request, Showtime $showtime)
    {
        $method = $request->input('payment_method');

        // Nếu chọn MoMo thì dùng flow thật
        if ($method === 'momo') {
            return $this->momoPayment($request, $showtime);
        }

        // Fake success cho các phương thức khác (demo)
        // Có thể lưu thông tin order/invoice ở đây nếu cần
        $extra = http_build_query([
            'showtime_id' => $showtime->id,
            'seats'       => session('booking.seats', ''),
            'combo_total' => session('booking.combo_total', 0),
            'promo_code'  => request('promo_code'),
        ]);

        return redirect()->route('booking.payment.return', [
            'resultCode' => 0,
            'message'    => 'Thanh toán thành công (fake).',
            'extraData'  => $extra,
        ]);
    }

    /**
     * Áp dụng mã khuyến mãi (tạm tính).
     */
    public function applyPromotion(Request $request): JsonResponse
    {
        $code = strtoupper(trim($request->input('code', '')));
        $total = (float) $request->input('total', 0);

        if (empty($code) || $total <= 0) {
            return response()->json(['success' => false, 'message' => 'Mã không hợp lệ.'], 422);
        }

        $today = Carbon::today();
        $promo = DB::transaction(function () use ($code, $today) {
            $row = Promotion::where('code', $code)
                ->where(function ($q) {
                    $q->where('status', 1)->orWhere('status', 'active')->orWhereNull('status');
                })
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return null;
            }

            // Nếu có giới hạn lượt dùng, trừ ngay tại đây
            if (!is_null($row->usage_limit)) {
                if ($row->usage_limit <= 0) {
                    return false; // hết lượt
                }
                $row->usage_limit = $row->usage_limit - 1;
                $row->save();
            }

            return $row;
        });

        if ($promo === null) {
            return response()->json(['success' => false, 'message' => 'Mã không tồn tại hoặc đã hết hạn.'], 404);
        }
        if ($promo === false) {
            return response()->json(['success' => false, 'message' => 'Mã đã hết lượt sử dụng.'], 422);
        }

        $discount = 0;
        if ($promo->type === 'percent') {
            $discount = $total * ($promo->value / 100);
            // Có thể đặt trần giảm tại đây nếu cần
        } else {
            $discount = $promo->value;
        }
        $discount = min($discount, $total);

        return response()->json([
            'success'  => true,
            'discount' => round($discount),
            'message'  => 'Áp dụng mã thành công.',
            'name'     => $promo->name,
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
     * Lịch sử vé của khách (role customer).
     */
    public function ticketHistory()
    {
        $userId = auth()->id();
        $tickets = Ticket::with(['showtime.movie', 'showtime.room.cinema', 'seat', 'payment'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(5);

        // Tổng chi tiêu (payment paid của user)
        $totalSpent = \App\Models\Payment::where('status', 'paid')
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $userId))
            ->sum('amount');

        $year = now()->year;
        $yearSpent = \App\Models\Payment::where('status', 'paid')
            ->whereHas('ticket', fn ($q) => $q->where('user_id', $userId))
            ->whereYear('paid_at', $year)
            ->sum('amount');

        // Mốc giả định để hiển thị progress
        $tier1 = 2000000;
        $tier2 = 4000000;
        $progress = min(1, $yearSpent / $tier2);

        return view('customer.tickets.index', compact('tickets', 'totalSpent', 'yearSpent', 'tier1', 'tier2', 'progress'));
    }
}
