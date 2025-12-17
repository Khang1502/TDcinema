<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    /**
     * Danh sách vé với bộ lọc.
     */
    public function index(Request $request)
    {
        $query = Ticket::with([
            'showtime.movie',
            'showtime.room.cinema',
            'seat',
            'user',
            'payment',
            'foodOrders.items.foodItem',
        ]);

        // Bộ lọc
        if ($request->filled('movie_id')) {
            $query->whereHas('showtime.movie', fn ($q) => $q->where('id', $request->movie_id));
        }
        if ($request->filled('cinema_id')) {
            $query->whereHas('showtime.room.cinema', fn ($q) => $q->where('id', $request->cinema_id));
        }
        if ($request->filled('showtime_id')) {
            $query->where('showtime_id', $request->showtime_id);
        }
        if ($request->filled('seat_id')) {
            $query->where('seat_id', $request->seat_id);
        }
        if ($request->filled('user')) {
            $term = $request->user;
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_status')) {
            $query->whereHas('payment', fn ($q) => $q->where('status', $request->payment_status));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $tickets = $query->latest()->paginate(15)->appends($request->query());

        // Chuẩn bị dữ liệu cho filter phụ thuộc
        $showtimeQuery = Showtime::with('movie', 'room.cinema')->orderBy('start_time', 'desc');
        if ($request->filled('cinema_id')) {
            $showtimeQuery->whereHas('room.cinema', fn ($q) => $q->where('id', $request->cinema_id));
        }
        if ($request->filled('movie_id')) {
            $showtimeQuery->where('movie_id', $request->movie_id);
        }
        $showtimes = $showtimeQuery->get();

        // Danh sách ghế: chỉ load khi đã chọn suất chiếu, giới hạn ghế active của đúng phòng
        $seats = collect();
        if ($request->filled('showtime_id')) {
            $roomId = Showtime::where('id', $request->showtime_id)->value('room_id');
            if ($roomId) {
                $seats = Seat::with('room.cinema')
                    ->where('status', 1)
                    ->where('room_id', $roomId)
                    ->orderBy('seat_row')
                    ->orderBy('seat_number')
                    ->get();
            }
        }

        return view('admin.tickets.index', [
            'tickets'      => $tickets,
            'movies'       => \App\Models\Movie::orderBy('title')->get(),
            'cinemas'      => Cinema::orderBy('name')->get(),
            'showtimes'    => $showtimes,
            'seats'        => $seats,
            'statuses'     => ['pending', 'confirmed', 'cancelled', 'used'],
            'payStatuses'  => ['unpaid', 'paid', 'refunded', 'failed'],
            'filters'      => $request->only(['movie_id', 'cinema_id', 'showtime_id', 'seat_id', 'user', 'status', 'payment_status', 'from', 'to']),
        ]);
    }

    /**
     * Check-in hàng loạt theo bộ lọc hiện tại (chỉ áp dụng vé confirmed + paid).
     */
    public function bulkCheckin(Request $request)
    {
        $query = Ticket::where('status', 'confirmed')
            ->whereHas('payment', fn ($q) => $q->where('status', 'paid'));

        // Áp dụng các bộ lọc giống trang index
        if ($request->filled('movie_id')) {
            $query->whereHas('showtime.movie', fn ($q) => $q->where('id', $request->movie_id));
        }
        if ($request->filled('cinema_id')) {
            $query->whereHas('showtime.room.cinema', fn ($q) => $q->where('id', $request->cinema_id));
        }
        if ($request->filled('showtime_id')) {
            $query->where('showtime_id', $request->showtime_id);
        }
        if ($request->filled('seat_id')) {
            $query->where('seat_id', $request->seat_id);
        }
        if ($request->filled('user')) {
            $term = $request->user;
            $query->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $count = $query->count();
        if ($count === 0) {
            return back()->with('error', 'Không có vé nào phù hợp để check-in.');
        }

        $updated = $query->update(['status' => 'used']);

        return back()->with('success', "Đã check-in {$updated}/{$count} vé (lọc: confirmed + paid).");
    }

    /**
     * Form tạo vé thủ công.
     */
    public function create(Request $request)
    {
        $selectedShowtimeId = old('showtime_id', $request->query('showtime_id'));
        $selectedSeatId     = old('seat_id', $request->query('seat_id'));

        $showtimes = Showtime::with('movie', 'room.cinema')
            ->orderBy('start_time', 'desc')
            ->get();

        $selectedShowtime = null;
        $bookedSeatIds    = collect();
        $seats            = collect();

        if ($selectedShowtimeId) {
            $selectedShowtime = Showtime::with('movie', 'room.cinema')->find($selectedShowtimeId);
            if ($selectedShowtime) {
                $bookedSeatIds = Ticket::where('showtime_id', $selectedShowtime->id)
                    ->whereIn('status', ['pending', 'confirmed', 'used'])
                    ->pluck('seat_id');

                $seats = Seat::with('room.cinema')
                    ->where('room_id', $selectedShowtime->room_id)
                    ->where('status', 1)
                    ->orderBy('seat_row')
                    ->orderBy('seat_number')
                    ->get();
            }
        }

        // Mặc định chỉ lấy user vai trò customer để tạo vé cho khách
        $users = User::where('role', 'customer')->orderBy('name')->get();

        return view('admin.tickets.create', [
            'showtimes'    => $showtimes,
            'seats'        => $seats,
            'users'        => $users,
            'prefillShowtime' => $selectedShowtimeId,
            'prefillSeat'  => $selectedSeatId,
            'selectedShowtime' => $selectedShowtime,
            'bookedSeatIds' => $bookedSeatIds,
        ]);
    }

    /**
     * Lưu vé thủ công (confirmed + thanh toán offline).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'showtime_id' => ['required', 'exists:showtimes,id'],
            'seat_id'     => ['required', 'exists:seats,id'],
            'user_id'     => ['required', 'exists:users,id'],
            'price'       => ['nullable', 'numeric', 'min:0'],
        ], [
            'showtime_id.required' => 'Chọn suất chiếu.',
            'seat_id.required'     => 'Chọn ghế.',
            'user_id.required'     => 'Chọn khách hàng.',
        ]);

        // Check ghế trống
        $seatBusy = Ticket::where('showtime_id', $data['showtime_id'])
            ->where('seat_id', $data['seat_id'])
            ->whereIn('status', ['pending', 'confirmed', 'used'])
            ->exists();
        if ($seatBusy) {
            return back()->withErrors(['seat_id' => 'Ghế đã được đặt cho suất chiếu này.'])->withInput();
        }

        $showtime = Showtime::with('room')->findOrFail($data['showtime_id']);
        $seat     = Seat::findOrFail($data['seat_id']);

        // Đảm bảo ghế thuộc đúng phòng của suất chiếu và ghế đang active
        if ($seat->room_id !== $showtime->room_id || ($seat->status ?? 1) != 1) {
            return back()->withErrors(['seat_id' => 'Ghế không thuộc phòng của suất chiếu hoặc không khả dụng.'])->withInput();
        }

        $price = $data['price'] ?? ($showtime->base_price + ($seat->extra_price ?? 0));

        $ticket = Ticket::create([
            'user_id'     => $data['user_id'],
            'showtime_id' => $data['showtime_id'],
            'seat_id'     => $data['seat_id'],
            'price'       => $price,
            'status'      => 'confirmed',
            'booked_at'   => Carbon::now(),
        ]);

        Payment::create([
            'ticket_id' => $ticket->id,
            'amount'    => $price,
            'method'    => 'cash',
            'status'    => 'paid',
            'paid_at'   => Carbon::now(),
        ]);

        // Tạo QR code cho vé (nếu chưa có)
        $this->generateQrForTicket($ticket);

        return redirect()->route('admin.tickets.index')->with('success', 'Đã tạo vé thủ công.');
    }

    /**
     * Chi tiết vé.
     */
    public function show(Ticket $ticket)
    {
        $ticket->load([
            'showtime.movie',
            'showtime.room.cinema',
            'seat',
            'user',
            'payment',
            'foodOrders.items.foodItem',
        ]);

        // Bảo đảm vé luôn có QR code (chuỗi) đồng bộ với FE
        $this->generateQrForTicket($ticket);

        // Tạo QR inline (data-uri) từ qr_code hiện tại
        $qrText = $ticket->qr_code ?: ('TICKET-' . $ticket->id);
        $qrSvg = QrCode::format('svg')
            ->encoding('UTF-8')
            ->size(320)
            ->margin(1)
            ->generate($qrText);
        $qrDataUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'qrDataUri' => $qrDataUri,
            'qrText' => $qrText,
        ]);
    }

    /**
     * Cập nhật trạng thái used/cancelled.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => 'required|in:used,cancelled',
        ]);

        if ($ticket->status === 'used') {
            return back()->withErrors(['status' => 'Vé đã check-in, không thể đổi trạng thái.']);
        }

        if ($data['status'] === 'used' && $ticket->status !== 'confirmed') {
            return back()->withErrors(['status' => 'Chỉ vé đã thanh toán (confirmed) mới được check-in.']);
        }

        // Huỷ vé: nếu đã thanh toán thì hoàn tiền (mock)
        if ($data['status'] === 'cancelled') {
            if ($ticket->payment && $ticket->payment->status === 'paid') {
                $ticket->payment->update([
                    'status'  => 'refunded',
                    'paid_at' => $ticket->payment->paid_at ?? Carbon::now(),
                ]);
            }
        }

        $ticket->update(['status' => $data['status']]);

        return back()->with('success', 'Đã cập nhật trạng thái vé.');
    }

    /**
     * Đảm bảo vé có mã QR (chuỗi). Không lưu file, dùng cùng mã với FE.
     */
    protected function generateQrForTicket(Ticket $ticket): void
    {
        // Nếu đã có mã (booking code) thì giữ nguyên để đồng bộ FE/BE
        if (!empty($ticket->qr_code)) {
            return;
        }

        // Nếu chưa có, gán mã cơ bản theo ticket id
        $ticket->update(['qr_code' => 'TICKET-' . $ticket->id]);
    }
}
