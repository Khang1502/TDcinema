<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bảng điều khiển') - TD Cinema Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0f172a] text-slate-50 min-h-screen overflow-x-hidden">
    <style>
        html, body { margin: 0; padding: 0; background: #0f172a; }
    </style>
    <div class="flex min-h-screen bg-[#0f172a]">
        {{-- Sidebar cố định bên trái (sticky) --}}
        @include('admin.components.sidebar')
        {{-- Nội dung --}}
        <div class="flex-1 min-h-screen flex flex-col bg-[#0f172a]">
            @include('admin.components.header')
            <main class="flex-1 px-8 py-8">
                {{-- Flash modal style --}}
                @php
                    $flashTypes = [
                        'success' => ['title' => 'Thành công', 'icon' => '✓', 'color' => 'emerald', 'button' => 'OK'],
                        'error'   => ['title' => 'Có lỗi', 'icon' => '✕', 'color' => 'red', 'button' => 'Đóng'],
                    ];
                @endphp
                @foreach ($flashTypes as $key => $meta)
                    @if (session($key))
                        <div class="flash-modal fixed inset-0 z-50 flex items-center justify-center px-4">
                            <div class="absolute inset-0 bg-black/65 backdrop-blur-sm"></div>
                            <div class="relative w-[320px] max-w-[90vw] rounded-[24px] shadow-2xl overflow-hidden border" style="background:#EEE8AA; color:#1f2937; border-color:#d7c870;">
                                <div class="px-6 pt-8 pb-6 text-center">
                                    <div class="mx-auto h-12 w-12 rounded-full grid place-items-center text-xl font-bold" style="border:2px solid #91bcf9; color:#1f2937; background:rgba(255,255,255,0.5);">
                                        {{ $meta['icon'] }}
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold" style="color:#EEE8AA;">{{ $meta['title'] }}</h3>
                                    <p class="mt-2 text-sm" style="color:#3a495f;">{{ session($key) }}</p>
                                    <button onclick="this.closest('.flash-modal').classList.add('hidden')" class="mt-6 inline-flex items-center justify-center w-full rounded-lg font-semibold py-2.5 shadow-lg" style="background:#85b4f5; color:#EEE8AA; box-shadow:0 10px 25px rgba(31,41,55,0.4);">
                                        {{ $meta['button'] }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
