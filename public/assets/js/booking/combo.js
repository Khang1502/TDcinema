(() => {
  const root = document.body;
  if (!root) {
    return;
  }

  const holdExpiresAt = Number(root.dataset.holdExpiresAt || 0);
  const expiredUrl = root.dataset.expiredUrl || '';
  const ticketTotal = Number(root.dataset.ticketTotal || 0);
  const seatParam = root.dataset.seatParam || '';
  const paymentUrl = root.dataset.paymentUrl || '';
  const currencySuffix = root.dataset.currencySuffix || '';
  const maxComboQty = Number(root.dataset.maxComboQty || 10);

  const countdownEl = document.getElementById('countdown');
  const tick = () => {
    if (!countdownEl) {
      return;
    }
    const remaining = Math.max(0, holdExpiresAt - Math.floor(Date.now() / 1000));
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    countdownEl.textContent = `${m}:${s}`;
    if (remaining === 0) {
      if (payLink) payLink.setAttribute('aria-disabled', 'true');
      window.location.href = expiredUrl;
    }
  };
  tick();
  setInterval(tick, 1000);

  const combos = Array.from(document.querySelectorAll('.combo-item'));
  const summaryItems = document.getElementById('summaryItems');
  const grandTotal = document.getElementById('grandTotal');
  const payLink = document.getElementById('payLink');
  const ticketTotalEl = document.getElementById('ticketTotal');
  const fmt = (value) => new Intl.NumberFormat('vi-VN').format(value) + currencySuffix;

  const render = () => {
    if (!summaryItems || !grandTotal) {
      return;
    }
    summaryItems.querySelectorAll('.row.combo-line').forEach((row) => row.remove());
    let comboTotal = 0;
    combos.forEach((combo) => {
      const qtyEl = combo.querySelector('.qty-value');
      const qty = Number(qtyEl?.textContent || 0);
      if (qty > 0) {
        const price = Number(combo.dataset.price || 0);
        comboTotal += qty * price;
        const row = document.createElement('div');
        row.className = 'row combo-line';
        const name = combo.querySelector('.combo-name')?.textContent || '';
        row.innerHTML = `<span>${qty}x ${name}</span><span>${fmt(qty * price)}</span>`;
        summaryItems.appendChild(row);
      }
    });
    grandTotal.textContent = fmt(ticketTotal + comboTotal);
    if (ticketTotalEl) {
      ticketTotalEl.textContent = fmt(ticketTotal);
    }
    if (payLink && paymentUrl) {
      let query = '';
      if (comboTotal > 0) {
        query = `?combo_total=${comboTotal}`;
      }
      payLink.href = paymentUrl + query;
    }
  };

  combos.forEach((combo) => {
    const minus = combo.querySelector('.btn-minus');
    const plus = combo.querySelector('.btn-plus');
    const value = combo.querySelector('.qty-value');
    minus?.addEventListener('click', () => {
      if (!value) {
        return;
      }
      const next = Math.max(0, Number(value.textContent) - 1);
      value.textContent = String(next);
      render();
    });
    plus?.addEventListener('click', () => {
      if (!value) {
        return;
      }
      const next = Math.min(maxComboQty, Number(value.textContent) + 1);
      value.textContent = String(next);
      render();
    });
  });

  render();
})();
