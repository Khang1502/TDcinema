<div class="modal-overlay" id="authOverlay" data-registration-error="{{ $errors->registration->any() ? '1' : '0' }}">
    <div class="modal-box" id="loginBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Chào mừng trở lại!</div>
            <div class="modal-sub">Đăng nhập để tiếp tục trải nghiệm của bạn.</div>
        </div>
        @if($errors->has('email') && !session('reset_error'))
            <div class="auth-alert">{{ $errors->first('email') }}</div>
        @endif
        <form class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Nhập email">
            </div>
            <div>
                <label>Mật khẩu</label>
                <div class="auth-password-field">
                    <input type="password" name="password" placeholder="Nhập mật khẩu của bạn" autocomplete="current-password">
                    <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Hiển thị mật khẩu" aria-pressed="false">
                        <span data-password-toggle-label>Hiện</span>
                    </button>
                </div>
            </div>
            <div class="auth-actions">
                <span class="auth-link" id="openForgot">Quên mật khẩu?</span>
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng nhập</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Chưa có tài khoản?</span>
                <span class="auth-link" id="switchRegister">Đăng ký ngay</span>
            </div>
        </form>
    </div>

    <div class="modal-box hidden" id="registerBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Tạo tài khoản mới</div>
            <div class="modal-sub">Bắt đầu hành trình điện ảnh của bạn hôm nay.</div>
        </div>
        <form class="auth-form" method="POST" action="{{ route('register') }}">
            @csrf
            <div>
                <label>Họ và tên</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="Nguyễn Văn A" autocomplete="name" aria-invalid="{{ $errors->registration->has('name') ? 'true' : 'false' }}">
                @error('name', 'registration')<div class="auth-error" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="nguyenvana@email.com" autocomplete="email" aria-invalid="{{ $errors->registration->has('email') ? 'true' : 'false' }}">
                @error('email', 'registration')<div class="auth-error" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="register-phone">Số điện thoại</label>
                <input id="register-phone" type="tel" name="phone" value="{{ old('phone') }}" placeholder="0901234567" autocomplete="tel" maxlength="20" aria-invalid="{{ $errors->registration->has('phone') ? 'true' : 'false' }}" @if($errors->registration->has('phone')) aria-describedby="register-phone-error" @endif>
                @error('phone', 'registration')<div id="register-phone-error" class="auth-error" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Mật khẩu</label>
                <div class="auth-password-field">
                    <input type="password" name="password" placeholder="Nhập mật khẩu của bạn" autocomplete="new-password" aria-invalid="{{ $errors->registration->has('password') ? 'true' : 'false' }}">
                    <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Hiển thị mật khẩu" aria-pressed="false">
                        <span data-password-toggle-label>Hiện</span>
                    </button>
                </div>
                <div class="auth-hint">Phải có ít nhất 8 ký tự.</div>
                @error('password', 'registration')<div class="auth-error" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Xác nhận mật khẩu</label>
                <div class="auth-password-field">
                    <input type="password" name="password_confirmation" placeholder="Nhập lại mật khẩu" autocomplete="new-password">
                    <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Hiển thị mật khẩu" aria-pressed="false">
                        <span data-password-toggle-label>Hiện</span>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng ký</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Đã có tài khoản?</span>
                <span class="auth-link" id="switchLogin">Đăng nhập ngay</span>
            </div>
        </form>
    </div>
    <div class="modal-box hidden" id="forgotBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header" id="forgotHeader">
            <div class="modal-title">Quên mật khẩu</div>
            <div class="modal-sub">Nhập email để nhận liên kết đặt lại mật khẩu.</div>
        </div>
        <div id="forgotFormWrap">
            @if(session('reset_error') && $errors->has('email'))
                <div class="auth-alert">{{ $errors->first('email') }}</div>
            @endif
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="btn btn-primary auth-submit">Gửi liên kết</button>
                <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                    <span class="auth-link" id="backLogin">Quay lại đăng nhập</span>
                </div>
            </form>
        </div>
        <div id="forgotSuccess" class="auth-success hidden">
            <img src="{{ asset('assets/images/membership/benefit.png') }}" alt="Quên mật khẩu"
                 onerror="this.style.display='none'">
            <div class="modal-title" style="margin-top:8px;">Quên mật khẩu?</div>
            @if(session('reset_link'))
                <div class="msg">Máy chủ email chưa kết nối. Bạn có thể đặt lại mật khẩu cho <strong id="resetEmailTxt"></strong> ngay tại đây.</div>
                <a href="{{ session('reset_link') }}" class="btn btn-primary auth-submit">Đặt lại mật khẩu ngay</a>
            @else
                <div class="msg">Email khôi phục mật khẩu đã được gửi thành công tới <strong id="resetEmailTxt"></strong>.</div>
            @endif
            <button type="button" class="btn btn-primary auth-submit" id="closeForgotSuccess">Đóng</button>
        </div>
    </div>
</div>
