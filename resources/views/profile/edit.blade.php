<x-app-layout>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        .profile-page { font-family: 'Be Vietnam Pro', sans-serif; background:linear-gradient(180deg,#f7f9ff 0%,#f2f4f8 100%); min-height:100vh; }
        .profile-container { max-width: 1100px; margin: 0 auto; padding: 18px 12px 36px; }
        .profile-grid { display:grid; grid-template-columns: 270px 1fr; gap:14px; align-items:start; }
        .card { background:#fff; border-radius:14px; box-shadow:0 8px 30px rgba(15,23,42,0.05); border:1px solid #e5e7eb; }
        .side-header { padding:16px; border-bottom:1px solid #eef1f5; display:flex; gap:12px; align-items:center; }
        .avatar { width:52px; height:52px; border-radius:50%; background:#e5e7eb; display:grid; place-items:center; font-weight:800; color:#0b2c3d; }
        .side-name { font-weight:700; font-size:16px; color:#1f2937; margin:0; }
        .side-tier { font-size:13px; color:#6b7280; margin:2px 0 0; }
        .side-menu { list-style:none; padding:0; margin:0; }
        .side-menu li a { display:flex; align-items:center; gap:10px; padding:10px 16px; color:#1f2937; font-weight:600; border-radius:10px; text-decoration:none; }
        .side-menu li a.active { background:#e8f2ff; color:#1d4ed8; }
        .side-menu li a:hover { background:#f3f4f6; }
        .logout-link { display:block; padding:12px 16px; color:#dc2626; font-weight:600; text-decoration:none; }
        .main-card { padding:18px; }
        .section-title { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        .section-title h2 { margin:0; font-size:17px; font-weight:800; color:#111827; }
        .badge-year { background:#e0f2ff; color:#0b70e0; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:700; }
        .progress-bar { height:8px; background:#e5e7eb; border-radius:999px; overflow:hidden; }
        .progress-fill { height:100%; background:#0ea5e9; }
        .info-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap:12px 14px; margin-top:10px; }
        .form-group label { display:block; font-weight:700; font-size:13px; color:#111827; margin-bottom:5px; }
        .form-group input { width:100%; border:1px solid #d1d5db; border-radius:10px; padding:11px 12px; font-size:14px; color:#111827; background:#f9fafb; }
        .form-group input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.12); }
        .save-btn { margin-top:12px; display:inline-flex; align-items:center; justify-content:center; padding:11px 18px; background:#2563eb; color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer; }
        .save-btn:hover { background:#1d4ed8; }
        .hint { font-size:12px; color:#6b7280; margin-top:6px; }
        @media (max-width: 960px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        /* Footer reuse */
        .footer { background:#232633; color:#fff; padding:32px 0 20px; font-size:14px; margin-top:32px; }
        .footer-inner { max-width:1200px; margin:0 auto; padding:0 16px; display:grid; grid-template-columns: minmax(0,2.2fr) repeat(3,minmax(0,1fr)); gap:24px; align-items:flex-start; }
        .footer-brand { display:flex; align-items:flex-start; gap:12px; }
        .footer-logo img { width:110px; height:auto; display:block; }
        .footer-company-name { font-weight:700; margin-bottom:6px; }
        .footer-company p { margin:0; color:rgba(255,255,255,.7); }
        .footer h4 { text-transform:uppercase; font-size:13px; margin-bottom:12px; font-weight:700; }
        .footer-col a { display:block; margin-bottom:8px; color:rgba(255,255,255,.7); text-decoration:none; }
        .footer-col a:hover { color:#fff; text-decoration:underline; }
        .footer-contact-item { display:flex; align-items:center; margin-bottom:8px; color:rgba(255,255,255,.75); }
        .footer-icon { width:18px; height:18px; margin-right:10px; flex-shrink:0; }
        .footer-social { margin-top:10px; display:flex; gap:12px; }
        .footer-social-icon { width:26px; height:26px; display:block; opacity:.85; transition:.2s; }
        .footer-social-icon:hover { opacity:1; transform:scale(1.08); }
        .footer-bottom { border-top:1px solid rgba(255,255,255,.1); margin-top:20px; padding-top:12px; text-align:center; font-size:12px; color:rgba(255,255,255,.5); }
    </style>

    <div class="profile-page">
        <div class="profile-container">
            <div class="profile-grid">
                <div class="card">
                    <div class="side-header">
                        <div class="avatar">
                            @if(auth()->user()->avatar ?? false)
                                <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            @else
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                        <div>
                            <p class="side-name">{{ auth()->user()->name ?? 'Người dùng' }}</p>
                            <p class="side-tier">Thành viên</p>
                        </div>
                    </div>
                    <ul class="side-menu" style="padding:12px;">
                        <li><a href="#" class="active">Thông tin cá nhân</a></li>
                        <li><a href="{{ route('customer.tickets') }}">Lịch sử đặt vé</a></li>
                        <li><a href="#">Thông báo</a></li>
                        <li><a href="#">Quà tặng</a></li>
                        <li><a href="#">Chính sách</a></li>
                    </ul>
                    <div style="padding:0 12px 14px;">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="logout-link">Đăng xuất</button>
                        </form>
                    </div>
                </div>

                <div class="card main-card">
                    <div style="background:linear-gradient(90deg,#f0f4ff,#e9ecf5);border-radius:12px;padding:12px 16px;margin-bottom:20px;border:1px solid #e5e7eb;">
                        <div class="section-title" style="margin-bottom:10px;">
                            <h2 style="font-size:16px;">Tổng chi tiêu 2025</h2>
                            <span class="badge-year">{{ number_format($yearSpent ?? 0, 0, ',', '.') }} đ</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; font-size:13px; color:#374151;">
                            <span>0 đ</span>
                            <div class="progress-bar" style="flex:1; background:#e5e7eb;">
                                <div class="progress-fill" style="width: {{ ($progress ?? 0) * 100 }}%; background:#2563eb;"></div>
                            </div>
                            <span>{{ number_format($tier2 ?? 4000000, 0, ',', '.') }} đ</span>
                        </div>
                    </div>

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
                                        <div class="hint text-green-600">Đã gửi lại link xác thực.</div>
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
                                <span class="hint" style="color:#16a34a;">Đã lưu.</span>
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
    <footer class="footer">
        <div class="footer-inner">
            <div class="footer-brand">
                <div class="footer-logo">
                    <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema">
                </div>
                <div class="footer-company">
                    <p class="footer-company-name">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                    <p>180 Cao Lỗ, phường Chánh Hưng, tp. Hồ Chí Minh</p>
                </div>
            </div>
            <div class="footer-col">
                <h4>Góc điện ảnh</h4>
                <a href="#">Thể loại phim</a>
                <a href="#">Bình luận phim</a>
                <a href="#">Blog điện ảnh</a>
                <a href="#">Phim hay tháng</a>
            </div>
            <div class="footer-col">
                <h4>Góc điện ảnh</h4>
                <a href="#">Thể loại phim</a>
                <a href="#">Bình luận phim</a>
                <a href="#">Blog điện ảnh</a>
                <a href="#">Phim hay tháng</a>
            </div>
            <div class="footer-col footer-contact">
                <h4>Liên hệ với chúng tôi</h4>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" class="footer-icon">
                    180 Cao Lỗ, phường Chánh Hưng, tp.HCM
                </div>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" class="footer-icon">
                    10971395075
                </div>
                <div class="footer-contact-item">
                    <img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" class="footer-icon">
                    daikha2521@gmail.com
                </div>
                <div class="footer-social">
                    <a href="#"><img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}"
                         alt="Facebook" class="footer-social-icon"></a>
                    <a href="#"><img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}"
                         alt="Instagram" class="footer-social-icon"></a>
                    <a href="#"><img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}"
                         alt="Youtube" class="footer-social-icon"></a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            © 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.
        </div>
    </footer>
</x-app-layout>
