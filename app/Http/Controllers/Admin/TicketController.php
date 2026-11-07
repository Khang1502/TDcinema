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
        $filters = $this->ticketFilters($request);
        $base = Ticket::query();
        $this->applyTicketFilters($base,array_replace($filters,['status'=>'','payment_status'=>'']));
        $counts = ['total'=>(clone $base)->count(),
            'ready'=>(clone $base)->where('status','confirmed')->whereHas('payment',fn ($q)=>$q->where('status','paid'))->count(),
            'used'=>(clone $base)->where('status','used')->count(),'cancelled'=>(clone $base)->where('status','cancelled')->count()];
        $query = Ticket::with(['showtime.movie','showtime.room.cinema','seat','user','payment','foodOrders']);
        $this->applyTicketFilters($query,$filters);
        $tickets = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $showtimeQuery = Showtime::with(['movie:id,title','room:id,cinema_id,name','room.cinema:id,name']);
        if ($filters['cinema_id']) $showtimeQuery->whereHas('room',fn ($q)=>$q->where('cinema_id',$filters['cinema_id']));
        if ($filters['movie_id']) $showtimeQuery->where('movie_id',$filters['movie_id']);
        if ($filters['showtime_id']) $showtimeQuery->whereKey($filters['showtime_id']);
        elseif ($filters['from'] || $filters['to']) $showtimeQuery->whereHas('tickets',function ($q) use ($filters) {
            if ($filters['from']) $q->whereDate('created_at','>=',$filters['from']);
            if ($filters['to']) $q->whereDate('created_at','<=',$filters['to']);
        });
        else $showtimeQuery->where('start_time','>=',now()->startOfDay());
        $showtimes = $showtimeQuery->orderBy('start_time')->limit(200)->get();
        $seats = collect();
        if ($filters['showtime_id'] && ($roomId = Showtime::whereKey($filters['showtime_id'])->value('room_id'))) {
            $seats = Seat::with('room.cinema')->where('room_id',$roomId)->orderBy('seat_row')->orderBy('seat_number')->get();
        }
        $readyQuery = Ticket::where('status','confirmed')->whereHas('payment',fn ($q)=>$q->where('status','paid'));
        $this->applyTicketFilters($readyQuery,$filters);
        return view('admin.tickets.index',[
            'tickets'=>$tickets,'counts'=>$counts,'readyCount'=>$readyQuery->count(),'filters'=>$filters,
            'movies'=>\App\Models\Movie::orderBy('title')->get(['id','title']),'cinemas'=>Cinema::orderBy('name')->get(['id','name']),
            'showtimes'=>$showtimes,'seats'=>$seats,'statuses'=>['pending','confirmed','cancelled','used'],
            'payStatuses'=>['unpaid','pending','paid','refunded','failed'],
        ]);
    }

    private function ticketFilters(Request $request): array
    {
        $data = $request->validate([
            'movie_id'=>['nullable','integer','exists:movies,id'],'cinema_id'=>['nullable','integer','exists:cinemas,id'],
            'showtime_id'=>['nullable','integer','exists:showtimes,id'],'seat_id'=>['nullable','integer','exists:seats,id'],
            'q'=>['nullable','string','max:150'],'user'=>['nullable','string','max:150'],
            'status'=>['nullable','in:pending,confirmed,cancelled,used'],'payment_status'=>['nullable','in:unpaid,pending,paid,refunded,failed'],
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>array_filter(['nullable','date_format:Y-m-d',$request->filled('from') ? 'after_or_equal:from' : null]),
        ]);
        $defaults = array_fill_keys(['movie_id','cinema_id','showtime_id','seat_id','q','user','status','payment_status','from','to'],'');
        return array_replace($defaults,array_map(fn ($value)=>is_string($value) ? trim($value) : ($value ?? ''),$data));
    }

    private function applyTicketFilters($query, array $filters): void
    {
        if ($filters['movie_id']) $query->whereHas('showtime',fn ($q)=>$q->where('movie_id',$filters['movie_id']));
        if ($filters['cinema_id']) $query->whereHas('showtime.room',fn ($q)=>$q->where('cinema_id',$filters['cinema_id']));
        if ($filters['showtime_id']) $query->where('showtime_id',$filters['showtime_id']);
        if ($filters['seat_id']) $query->where('seat_id',$filters['seat_id']);
        if ($filters['q'] !== '') {
            $term = $filters['q'];
            $query->where(function ($q) use ($term) {
                $number = ltrim($term,'#');
                if (ctype_digit($number)) $q->orWhere('tickets.id',$number);
                $q->orWhereHas('user',fn ($u)=>$u->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%')->orWhere('phone','like','%'.$term.'%'));
            });
        }
        if ($filters['user'] !== '') {
            $term = $filters['user'];
            $query->whereHas('user',fn ($u)=>$u->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%')->orWhere('phone','like','%'.$term.'%'));
        }
        if ($filters['status']) $query->where('status',$filters['status']);
        if ($filters['payment_status'] === 'unpaid') $query->whereDoesntHave('payment');
        elseif ($filters['payment_status']) $query->whereHas('payment',fn ($q)=>$q->where('status',$filters['payment_status']));
        if ($filters['from']) $query->whereDate('created_at','>=',$filters['from']);
        if ($filters['to']) $query->whereDate('created_at','<=',$filters['to']);
    }

    public function bulkCheckin(Request $request)
    {
        $filters = $this->ticketFilters($request);
        $query = Ticket::where('status','confirmed')->whereHas('payment',fn ($q)=>$q->where('status','paid'));
        $this->applyTicketFilters($query,$filters);
        $updated = $query->update(['status'=>'used']);
        return $updated ? back()->with('success',"Đã check-in {$updated} vé theo bộ lọc hiện tại.")
            : back()->with('error','Không có vé nào phù hợp để check-in.');
    }

    /**
     * Form tạo vé thủ công.
     */
    public function create(Request $request)
    {
        $selectedShowtimeId = old('showtime_id', $request->query('showtime_id'));
        $selectedSeatId     = old('seat_id', $request->query('seat_id'));

        $showtimes = Showtime::with([
            'movie:id,title',
            'room:id,cinema_id,name',
            'room.cinema:id,name',
        ])
            ->where(function ($query) use ($selectedShowtimeId) {
                $query->where('start_time', '>=', now()->startOfDay());
                if ($selectedShowtimeId) {
                    $query->orWhere('showtimes.id',$selectedShowtimeId);
                }
            })
            ->orderBy('start_time')
            ->limit(200)
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
        $users = User::where('role', 'customer')->orderBy('name')->limit(500)->get();

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
        if (!\App\Models\PaymentMethod::where('code', 'cash')->where('is_enabled', true)->exists()) {
            return back()->withInput()->with('error', 'Thanh toán tiền mặt tại quầy đang tạm dừng. Vui lòng kiểm tra cấu hình phương thức thanh toán.');
        }
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

        if ($data['status'] === 'used') {
            // Conditional update also prevents two simultaneous check-ins succeeding.
            $updated = Ticket::whereKey($ticket->id)->where('status', 'confirmed')
                ->whereHas('payment', fn ($query) => $query->where('status', 'paid'))
                ->update(['status' => 'used']);

            if (!$updated) {
                return back()->withErrors(['status' => 'Chỉ vé đã xác nhận và thanh toán thành công mới được check-in.']);
            }

            return back()->with('success', 'Đã check-in vé thành công.');
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
