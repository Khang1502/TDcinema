<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\FoodOrder;
use App\Models\Invoice;
use App\Models\Movie;
use App\Models\PaymentMethod;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Services\BookingHoldService;
use App\Services\MembershipService;
use App\Services\PromotionService;
use App\Services\TicketExpiryService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TicketController extends Controller
{
    /**
     * Danh sách vé với bộ lọc.
     */
    public function index(Request $request, TicketExpiryService $expiry)
    {
        $expiry->expireOverdue();
        $filters = $this->ticketFilters($request);
        $base = Ticket::query();
        $this->applyTicketFilters($base,array_replace($filters,['status'=>'','payment_status'=>'']));
        $counts = ['total'=>(clone $base)->count(),
            'ready'=>(clone $base)->where('status','confirmed')->whereHas('payment',fn ($q)=>$q->where('status','paid'))->count(),
            'used'=>(clone $base)->where('status','used')->count(),'cancelled'=>(clone $base)->where('status','cancelled')->count()];
        $query = Ticket::with(['showtime.movie','showtime.room.cinema','seat','user','payment','foodOrders']);
        $this->applyTicketFilters($query,$filters);
        $tickets = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString();
        $bookingTickets = Ticket::with(['seat', 'payment', 'foodOrders'])
            ->whereIn('qr_code', $tickets->getCollection()->pluck('qr_code')->filter()->unique())
            ->whereIn('showtime_id', $tickets->getCollection()->pluck('showtime_id')->unique())
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->showtime_id.'|'.$row->qr_code);
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
        return view('admin.tickets.index',[
            'tickets'=>$tickets,'bookingTickets'=>$bookingTickets,'counts'=>$counts,'filters'=>$filters,
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
                $q->orWhere('tickets.qr_code', 'like', '%'.$term.'%');
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

    /**
     * Form tạo vé thủ công.
     */
    public function create(Request $request)
    {
        $selectedShowtimeId = old('showtime_id', $request->query('showtime_id'));
        $selectedSeatId     = old('seat_id', $request->query('seat_id'));

        $movieIds = $this->availableCounterShowtimes()->distinct()->pluck('showtimes.movie_id');
        $movies = Movie::where('status', 'now_showing')->whereIn('id', $movieIds)
            ->orderBy('title')->get(['id', 'title']);

        $selectedShowtime = $selectedShowtimeId
            ? $this->availableCounterShowtimes()->with(['movie', 'room.cinema'])->find($selectedShowtimeId)
            : null;
        $bookedSeatIds    = collect();
        $seats            = collect();

        if ($selectedShowtime) {
            $bookedSeatIds = Ticket::where('showtime_id', $selectedShowtime->id)->pluck('seat_id');
            $seats = Seat::with('room.cinema')
                ->where('room_id', $selectedShowtime->room_id)
                ->where('status', 1)
                ->orderBy('seat_row')
                ->orderBy('seat_number')
                ->get();
        }

        $selectedCustomer = old('user_id')
            ? User::whereKey(old('user_id'))->where('role', 'customer')->where('status', 'active')->where('is_active', true)->first(['id', 'name', 'email', 'phone'])
            : null;

        return view('admin.tickets.create', [
            'movies'       => $movies,
            'seats'        => $seats,
            'products'     => FoodItem::where('status', 'available')->orderBy('name')->get(),
            'selectedCustomer' => $selectedCustomer,
            'prefillSeat'  => $selectedSeatId,
            'selectedShowtime' => $selectedShowtime,
            'bookedSeatIds' => $bookedSeatIds,
            'saleToken' => old('sale_token', (string) Str::uuid()),
        ]);
    }

    private function availableCounterShowtimes()
    {
        return Showtime::query()
            ->where('showtimes.start_time', '>', now())
            ->where('showtimes.start_time', '<=', $this->counterSaleEndTime())
            ->where('showtimes.status', 'active')
            ->whereHas('movie', fn ($query) => $query->where('status', 'now_showing'))
            ->whereHas('room', fn ($query) => $query->where('status', 'active')
                ->whereHas('cinema', fn ($cinema) => $cinema->where('status', 'active')));
    }

    private function counterSaleEndTime(): Carbon
    {
        return now()->startOfDay()->addDays(6)->endOfDay();
    }

    public function showtimeOptions(Request $request)
    {
        $data = $request->validate([
            'stage' => ['required', 'in:cinemas,rooms,dates,showtimes'],
            'movie_id' => ['required', 'integer', 'exists:movies,id'],
            'cinema_id' => ['required_if:stage,rooms,dates,showtimes', 'nullable', 'integer', 'exists:cinemas,id'],
            'room_id' => ['required_if:stage,dates,showtimes', 'nullable', 'integer', 'exists:rooms,id'],
            'date' => ['required_if:stage,showtimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $query = $this->availableCounterShowtimes()->where('showtimes.movie_id', $data['movie_id']);

        $options = match ($data['stage']) {
            'cinemas' => $query->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                ->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
                ->distinct()->orderBy('cinemas.name')
                ->get(['cinemas.id as value', 'cinemas.name as label']),
            'rooms' => $query->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                ->where('rooms.cinema_id', $data['cinema_id'])
                ->distinct()->orderBy('rooms.name')
                ->get(['rooms.id as value', 'rooms.name as label']),
            'dates' => $query->where('showtimes.room_id', $data['room_id'])
                ->whereHas('room', fn ($room) => $room->where('cinema_id', $data['cinema_id']))
                ->selectRaw('DATE(showtimes.start_time) as value')->distinct()->orderBy('value')->get()
                ->map(fn ($row) => ['value' => $row->value, 'label' => Carbon::parse($row->value)->format('d/m/Y')]),
            'showtimes' => $query->where('showtimes.room_id', $data['room_id'])
                ->whereHas('room', fn ($room) => $room->where('cinema_id', $data['cinema_id']))
                ->whereDate('showtimes.start_time', $data['date'])
                ->orderBy('showtimes.start_time')->get(['showtimes.id', 'showtimes.start_time', 'showtimes.end_time'])
                ->map(fn ($showtime) => ['value' => $showtime->id,
                    'label' => $showtime->start_time->format('H:i').($showtime->end_time ? ' – '.$showtime->end_time->format('H:i') : '')]),
        };

        return response()->json(['options' => $options]);
    }

    public function memberSearch(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($data['q'] ?? '');

        if (mb_strlen($term) < 2) {
            return response()->json(['members' => []]);
        }

        $members = User::query()
            ->where('role', 'customer')
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'phone']);

        return response()->json(['members' => $members]);
    }

    public function memberPromotions(Request $request, MembershipService $membership)
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $customer = User::whereKey($data['user_id'])->where('role', 'customer')
            ->where('status', 'active')->where('is_active', true)->firstOrFail();
        $level = $membership->levelFor((int) $customer->points);
        $promotions = Promotion::available()->eligibleFor($customer)->orderBy('end_date')->get()
            ->map(fn (Promotion $promotion) => [
                'code' => $promotion->code,
                'name' => $promotion->name,
                'description' => $promotion->description,
                'discount_label' => $promotion->discount_label,
                'type' => $promotion->type,
                'value' => $promotion->value,
                'end_date' => $promotion->end_date->format('d/m/Y'),
            ]);

        return response()->json([
            'promotions' => $promotions,
            'membership' => [
                'level' => $level?->name,
                'discount_percent' => $membership->settings()->is_enabled ? (float) ($level?->discount_percent ?? 0) : 0,
            ],
        ]);
    }

    /**
     * Lưu vé thủ công (confirmed + thanh toán offline).
     */
    public function store(Request $request, BookingHoldService $holds, MembershipService $membership, PromotionService $promotions)
    {
        if ($request->input('customer_type') === 'guest') {
            $request->merge(['user_id' => null, 'promo_code' => null]);
        }
        $maxSeats = (int) config('booking_limits.seats_per_order');
        $maxFoodQuantity = (int) config('booking_limits.quantity_per_food_item');
        $data = $request->validate([
            'showtime_id' => ['required', 'exists:showtimes,id'],
            'seat_id'     => ['nullable', 'exists:seats,id'],
            'seat_ids'    => ['nullable', 'array', 'min:1', 'max:'.$maxSeats],
            'seat_ids.*'  => ['integer', 'distinct', 'exists:seats,id'],
            'customer_type' => ['nullable', 'in:guest,member'],
            'user_id'     => ['nullable', 'required_if:customer_type,member', 'exists:users,id'],
            'promo_code'  => ['nullable', 'string', 'max:50'],
            'items'       => ['nullable', 'array', 'max:100'],
            'items.*'     => ['nullable', 'integer', 'min:0', 'max:'.$maxFoodQuantity],
            'sale_token'  => ['nullable', 'uuid'],
        ], [
            'showtime_id.required' => 'Chọn suất chiếu.',
            'seat_ids.max' => 'Chỉ được chọn tối đa '.$maxSeats.' ghế cho mỗi giao dịch.',
            'items.*.max' => 'Mỗi combo hoặc sản phẩm chỉ được chọn tối đa '.$maxFoodQuantity.' phần.',
        ]);
        if (($data['customer_type'] ?? null) === 'guest') {
            $data['user_id'] = null;
            $data['promo_code'] = null;
        }
        $seatIds = collect($data['seat_ids'] ?? (isset($data['seat_id']) ? [$data['seat_id']] : []))->map(fn ($id) => (int) $id)->unique();
        $quantities = collect($data['items'] ?? [])->filter(fn ($quantity) => (int) $quantity > 0);
        if ($seatIds->isEmpty()) throw ValidationException::withMessages(['seat_ids' => 'Chọn ít nhất một ghế.']);
        if (! PaymentMethod::where('code', 'cash')->where('is_enabled', true)->exists()) {
            return back()->withInput()->with('error', 'Thanh toán tiền mặt tại quầy đang tạm dừng. Vui lòng kiểm tra cấu hình phương thức thanh toán.');
        }
        $data['sale_token'] ??= (string) Str::uuid();
        $code = 'POS-'.$data['sale_token'];
        $firstId = DB::transaction(function () use ($data, $seatIds, $quantities, $code, $holds, $membership, $promotions, $request) {
            $existing = Invoice::where('invoice_code', $code)->first();
            if ($existing) return $existing->ticket_id;
            $showtime = Showtime::with(['movie', 'room.cinema'])->whereKey($data['showtime_id'])->lockForUpdate()->firstOrFail();
            if ($showtime->status !== 'active' || $showtime->start_time <= now() || $showtime->start_time > $this->counterSaleEndTime()
                || $showtime->movie?->status !== 'now_showing'
                || $showtime->room?->status !== 'active' || $showtime->room?->cinema?->status !== 'active') {
                throw ValidationException::withMessages(['showtime_id' => 'Suất chiếu không còn khả dụng.']);
            }
            $seats = Seat::whereIn('id', $seatIds)->where('room_id', $showtime->room_id)->where('status', 1)->orderBy('id')->get();
            if ($seats->count() !== $seatIds->count()) throw ValidationException::withMessages(['seat_ids' => 'Có ghế không khả dụng hoặc khác phòng.']);
            $codes = $seats->map(fn ($s) => $s->seat_row.$s->seat_number);
            if ($holds->heldByOther($showtime->id, $codes)->isNotEmpty()) throw ValidationException::withMessages(['seat_ids' => 'Ghế đang được khách khác giữ.']);
            if (Ticket::where('showtime_id', $showtime->id)->whereIn('seat_id', $seatIds)->exists()) {
                throw ValidationException::withMessages(['seat_ids' => 'Ghế đã có vé cho suất chiếu này.']);
            }
            $customer = isset($data['user_id']) ? User::whereKey($data['user_id'])->where('role', 'customer')->where('status', 'active')->where('is_active', true)->first() : null;
            if (isset($data['user_id']) && ! $customer) throw ValidationException::withMessages(['user_id' => 'Chỉ có thể chọn tài khoản khách hàng đang hoạt động.']);
            if (! empty($data['promo_code']) && ! $customer) throw ValidationException::withMessages(['promo_code' => 'Chọn thành viên trước khi áp dụng khuyến mãi.']);
            $promotion = ! empty($data['promo_code']) ? $promotions->findAvailableForUser($data['promo_code'], $customer, true) : null;
            $products = $quantities->isNotEmpty()
                ? FoodItem::whereIn('id', $quantities->keys())->where('status', 'available')->lockForUpdate()->get()->keyBy('id')
                : collect();
            if ($products->count() !== $quantities->count()) throw ValidationException::withMessages(['items' => 'Có sản phẩm đã ngừng bán. Vui lòng tải lại trang.']);
            $foodTotal = $quantities->reduce(fn ($sum, $quantity, $id) => $sum + (float) $products[$id]->price * (int) $quantity, 0);
            $gross = $seats->sum(fn ($seat) => (float) $showtime->base_price + (float) $seat->extra_price);
            $membershipDiscount = $customer && ! $promotion ? $membership->discount($customer, $gross) : 0;
            $promotionDiscount = $promotion?->discountFor($gross + $foodTotal) ?? 0;
            $ticketDiscount = $promotion
                ? min($gross, round($promotionDiscount * $gross / max(1, $gross + $foodTotal), 2))
                : $membershipDiscount;
            $foodDiscount = $promotion ? min($foodTotal, $promotionDiscount - $ticketDiscount) : 0;
            $net = max(0, $gross - $ticketDiscount);
            $foodNet = max(0, $foodTotal - $foodDiscount);
            $tickets = collect();
            $remaining = $net;
            foreach ($seats as $index => $seat) {
                $amount = $index === $seats->count() - 1 ? $remaining : round($net * (((float) $showtime->base_price + (float) $seat->extra_price) / max(1, $gross)), 2);
                $remaining -= $amount;
                $ticket = Ticket::create(['user_id' => $customer?->id, 'sold_by' => $request->user()->id, 'sales_channel' => 'counter',
                    'showtime_id' => $showtime->id, 'seat_id' => $seat->id, 'price' => $amount, 'status' => 'confirmed',
                    'qr_code' => $code, 'booked_at' => now()]);
                Payment::create(['ticket_id' => $ticket->id, 'amount' => $amount, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
                $tickets->push($ticket);
            }
            if ($quantities->isNotEmpty()) {
                $order = FoodOrder::create(['user_id' => $customer?->id, 'ticket_id' => $tickets->first()->id,
                    'sold_by' => $request->user()->id, 'sale_token' => $data['sale_token'], 'total_price' => $foodNet, 'status' => 'completed']);
                $order->update(['pickup_code' => 'F'.$order->id]);
                foreach ($quantities as $id => $quantity) {
                    $order->items()->create(['food_item_id' => $id, 'quantity' => (int) $quantity, 'price' => $products[$id]->price]);
                }
                Payment::create(['food_order_id' => $order->id, 'amount' => $foodNet,
                    'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
            }
            $invoice = Invoice::create(['user_id' => $customer?->id, 'ticket_id' => $tickets->first()->id,
                'promotion_id' => $promotion?->id, 'total_amount' => $net + $foodNet,
                'invoice_code' => $code, 'membership_level_name' => $customer ? $membership->levelFor((int) $customer->points)?->name : null,
                'membership_discount' => $membershipDiscount]);
            if ($promotion) $promotions->consume($promotion);
            if ($customer) $membership->award($invoice);
            return $tickets->first()->id;
        });
        return redirect()->route('admin.tickets.show', $firstId)->with('success',
            $quantities->isNotEmpty() ? 'Đã thu tiền, tạo vé và đơn bắp nước tại quầy.' : 'Đã thu tiền và tạo vé tại quầy.');
    }

    /**
     * Chi tiết vé.
     */
    public function show(Ticket $ticket, TicketExpiryService $expiry)
    {
        $expiry->expireOverdue();
        $ticket->refresh();
        $ticket->load([
            'showtime.movie',
            'showtime.room.cinema',
            'seat',
            'user',
            'seller',
            'payment',
            'invoice.promotion',
            'foodOrders.items.foodItem',
        ]);

        // Bảo đảm vé luôn có QR code (chuỗi) đồng bộ với FE
        $this->generateQrForTicket($ticket);
        $bookingTickets = Ticket::with(['seat', 'payment'])
            ->where('showtime_id', $ticket->showtime_id)
            ->where('qr_code', $ticket->qr_code)
            ->orderBy('id')
            ->get();
        $bookingFoodOrders = FoodOrder::with(['items.foodItem', 'payment', 'ticket.payment'])
            ->whereIn('ticket_id', $bookingTickets->pluck('id'))
            ->orderBy('id')
            ->get();
        $bookingInvoice = Invoice::with('promotion')
            ->whereIn('ticket_id', $bookingTickets->pluck('id'))
            ->first();

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
            'bookingTickets' => $bookingTickets,
            'bookingFoodOrders' => $bookingFoodOrders,
            'bookingInvoice' => $bookingInvoice,
        ]);
    }

    public function checkinBooking(Ticket $ticket, TicketExpiryService $expiry)
    {
        $expiry->expireOverdue();
        $this->generateQrForTicket($ticket);

        $updated = Ticket::query()
            ->where('showtime_id', $ticket->showtime_id)
            ->where('qr_code', $ticket->qr_code)
            ->where('status', 'confirmed')
            ->whereHas('payment', fn ($query) => $query->where('status', 'paid'))
            ->whereHas('showtime', fn ($query) => $query->where('end_time', '>', now()))
            ->update(['status' => 'used']);

        return $updated
            ? back()->with('success', "Đã check-in {$updated} ghế trong lượt đặt.")
            : back()->withErrors(['status' => 'Lượt đặt không còn ghế đã thanh toán nào chờ check-in.']);
    }

    /** Hủy vé riêng lẻ; check-in chỉ thực hiện theo lượt đặt. */
    public function updateStatus(Request $request, Ticket $ticket, TicketExpiryService $expiry)
    {
        $expiry->expireOverdue($ticket->id);
        $ticket->refresh();
        $data = $request->validate([
            'status' => 'required|in:cancelled',
        ]);

        if ($ticket->status === 'used') {
            return back()->withErrors(['status' => 'Vé đã check-in, không thể đổi trạng thái.']);
        }
        if ($ticket->status === 'cancelled') {
            return back()->withErrors(['status' => $ticket->cancellation_reason === Ticket::EXPIRED_REASON
                ? 'Vé đã quá hạn và được chuyển vào mục đã hủy.' : 'Vé đã hủy, không thể đổi trạng thái.']);
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

        $ticket->update(['status' => $data['status'], 'cancellation_reason' => null]);

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
