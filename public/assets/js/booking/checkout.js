(() => {
  const root = document.body;
  if (!root) {
    return;
  }

  const comboUrl = root.dataset.comboUrl || '';
  const holdUrl = root.dataset.holdUrl || '';
  const holdExpiresAt = Number(root.dataset.holdExpiresAt || 0);
  let initialSeats = [];
  try { initialSeats = JSON.parse(root.dataset.initialSeats || '[]'); } catch (e) { initialSeats = []; }
  const maxSeats = Number(root.dataset.maxSeats || 0);
  const msgSeatRequired = root.dataset.msgSeatRequired || 'Please select at least one seat.';
  const msgSeatLimit = root.dataset.msgSeatLimit || 'Seat limit reached.';
  const currencySuffix = root.dataset.currencySuffix || '';

  const openLogin = document.getElementById('openLogin');
  const authOverlay = document.getElementById('authOverlay');
  const loginModal = document.getElementById('loginModal');
  const registerModal = document.getElementById('registerModal');
  const openRegister = document.getElementById('openRegister');
  const backLogin = document.getElementById('backLogin');
  const closeLogin = document.getElementById('closeLogin');
  const closeRegister = document.getElementById('closeRegister');

  const showOverlay = (target) => {
    if (!authOverlay || !target) {
      return;
    }
    if (loginModal) {
      loginModal.style.display = 'none';
    }
    if (registerModal) {
      registerModal.style.display = 'none';
    }
    target.style.display = 'block';
    authOverlay.classList.add('active');
  };
  const closeOverlay = () => {
    authOverlay?.classList.remove('active');
  };
  openLogin?.addEventListener('click', (e) => {
    e.preventDefault();
    showOverlay(loginModal);
  });
  openRegister?.addEventListener('click', (e) => {
    e.preventDefault();
    showOverlay(registerModal);
  });
  backLogin?.addEventListener('click', (e) => {
    e.preventDefault();
    showOverlay(loginModal);
  });
  closeLogin?.addEventListener('click', closeOverlay);
  closeRegister?.addEventListener('click', closeOverlay);
  authOverlay?.addEventListener('click', (e) => {
    if (e.target === authOverlay) {
      closeOverlay();
    }
  });

  const seatButtons = Array.from(document.querySelectorAll('.seat:not(.sold):not(.held):not(.inactive)'));
  const selectedList = document.getElementById('selectedList');
  const emptySelected = document.getElementById('emptySelected');
  const totalPriceEl = document.getElementById('totalPrice');
  const selectedCountEl = document.getElementById('selectedCount');
  const seatAlert = document.getElementById('seatAlert');
  const btnContinue = document.getElementById('btnContinue');
  const seatLimit = maxSeats > 0 ? maxSeats : 8;
  const selected = [];

  if (holdExpiresAt > 0) {
    const countdownEl = document.getElementById('countdown');
    const tick = () => {
      const remaining = Math.max(0, holdExpiresAt - Math.floor(Date.now() / 1000));
      if (countdownEl) {
        const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
        const seconds = String(remaining % 60).padStart(2, '0');
        countdownEl.textContent = `${minutes}:${seconds}`;
      }
      if (remaining === 0) window.location.reload();
    };
    tick();
    window.setInterval(tick, 1000);
  }

  const currency = (value) => new Intl.NumberFormat('vi-VN').format(value) + currencySuffix;
  const render = () => {
    if (!selectedList || !emptySelected || !totalPriceEl) {
      return;
    }
    selectedList.innerHTML = '';
    if (selected.length === 0) {
      emptySelected.style.display = 'block';
    } else {
      emptySelected.style.display = 'none';
      selected.forEach((seat) => {
        const chip = document.createElement('div');
        chip.className = 'chip';
        chip.textContent = `${seat.code} (${currency(seat.price)})`;
        selectedList.appendChild(chip);
      });
    }
    const total = selected.reduce((sum, seat) => sum + Number(seat.price || 0), 0);
    totalPriceEl.textContent = currency(total);
    if (selectedCountEl) {
      selectedCountEl.textContent = `${selected.length}/${seatLimit}`;
    }
  };

  const toggleSeat = (btn) => {
    const id = btn.dataset.seatId;
    const code = btn.dataset.code;
    const price = Number(btn.dataset.price || 0);
    const idx = selected.findIndex((seat) => seat.id === id);
    if (seatAlert) {
      seatAlert.style.display = 'none';
    }
    if (idx >= 0) {
      selected.splice(idx, 1);
      btn.classList.remove('sel');
      btn.setAttribute('aria-pressed', 'false');
    } else {
      if (selected.length >= seatLimit) {
        window.TDNotify.warning(msgSeatLimit.replace('{max}', String(seatLimit)));
        return;
      }
      selected.push({ id, code, price });
      btn.classList.add('sel');
      btn.setAttribute('aria-pressed', 'true');
    }
    render();
  };

  seatButtons.forEach((btn) => btn.addEventListener('click', () => toggleSeat(btn)));

  initialSeats.forEach((code) => {
    const button = seatButtons.find((seat) => seat.dataset.code === code);
    if (button) toggleSeat(button);
  });

  btnContinue?.addEventListener('click', async () => {
    if (selected.length === 0) {
      window.TDNotify.warning(msgSeatRequired);
      return;
    }
    if (!comboUrl || !holdUrl) {
      return;
    }
    const seatCodes = selected.map((seat) => seat.code).join(',');
    btnContinue.disabled = true;
    btnContinue.textContent = 'Đang giữ ghế...';
    try {
      const response = await fetch(holdUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ seats: seatCodes.split(',') })
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.errors?.seats?.[0] || data.message || 'Không thể giữ ghế.');
      }
      window.location.href = data.redirect || comboUrl;
    } catch (error) {
      window.TDNotify.error(error.message || 'Không thể giữ ghế. Vui lòng thử lại.');
      btnContinue.disabled = false;
      btnContinue.textContent = 'Tiếp tục';
    }
  });

  render();
})();
