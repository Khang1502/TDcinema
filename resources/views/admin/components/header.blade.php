<header class="w-full bg-[#0f1f36] border-b border-slate-800/70 shadow-lg shadow-black/20">
    <div class="h-16 max-w-7xl mx-auto px-8 flex items-center justify-between">
        {{-- Bên trái: breadcrumb + tiêu đề --}}
        <div>
            <div class="text-xs text-slate-400 uppercase tracking-wide">ADMIN / Home</div>
            <h1 class="text-xl font-semibold mt-1">@yield('title', 'Bảng điều khiển')</h1>
        </div>

        {{-- Bên phải: filter & avatar --}}
        <div class="flex items-center space-x-4">
            @if (!empty($showCinemaFilter))
                <button
                    class="inline-flex items-center px-3 py-2 text-xs font-medium rounded-md border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700">
                    Tất cả các rạp
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                         fill="currentColor" class="w-4 h-4 ml-2">
                        <path fill-rule="evenodd"
                              d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.1 1.02l-4.25 4.5a.75.75 0 01-1.1 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                              clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>
    </div>
</header>
