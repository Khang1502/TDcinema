<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/profile.css') }}?v={{ filemtime(public_path('assets/css/frontend/profile.css')) }}">
    @endpush
    @push('scripts')
        <script src="{{ asset('assets/js/frontend/account.js') }}?v={{ filemtime(public_path('assets/js/frontend/account.js')) }}" defer></script>
    @endpush

    <div class="profile-page">
        <div class="profile-container">
            <div class="profile-grid">
                @include('profile.partials.account-sidebar', ['active' => $section])

                <main class="card main-card account-content">
                    @if($section === 'notifications')
                        <div class="account-heading">
                            <div><span>TRUNG TÂM TÀI KHOẢN</span><h1>Thông báo của bạn</h1><p>Theo dõi vé sắp chiếu và các cập nhật quan trọng.</p></div>
                            <form method="POST" action="{{ route('customer.notifications.read') }}">@csrf<button class="account-action">Đánh dấu đã đọc</button></form>
                        </div>
                        @if(session('status') === 'notifications-read')<div data-inline-notification class="account-success">Đã đánh dấu toàn bộ thông báo là đã đọc.</div>@endif
                        <div class="notification-list">
                            @forelse($upcomingTickets as $ticket)
                                <article class="notification-item {{ $readAt ? '' : 'unread' }}">
                                    <span class="notification-icon">🎟</span>
                                    <div><strong>Sắp đến giờ xem {{ $ticket->showtime->movie->title ?? 'phim' }}</strong><p>{{ \Carbon\Carbon::parse($ticket->showtime->start_time)->format('H:i · d/m/Y') }} tại {{ $ticket->showtime->room->cinema->name ?? 'TD Cinema' }}, ghế {{ $ticket->seat->seat_row ?? '' }}{{ $ticket->seat->seat_number ?? '' }}.</p></div>
                                    @unless($readAt)<span class="unread-dot" aria-label="Chưa đọc"></span>@endunless
                                </article>
                            @empty
                                <div class="account-empty"><strong>Chưa có thông báo mới</strong><p>Khi bạn đặt vé hoặc có suất chiếu sắp tới, thông tin sẽ xuất hiện tại đây.</p></div>
                            @endforelse
                        </div>
                    @elseif($section === 'gifts')
                        <div class="account-heading"><div><span>TD STARS · QUÀ THÀNH VIÊN</span><h1>Quà tặng thành viên</h1><p>Bạn có {{ number_format(auth()->user()->points ?? 0,0,',','.') }} Stars. Các voucher dưới đây dành riêng cho bạn; sử dụng không trừ điểm.</p></div><a class="account-action" href="{{ route('promotions.index') }}">Khuyến mãi chung →</a></div>
                        <div class="gift-grid">
                            @forelse($promotions as $promotion)
                                <article class="gift-card">
                                    @if($image=$promotion->imageUrl())<img class="member-gift-image" src="{{ $image }}" alt="{{ $promotion->name }}" loading="lazy">@endif
                                    <div class="gift-ticket-edge"></div><span class="gift-tag">{{ $promotion->is_member_gift ? 'QUÀ THÀNH VIÊN' : 'TD CINEMA' }}</span>
                                    <h2>{{ $promotion->name }}</h2><p>{{ $promotion->description ?: 'Ưu đãi áp dụng cho đơn hàng hợp lệ.' }}</p><p>Giảm {{ $promotion->discount_label }} @if($promotion->is_member_gift) · Từ {{ number_format($promotion->min_member_points,0,',','.') }} Stars @endif</p>
                                    <div class="gift-code"><code>{{ $promotion->code }}</code><button type="button" data-copy-code="{{ $promotion->code }}">Sao chép</button></div>
                                    <small>Hạn dùng {{ optional($promotion->end_date)->format('d/m/Y') }}</small>
                                    <form method="POST" action="{{ route('promotions.select', $promotion) }}">@csrf<button class="account-action" type="submit">Dùng voucher & đặt vé →</button></form>
                                </article>
                            @empty
                                <div class="account-empty"><strong>Chưa có quà thành viên phù hợp</strong><p>Tích lũy thêm Stars khi đặt vé để nhận các voucher dành riêng cho thành viên.</p><a href="{{ route('membership.index') }}">Xem cấp bậc & quyền lợi →</a></div>
                            @endforelse
                        </div>
                    @else
                        <div class="account-heading"><div><span>THÔNG TIN CẦN BIẾT</span><h1>Chính sách thành viên</h1><p>Các quy định áp dụng khi đặt vé và sử dụng dịch vụ TD Cinema.</p></div></div>
                        <section id="member-benefits" class="member-benefits" aria-labelledby="memberBenefitsTitle">
                            <h2 id="memberBenefitsTitle">Quyền lợi thành viên</h2>
                            <p>Quản lý vé, tìm ưu đãi và theo dõi điểm Stars trong tài khoản của bạn.</p>
                            <div class="member-benefit-grid">
                                <a href="{{ route('customer.tickets') }}"><span aria-hidden="true">🎟</span><strong>Vé của bạn</strong><p>Xem lịch sử đặt vé, thông tin suất chiếu và mã vé.</p></a>
                                <a href="{{ route('customer.gifts') }}"><span aria-hidden="true">%</span><strong>Quà tặng & ưu đãi</strong><p>Tìm mã khuyến mãi khả dụng để áp dụng khi thanh toán.</p></a>
                                <div><span aria-hidden="true">★</span><strong>{{ number_format(auth()->user()->points ?? 0, 0, ',', '.') }} điểm Stars</strong><p>Điểm trên tài khoản của bạn. Hiện chưa hỗ trợ đổi điểm để giảm giá đơn hàng.</p></div>
                            </div>
                        </section>
                        <div class="policy-list">
                            <details open><summary>Đổi hoặc hủy vé</summary><p>Vé đã thanh toán không hỗ trợ đổi suất tự động. Trường hợp suất chiếu bị hủy, TD Cinema sẽ liên hệ và hoàn tiền theo phương thức thanh toán ban đầu.</p></details>
                            <details><summary>Thời gian giữ ghế</summary><p>Ghế được giữ trong 10 phút xuyên suốt bước chọn combo và thanh toán. Khi hết thời gian, hệ thống tự động giải phóng ghế.</p></details>
                            <details><summary>Điểm Stars và ưu đãi</summary><p>Điểm Stars hiện được hiển thị trên tài khoản và chưa dùng để giảm giá khi thanh toán. Mỗi giao dịch áp dụng một mã khuyến mãi khả dụng.</p></details>
                            <details><summary>Bảo mật tài khoản</summary><p>Không chia sẻ mật khẩu hoặc mã vé QR. TD Cinema không yêu cầu khách hàng cung cấp mật khẩu qua điện thoại hay mạng xã hội.</p></details>
                        </div>
                    @endif
                </main>
            </div>
        </div>
    </div>
</x-app-layout>
