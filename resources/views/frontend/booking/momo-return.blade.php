<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả thanh toán MoMo</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        body{font-family:'Be Vietnam Pro',sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f7f8fb;color:#0f172a;}
        .card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.1);max-width:420px;width:90vw;text-align:center;}
        .success{color:#16a34a;font-weight:800;font-size:20px;}
        .fail{color:#dc2626;font-weight:800;font-size:20px;}
        a{color:#f26b38;font-weight:700;text-decoration:none;}
    </style>
</head>
<body>
    <div class="card">
        <div class="{{ $success ? 'success' : 'fail' }}">
            {{ $success ? 'Thanh toán thành công' : 'Thanh toán thất bại' }}
        </div>
        <p style="margin-top:12px;">{{ $message ?? '' }}</p>
        <div style="margin-top:18px;"><a href="{{ route('home') }}">Về trang chủ</a></div>
    </div>
</body>
</html>
