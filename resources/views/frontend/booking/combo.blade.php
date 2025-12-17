@php
    use Illuminate\Support\Str;
    use Carbon\Carbon;

    $movie = $showtime->movie;
    $poster = $movie->poster_path ?? '';
    $posterUrl = preg_match('/^https?:\/\//', $poster) ? $poster : asset('storage/' . ltrim($poster, '/'));
    $ageBadge = strtoupper($movie->age_rating ?? 'T13');
    $ticketCount = max($selectedSeats->count(), 0);
    $ticketPrice = (float) ($showtime->base_price ?? 0);
    $ticketTotal = $ticketCount * $ticketPrice;
    $countdownSeconds = ($holdMinutes ?? 10) * 60;
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn combo - {{ $movie->title }}</title>
    <style>
        :root { --p:#f26b38; --p-dark:#0b5394; --bg:#f7f8fb; --text:#0f172a; --muted:#6b7280; }
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        *{box-sizing:border-box;margin:0;padding:0;} body{font-family:'Be Vietnam Pro',sans-serif;background:var(--bg);color:var(--text);}
        a{text-decoration:none;color:inherit;}
        .topbar{background:#fff;border-bottom:1px solid #e5e7eb;box-shadow:0 6px 18px rgba(0,0,0,.06);padding:10px 0;}
        .topbar-inner{max-width:1240px;margin:0 auto;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;}
        .topbar-logo{display:flex;align-items:center;gap:10px;font-weight:800;color:#0b2c3d;}
        .topbar-logo img{height:40px;width:auto;}
        .topbar-cancel{color:#0b5394;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
        .page{max-width:1240px;margin:0 auto;padding:18px 20px 40px;}
        .progress{display:flex;justify-content:center;gap:8px;overflow:auto;padding:6px 0 16px;margin-bottom:16px;}
        .step{display:flex;align-items:center;gap:6px;padding:10px 12px;border:none;font-weight:800;font-size:14px;color:#8c99a8;min-width:150px;white-space:nowrap;background:transparent;position:relative;}
        .step::after{content:'';position:absolute;left:8px;right:8px;bottom:0;height:2px;background:#dfe3e8;border-radius:999px;}
        .step.done{color:var(--p-dark);} .step.done::after{background:var(--p-dark);}
        .step.active{color:var(--p);} .step.active::after{background:var(--p);}

        .layout{display:grid;grid-template-columns:1.4fr 0.6fr;gap:18px;align-items:start;}
        .card{background:#fff;border:1px solid #eef1f5;border-radius:10px;box-shadow:0 10px 24px rgba(0,0,0,.06);padding:18px;}
        .list-title{font-weight:800;font-size:18px;margin-bottom:14px;}
        .combo-item{display:grid;grid-template-columns:140px 1fr 120px;gap:12px;align-items:center;padding:14px 0;border-bottom:1px solid #f0f2f5;}
        .combo-thumb img{width:140px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;}
        .combo-name{font-weight:700;font-size:16px;margin-bottom:6px;}
        .combo-desc{color:#4b5563;font-size:14px;line-height:1.4;max-height:40px;overflow:hidden;}
        .combo-price{color:var(--p);font-weight:800;margin-top:6px;}
        .qty{display:flex;align-items:center;gap:8px;justify-content:flex-end;}
        .qty button{width:32px;height:32px;border:1px solid #e5e7eb;background:#fff;border-radius:6px;cursor:pointer;font-weight:700;color:#111827;}
        .qty button:disabled{opacity:.5;cursor:not-allowed;}
        .qty span{min-width:28px;text-align:center;font-weight:700;}

        .summary-bar{height:6px;background:var(--p);margin:-18px -18px 14px -18px;border-radius:10px 10px 0 0;}
        .summary-head{display:grid;grid-template-columns:90px 1fr;gap:12px;align-items:flex-start;}
        .summary-title{font-size:18px;font-weight:800;line-height:1.3;}
        .summary-meta{font-size:14px;color:#475569;line-height:1.5;}
        .badge{background:var(--p);color:#fff;border-radius:6px;padding:2px 8px;font-size:12px;font-weight:700;}
        .row{display:flex;justify-content:space-between;font-size:14px;color:#111827;margin-top:10px;}
        .row + .row{margin-top:6px;}
        .divider{border-top:1px dashed #e5e7eb;margin:12px 0;}
        .total{display:flex;justify-content:space-between;align-items:center;font-weight:800;font-size:18px;margin-top:6px;}
        .actions{display:flex;gap:12px;margin-top:14px;}
        .btn{flex:1;padding:12px 18px;font-weight:700;border-radius:0;border:1px solid #e5e7eb;cursor:pointer;text-align:center;}
        .btn.primary{background:var(--p);color:#fff;border-color:var(--p);box-shadow:0 6px 16px rgba(242,107,56,.2);}
        .btn.ghost{background:#fff;color:var(--p);}
        .timer{color:#e56b00;font-weight:800;font-size:15px;text-align:right;margin-bottom:8px;}
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('home') }}" class="topbar-logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema">
                <span>TD Cinema</span>
            </a>
            <a href="{{ route('home') }}" class="topbar-cancel">Hủy giao dịch ×</a>
        </div>
    </div>

    <div class="page">
        <div class="progress">
            <div class="step done">1. Chọn phim / Rạp / Suất</div>
            <div class="step done">2. Chọn ghế</div>
            <div class="step active">3. Combo</div>
            <div class="step">4. Thanh toán</div>
            <div class="step">5. Xác nhận</div>
        </div>

        <div class="layout">
            <div class="card">
                <div class="list-title">Chọn Combo / Sản phẩm</div>
                @foreach($combos as $combo)
                    <div class="combo-item" data-id="{{ $combo->id }}" data-price="{{ $combo->price }}">
                        <div class="combo-thumb">
                            <img src="{{ $combo->image ? (preg_match('/^https?:\/\//', $combo->image) ? $combo->image : asset('storage/' . ltrim($combo->image, '/'))) : 'https://via.placeholder.com/160x90?text=Combo' }}" alt="{{ $combo->name }}">
                        </div>
                        <div>
                            <div class="combo-name">{{ $combo->name }}</div>
                            <div class="combo-desc">{{ Str::limit($combo->description ?? 'Combo', 120) }}</div>
                            <div class="combo-price">{{ number_format($combo->price, 0, ',', '.') }} đ</div>
                        </div>
                        <div class="qty">
                            <button class="btn-minus">-</button>
                            <span class="qty-value">0</span>
                            <button class="btn-plus">+</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card">
                <div class="summary-bar"></div>
                <div class="timer">Thời gian giữ ghế: <span id="countdown">--:--</span></div>
                <div class="summary-head">
                    <div><img src="{{ $posterUrl }}" alt="{{ $movie->title }}" style="width:90px;height:130px;object-fit:cover;border:1px solid #e5e7eb;"></div>
                    <div>
                        <div class="summary-title">{{ $movie->title }}</div>
                        <div class="summary-meta">2D Phụ Đề – <span class="badge">{{ $ageBadge }}</span></div>
                    </div>
                </div>
                <div class="summary-meta" style="margin-top:10px;font-weight:700;">{{ $showtime->room->cinema->name ?? '-' }} - {{ strtoupper($showtime->room->name ?? '-') }}</div>
                <div class="summary-meta">Suất: <strong>{{ $startTime }}</strong> - {{ $startDate }}</div>
                <div class="summary-meta">Ghế: {{ $selectedSeats->isNotEmpty() ? $selectedSeats->implode(', ') : 'Chưa chọn ghế' }}</div>

                <div class="divider"></div>
                <div id="summaryItems">
                    @if($ticketCount > 0)
                        <div class="row"><span>{{ $ticketCount }}x Vé</span><span id="ticketTotal">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                    @endif
                </div>
                <div class="divider"></div>
                <div class="total"><span>Tổng cộng</span><span id="grandTotal">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                <div class="actions">
                    <a href="{{ route('booking.checkout', $showtime) }}" class="btn ghost">Quay lại</a>
                    <a id="payLink" href="{{ route('booking.payment', $showtime) }}{{ $selectedSeats->isNotEmpty() ? '?seats='.urlencode($selectedSeats->implode(',')) : '' }}" class="btn primary" style="display:inline-flex;align-items:center;justify-content:center;">Tiếp tục</a>
                </div>
            </div>
        </div>
    </div>

<script>
    const countdownEl = document.getElementById('countdown');
    let remaining = {{ $countdownSeconds }};
    const tick = () => {
        if (remaining < 0) return;
        const m = String(Math.floor(remaining/60)).padStart(2,'0');
        const s = String(remaining%60).padStart(2,'0');
        countdownEl.textContent = `${m}:${s}`;
        remaining--;
    };
    tick();
    setInterval(tick, 1000);

    const combos = Array.from(document.querySelectorAll('.combo-item'));
    const summaryItems = document.getElementById('summaryItems');
    const grandTotal = document.getElementById('grandTotal');
    const payLink = document.getElementById('payLink');
    const ticketTotalEl = document.getElementById('ticketTotal');
    const ticketTotal = {{ $ticketTotal }};
    function format(v){ return new Intl.NumberFormat('vi-VN').format(v) + ' đ'; }
    function render(){
        summaryItems.querySelectorAll('.row.combo-line').forEach(r=>r.remove());
        let comboTotal = 0;
        combos.forEach(c=>{
            const qty = Number(c.querySelector('.qty-value').textContent || 0);
            if(qty>0){
                const price = Number(c.dataset.price||0);
                comboTotal += qty*price;
                const row = document.createElement('div');
                row.className='row combo-line';
                row.innerHTML=`<span>${qty}x ${c.querySelector('.combo-name').textContent}</span><span>${format(qty*price)}</span>`;
                summaryItems.appendChild(row);
            }
        });
        grandTotal.textContent = format(ticketTotal + comboTotal);
        if(ticketTotalEl) ticketTotalEl.textContent = format(ticketTotal);
        // cập nhật link thanh toán kèm tổng combo
        if(payLink){
            const seatParam = @json($selectedSeats->isNotEmpty() ? $selectedSeats->implode(',') : '');
            const comboParam = comboTotal > 0 ? `&combo_total=${comboTotal}` : '';
            payLink.href = @json(route('booking.payment', $showtime)) + (seatParam ? `?seats=${encodeURIComponent(seatParam)}${comboParam}` : (comboParam ? `?combo_total=${comboTotal}` : ''));
        }
    }
    combos.forEach(c=>{
        const minus = c.querySelector('.btn-minus');
        const plus = c.querySelector('.btn-plus');
        const val = c.querySelector('.qty-value');
        minus.addEventListener('click',()=>{
            const n = Math.max(0, Number(val.textContent)-1);
            val.textContent = n;
            render();
        });
        plus.addEventListener('click',()=>{
            const n = Math.min(10, Number(val.textContent)+1);
            val.textContent = n;
            render();
        });
    });
    render();
</script>
</body>
</html>
