<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Showtime;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Invoice;
use App\Models\FoodOrder;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View as ViewContract;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FakePaymentController extends Controller
{
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

        $selectedSeats = collect(explode(',', $extra['seats'] ?? ''))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        if ($showtime) {
            $ticketPrice = (float) ($showtime->base_price ?? 0);
            $ticketTotal = $selectedSeats->count() * $ticketPrice;
        }

        $grandTotal = $ticketTotal + $comboTotal;

        $success = $resultCode === '0';

        // Tạo mã vé tạm và QR (demo). Nếu đã có ticket từ DB, có thể thay bằng ticket->code.
        $bookingCode = strtoupper(substr(md5(($extra['showtime_id'] ?? '').$selectedSeats->implode(',').microtime(true)), 0, 10));
        // Tạo QR inline (SVG base64) với payload chứa thông tin vé
        $cinema = $showtime?->room?->cinema?->name ?? 'N/A';
        $room = $showtime?->room?->name ?? 'N/A';
        $movieTitle = $showtime?->movie?->title ?? 'N/A';
        $payload = implode('|', [
            'TD-TICKET',
            'BOOKING=' . $bookingCode,
            'SHOWTIME=' . ($showtime->id ?? 'N/A'),
            'MOVIE=' . $movieTitle,
            'CINEMA=' . $cinema,
            'ROOM=' . $room,
            'SEATS=' . ($selectedSeats->implode(', ') ?: 'N/A'),
        ]);
        $qrSvg = QrCode::format('svg')
            ->encoding('UTF-8')
            ->size(200)
            ->margin(1)
            ->generate($payload);
        $qrUrl = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        // Nếu thành công và có showtime + ghế => ghi xuống DB (demo)
        if ($success && $showtime && $selectedSeats->isNotEmpty()) {
            DB::transaction(function () use ($showtime, $selectedSeats, $ticketTotal, $comboTotal, $grandTotal, $bookingCode) {
                $seatIds = Seat::where('room_id', $showtime->room_id)
                    ->whereIn(DB::raw("CONCAT(seat_row, seat_number)"), $selectedSeats->all())
                    ->pluck('id', DB::raw("CONCAT(seat_row, seat_number)"))
                    ->toArray();

                $userId = Auth::check() ? Auth::id() : null;
                $tickets = [];
                $seatCount = max(count($selectedSeats), 1);
                $perTicketPrice = ($ticketTotal / $seatCount); // chỉ phần vé
                $perTicketPayment = ($ticketTotal + $comboTotal) / $seatCount; // cộng dồn combo chia đều
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

                    Payment::create([
                        'ticket_id' => $ticket->id,
                        'amount'    => $perTicketPayment,
                        'method'    => 'momo',
                        'status'    => 'paid',
                        'paid_at'   => now(),
                    ]);

                    $tickets[] = $ticket;
                }

                if (!empty($tickets)) {
                    Invoice::create([
                        'user_id'      => $userId,
                        'ticket_id'    => $tickets[0]->id,
                        'promotion_id' => null,
                        'total_amount' => $grandTotal,
                        'invoice_code' => 'INV' . strtoupper(Str::random(8)),
                    ]);

                    // Lưu order combo (nếu có) gắn với vé đầu tiên (demo)
                    if ($comboTotal > 0 && $userId) {
                        FoodOrder::create([
                            'user_id'     => $userId,
                            'ticket_id'   => $tickets[0]->id,
                            'total_price' => $comboTotal,
                            'pickup_code' => 'F' . strtoupper(Str::random(5)),
                            'status'      => 'completed',
                        ]);
                    }
                }
            });
        }

        return view('frontend.booking.confirm', [
            'success'       => $success,
            'message'       => $message,
            'showtime'      => $showtime,
            'selectedSeats' => $selectedSeats,
            'ticketTotal'   => $ticketTotal,
            'comboTotal'    => $comboTotal,
            'grandTotal'    => $grandTotal,
            'bookingCode'   => $bookingCode,
            'qrUrl'         => $qrUrl,
        ]);
    }
}
