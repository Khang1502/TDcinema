<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')

    <title>Staff Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 text-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-6 w-full max-w-xl shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Staff dashboard</h1>
                <p class="text-sm text-slate-300">Xin chào, {{ auth()->user()->name ?? 'Staff' }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="px-4 py-2 rounded-md text-sm font-semibold bg-black text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black">
                    Đăng xuất
                </button>
            </form>
        </div>
        <div class="text-slate-200 text-sm">
            Đây là placeholder dashboard cho role Staff. Bạn có thể thêm nội dung sau.
        </div>
    </div>
@include('shared.notifications')
</body>
</html>
