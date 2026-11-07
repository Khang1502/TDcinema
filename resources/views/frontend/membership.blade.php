<x-app-layout>
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/frontend/membership.css') }}?v={{ filemtime(public_path('assets/css/frontend/membership.css')) }}">@endpush
@guest
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/frontend/auth-modal.css') }}?v={{ filemtime(public_path('assets/css/frontend/auth-modal.css')) }}">@endpush
@push('scripts')<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>@endpush
@endguest
<div class="membership-page">
    <div class="membership-container">
        <section class="membership-hero">
            <div><span class="membership-eyebrow">CHƯƠNG TRÌNH THÀNH VIÊN · {{ $settings->name }}</span><h1>{{ $settings->title }}</h1><p>{{ $settings->subtitle }}</p>
                <div class="membership-hero-actions">@auth<a class="membership-button" href="#my-membership">Xem hạng của tôi <span>↓</span></a>@else<a class="membership-button" href="{{ route('register') }}">Tham gia ngay <span>→</span></a>@endauth<a class="membership-text-link" href="#member-benefits">Khám phá quyền lợi →</a></div>
            </div>
            <div class="membership-digital-card {{ $summary['level']?->color ?? 'blue' }}">
                <div class="membership-card-header"><span class="membership-card-brand"><img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt=""><b>TD CINEMA</b></span><span class="membership-card-program">{{ $settings->name }}</span></div>
                <div class="membership-card-body"><span class="membership-card-caption">THẺ THÀNH VIÊN</span><strong>{{ $summary['level']?->name ?? 'Đặc quyền điện ảnh' }}</strong><span class="membership-card-orbit" aria-hidden="true"><span>✦</span></span></div>
                <div class="membership-card-footer"><div><span>CHỦ THẺ</span><strong>{{ auth()->user()?->name ?? 'Dành cho người yêu điện ảnh' }}</strong></div><div class="membership-card-points"><span>ĐIỂM TÍCH LŨY</span><strong>{{ number_format($summary['points'] ?? 0, 0, ',', '.') }} <small>Stars</small></strong></div></div>
            </div>
        </section>
        @unless($settings->is_enabled)<div class="membership-notice">Chương trình đang tạm dừng tích điểm và giảm giá theo hạng. Stars hiện có của bạn vẫn được giữ.</div>@endunless
        <nav class="membership-section-nav" aria-label="Nội dung thành viên"><a href="{{ $summary ? '#my-membership' : '#how-it-works' }}">Tổng quan</a><a href="#member-benefits">Cấp bậc & quyền lợi</a>@if($memberGifts->isNotEmpty())<a href="#member-rewards">Voucher thành viên</a>@endif<a href="#terms">Chính sách</a>@if($summary)<a href="#stars-history">Lịch sử Stars</a>@endif</nav>
        @if($summary)
        <section class="membership-account" id="my-membership">
            <div class="membership-section-heading"><div><span class="membership-eyebrow">HÀNH TRÌNH CỦA BẠN</span><h2>Xin chào, {{ auth()->user()->name }}</h2></div><a class="membership-text-link" href="{{ route('profile.edit') }}">Thông tin tài khoản →</a></div>
            <div class="membership-account-grid"><div class="membership-current"><span class="membership-tier-tag {{ $summary['level']?->color ?? 'blue' }}">{{ $summary['level']?->name ?? 'Thành viên TD' }}</span><strong>{{ number_format($summary['points'],0,',','.') }} <small>Stars</small></strong><p>Điểm dùng để xét hạng thành viên</p></div>
                <div class="membership-upgrade">
                    <div><strong>{{ $summary['next'] ? 'Mỗi trải nghiệm đưa bạn gần hơn với hạng mới' : 'Bạn đã đạt hạng cao nhất' }}</strong><span>{{ $summary['next']?->name ?? 'Tiếp tục tích Stars cùng TD Cinema' }}</span></div>
                    <div class="membership-progress" role="progressbar" aria-label="Tiến độ lên hạng" aria-valuenow="{{ round($summary['progress']) }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $summary['progress'] }}%"></span></div>
                    <p>
                        @if($summary['next'])
                            Còn <b>{{ number_format($summary['remaining'],0,',','.') }} Stars</b> để mở khóa quyền lợi của {{ $summary['next']->name }}.
                        @else
                            Bạn đang tận hưởng toàn bộ quyền lợi theo hạng hiện tại.
                        @endif
                    </p>
                    <div class="membership-quick-benefits"><span>Giảm theo hạng <b>{{ $settings->is_enabled ? ($summary['level']?->discount_percent ?? 0) : 0 }}%</b></span><span>Tích điểm <b>{{ number_format($settings->earn_unit,0,',','.') }} đ = 1 Star</b></span></div>
                </div>
            </div>
        </section>
        @endif
        <section id="member-benefits">
            <div class="membership-section-heading"><div><span class="membership-eyebrow">THÊM STARS, THÊM ĐẶC QUYỀN</span><h2>Cấp bậc & quyền lợi</h2><p>Hạng tự động cập nhật theo số Stars của bạn. Các mốc được áp dụng trên toàn hệ thống.</p></div></div>
            <div class="membership-level-grid {{ $levels->count() === 5 ? 'membership-level-grid--five' : '' }}">
                @forelse($levels as $level)
                <article class="membership-level {{ $level->color }} {{ $summary && $summary['level']?->id === $level->id ? 'is-current' : '' }}" data-level-id="{{ $level->id }}">
                    <div class="membership-level-top"><span class="membership-emblem" aria-hidden="true">✦</span>@if($summary && $summary['level']?->id === $level->id)<span class="membership-current-badge">Hạng của bạn</span>@endif</div>
                    <h3>{{ $level->name }}</h3><div class="membership-threshold">{{ $level->min_points === 0 ? 'Chào đón mọi thành viên' : 'Từ '.number_format($level->min_points,0,',','.').' Stars' }}</div><p>{{ $level->description }}</p>
                    <div class="membership-discount"><strong>{{ $level->discount_percent }}<small>%</small></strong><span>Giảm trên đơn đặt vé<br>khi không dùng mã khuyến mãi</span></div>
                    <ul><li>{{ number_format($settings->earn_unit,0,',','.') }} đ thanh toán = 1 Star</li>@foreach($level->benefits ?? [] as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
                </article>
                @empty<div class="membership-empty">Chưa có cấp bậc được bật. Nội dung sẽ được cập nhật sớm.</div>@endforelse
            </div><p class="membership-footnote">Giảm giá theo hạng và mã khuyến mãi không cộng dồn. Khi áp dụng mã, hệ thống dùng mức giảm của mã cho đơn đó.</p>
        </section>
        @include('frontend.partials.member-rewards')
        <section class="membership-guide" id="how-it-works"><div class="membership-section-heading"><div><span class="membership-eyebrow">BẮT ĐẦU THẬT ĐƠN GIẢN</span><h2>Ba bước để nhận quyền lợi</h2></div></div><div class="membership-guide-grid"><div><b>01</b><h3>Tạo tài khoản</h3><p>Đăng ký miễn phí và bắt đầu với hạng thành viên đầu tiên.</p></div><div><b>02</b><h3>Đặt vé & tích Stars</h3><p>Mỗi {{ number_format($settings->earn_unit,0,',','.') }} đ thanh toán sau giảm giá được cộng 1 Star khi đơn hoàn tất.</p></div><div><b>03</b><h3>Nâng hạng tự động</h3><p>Đạt mốc Stars để mở khóa mức giảm giá và quyền lợi mới.</p></div></div></section>
        <section class="membership-terms" id="terms"><div class="membership-section-heading"><div><span class="membership-eyebrow">MINH BẠCH & DỄ THEO DÕI</span><h2>Chính sách thành viên</h2></div></div><ul>@foreach(preg_split('/\R/', $settings->terms ?? '') as $term)@if(trim($term) !== '')<li>{{ $term }}</li>@endif @endforeach</ul><div class="membership-links"><a href="{{ route('promotions.index') }}">Xem khuyến mãi khả dụng →</a><a href="{{ route('movies.index') }}">Chọn phim & đặt vé →</a></div></section>
        @if($summary)
        <section class="membership-history" id="stars-history"><div class="membership-section-heading"><div><span class="membership-eyebrow">MỌI THAY ĐỔI ĐỀU ĐƯỢC GHI NHẬN</span><h2>Lịch sử Stars</h2></div></div><div class="membership-history-list">@forelse($transactions as $entry)<article><span class="membership-entry-icon" aria-hidden="true">{{ $entry->points >= 0 ? '+' : '−' }}</span><div><strong>{{ $entry->description }}</strong><small>{{ $entry->created_at->format('H:i · d/m/Y') }} · Sau giao dịch: {{ number_format($entry->balance_after,0,',','.') }} Stars</small></div><b class="{{ $entry->points < 0 ? 'negative' : '' }}">{{ $entry->points > 0 ? '+' : '' }}{{ number_format($entry->points,0,',','.') }}</b></article>@empty<div class="membership-empty">Chưa có giao dịch Stars. Điểm sẽ được cộng sau đơn đặt vé tiếp theo của bạn.</div>@endforelse</div>{{ $transactions->onEachSide(1)->links() }}</section>
        @endif
    </div>
</div>
@guest @include('frontend.partials.auth-modal') @endguest
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/frontend/member-rewards.css') }}?v={{ filemtime(public_path('assets/css/frontend/member-rewards.css')) }}">@endpush
@include('shared.membership-color-assets')
</x-app-layout>
