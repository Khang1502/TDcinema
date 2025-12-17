<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>TD Cinema - Cấp lại mật khẩu</title>
    <style>
        body {
            margin: 0;
            padding: 40px 0;
            background: #0b192f;
            font-family: Arial, Helvetica, sans-serif;
        }
        .wrapper { max-width: 600px; margin: 0 auto; }
        .card {
            background: #0f172a;
            border-radius: 8px;
            overflow: hidden;
            color: #ffffff;
            text-align: left;
            border: 1px solid #1f2937;
        }
        .card-header {
            background: #f59e0b;
            padding: 16px 24px;
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1f2937;
        }
        .card-body { padding: 24px 32px 32px; font-size: 14px; line-height: 1.6; }
        .card-body p { margin: 0 0 12px; color:#e5e7eb; }
        .btn {
            display: inline-block;
            margin-top: 18px;
            padding: 10px 26px;
            background: #f97316;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 999px;
            font-weight: bold;
            text-align: center;
        }
        .btn:hover { filter: brightness(1.05); }
        .footer {
            margin-top: 12px;
            font-size: 12px;
            color: #cbd5e1;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="card-header">Cấp lại mật khẩu</div>
        <div class="card-body">
            <p>Xin chào <strong>{{ $user->name ?? $user->email }}</strong>,</p>
            <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản TD Cinema của bạn.</p>
            <p>Vui lòng nhấn vào nút bên dưới để tạo mật khẩu mới:</p>
            <p style="text-align:center;">
                <a href="{{ $url }}" class="btn">Đặt lại mật khẩu</a>
            </p>
            <p>Liên kết này sẽ hết hạn sau <strong>60 giây</strong>. Nếu không phải bạn yêu cầu, hãy bỏ qua email này.</p>
            <p>Trân trọng,<br>Đội ngũ TD Cinema</p>
        </div>
    </div>
    <div class="footer">© {{ date('Y') }} TD Cinema. All rights reserved.</div>
</div>
</body>
</html>
