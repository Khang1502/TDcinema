<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/profile.css') }}?v={{ filemtime(public_path('assets/css/frontend/profile.css')) }}">
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/tickets.css') }}?v={{ filemtime(public_path('assets/css/frontend/tickets.css')) }}">
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/membership.css') }}?v={{ filemtime(public_path('assets/css/frontend/membership.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/js/frontend/tickets.js') }}" defer></script>
    @endpush
<div class="history-page">
    <div class="history-container">
        <div class="history-grid">
            @include('profile.partials.account-sidebar', ['active' => 'tickets'])

            <div class="card main-card">
                @include('frontend.partials.membership-summary')

                <div class="section-title">
                    <h2>Lịch sử đặt vé</h2>
                </div>
                <div class="tab-bar">
                    <a href="#" class="active">Chi tiết lịch sử đặt vé</a>
                    <a href="{{ route('customer.membership') }}#stars-history">Lịch sử Stars</a>
                    <a href="{{ route('customer.gifts') }}">Quà tặng & ưu đãi</a>
                </div>

                @if($bookings->isEmpty())
                    <div class="empty">Bạn chưa có vé nào.</div>
                @else
                    <div class="ticket-list">
                        @foreach($bookings as $booking)
                            @php
                                $ticket = $booking->tickets->first();
                                $movie = $ticket->showtime->movie ?? null;
                                $poster = $movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg');
                                $seatLabels = $booking->tickets->map(fn ($item) => ($item->seat?->seat_row ?? '').($item->seat?->seat_number ?? ''))->implode(', ');
                                $usedCount = $booking->tickets->where('status', 'used')->count();
                                $cancelledCount = $booking->tickets->where('status', 'cancelled')->count();
                                $expiredCount = $booking->tickets->where('cancellation_reason', \App\Models\Ticket::EXPIRED_REASON)->count();
                                $readyCount = $booking->tickets->filter(fn ($item) => $item->status === 'confirmed' && $item->payment?->status === 'paid')->count();
                                $statusBadge = 'badge-info';
                                $statusLabel = 'Chờ check-in';
                                if ($usedCount === $booking->tickets->count()) { $statusBadge = 'badge-success'; $statusLabel = 'Đã check-in toàn bộ'; }
                                elseif ($usedCount > 0) { $statusBadge = $expiredCount ? 'badge-danger' : 'badge-info'; $statusLabel = 'Đã check-in '.$usedCount.'/'.$booking->tickets->count().' ghế'.($expiredCount ? ' · '.$expiredCount.' quá hạn' : ''); }
                                elseif ($cancelledCount === $booking->tickets->count()) { $statusBadge = 'badge-danger'; $statusLabel = $expiredCount ? 'Quá hạn' : 'Đã hủy'; }
                                elseif ($readyCount === 0) { $statusLabel = 'Chưa sẵn sàng check-in'; }
                                $bookingTotal = $booking->tickets->sum(fn ($item) => (float) ($item->payment?->amount ?? $item->price));
                                $foodOrders = $booking->tickets->flatMap(fn ($item) => $item->foodOrders)->unique('id');
                                $pickupOrders = $foodOrders->where('status', 'completed')->values();
                                $pickupDetails = $pickupOrders->map(fn ($order) => [
                                    'code' => $order->pickup_code ?: 'F'.$order->id,
                                    'received' => (bool) $order->fulfilled_at,
                                    'fulfilled' => $order->fulfilledQuantity(),
                                    'total' => $order->totalQuantity(),
                                ])->values();
                            @endphp
                            <div class="ticket-card">
                                <div class="ticket-poster">
                                    <img src="{{ $poster }}" alt="{{ $movie->title ?? '' }}">
                                </div>
                                <div class="ticket-info">
                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                                        <div>
                                            <div class="ticket-meta" style="font-weight:700;color:#1f2937;">
                                                {{ \Carbon\Carbon::parse($ticket->showtime->start_time)->format('d/m/Y · H:i') }}
                                            </div>
                                            <h3>{{ $movie->title ?? 'Vé phim' }}</h3>
                                            <div class="ticket-meta">Rạp: {{ $ticket->showtime->room->cinema->name ?? '' }}</div>
                                            <div class="ticket-meta">Mã check-in chung: <strong>{{ $booking->checkin_code }}</strong></div>
                                            <div class="ticket-meta">{{ $booking->tickets->count() }} vé · Ghế: <strong>{{ $seatLabels }}</strong></div>
                                            <div class="ticket-meta">Tổng tiền vé: {{ number_format($bookingTotal, 0, ',', '.') }}đ</div>
                                            <div class="booking-seats" aria-label="Trạng thái từng ghế">
                                                @foreach($booking->tickets as $bookingTicket)
                                                    <span class="booking-seat {{ $bookingTicket->status === 'used' ? 'is-used' : ($bookingTicket->status === 'cancelled' ? 'is-cancelled' : '') }}">
                                                        {{ $bookingTicket->seat?->seat_row }}{{ $bookingTicket->seat?->seat_number }} · {{ $bookingTicket->status === 'used' ? 'Đã check-in' : ($bookingTicket->cancellation_reason === \App\Models\Ticket::EXPIRED_REASON ? 'Quá hạn' : ($bookingTicket->status === 'cancelled' ? 'Đã hủy' : ($bookingTicket->payment?->status !== 'paid' ? 'Chưa thanh toán' : ($bookingTicket->status === 'confirmed' ? 'Chưa check-in' : 'Chờ xác nhận')))) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            @foreach($foodOrders as $foodOrder)
                                                <div class="ticket-meta">Bắp nước · mã nhận {{ $foodOrder->pickup_code ?? '#'.$foodOrder->id }} · {{ $foodOrder->fulfilled_at ? 'Đã nhận đủ' : ($foodOrder->fulfilledQuantity() ? 'Đã nhận '.$foodOrder->fulfilledQuantity().'/'.$foodOrder->totalQuantity().' phần' : 'Chưa nhận') }}</div>
                                            @endforeach
                                        </div>
                                        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; min-width:140px;">
                                            <span class="badge-status {{ $statusBadge }}">{{ $statusLabel }}</span>
                                            <button class="btn-outline btn-detail"
                                                data-title="{{ $movie->title ?? 'Vé phim' }}"
                                                data-format="{{ $movie->format ?? '2D' }}"
                                                data-age="{{ $movie->age_rating ?? 'T13' }}"
                                                data-cinema="{{ $ticket->showtime->room->cinema->name ?? '' }}"
                                                data-room="{{ $ticket->showtime->room->name ?? '' }}"
                                                data-time="{{ \Carbon\Carbon::parse($ticket->showtime->start_time)->format('H:i - l, d/m/Y') }}"
                                                data-seat="{{ $seatLabels }}"
                                                data-code="{{ $booking->checkin_code }}"
                                                data-qr="{{ $booking->qr_data_uri }}"
                                                data-note="{{ $readyCount ? 'Xuất trình mã QR này để check-in các ghế còn lại.' : 'Lượt đặt không còn ghế nào sẵn sàng check-in.' }}"
                                                data-poster="{{ $poster }}"
                                                data-price="{{ number_format($bookingTotal, 0, ',', '.') }}đ"
                                                data-food-orders="{{ $pickupDetails->toJson() }}"
                                            >Xem mã check-in</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{ $bookings->onEachSide(1)->links('customer.tickets.pagination') }}
                @endif
            </div>
    </div>
</div>

<div class="modal-backdrop" id="ticketModal">
    <div class="modal-card">
        <button class="modal-close" id="modalClose">×</button>
        <div class="modal-poster"><img id="modalPoster" src="" alt="Poster"></div>
        <div class="modal-title" id="modalTitle"></div>
        <div class="modal-sub" id="modalFormat"></div>
        <div class="modal-info" id="modalCinema"></div>
        <div class="modal-info" id="modalTime"></div>
        <div class="modal-qr"><img id="modalQr" src="" alt="QR Code"></div>
        <div class="modal-row"><span>Mã check-in lượt đặt</span><strong id="modalCode"></strong></div>
        <section class="modal-food-orders" id="modalFoodOrders" aria-label="Mã nhận bắp nước" hidden>
            <div class="modal-food-orders__heading">Mã nhận bắp nước</div>
            <div class="modal-food-orders__list" id="modalFoodOrdersList"></div>
            <p>Xuất trình mã nhận tại quầy bắp nước.</p>
        </section>
        <div class="modal-row"><span>Ghế</span><strong id="modalSeat"></strong></div>
        <div class="modal-row"><span>Tổng tiền vé</span><strong id="modalPrice"></strong></div>
        <div class="modal-footer">Vui lòng xuất trình mã QR này khi vào rạp.</div>
    </div>
</div>


</x-app-layout>
