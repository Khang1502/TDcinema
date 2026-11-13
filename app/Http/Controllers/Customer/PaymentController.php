<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Showtime;
use App\Models\PaymentMethod;
use App\Models\FoodItem;
use App\Services\BookingService;
use App\Services\BookingHoldService;
use App\Services\PromotionService;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingHoldService $bookingHoldService,
        private readonly PromotionService $promotionService,
        private readonly MembershipService $membership
    ) {
    }

    /**
     * Bước 4: Thanh toán.
     */
    public function payment(Showtime $showtime): ViewContract|RedirectResponse
    {
        abort_unless($showtime->status === 'active' && $showtime->movie?->status === 'now_showing', 404);
        $tz = 'Asia/Ho_Chi_Minh';
        $showtime->load(['movie', 'room.cinema']);

        $hold = $this->bookingHoldService->active($showtime->id);
        $selectedSeats = collect($hold['seats'] ?? []);

        // Không có ghế -> quay lại bước chọn ghế
        if ($selectedSeats->isEmpty()) {
            return redirect()->route('booking.checkout', $showtime);
        }

        $ticketPrice = (float) ($showtime->base_price ?? 0);
        $ticketTotal = $this->bookingService->calculateTicketTotal($showtime, $selectedSeats);
        $comboTotal = max(0, (float) session('booking.combo_total', 0));
        if (request()->query->has('items')) {
            $selection = request()->validate([
                'items' => ['required', 'array', 'max:100'],
                'items.*' => ['integer', 'min:0', 'max:'.config('booking_limits.quantity_per_food_item')],
            ]);
            $quantities = collect($selection['items'])->filter(fn ($quantity) => (int) $quantity > 0);
            $products = FoodItem::whereIn('id', $quantities->keys())->where('status', 'available')->get()->keyBy('id');
            if ($products->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['items' => 'Có sản phẩm không còn bán. Vui lòng chọn lại combo.']);
            }
            $comboItems = $quantities->mapWithKeys(fn ($quantity, $id) => [$id => [
                'quantity' => (int) $quantity,
                'price' => (float) $products[$id]->price,
            ]])->all();
            $comboTotal = $quantities->reduce(fn ($sum, $quantity, $id) => $sum + (float) $products[$id]->price * (int) $quantity, 0);
            session()->put('booking.combo_items', $comboItems);
        } elseif (request()->query->has('combo_total')) {
            // Hỗ trợ các đường dẫn thanh toán cũ chỉ truyền tổng combo.
            $comboTotal = max(0, (float) request('combo_total'));
            session()->forget('booking.combo_items');
        }

        // Lưu state tạm vào session để dùng cho bước thanh toán
        session()->put('booking.seats', $selectedSeats->implode(','));
        session()->put('booking.combo_total', $comboTotal);

        // Khuyến mãi khả dụng (chung)
        $availablePromos = Promotion::available()->eligibleFor(auth()->user())->orderBy('end_date')->get()
            ->sortByDesc(fn ($promotion) => $promotion->discountFor($ticketTotal + $comboTotal))->values();
        $selectedCode = session('booking.promo_code', session('promotion.preferred_code', ''));
        $appliedPromo = $availablePromos->first(fn ($promotion) => strcasecmp($promotion->code, $selectedCode) === 0);
        $memberLevel = $this->membership->levelFor((int) auth()->user()->points);
        $memberDiscount = $this->membership->discount(auth()->user(), $ticketTotal + $comboTotal);
        $promoDiscount = $appliedPromo?->discountFor($ticketTotal + $comboTotal) ?? $memberDiscount;
        if ($appliedPromo) session()->put('booking.promo_code', $appliedPromo->code);
        else session()->forget('booking.promo_code');

        $paymentMethods = PaymentMethod::availableOnline()->get()->map(fn ($method) => [
            'key'=>$method->code, 'label'=>$method->name, 'description'=>$method->description,
            'instructions'=>$method->instructions, 'badge'=>$method->badge, 'demo'=>$method->isDemo(),
            'logo'=>$method->logoUrl(),
        ]);

        return view('frontend.booking.payment', [
            'showtime'       => $showtime,
            'selectedSeats'  => $selectedSeats,
            'ticketPrice'    => $ticketPrice,
            'ticketTotal'    => $ticketTotal,
            'comboTotal'     => $comboTotal,
            'holdExpiresAt'  => $hold['expires_at'] ?? null,
            'paymentMethods' => $paymentMethods,
            'availablePromos'=> $availablePromos,
            'appliedPromo'   => $appliedPromo,
            'promoDiscount'  => $promoDiscount,
            'memberLevel'    => $memberLevel,
            'memberDiscount' => $memberDiscount,
            'membershipSettings' => $this->membership->settings(),
            'momoDemo'       => $this->momoDemoEnabled() && $paymentMethods->contains('key', 'momo'),
            'startTime'      => Carbon::parse($showtime->start_time)->setTimezone($tz)->format('H:i'),
            'startDate'      => Carbon::parse($showtime->start_time)->setTimezone($tz)->translatedFormat('l, d/m/Y'),
        ]);
    }

    /**
     * Tạo giao dịch MoMo (sandbox/demo).
     */
    public function momoPayment(Request $request, Showtime $showtime): RedirectResponse
    {
        abort_unless($showtime->status === 'active' && $showtime->movie?->status === 'now_showing', 404);
        if (!PaymentMethod::onlineEnabled('momo')) {
            return redirect()->route('booking.payment', $showtime)->with('error', 'Phương thức MoMo đang tạm dừng. Vui lòng chọn phương thức khác.');
        }
        $hold = $this->bookingHoldService->active($showtime->id);
        $seats = collect($hold['seats'] ?? []);
        if ($seats->isEmpty()) {
            return back()->with('error', 'Vui lòng chọn ghế.');
        }

        $ticketTotal = $this->bookingService->calculateTicketTotal($showtime, $seats);
        $comboTotal  = max(0, (float) session('booking.combo_total', 0));
        $amount      = $ticketTotal + $comboTotal;

        // Tính lại từ dữ liệu đặt vé; lượt dùng chỉ trừ khi thanh toán thành công.
        $data = $request->validate(['promo_code' => ['nullable', 'string', 'max:50']]);
        $code = strtoupper(trim($data['promo_code'] ?? ''));
        if ($code) {
            try {
                $promo = $this->promotionService->findAvailable($code);
                $code = $promo->code;
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return redirect()->route('booking.payment', $showtime)->with('error', $exception->getMessage());
            }
            $amount = max(0, $amount - $promo->discountFor($amount));
        } else {
            $amount = max(0, $amount - $this->membership->discount($request->user(), $amount));
        }

        if ($this->momoDemoEnabled()) {
            session()->put('booking.pending_payment', [
                'showtime_id' => $showtime->id, 'promo_code' => $code, 'method' => 'momo', 'mode' => 'demo',
                'combo_total' => $comboTotal, 'combo_items' => session('booking.combo_items', []),
            ]);
            return $this->demoPaymentReturn($showtime, $code);
        }

        if ($amount <= 0) {
            return redirect()->route('booking.payment', $showtime)->with('error', 'Số tiền không hợp lệ.');
        }
        // Config MoMo
        $endpoint    = config('services.momo.endpoint');
        $partnerCode = (string) config('services.momo.partner_code');
        $accessKey   = (string) config('services.momo.access_key');
        $secretKey   = (string) config('services.momo.secret_key');
        if (! $partnerCode || ! $accessKey || ! $secretKey || ! $endpoint) {
            session()->forget('booking.pending_payment');
            return redirect()->route('booking.payment', $showtime)
                ->with('error', 'MoMo chưa được cấu hình đầy đủ. Vui lòng chọn phương thức thanh toán khác. Ghế và mã khuyến mãi của bạn vẫn được giữ trong thời gian còn lại.');
        }
        $redirectUrl = route('booking.payment.return');
        $ipnUrl      = route('booking.payment.ipn');

        $orderId  = $partnerCode . '-' . Str::uuid();
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

        session()->put('booking.pending_payment', [
            'showtime_id' => $showtime->id, 'promo_code' => $code, 'method' => 'momo', 'mode' => 'gateway',
            'combo_total' => $comboTotal, 'combo_items' => session('booking.combo_items', []),
        ]);
        try {
            $gatewayResponse = Http::acceptJson()->timeout(15)->connectTimeout(5)->post($endpoint, $payload);
            $response = $gatewayResponse->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            session()->forget('booking.pending_payment');
            return redirect()->route('booking.payment', $showtime)
                ->with('error', 'Chưa kết nối được MoMo. Vui lòng thử lại hoặc chọn phương thức khác. Ghế và mã khuyến mãi của bạn vẫn được giữ trong thời gian còn lại.');
        }

        if ($gatewayResponse->successful() && is_array($response)
            && ($response['resultCode'] ?? null) === 0 && ! empty($response['payUrl'])
            && filter_var($response['payUrl'], FILTER_VALIDATE_URL)) {
            return redirect()->away($response['payUrl']);
        }

        session()->forget('booking.pending_payment');
        $resultCode = is_array($response) ? ($response['resultCode'] ?? null) : null;
        Log::warning('MoMo rejected payment creation', ['http_status' => $gatewayResponse->status(), 'result_code' => $resultCode]);
        $message = in_array((string) $resultCode, ['11', '13'], true)
            ? 'Tài khoản nhận thanh toán MoMo chưa hợp lệ hoặc chưa được kích hoạt. Vui lòng chọn phương thức khác.'
            : 'MoMo chưa thể tạo giao dịch. Vui lòng thử lại hoặc chọn phương thức khác.';
        return redirect()->route('booking.payment', $showtime)
            ->with('error', $message.' Ghế và mã khuyến mãi của bạn vẫn được giữ trong thời gian còn lại.');
    }

    public function momoIpn(Request $request): JsonResponse
    {
        // TODO: xác thực chữ ký, cập nhật order (invoice) sang paid/failed
        return response()->json(['message' => 'ok']);
    }

    /**
     * Xử lý thanh toán theo phương thức được chọn.
     * - momo: dùng chế độ demo/gateway đã cấu hình.
     * - khác: fake success (dùng cho demo/đồ án).
     */
    public function processPayment(Request $request, Showtime $showtime)
    {
        abort_unless($showtime->status === 'active' && $showtime->movie?->status === 'now_showing', 404);
        if (! $this->bookingHoldService->active($showtime->id)) {
            return redirect()->route('booking.checkout', $showtime)
                ->with('error', 'Thời gian giữ ghế đã hết. Vui lòng chọn lại ghế.');
        }

        $data = $request->validate(['payment_method' => ['required', Rule::in(PaymentMethod::onlineCodes())], 'promo_code' => ['nullable', 'string', 'max:50']],
            ['payment_method.in'=>'Phương thức đã tạm dừng hoặc không hợp lệ. Vui lòng chọn lại.']);
        $code = strtoupper(trim($data['promo_code'] ?? ''));
        if ($code) {
            try {
                $promotion = $this->promotionService->findAvailable($code);
                $code = $promotion->code;
            } catch (\Illuminate\Validation\ValidationException $exception) {
                return redirect()->route('booking.payment', $showtime)->with('error', $exception->getMessage());
            }
        }
        $method = $data['payment_method'];
        // MoMo dùng chế độ đã cấu hình, không tự chuyển sang demo khi cổng báo lỗi.
        if ($method === 'momo') {
            return $this->momoPayment($request, $showtime);
        }

        session()->put('booking.pending_payment', [
            'showtime_id' => $showtime->id, 'promo_code' => $code, 'method' => $method, 'mode' => 'demo',
            'combo_total' => (float) session('booking.combo_total', 0),
            'combo_items' => session('booking.combo_items', []),
        ]);
        return $this->demoPaymentReturn($showtime, $code);
    }

    private function momoDemoEnabled(): bool
    {
        return config('services.momo.mode') === 'demo' && app()->environment('local', 'testing');
    }

    private function demoPaymentReturn(Showtime $showtime, string $code): RedirectResponse
    {
        $extra = http_build_query([
            'showtime_id' => $showtime->id,
            'seats'       => session('booking.seats', ''),
            'combo_total' => session('booking.combo_total', 0),
            'promo_code'  => $code,
        ]);

        return redirect()->route('booking.payment.return', [
            'resultCode' => 0,
            'message'    => 'Thanh toán mô phỏng thành công. Không có tiền được trừ từ tài khoản của bạn.',
            'extraData'  => $extra,
        ]);
    }
}
