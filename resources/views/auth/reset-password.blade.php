<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>TD Cinema - Đặt lại mật khẩu</title>

    <style>
        :root {
            --primary: #359eff;
            --accent: #f97316;
            --bg: #0f172a;
            --card: #111827;
            --text: #e5e7eb;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: #0b192f;
            font-family: 'Be Vietnam Pro', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            width: 440px;
            max-width: 95vw;
            background: var(--card);
            border: 1px solid #1f2937;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,.25);
            padding: 24px 28px 28px;
            color: var(--text);
            position: relative;
        }
        .logo {
            text-align: center;
            margin-bottom: 12px;
        }
        h1 {
            margin: 8px 0 6px;
            font-size: 22px;
            font-weight: 800;
            text-align: center;
            color: #fff;
        }
        .sub { text-align:center; color:#cbd5e1; margin-bottom: 16px; }
        label { display:block; margin-bottom:6px; font-size:13px; color:#cbd5e1; font-weight:600; }
        input {
            width:100%;
            padding:12px;
            border-radius:10px;
            border:1px solid #1f2937;
            background:#0b1527;
            color:#fff;
            font-size:14px;
        }
        input:focus { outline:none; border-color: var(--primary); box-shadow:0 0 0 2px rgba(53,158,255,.2); }
        .field { margin-bottom:12px; }
        .error { color:#f87171; font-size:12px; margin-top:4px; }
        .btn {
            width:100%;
            padding:12px 16px;
            border:none;
            border-radius:10px;
            background: var(--primary);
            color:#fff;
            font-weight:700;
            font-size:14px;
            cursor:pointer;
            margin-top:8px;
        }
        .btn:hover { filter: brightness(1.05); }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt="TD Cinema" style="width:120px;height:78px;object-fit:contain;transform:scale(1.25);filter:brightness(1.6) saturate(.9);" onerror="this.style.display='none'">
        </div>
        <h1>Đặt lại mật khẩu</h1>
        <div class="sub">Tạo mật khẩu mới để tiếp tục đặt vé cùng TD Cinema.</div>

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Mật khẩu mới</label>
                <input type="password" name="password" required autocomplete="new-password">
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Nhập lại mật khẩu</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password">
                @error('password_confirmation') <div class="error">{{ $message }}</div> @enderror
            </div>

            <button type="submit" class="btn">Cập nhật mật khẩu</button>
        </form>
    </div>
@include('shared.notifications')
</body>
</html>
