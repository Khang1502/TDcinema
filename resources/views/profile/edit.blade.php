<x-app-layout>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/profile.css') }}?v={{ filemtime(public_path('assets/css/frontend/profile.css')) }}">
        <link rel="stylesheet" href="{{ asset('assets/css/frontend/membership.css') }}?v={{ filemtime(public_path('assets/css/frontend/membership.css')) }}">
    @endpush

    <div class="profile-page">
        <div class="profile-container">
            <div class="profile-grid">
                @include('profile.partials.account-sidebar', ['active' => 'profile'])

                <div class="card main-card">
                    @include('frontend.partials.membership-summary')

                    <div class="section-title" style="margin-top:10px;">
                        <h2>Thông tin cá nhân</h2>
                    </div>

                    <form method="post" action="{{ route('profile.update') }}">
                        @csrf
                        @method('patch')
                        <div class="info-grid">
                            <div class="form-group">
                                <label for="name">Họ và tên</label>
                                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('name')" />
                            </div>
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('email')" />
                                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                    <div class="hint">
                                        Email chưa xác thực.
                                        <button form="send-verification" class="underline">Gửi lại email xác thực</button>
                                    </div>
                                    @if (session('status') === 'verification-link-sent')
                                        <div data-inline-notification class="hint text-green-600">Đã gửi lại link xác thực.</div>
                                    @endif
                                @endif
                            </div>
                            <div class="form-group">
                                <label for="password">Mật khẩu mới</label>
                                <input id="password" name="password" type="password" placeholder="(Để trống nếu không đổi)" autocomplete="new-password">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('password')" />
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation">Nhập lại mật khẩu</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" placeholder="(Để trống nếu không đổi)" autocomplete="new-password">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('password_confirmation')" />
                            </div>
                            <div class="form-group">
                                <label>Ngày sinh</label>
                                <input type="date" name="birthday" value="{{ old('birthday', optional($user->birthday)->format('Y-m-d')) }}">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('birthday')" />
                            </div>
                            <div class="form-group">
                                <label>Số điện thoại</label>
                                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Nhập số điện thoại">
                                <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('phone')" />
                            </div>
                        </div>
                        <div class="form-group" style="margin-top:12px;">
                            <label>Địa chỉ</label>
                            <input type="text" name="address" value="{{ old('address', $user->address) }}" placeholder="Nhập địa chỉ">
                            <x-input-error class="mt-1 text-red-500 text-xs" :messages="$errors->get('address')" />
                        </div>
                        <div>
                            <button class="save-btn" type="submit">Lưu thay đổi</button>
                            @if (session('status') === 'profile-updated')
                                <span data-inline-notification class="hint" style="color:#16a34a;">Đã lưu.</span>
                            @endif
                        </div>
                    </form>

                    <form id="send-verification" method="post" action="{{ route('verification.send') }}" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
    
</x-app-layout>
