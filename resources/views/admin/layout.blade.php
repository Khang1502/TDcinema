<!doctype html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <title>@yield('title', 'Admin Dashboard')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-900 text-slate-50">
    <div class="min-h-screen flex bg-[#0b1629] text-slate-50">
        {{-- Sidebar --}}
        <aside class="w-72 bg-[#0e2038] border-r border-slate-800/70 flex flex-col shadow-xl shadow-black/30">
            <div class="p-6 border-b border-slate-800/70">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 grid place-items-center text-lg font-bold text-white shadow-inner shadow-indigo-900/50">
                        {{ \Illuminate\Support\Str::substr(auth()->user()->name ?? 'A', 0, 1) }}
                    </div>
                    <div>
                        <div class="font-semibold text-lg text-white">{{ auth()->user()->name ?? 'Admin Name' }}</div>
                        <div class="text-slate-400 text-sm">{{ auth()->user()->role ?? 'administrator' }}</div>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-5">
                <ul class="space-y-1 px-4">
                    <li>
                        @php $active = request()->is('admin/dashboard'); @endphp
                        <a href="/admin/dashboard"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'dashboard', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Bảng điều khiển</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/movies*'); @endphp
                        <a href="/admin/movies"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'film', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý phim</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/showtimes*'); @endphp
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'ticket', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý suất chiếu</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/users*'); @endphp
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'users', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý người dùng</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/promotions*'); @endphp
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'gift', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý khuyến mãi</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/cinemas*'); @endphp
                        <a href="/admin/cinemas"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'building', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý rạp phim</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/services*'); @endphp
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'bag', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý dịch vụ</span>
                        </a>
                    </li>
                    <li>
                        @php $active = request()->is('admin/schedules*'); @endphp
                        <a href="#"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition {{ $active ? 'bg-[#152a46] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                            {!! view('admin.partials.icon', ['name' => 'calendar', 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                            <span class="text-sm font-medium">Quản lý Lịch Chiếu</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="border-t border-slate-800/70 p-4 space-y-2">
                <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-[#13263f] transition">
                    {!! view('admin.partials.icon', ['name' => 'settings']) !!}
                    <span class="text-sm font-medium">Settings</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-[#13263f] transition">
                        {!! view('admin.partials.icon', ['name' => 'logout']) !!}
                        <span class="text-sm font-medium">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main --}}
        <div class="flex-1 flex flex-col">
            <header class="h-16 border-b border-slate-800/70 bg-[#0f1f36] px-6 flex items-center justify-between shadow-lg shadow-black/20">
                <div class="text-sm uppercase tracking-wide text-slate-400 font-semibold">ADMIN / Home</div>
                <div class="flex items-center gap-4">
                    <button class="h-10 w-10 grid place-items-center rounded-full bg-[#13263f] text-slate-300 hover:text-white transition">
                        {!! view('admin.partials.icon', ['name' => 'bell']) !!}
                    </button>
                    <div class="h-10 w-10 rounded-full bg-cover bg-center ring-2 ring-slate-700" style="background-image: url('https://i.pravatar.cc/100');"></div>
                </div>
            </header>

            <main class="flex-1 p-6 bg-[#0b1629]">
                {{-- Flash messages (card style) --}}
                
                
                @yield('content')
            </main>
        </div>
    </div>
@include('shared.notifications')
</body>
</html>
