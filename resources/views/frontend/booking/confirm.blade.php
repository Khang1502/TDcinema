<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TD Cinema - Xác nhận thanh toán</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #0f6fc9;
            --orange: #f26b38;
            --green: #16a34a;
            --text: #0f172a;
            --muted: #6b7280;
            --bg: #f6f7fb;
        }
        * { box-sizing: border-box; }
    @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
    body { margin: 0; font-family: 'Be Vietnam Pro', sans-serif; background: var(--bg); color: var(--text); }
        .page { max-width: 1180px; margin: 0 auto; padding: 24px; }
        .banner { background: #e8f9f1; border: 1px solid #c7f0d9; border-radius: 14px; padding: 20px; display: flex; align-items: center; gap: 12px; box-shadow: 0 16px 40px rgba(0,0,0,0.06); }
        .badge { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; }
        .ok { background: #d1fae5; color: #0f9d58; }
        .fail { background: #fee2e2; color: #dc2626; }
        h1 { margin: 10px 0 18px; font-size: 24px; font-weight: 800; }
        .grid { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); margin-top: 18px; }
        .panel { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 8px 24px rgba(0,0,0,0.05); }
        .movie-info { display: grid; grid-template-columns: 120px 1fr; gap: 14px; align-items: center; }
        .movie-info img { width: 120px; height: 180px; object-fit: cover; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.12); background: #e5e7eb; }
        .label { font-size: 13px; color: var(--muted); }
        .value { font-weight: 700; color: var(--text); }
        .list { display: grid; row-gap: 8px; margin-top: 8px; }
        .item { display: flex; justify-content: space-between; font-size: 14px; }
        .divider { border-top: 1px dashed #e5e7eb; margin: 14px 0; }
        .total { display: flex; justify-content: space-between; font-weight: 800; font-size: 16px; }
        .ticket-box { text-align: center; background: #f8fafc; border: 1px dashed #d1d5db; border-radius: 12px; padding: 18px; }
        .ticket-id { font-size: 18px; font-weight: 800; letter-spacing: 0.5px; margin: 6px 0; }
        .actions { display: grid; gap: 10px; margin-top: 12px; }
        .btn { padding: 12px 14px; border-radius: 10px; border: 1px solid transparent; cursor: pointer; font-weight: 700; text-decoration: none; display: block; text-align: center; }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-primary:hover { background: #1e82dd; }
        .btn-outline { background: #fff; color: var(--text); border-color: #e5e7eb; }
        .btn-neutral { background: #f8fafc; color: var(--text); border-color: #e5e7eb; }
        .muted { color: var(--muted); font-size: 13px; margin-top: 6px; text-align: center; }
        @media (max-width: 640px) {
            .movie-info { grid-template-columns: 1fr; text-align: center; }
            .movie-info img { margin: 0 auto; }
        }
    </style>
</head>
<body>
<div class="page">
    @if(!empty($success))
        <div class="banner">
            <div class="badge ok">✔</div>
            <div>
                <div class="value">Booking Confirmed!</div>
                <div class="label">Cảm ơn bạn đã thanh toán, thông tin vé đã được ghi nhận.</div>
            </div>
        </div>
    @else
        <div class="banner" style="background:#fdecec;border-color:#f5c2c0;">
            <div class="badge fail">!</div>
            <div>
                <div class="value" style="color:#dc2626;">Thanh toán thất bại</div>
                <div class="label">{{ $message ?? 'Vui lòng thử lại.' }}</div>
            </div>
        </div>
    @endif

    @if(!empty($showtime))
        <h1>{{ $showtime->movie->title ?? 'Vé phim' }}</h1>

        <div class="grid">
            <div class="panel">
                <div class="movie-info">
                    @php
                        $poster = $showtime->movie->poster_path ?? $showtime->movie->banner_path;
                        if ($poster && !\Illuminate\Support\Str::startsWith($poster, ['http://', 'https://'])) {
                            $poster = asset('storage/' . ltrim($poster, '/'));
                        }
                        $poster = $poster ?: 'https://via.placeholder.com/120x180?text=TD+Cinema';
                    @endphp
                    <img src="{{ $poster }}" alt="Poster">
                    <div>
                        <div class="label">Định dạng</div>
                        <div class="value">{{ strtoupper($showtime->movie->format ?? '2D') }}</div>
                        <div class="label" style="margin-top:6px;">Rạp chiếu</div>
                        <div class="value">{{ $showtime->room->cinema->name ?? '' }} - {{ $showtime->room->name ?? '' }}</div>
                    </div>
                </div>

                <div class="divider"></div>
                <div class="list">
                    <div class="item"><span class="label">Ngày</span><span class="value">{{ \Carbon\Carbon::parse($showtime->start_time)->format('d/m/Y') }}</span></div>
                    <div class="item"><span class="label">Suất chiếu</span><span class="value">{{ \Carbon\Carbon::parse($showtime->start_time)->format('H:i') }}</span></div>
                    <div class="item"><span class="label">Ghế</span><span class="value">{{ $selectedSeats->implode(', ') ?: 'N/A' }}</span></div>
                </div>

                <div class="divider"></div>
                <div class="list">
                    <div class="item"><span class="label">Vé</span><span class="value">{{ number_format($ticketTotal, 0, ',', '.') }} đ</span></div>
                    <div class="item"><span class="label">Combo</span><span class="value">{{ number_format($comboTotal, 0, ',', '.') }} đ</span></div>
                </div>
                <div class="divider"></div>
                <div class="total"><span>Tổng thanh toán</span><span>{{ number_format($grandTotal, 0, ',', '.') }} đ</span></div>
            </div>

            <div class="panel">
                <div class="ticket-box">
                    <div class="label">Mã vé</div>
                    <div class="ticket-id">{{ $bookingCode ?? strtoupper(substr(md5(now()), 0, 10)) }}</div>
                    @if(!empty($qrUrl))
                        <img src="{{ $qrUrl }}" alt="QR code" style="width:160px;height:160px;object-fit:contain;margin:10px auto 6px;display:block;">
                    @endif
                    <div class="muted">Vui lòng xuất trình mã hoặc QR này khi vào rạp.</div>
                </div>
                <div class="actions">
                    <a class="btn btn-primary" href="#">Xem E-Ticket</a>
                    <button class="btn btn-outline" onclick="window.print()">In vé</button>
                    <button class="btn btn-neutral" onclick="navigator.share ? navigator.share({title:'TD Cinema', text:'Thông tin vé', url: window.location.href}) : window.alert('Đã sao chép link');">Chia sẻ</button>
                    <a class="btn btn-outline" href="{{ route('home') }}">Về trang chủ</a>
                </div>
            </div>
        </div>
    @else
        <p class="label" style="margin-top:12px;">Không tìm thấy thông tin suất chiếu.</p>
    @endif
</div>
</body>
</html>
