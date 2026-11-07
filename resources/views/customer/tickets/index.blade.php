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

                @if($tickets->isEmpty())
                    <div class="empty">Bạn chưa có vé nào.</div>
                @else
                    <div class="ticket-list">
                        @foreach($tickets as $ticket)
                            @php
                                $movie = $ticket->showtime->movie ?? null;
                                $poster = $movie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg');
                                $payStatus = $ticket->payment->status ?? 'unpaid';
                                $statusBadge = 'badge-info';
                                $statusLabel = ucfirst($ticket->status ?? 'N/A');
                                if ($ticket->status === 'used') { $statusBadge = 'badge-success'; $statusLabel = 'Đã hoàn thành'; }
                                if ($ticket->status === 'cancelled') { $statusBadge = 'badge-danger'; $statusLabel = 'Đã hủy'; }
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
                                            <div class="ticket-meta">Mã đặt vé: GX{{ str_pad($ticket->id, 6, '0', STR_PAD_LEFT) }}</div>
                                            <div class="ticket-meta">Ghế: {{ $ticket->seat->seat_row ?? '' }}{{ $ticket->seat->seat_number ?? '' }}</div>
                                            <div class="ticket-meta">Tổng tiền: {{ number_format(($ticket->payment->amount ?? $ticket->price), 0, ',', '.') }}đ</div>
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
                                                data-seat="{{ $ticket->seat->seat_row ?? '' }}{{ $ticket->seat->seat_number ?? '' }}"
                                                data-code="{{ $ticket->qr_code ?? ('GX'.str_pad($ticket->id,6,'0',STR_PAD_LEFT)) }}"
                                                data-poster="{{ $poster }}"
                                                data-price="{{ number_format(($ticket->payment->amount ?? $ticket->price), 0, ',', '.') }}đ"
                                            >Xem chi tiết</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{ $tickets->onEachSide(1)->links('customer.tickets.pagination') }}
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
        <div class="modal-row"><span>Mã vé</span><strong id="modalCode"></strong></div>
        <div class="modal-row"><span>Ghế</span><strong id="modalSeat"></strong></div>
        <div class="modal-row"><span>Giá</span><strong id="modalPrice"></strong></div>
        <div class="modal-footer">Vui lòng xuất trình mã QR này khi vào rạp.</div>
    </div>
</div>


</x-app-layout>
