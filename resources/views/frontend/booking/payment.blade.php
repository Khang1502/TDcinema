@php
    use Carbon\Carbon;
    $movie = $showtime->movie;
    $posterUrl = $movie->posterUrl();
    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $grandTotal = $ticketTotal + $comboTotal;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>Thanh toán - {{ $movie->title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="stylesheet" href="{{ asset('assets/css/booking/payment.css') }}?v={{ filemtime(public_path('assets/css/booking/payment.css')) }}">
</head>
<body data-hold-expires-at="{{ $holdExpiresAt ?? '' }}"
      data-expired-url="{{ route('booking.checkout', $showtime) }}"
      data-base-total="{{ $grandTotal }}"
      data-promo-url="{{ route('booking.promo.apply') }}"
      data-promo-remove-url="{{ route('booking.promo.remove') }}"
      data-showtime-id="{{ $showtime->id }}"
      data-momo-demo="{{ $momoDemo ? 'true' : 'false' }}"
      data-member-discount="{{ $memberDiscount }}"
      data-member-level="{{ $memberLevel?->name }}"
      data-earn-unit="{{ $membershipSettings->earn_unit }}"
      data-membership-enabled="{{ $membershipSettings->is_enabled ? 'true' : 'false' }}"
      data-applied-code="{{ $appliedPromo?->code ?? '' }}"
      data-promo-discount="{{ $promoDiscount }}"
      data-currency-suffix=" đ"
      data-msg-enter-code="Vui lòng nhập mã."
      data-msg-apply-fail="Áp dụng mã thất bại."
      data-msg-apply-success="Áp dụng mã thành công."
      data-msg-apply-error="Có lỗi khi áp dụng mã."
      data-msg-agree="Vui lòng đồng ý điều khoản."
      data-msg-timeout="Hết thời gian">
    <div class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('home') }}" class="topbar-logo">
                <img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt="TD Cinema">
                <span>TD Cinema</span>
            </a>
            <a href="{{ route('home') }}" class="topbar-cancel">Hủy giao dịch ×</a>
        </div>
    </div>

    <div class="page">
        
        <div class="progress">
            <div class="step done">1. Chọn phim / Rạp / Suất</div>
            <div class="step done">2. Chọn ghế</div>
            <div class="step done">3. Combo</div>
            <div class="step active">4. Thanh toán</div>
            <div class="step">5. Xác nhận</div>
        </div>

        <div class="layout">
            <div class="card">
                <section class="promotion-panel" aria-labelledby="promotionTitle">
                    <div class="promotion-heading">
                        <span class="promotion-icon" aria-hidden="true">%</span>
                        <div><h2 id="promotionTitle">Chọn ưu đãi cho đơn của bạn</h2><p>Chạm “Áp dụng” để giảm tiền ngay. Mỗi đơn sử dụng một mã.</p></div>
                    </div>
                    @if($memberDiscount > 0)<p class="membership-payment-note">Quyền lợi {{ $memberLevel->name }}: giảm {{ number_format($memberDiscount,0,',','.') }} đ tự động. Khi chọn mã khuyến mãi, mức giảm của mã sẽ thay thế ưu đãi theo hạng.</p>@endif
                    <form id="promoForm" class="promo-code-form">
                        <label for="promoCode">Bạn có mã khuyến mãi?</label>
                        <div class="promo-input-group">
                            <input type="text" id="promoCode" value="{{ $appliedPromo?->code ?? '' }}" placeholder="Nhập mã khuyến mãi" maxlength="50" autocomplete="off" spellcheck="false" aria-describedby="promoHint promoFeedback">
                            <button type="submit" class="btn-apply" id="btnApply">Áp dụng</button>
                        </div>
                        <p id="promoHint" class="promo-note">Mỗi đơn thanh toán áp dụng một mã khuyến mãi.</p>
                    </form>
                    <p id="promoFeedback" class="promo-feedback" role="status" aria-live="polite" hidden></p>
                    <div id="appliedVoucher" class="applied-voucher" @if(!$appliedPromo) hidden @endif>
                        <span class="applied-voucher-check" aria-hidden="true">✓</span>
                        <div><strong id="appliedPromoName">{{ $appliedPromo?->name }}</strong><span><b id="appliedPromoCode">{{ $appliedPromo?->code }}</b> · Giảm <b id="appliedPromoDiscount">{{ number_format($promoDiscount, 0, ',', '.') }} đ</b></span></div>
                        <button type="button" id="removePromo" aria-label="Bỏ mã khuyến mãi đang áp dụng">Bỏ mã</button>
                    </div>
                    <details class="voucher-picker" open>
                        <summary><span>Khuyến mãi khả dụng <b>{{ $availablePromos->count() }}</b></span><span class="voucher-chevron" aria-hidden="true"></span></summary>
                        @if($availablePromos->count() > 3)<label class="voucher-search"><span>Tìm mã ưu đãi</span><input id="voucherSearch" type="search" placeholder="Nhập tên hoặc mã khuyến mãi"></label>@endif
                        <div id="promoList" class="promo-list">
                            @forelse(($availablePromos ?? collect()) as $promo)
                                <div class="voucher-card" data-promo-search="{{ $promo->name }} {{ $promo->code }}">
                                    <div class="voucher-value"><small>GIẢM</small><strong>{{ $promo->discount_label }}</strong></div>
                                    <div class="voucher-info">@if($loop->first)<span class="voucher-best">Tiết kiệm nhất cho đơn này</span>@endif<h3>{{ $promo->name }}</h3><code>{{ $promo->code }}</code><p>Hạn dùng {{ $promo->end_date->format('d/m/Y') }}</p><p class="voucher-saving">Giảm {{ number_format($promo->discountFor($grandTotal), 0, ',', '.') }} đ cho đơn này</p></div>
                                    <button type="button" class="voucher-use" data-code="{{ $promo->code }}">Áp dụng</button>
                                </div>
                            @empty
                                <div class="voucher-empty"><span aria-hidden="true">%</span><div><strong>Chưa có ưu đãi khả dụng</strong><p>Nếu bạn có mã khuyến mãi, hãy nhập vào ô phía trên.</p></div></div>
                            @endforelse
                        </div>
                        <p class="voucher-no-results" id="voucherNoResults" hidden>Không tìm thấy ưu đãi. Thử tên hoặc mã khác.</p>
                    </details>
                    <div class="stars-card">
                        <span class="stars-icon" aria-hidden="true">★</span>
                        <div><strong>Điểm thành viên Stars <b>{{ number_format(auth()->user()->points ?? 0, 0, ',', '.') }}</b></strong><p>Stars dùng để xét cấp bậc. Quyền lợi giảm giá theo hạng được áp dụng tự động khi không dùng mã.</p></div>
                        <a href="{{ route('customer.membership') }}#member-benefits" target="_blank" rel="noopener">Xem quyền lợi <span aria-hidden="true">↗</span></a>
                    </div>
                </section>

                <div class="divider divider-lg"></div>
                <div class="section-title section-title-tight">Phương thức thanh toán</div>
                @if($momoDemo)
                    <p class="payment-demo-note">MoMo đang ở chế độ mô phỏng cho đồ án. Không có tiền được trừ từ ví của bạn.</p>
                @endif
                <div class="method-list" id="methodList">
                    @forelse($paymentMethods as $pm)
                        <label class="method">
                            <input type="radio" name="payment_method" value="{{ $pm['key'] }}" data-demo="{{ $pm['demo'] ? 'true' : 'false' }}">
                            <div>
                                <div class="label">{{ $pm['label'] }} @if($pm['badge'])<span class="method-highlight-badge">{{ $pm['badge'] }}</span>@endif @if($pm['demo'] ?? false)<span class="method-demo-badge">Mô phỏng</span>@endif</div>
                                @if($pm['description'])<p class="method-description">{{ $pm['description'] }}</p>@endif
                                @if($pm['instructions'])<p class="method-instructions">{{ $pm['instructions'] }}</p>@endif
                            </div>
                        </label>
                    @empty
                        <p class="payment-methods-empty" role="status">Hiện chưa có phương thức thanh toán khả dụng. Vui lòng thử lại sau hoặc liên hệ rạp để được hỗ trợ.</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="summary-bar"></div>
                <div class="timer">Thời gian giữ ghế: <span id="countdown">--:--</span></div>
                <div class="summary-head">
                    <div><img src="{{ $posterUrl }}" alt="{{ $movie->title }}" class="summary-poster"></div>
                    <div>
                        <div class="summary-title">{{ $movie->title }}</div>
                        <div class="summary-meta">{{ $movie->format ?? '2D Phụ Đề' }} – <span class="badge">{{ $ageBadge }}</span></div>
                    </div>
                </div>
                <div class="summary-meta summary-meta-strong">{{ $showtime->room->cinema->name ?? '-' }} - {{ strtoupper($showtime->room->name ?? '-') }}</div>
                <div class="summary-meta">Suất: <strong>{{ $startTime }}</strong> - {{ $startDate }}</div>
                <div class="summary-meta">Ghế: {{ $selectedSeats->isNotEmpty() ? $selectedSeats->implode(', ') : 'Chưa chọn ghế' }}</div>

                <div class="divider"></div>
                <div class="row"><span>{{ $selectedSeats->count() }}x Vé</span><span id="ticketTotal">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                @if($comboTotal > 0)
                    <div class="row"><span>Combo</span><span id="comboTotal">{{ number_format($comboTotal, 0, ',', '.') }} đ</span></div>
                @endif
                <div class="row promo-row" id="promoRow"><span><span id="summaryDiscountLabel">{{ $appliedPromo ? 'Khuyến mãi' : 'Ưu đãi thành viên' }}</span> <b id="summaryPromoCode"></b></span><span id="promoValue">-{{ number_format($promoDiscount, 0, ',', '.') }} đ</span></div>
                @if($membershipSettings->is_enabled)<p class="membership-points-preview">Hoàn tất đơn này, bạn nhận <b id="pointsEstimate">{{ floor(max(0,$grandTotal - $promoDiscount) / max(1,$membershipSettings->earn_unit)) }}</b> Stars.</p>@endif
                <div class="divider"></div>
                <div class="total"><span>Tổng thanh toán</span><span id="grandTotal">{{ number_format(max(0, $grandTotal - $promoDiscount), 0, ',', '.') }} đ</span></div>
                <form id="payForm" method="POST" action="{{ route('booking.payment.process', $showtime) }}">
                    @csrf
                    <input type="hidden" name="seats" id="paySeats" value="{{ $selectedSeats->implode(',') }}">
                    <input type="hidden" name="combo_total" id="payCombo" value="{{ $comboTotal }}">
                    <input type="hidden" name="promo_code" id="payPromo" value="{{ $appliedPromo?->code }}">
                    <input type="hidden" name="payment_method" id="payMethod" value="">
                    <input type="hidden" name="total" id="payTotal" value="{{ $grandTotal }}">
                    <div class="actions">
                        <a href="{{ route('booking.combo', $showtime) }}" class="btn-action ghost">Quay lại</a>
                        <button type="button" class="btn-action primary" id="btnPay" disabled>Thanh toán</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="overlay" id="confirmOverlay">
        <div class="modal">
            <button class="close-modal" type="button" id="closeModal">×</button>
            <h3>Thông tin đặt vé</h3>
            <div class="row-line"><span>Phim</span><strong>{{ $movie->title }}</strong></div>
            <div class="row-line"><span>Định dạng</span><span>{{ $movie->format ?? '2D Phụ Đề' }} – {{ $ageBadge }}</span></div>
            <div class="row-line"><span>Rạp</span><span>{{ $showtime->room->cinema->name ?? '-' }}</span></div>
            <div class="row-line"><span>Phòng</span><span>{{ strtoupper($showtime->room->name ?? '-') }}</span></div>
            <div class="row-line"><span>Suất</span><span>{{ $startTime }} - {{ $startDate }}</span></div>
            <div class="row-line"><span>Ghế</span><span id="modalSeats">{{ $selectedSeats->implode(', ') ?: 'N/A' }}</span></div>
            <div class="divider"></div>
            <div class="row-line" id="modalPromoRow" hidden><span><span id="modalDiscountLabel">Khuyến mãi</span> <b id="modalPromoCode"></b></span><strong id="modalPromoValue"></strong></div>
            <div class="row-line"><span>Tổng tạm tính</span><strong id="modalTotal">{{ number_format($grandTotal,0,',','.') }} đ</strong></div>
            <p class="payment-demo-note" id="modalDemoNote" hidden>Đây là thanh toán mô phỏng cho đồ án. Không có tiền được trừ từ tài khoản của bạn.</p>
            <div class="agree">
                <input type="checkbox" id="agreeConfirm">
                <label for="agreeConfirm">Tôi xác nhận thông tin đặt vé và đồng ý với điều khoản & chia sẻ thông tin.</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-action ghost" id="cancelModal">Kiểm tra lại</button>
                <button type="button" class="btn-action primary" id="confirmPay">Đồng ý & Thanh toán</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/booking/payment.js') }}?v={{ filemtime(public_path('assets/js/booking/payment.js')) }}" defer></script>
@include('shared.notifications')
</body>
</html>
