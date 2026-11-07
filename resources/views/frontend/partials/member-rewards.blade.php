@if($memberGifts->isNotEmpty())
<section class="membership-rewards" id="member-rewards">
    <div class="membership-section-heading"><div><span class="membership-eyebrow">ƯU ĐÃI DÀNH RIÊNG CHO BẠN</span><h2>Voucher thành viên</h2><p>Đủ Stars để mở khóa. Chọn một voucher cho mỗi đơn vé và combo.</p></div>@auth @if(auth()->user()->role === 'customer')<a class="membership-text-link" href="{{ route('customer.gifts') }}">Quà của tôi →</a>@endif @endauth</div>
    <div class="membership-reward-grid">
        @foreach($memberGifts as $gift)
        @php
            $eligible = auth()->check() && auth()->user()->role === 'customer' && (int)auth()->user()->points >= $gift->min_member_points;
            $rewardLevel = $levels->last(fn ($level) => $level->min_points <= $gift->min_member_points);
        @endphp
        <article class="membership-reward {{ $rewardLevel?->color ?? 'blue' }} {{ $eligible ? 'is-available' : 'is-locked' }}" data-member-gift="{{ $gift->code }}">
            <div class="membership-reward-face"><span>TD STARS · MEMBER VOUCHER</span><strong>{{ $gift->type === 'percent' ? $gift->value.'%' : number_format($gift->value,0,',','.') }}<small>{{ $gift->type === 'percent' ? 'tổng đơn' : 'đồng' }}</small></strong><b>{{ $eligible ? 'Đã mở khóa' : 'Từ '.number_format($gift->min_member_points,0,',','.').' Stars' }}</b></div>
            <div class="membership-reward-copy"><h3>{{ $gift->name }}</h3><p>{{ $gift->description }}</p><span class="membership-reward-date">Đến {{ $gift->end_date->format('d/m/Y') }} @if($gift->usage_limit !== null) · {{ number_format($gift->usage_limit) }} lượt còn lại toàn hệ thống @endif</span>
                @if($eligible)<form method="POST" action="{{ route('promotions.select',$gift) }}">@csrf<button class="membership-button" type="submit">Chọn voucher & đặt vé →</button></form>
                @elseif(auth()->guest())<a class="membership-reward-link" href="{{ route('login') }}">Đăng nhập để xem quyền lợi →</a>
                @else <span class="membership-reward-locked">{{ auth()->user()->role === 'customer' ? 'Còn '.number_format(max(0,$gift->min_member_points-(int)auth()->user()->points),0,',','.').' Stars để mở khóa' : 'Dành cho tài khoản khách hàng' }}</span> @endif
            </div>
        </article>
        @endforeach
    </div>
    <p class="membership-footnote">Dùng voucher không trừ Stars. Mức giảm của voucher thay thế giảm giá theo hạng; mức giảm tối đa bằng tổng đơn.</p>
</section>
@endif
