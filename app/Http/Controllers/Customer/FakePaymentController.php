<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Showtime;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Invoice;
use App\Models\FoodOrder;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\BookingHoldService;
use App\Services\PromotionService;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View as ViewContract;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FakePaymentController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly BookingHoldService $bookingHoldService,
        private readonly PromotionService $promotionService,
        private readonly MembershipService $membership
    ) {
    }

    /**
     * Nhận redirect từ cổng thanh toán (fake hoặc demo) và hiển thị trang xác nhận.
     */
    public function return(Request $request): ViewContract
    {
        $resultCode = (string) $request->query('resultCode', '');
        $message    = urldecode($request->query('message', 'Thanh toán thất bại.'));

        // extraData: showtime_id=...&seats=A1,A2&combo_total=...
        $extra = [];
        parse_str($request->query('extraData', ''), $extra);

        $showtime = null;
        $selectedSeats = collect();
        $ticketTotal = 0;
        $comboTotal = (float) ($extra['combo_total'] ?? 0);

        if (!empty($extra['showtime_id'])) {
            $showtime = Showtime::with(['movie', 'room.cinema'])->find($extra['showtime_id']);
        }

        $selectedSeats = $this->bookingService->parseSeatCodes($extra['seats'] ?? '');
        $sessionSeats = $this->bookingService->parseSeatCodes(session('booking.seats', ''));
        $activeHold = $showtime ? $this->bookingHoldService->active($showtime->id) : null;
        $pending = session('booking.pending_payment', []);
        $hasValidSession = $activeHold !== null
            && (int) ($pending['showtime_id'] ?? 0) === $showtime?->id
            && $sessionSeats->isNotEmpty()
            && $selectedSeats->isNotEmpty()
            && $this->bookingService->areSameSeatCodes($sessionSeats, $selectedSeats);

        if ($hasValidSession) {
            $selectedSeats = $sessionSeats;
            $comboTotal = (float) ($pending['combo_total'] ?? session('booking.combo_total', $comboTotal));
        }
        $comboItems = $hasValidSession ? ($pending['combo_items'] ?? session('booking.combo_items', [])) : [];

        if ($showtime) {
            $ticketTotal = $this->bookingService->calculateTicketTotal($showtime, $selectedSeats);
        }

        $grandTotal = $ticketTotal + $comboTotal;
        $promoDiscount = 0;
        $appliedPromoCode = '';
        $pointsEarned = 0;
        $foodPickupCode = null;

        $success = $resultCode === '0';
        if ($success && (!$hasValidSession || !Auth::check())) {
            $success = false;
            $message = 'Phiên đặt vé đã hết hoặc chưa đăng nhập.';
        }

        $bookingCode = 'WEB-'.Str::upper(Str::random(16));
        $qrSvg = QrCode::format('svg')
            ->encoding('UTF-8')
            ->size(200)
            ->margin(1)
            ->generate($bookingCode);
        $qrUrl = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // Nếu thành công và có showtime + ghế => ghi xuống DB (demo)
        if ($success && $showtime && $selectedSeats->isNotEmpty() && Auth::check()) {
            try {
                DB::transaction(function () use ($showtime, $selectedSeats, $ticketTotal, $comboTotal, $comboItems, &$grandTotal, &$promoDiscount, &$appliedPromoCode, &$pointsEarned, &$foodPickupCode, $bookingCode, $pending) {
                    $currentShow = Showtime::with('movie')->lockForUpdate()->findOrFail($showtime->id);
                    if ($currentShow->status !== 'active' || $currentShow->movie?->status !== 'now_showing') {
                        throw \Illuminate\Validation\ValidationException::withMessages(['showtime' => 'Suất chiếu đã đóng hoặc phim chưa mở lịch.']);
                    }
                    $seatIds = Seat::where('room_id', $showtime->room_id)
                        ->whereIn(DB::raw("CONCAT(seat_row, seat_number)"), $selectedSeats->all())
                        ->pluck('id', DB::raw("CONCAT(seat_row, seat_number)"))
                        ->toArray();
                    if (count($seatIds) !== $selectedSeats->count()) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['seats' => 'Ghế đã chọn không còn hợp lệ.']);
                    }
                    $promotion = ! empty($pending['promo_code']) ? $this->promotionService->findAvailable($pending['promo_code'], true) : null;
                    $member = \App\Models\User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
                    $memberLevel = $this->membership->levelFor((int) $member->points);
                    $memberDiscount = $promotion ? 0 : $this->membership->discount($member, $ticketTotal + $comboTotal);
                    $promoDiscount = $promotion?->discountFor($ticketTotal + $comboTotal) ?? $memberDiscount;
                    $grandTotal = max(0, $ticketTotal + $comboTotal - $promoDiscount);
                    $appliedPromoCode = $promotion?->code ?? ($memberDiscount > 0 ? $memberLevel?->name : '');

                    $userId = Auth::check() ? Auth::id() : null;
                    $tickets = [];
                    $seatCount = max(count($selectedSeats), 1);
                    $perTicketPrice = ($ticketTotal / $seatCount); // chỉ phần vé
                    $perTicketPayment = round($grandTotal / $seatCount, 2);
                    $paidAmount = 0;
                    foreach ($selectedSeats as $code) {
                        if (empty($seatIds[$code])) {
                            continue;
                        }
                        $ticket = Ticket::create([
                            'user_id'     => $userId,
                            'showtime_id' => $showtime->id,
                            'seat_id'     => $seatIds[$code],
                            'price'       => $perTicketPrice,
                            // Dùng mã booking chung cho mọi vé để khớp QR hiển thị cho khách
                            'qr_code'     => $bookingCode,
                            'status'      => 'confirmed',
                            'booked_at'   => now(),
                        ]);

                        $paymentAmount = count($tickets) === $seatCount - 1 ? round($grandTotal - $paidAmount, 2) : $perTicketPayment;
                        $paidAmount += $paymentAmount;
                        Payment::create([
                            'ticket_id' => $ticket->id,
                            'amount'    => $paymentAmount,
                            'method'    => $pending['method'],
                            'status'    => 'paid',
                            'paid_at'   => now(),
                        ]);

                        $tickets[] = $ticket;
                    }

                    if (!empty($tickets)) {
                        $invoice = Invoice::create([
                            'user_id'      => $userId,
                            'ticket_id'    => $tickets[0]->id,
                            'promotion_id' => $promotion?->id,
                            'total_amount' => $grandTotal,
                            'invoice_code' => 'INV' . strtoupper(Str::random(8)),
                            'membership_level_name' => $memberDiscount > 0 ? $memberLevel?->name : null,
                            'membership_discount' => $memberDiscount,
                        ]);
                        if ($promotion) $this->promotionService->consume($promotion);
                        $pointsEarned = $this->membership->award($invoice);

                        // Gắn đơn bắp nước với vé đầu tiên; giá và số lượng lấy từ phiên thanh toán đã xác minh.
                        if ($comboTotal > 0 && $userId) {
                            $foodOrder = FoodOrder::create([
                                'user_id'     => $userId,
                                'ticket_id'   => $tickets[0]->id,
                                'total_price' => $comboTotal,
                                'pickup_code' => 'F' . strtoupper(Str::random(5)),
                                'status'      => 'completed',
                            ]);
                            $foodPickupCode = $foodOrder->pickup_code;
                            foreach ($comboItems as $id => $item) {
                                $foodOrder->items()->create([
                                    'food_item_id' => $id,
                                    'quantity' => (int) $item['quantity'],
                                    'price' => (float) $item['price'],
                                ]);
                            }
                        }
                    }
                });
                $this->bookingHoldService->release();
                session()->forget('promotion.preferred_code');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $success = false;
                $message = $exception->getMessage();
                $promoDiscount = 0;
                $grandTotal = $ticketTotal + $comboTotal;
                $appliedPromoCode = '';
                $pointsEarned = 0;
            }
        }

        return view('frontend.booking.confirm', [
            'success'       => $success,
            'message'       => $message,
            'demoPayment'   => ($pending['mode'] ?? '') === 'demo',
            'showtime'      => $showtime,
            'selectedSeats' => $selectedSeats,
            'ticketTotal'   => $ticketTotal,
            'comboTotal'    => $comboTotal,
            'grandTotal'    => $grandTotal,
            'promoDiscount' => $promoDiscount,
            'appliedPromoCode' => $appliedPromoCode,
            'pointsEarned'  => $pointsEarned,
            'bookingCode'   => $bookingCode,
            'foodPickupCode'=> $foodPickupCode,
            'qrUrl'         => $qrUrl,
        ]);
    }
}
