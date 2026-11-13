<article class="offer-card" data-promotion-id="{{ $promo->id }}">
    <div class="offer-art">
        @if($image = $promo->imageUrl())<img src="{{ $image }}" alt="{{ $promo->name }}" loading="lazy" decoding="async">@else<div class="offer-art-placeholder"><span>TD CINEMA</span><strong>{{ $promo->discount_label }}</strong><small>Cho trải nghiệm điện ảnh của bạn</small></div>@endif
        <span class="offer-badge">{{ $promo->display_status === 'upcoming' ? 'Sắp diễn ra' : 'Đang áp dụng' }}</span>
    </div>
    <div class="offer-content">
        <p class="offer-value">Giảm {{ $promo->discount_label }}</p>
        <h3>{{ $promo->name }}</h3>
        <p class="offer-dates">{{ $promo->start_date->format('d/m/Y') }} – {{ $promo->end_date->format('d/m/Y') }}</p>
        <div class="offer-code"><code>{{ $promo->code }}</code><button type="button" data-copy-promo="{{ $promo->code }}" aria-label="Sao chép mã {{ $promo->code }}">Sao chép</button></div>
        @if($promo->display_status === 'active')<form method="POST" action="{{ route('promotions.select', $promo) }}" @guest data-promo-select-guest @endguest>@csrf<button class="offer-select" type="submit">Chọn ưu đãi & đặt vé <span aria-hidden="true">→</span></button></form>@else<p class="offer-coming">Có thể sử dụng từ {{ $promo->start_date->format('d/m/Y') }}</p>@endif
        <small>Mỗi đơn áp dụng một mã. Giảm tối đa bằng giá trị đơn.</small>
    </div>
</article>
