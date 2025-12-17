<x-app-layout>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');
    .history-page{font-family:'Be Vietnam Pro',sans-serif;background:linear-gradient(180deg,#f7f9ff 0%,#f2f4f8 100%);min-height:100vh;}
    .history-container{max-width:1100px;margin:0 auto;padding:18px 12px 36px;}
    .history-grid{display:grid;grid-template-columns:270px 1fr;gap:14px;align-items:start;}
    .card{background:#fff;border-radius:14px;box-shadow:0 8px 30px rgba(15,23,42,0.05);border:1px solid #e5e7eb;}
    .side-header{padding:16px;border-bottom:1px solid #eef1f5;display:flex;gap:12px;align-items:center;}
    .avatar{width:52px;height:52px;border-radius:50%;background:#e5e7eb;display:grid;place-items:center;font-weight:800;color:#0b2c3d;}
    .side-name{font-weight:700;font-size:16px;color:#1f2937;margin:0;}
    .side-tier{font-size:13px;color:#6b7280;margin:2px 0 0;}
    .side-menu{list-style:none;padding:0;margin:0;}
    .side-menu li a{display:flex;align-items:center;gap:10px;padding:10px 16px;color:#1f2937;font-weight:600;border-radius:10px;text-decoration:none;}
    .side-menu li a.active{background:#e8f2ff;color:#1d4ed8;}
    .side-menu li a:hover{background:#f3f4f6;}
    .logout-link{display:block;padding:12px 16px;color:#dc2626;font-weight:600;text-decoration:none;}
    .main-card{padding:18px;}
    .section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
    .section-title h2{margin:0;font-size:17px;font-weight:800;color:#111827;}
    .badge-year{background:#e0f2ff;color:#0b70e0;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;}
    .progress-bar{height:8px;background:#e5e7eb;border-radius:999px;overflow:hidden;}
    .progress-fill{height:100%;background:#0ea5e9;}
    .tab-bar{display:flex;gap:18px;margin:12px 0;}
    .tab-bar a{font-weight:700;color:#64748b;text-decoration:none;position:relative;padding-bottom:6px;}
    .tab-bar a.active{color:#1d4ed8;}
    .tab-bar a.active::after{content:'';position:absolute;left:0;bottom:0;width:100%;height:3px;background:#1d4ed8;border-radius:6px;}
    .ticket-list{display:flex;flex-direction:column;gap:12px;margin-top:12px;}
    .ticket-card{border:1px solid #e5e7eb;border-radius:12px;padding:12px;display:grid;grid-template-columns:110px 1fr;gap:12px;align-items:center;background:#fff;}
    .ticket-poster{width:110px;height:150px;border-radius:10px;background:#f8fafc;display:grid;place-items:center;overflow:hidden;}
    .ticket-poster img{width:100%;height:100%;object-fit:cover;}
    .ticket-info h3{margin:0;font-size:16px;font-weight:800;color:#0f172a;}
    .ticket-meta{font-size:13px;color:#475569;margin:4px 0;}
    .ticket-meta strong{color:#0f172a;}
    .ticket-row{display:flex;align-items:center;gap:10px;font-size:13px;color:#334155;}
    .badge-status{padding:5px 10px;border-radius:999px;font-weight:700;font-size:12px;}
    .badge-success{background:#e7f9ee;color:#0f9d58;border:1px solid #c7f0d9;}
    .badge-danger{background:#fdecec;color:#dc2626;border:1px solid #f5c2c0;}
    .badge-info{background:#e0e7ff;color:#4338ca;border:1px solid #c7d2fe;}
    .btn-outline{padding:9px 12px;border:1px solid #d1d5db;border-radius:10px;background:#fff;font-weight:700;color:#111827;cursor:pointer;}
    .pagination{display:flex;align-items:center;gap:6px;margin-top:10px;}
    .pagination a,.pagination span{padding:8px 10px;border-radius:8px;border:1px solid #e5e7eb;font-size:13px;color:#334155;text-decoration:none;}
    .pagination .active{background:#2563eb;color:#fff;border-color:#2563eb;}
    .empty{padding:20px;text-align:center;color:#6b7280;}
    /* Modal */
    .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:100;}
    .modal-card{background:#fff;border-radius:16px;max-width:360px;width:90%;padding:18px;box-shadow:0 20px 60px rgba(0,0,0,.2);position:relative;}
    .modal-close{position:absolute;right:12px;top:12px;border:none;background:transparent;font-size:18px;cursor:pointer;color:#6b7280;}
    .modal-poster{width:120px;height:180px;border-radius:12px;overflow:hidden;margin:0 auto 10px;}
    .modal-poster img{width:100%;height:100%;object-fit:cover;}
    .modal-title{font-size:17px;font-weight:800;text-align:center;color:#0f172a;margin:6px 0;}
    .modal-sub{font-size:13px;text-align:center;color:#475569;margin:4px 0;}
    .modal-info{font-size:13px;color:#0f172a;text-align:center;margin:6px 0;}
    .modal-qr{display:grid;place-items:center;margin:12px 0;}
    .modal-qr img{width:160px;height:160px;object-fit:contain;}
    .modal-row{display:flex;justify-content:space-between;font-size:13px;color:#334155;margin:4px 0;}
    .modal-row strong{color:#0f172a;}
    .modal-footer{font-size:12px;color:#6b7280;text-align:center;margin-top:8px;}
    /* Footer */
    .footer { background:#232633; color:#fff; padding:32px 0 20px; font-size:14px; margin-top:32px; }
    .footer-inner { max-width:1200px; margin:0 auto; padding:0 16px; display:grid; grid-template-columns: minmax(0,2.2fr) repeat(3,minmax(0,1fr)); gap:24px; align-items:flex-start; }
    .footer-brand { display:flex; align-items:flex-start; gap:12px; }
    .footer-logo img { width:110px; height:auto; display:block; }
    .footer-company-name { font-weight:700; margin-bottom:6px; }
    .footer-company p { margin:0; color:rgba(255,255,255,.7); }
    .footer h4 { text-transform:uppercase; font-size:13px; margin-bottom:12px; font-weight:700; }
    .footer-col a { display:block; margin-bottom:8px; color:rgba(255,255,255,.7); text-decoration:none; }
    .footer-col a:hover { color:#fff; text-decoration:underline; }
    .footer-contact-item { display:flex; align-items:center; margin-bottom:8px; color:rgba(255,255,255,.75); }
    .footer-icon { width:18px; height:18px; margin-right:10px; flex-shrink:0; }
    .footer-social { margin-top:10px; display:flex; gap:12px; }
    .footer-social-icon { width:26px; height:26px; display:block; opacity:.85; transition:.2s; }
    .footer-social-icon:hover { opacity:1; transform:scale(1.08); }
    .footer-bottom { border-top:1px solid rgba(255,255,255,.1); margin-top:20px; padding-top:12px; text-align:center; font-size:12px; color:rgba(255,255,255,.5); }
    @media(max-width:960px){.history-grid{grid-template-columns:1fr;}}
</style>
<div class="history-page">
    <div class="history-container">
        <div class="history-grid">
            <div class="card">
                <div class="side-header">
                    <div class="avatar">
                        @if(auth()->user()->avatar ?? false)
                            <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                        @else
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        @endif
                    </div>
                    <div>
                        <p class="side-name">{{ auth()->user()->name ?? 'Người dùng' }}</p>
                        <p class="side-tier">Thành viên</p>
                    </div>
                </div>
                <ul class="side-menu" style="padding:12px;">
                    <li><a href="{{ route('profile.edit') }}">Thông tin cá nhân</a></li>
                    <li><a href="{{ route('customer.tickets') }}" class="active">Lịch sử đặt vé</a></li>
                    <li><a href="#">Thông báo</a></li>
                    <li><a href="#">Quà tặng</a></li>
                    <li><a href="#">Chính sách</a></li>
                </ul>
                <div style="padding:0 12px 14px;">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-link">Đăng xuất</button>
                    </form>
                </div>
            </div>

            <div class="card main-card">
                <div style="background:linear-gradient(90deg,#f0f4ff,#e9ecf5);border-radius:12px;padding:12px 16px;margin-bottom:16px;border:1px solid #e5e7eb;">
                    <div class="section-title" style="margin-bottom:8px;">
                        <h2 style="font-size:16px;">Tổng chi tiêu 2025</h2>
                        <span class="badge-year">{{ number_format($yearSpent ?? 0, 0, ',', '.') }} đ</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; font-size:13px; color:#374151;">
                        <span>0 đ</span>
                        <div class="progress-bar" style="flex:1; background:#e5e7eb;">
                            <div class="progress-fill" style="width: {{ ($progress ?? 0) * 100 }}%; background:#2563eb;"></div>
                        </div>
                        <span>{{ number_format($tier2 ?? 4000000, 0, ',', '.') }} đ</span>
                    </div>
                </div>

                <div class="section-title">
                    <h2>Lịch sử đặt vé</h2>
                </div>
                <div class="tab-bar">
                    <a href="#" class="active">Chi tiết lịch sử đặt vé</a>
                    <a href="#" style="pointer-events:none;opacity:.5;">Lịch sử giao dịch G-Star</a>
                    <a href="#" style="pointer-events:none;opacity:.5;">Lịch sử giao dịch Quà tặng</a>
                </div>

                @if($tickets->isEmpty())
                    <div class="empty">Bạn chưa có vé nào.</div>
                @else
                    <div class="ticket-list">
                        @foreach($tickets as $ticket)
                            @php
                                $movie = $ticket->showtime->movie ?? null;
                                $poster = $movie?->poster_path ?? '';
                                if ($poster && !\Illuminate\Support\Str::startsWith($poster, ['http://','https://'])) {
                                    $poster = asset('storage/' . ltrim($poster, '/'));
                                }
                                $poster = $poster ?: asset('https://via.placeholder.com/110x150?text=Poster');
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
                    <div class="pagination">
                        {{ $tickets->links() }}
                    </div>
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

<script>
    document.querySelectorAll('.btn-detail').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('ticketModal');
            document.getElementById('modalTitle').textContent = btn.dataset.title || '';
            document.getElementById('modalFormat').textContent = (btn.dataset.format || '2D') + ' · ' + (btn.dataset.age || 'T13');
            document.getElementById('modalCinema').textContent = btn.dataset.cinema || '';
            document.getElementById('modalTime').textContent = btn.dataset.time || '';
            document.getElementById('modalSeat').textContent = btn.dataset.seat || '';
            document.getElementById('modalCode').textContent = btn.dataset.code || '';
            document.getElementById('modalPrice').textContent = btn.dataset.price || '';
            document.getElementById('modalPoster').src = btn.dataset.poster || '';
            const qrData = encodeURIComponent(btn.dataset.code || '');
            document.getElementById('modalQr').src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${qrData}`;
            modal.style.display = 'flex';
        });
    });
    document.getElementById('modalClose')?.addEventListener('click', () => {
        document.getElementById('ticketModal').style.display = 'none';
    });
    document.getElementById('ticketModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'ticketModal') {
            e.currentTarget.style.display = 'none';
        }
    });
</script>

<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema">
            </div>
            <div class="footer-company">
                <p class="footer-company-name">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                <p>180 Cao Lỗ, phường Chánh Hưng, tp. Hồ Chí Minh</p>
            </div>
        </div>
        <div class="footer-col">
            <h4>Góc điện ảnh</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>
        <div class="footer-col">
            <h4>Góc điện ảnh</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>
        <div class="footer-col footer-contact">
            <h4>Liên hệ với chúng tôi</h4>
            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" class="footer-icon">
                180 Cao Lỗ, phường Chánh Hưng, tp.HCM
            </div>
            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" class="footer-icon">
                10971395075
            </div>
            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" class="footer-icon">
                daikha2521@gmail.com
            </div>
            <div class="footer-social">
                <a href="#"><img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}"
                     alt="Facebook" class="footer-social-icon"></a>
                <a href="#"><img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}"
                     alt="Instagram" class="footer-social-icon"></a>
                <a href="#"><img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}"
                     alt="Youtube" class="footer-social-icon"></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        © 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.
    </div>
</footer>
</x-app-layout>
