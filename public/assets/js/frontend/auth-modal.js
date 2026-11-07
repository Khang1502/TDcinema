(() => {
    const pageConfig = document.body?.dataset || {};
    const hasLoginError = pageConfig.loginError === '1';
    const hasResetError = pageConfig.resetError === '1';
    const resetSent = pageConfig.resetSent === '1';
    const resetEmail = pageConfig.resetEmail || '';

    const authOverlay = document.getElementById('authOverlay');
    const hasRegistrationError = authOverlay?.dataset.registrationError === '1';
    const loginBox = document.getElementById('loginBox');
    const registerBox = document.getElementById('registerBox');
    const forgotBox = document.getElementById('forgotBox');

    const forgotFormWrap = document.getElementById('forgotFormWrap');
    const forgotHeader = document.getElementById('forgotHeader');
    const forgotSuccess = document.getElementById('forgotSuccess');
    const resetEmailTxt = document.getElementById('resetEmailTxt');
    const closeForgotSuccess = document.getElementById('closeForgotSuccess');

    const openLogin = document.getElementById('openLogin');
    const openRegister = document.getElementById('openRegister');
    const switchRegister = document.getElementById('switchRegister');
    const switchLogin = document.getElementById('switchLogin');
    const openForgot = document.getElementById('openForgot');
    const backLogin = document.getElementById('backLogin');
    const passwordToggles = document.querySelectorAll('[data-password-toggle]');

    function setPasswordVisibility(toggle, visible) {
        const field = toggle.closest('.auth-password-field');
        const input = field?.querySelector('input');
        const label = toggle.querySelector('[data-password-toggle-label]');
        if (!input) return;

        input.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
        toggle.setAttribute('aria-label', visible ? 'Ẩn mật khẩu' : 'Hiển thị mật khẩu');
        if (label) label.textContent = visible ? 'Ẩn' : 'Hiện';
    }

    function hidePasswords() {
        passwordToggles.forEach(toggle => setPasswordVisibility(toggle, false));
    }

    function hideBoxes() {
        [loginBox, registerBox, forgotBox].forEach(box => {
            if (box) box.classList.add('hidden');
        });
    }

    function showBox(box) {
        if (!authOverlay || !box) return;
        authOverlay.classList.add('active');
        hideBoxes();
        box.classList.remove('hidden');
    }

    function closeModal() {
        if (authOverlay) authOverlay.classList.remove('active');
        hidePasswords();
    }

    function showForgotSuccess(email) {
        if (!forgotFormWrap || !forgotSuccess) return;
        forgotFormWrap.classList.add('hidden');
        forgotSuccess.classList.remove('hidden');
        if (forgotHeader) forgotHeader.classList.add('hidden');
        if (resetEmailTxt) resetEmailTxt.textContent = email || '';
    }

    function resetForgotState() {
        if (!forgotFormWrap || !forgotSuccess) return;
        forgotFormWrap.classList.remove('hidden');
        forgotSuccess.classList.add('hidden');
        if (forgotHeader) forgotHeader.classList.remove('hidden');
    }

    if (openLogin) openLogin.addEventListener('click', (e) => { e.preventDefault(); showBox(loginBox); });
    if (openRegister) openRegister.addEventListener('click', (e) => { e.preventDefault(); showBox(registerBox); });
    if (switchRegister) switchRegister.addEventListener('click', (e) => { e.preventDefault(); showBox(registerBox); });
    if (switchLogin) switchLogin.addEventListener('click', (e) => { e.preventDefault(); showBox(loginBox); });
    if (openForgot) openForgot.addEventListener('click', (e) => { e.preventDefault(); showBox(forgotBox); });
    if (backLogin) backLogin.addEventListener('click', (e) => { e.preventDefault(); showBox(loginBox); });
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            setPasswordVisibility(toggle, toggle.getAttribute('aria-pressed') !== 'true');
        });
    });

    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));
    authOverlay?.addEventListener('click', (e) => { if (e.target === authOverlay) closeModal(); });
    closeForgotSuccess?.addEventListener('click', () => { closeModal(); resetForgotState(); });

    if (hasRegistrationError) {
        showBox(registerBox);
        registerBox?.querySelector('[aria-invalid="true"]')?.focus();
    } else if (hasResetError) {
        showBox(forgotBox);
    } else if (resetSent) {
        showBox(forgotBox);
        showForgotSuccess(resetEmail);
    } else if (hasLoginError) {
        showBox(loginBox);
    }

    window.AuthModal = {
        open: (type) => {
            if (type === 'register') return showBox(registerBox);
            if (type === 'forgot') return showBox(forgotBox);
            return showBox(loginBox);
        },
        close: closeModal,
        resetForgot: resetForgotState,
    };
})();
