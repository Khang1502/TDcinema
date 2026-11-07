<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Quên mật khẩu? Hãy nhập email tài khoản để nhận liên kết tạo mật khẩu mới.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if(session('reset_link'))
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="mb-3">Máy chủ email chưa kết nối trong môi trường local.</p>
            <a href="{{ session('reset_link') }}" class="font-semibold underline">Đặt lại mật khẩu ngay</a>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                Gửi liên kết đặt lại mật khẩu
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
