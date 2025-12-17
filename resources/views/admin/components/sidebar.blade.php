<aside class="sticky top-0 h-screen w-72 flex-none bg-[#0e2038] border-r border-slate-800/70 flex flex-col shadow-xl shadow-black/30 z-30">
    {{-- Logo + info admin --}}
    <a href="{{ route('admin.dashboard') }}"
       class="px-5 py-5 border-b border-slate-800/70 flex items-center space-x-3 hover:bg-[#13263f] transition">
        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-sm font-semibold text-white shadow-inner shadow-indigo-900/40">
            {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}
        </div>
        <div class="flex flex-col text-left">
            <span class="font-semibold text-sm">{{ auth()->user()->name ?? 'Admin Name' }}</span>
            <span class="text-xs text-slate-400">administrator</span>
        </div>
    </a>

    {{-- Menu --}}
    <nav class="flex-1 px-3 py-5 space-y-1 text-sm">
        @php
            $menuItems = [
                ['route' => 'admin.dashboard', 'label' => 'Bảng điều khiển', 'icon' => 'dashboard'],
                ['route' => 'admin.movies.index', 'label' => 'Quản lý phim', 'icon' => 'film'],
                ['route' => 'admin.showtimes.index', 'label' => 'Quản lý suất chiếu', 'icon' => 'calendar'],
                ['route' => 'admin.showtimes.schedule', 'label' => 'Quản lý lịch chiếu', 'icon' => 'calendar'],
                ['route' => 'admin.tickets.index', 'label' => 'Quản lý vé', 'icon' => 'ticket'],
                ['route' => 'admin.users.index', 'label' => 'Quản lý người dùng', 'icon' => 'users'],
                ['route' => 'admin.promotions.index', 'label' => 'Quản lý khuyến mãi', 'icon' => 'gift'],
                ['route' => 'admin.cinemas.index', 'label' => 'Quản lý rạp phim', 'icon' => 'building'],
                ['route' => 'admin.rooms.index', 'label' => 'Quản lý phòng chiếu', 'icon' => 'ticket'],
                ['route' => 'admin.seats.index', 'label' => 'Quản lý ghế', 'icon' => 'seat'],
                ['route' => 'admin.foods.index', 'label' => 'Quản lý dịch vụ', 'icon' => 'bag'],
            ];
        @endphp

        @foreach ($menuItems as $item)
            @php
                $active = $item['route'] !== '#' ? request()->routeIs($item['route']) : false;
            @endphp

            <a href="{{ $item['route'] === '#' ? 'javascript:void(0)' : route($item['route']) }}"
               class="flex items-center space-x-3 px-3 py-2.5 rounded-lg transition
                      {{ $active ? 'bg-[#13263f] text-white border border-slate-700' : 'text-slate-300 hover:bg-[#13263f] hover:text-white' }}">
                <span class="w-5">
                    {!! view('admin.partials.icon', ['name' => $item['icon'], 'classes' => $active ? 'text-white' : 'text-slate-300']) !!}
                </span>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Bottom section: Settings + Logout --}}
    <div class="px-3 pb-4 pt-2 border-t border-slate-800/70 space-y-1 text-sm">
        <button
            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white">
            <span class="w-5">{!! view('admin.partials.icon', ['name' => 'settings']) !!}</span>
            <span>Settings</span>
        </button>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-red-300 hover:bg-red-600/20 hover:text-red-200">
                <span class="w-5">{!! view('admin.partials.icon', ['name' => 'logout', 'classes' => 'text-red-300']) !!}</span>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
