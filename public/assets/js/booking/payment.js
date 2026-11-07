(() => {
  const root = document.body;
  if (!root) {
    return;
  }

  const holdExpiresAt = Number(root.dataset.holdExpiresAt || 0);
  const expiredUrl = root.dataset.expiredUrl || '';
  const baseTotal = Number(root.dataset.baseTotal || 0);
  const currencySuffix = root.dataset.currencySuffix || '';
  const promoUrl = root.dataset.promoUrl || '';
  const promoRemoveUrl = root.dataset.promoRemoveUrl || '';
  const msgEnterCode = root.dataset.msgEnterCode || 'Please enter a promo code.';
  const msgApplyFail = root.dataset.msgApplyFail || 'Promo apply failed.';
  const msgApplyError = root.dataset.msgApplyError || 'Promo apply error.';
  const msgAgree = root.dataset.msgAgree || 'Please accept the terms.';
  const msgTimeout = root.dataset.msgTimeout || 'Time expired';

  const countdownEl = document.getElementById('countdown');
  const btnPay = document.getElementById('btnPay');
  const btnConfirm = document.getElementById('confirmPay');
  const overlay = document.getElementById('confirmOverlay');
  const agreeConfirm = document.getElementById('agreeConfirm');
  const modalSeatsDisplay = document.getElementById('modalSeats');
  const paySeats = document.getElementById('paySeats');
  const btnApply = document.getElementById('btnApply');
  const promoCode = document.getElementById('promoCode');
  const promoRow = document.getElementById('promoRow');
  const promoValue = document.getElementById('promoValue');
  const grandTotalEl = document.getElementById('grandTotal');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  let promoDiscount = Number(root.dataset.promoDiscount || 0);
  let memberDiscount = Number(root.dataset.memberDiscount || 0);
  let appliedCode = (root.dataset.appliedCode || '').toUpperCase();
  let applyingPromo = false;
  const promoForm = document.getElementById('promoForm');
  const feedback = document.getElementById('promoFeedback');
  const appliedVoucher = document.getElementById('appliedVoucher');
  const removePromo = document.getElementById('removePromo');
  const voucherButtons = [...document.querySelectorAll('#promoList [data-code]')];
  const fmt = (value) => new Intl.NumberFormat('vi-VN').format(value) + currencySuffix;

  const setFeedback = (message, success = false) => {
    if (feedback) feedback.hidden = true;
    if (message) window.TDNotify.show(message, { type: success ? 'success' : 'error' });
  };
  const updatePromoControls = () => {
    const code = (promoCode?.value || '').trim().toUpperCase();
    if (btnApply) {
      btnApply.disabled = applyingPromo || !code || code === appliedCode;
      btnApply.textContent = applyingPromo ? 'Đang kiểm tra…' : code && code === appliedCode ? 'Đã áp dụng' : 'Áp dụng';
    }
    if (promoCode) promoCode.disabled = applyingPromo;
    if (removePromo) removePromo.disabled = applyingPromo;
    promoForm?.setAttribute('aria-busy', String(applyingPromo));
    voucherButtons.forEach(button => {
      const selected = button.dataset.code?.toUpperCase() === appliedCode;
      button.disabled = applyingPromo || selected;
      button.textContent = selected ? 'Đã áp dụng' : 'Áp dụng';
      button.setAttribute('aria-pressed', String(selected));
      button.closest('.voucher-card')?.classList.toggle('is-applied', selected);
    });
    if (btnPay) btnPay.disabled = applyingPromo || !document.querySelector('input[name="payment_method"]:checked')
      || holdExpiresAt <= Math.floor(Date.now() / 1000);
  };
  const renderDiscount = () => {
    if (promoRow) promoRow.style.display = appliedCode || promoDiscount > 0 ? 'flex' : 'none';
    if (promoValue) promoValue.textContent = '-' + fmt(promoDiscount);
    const summaryCode = document.getElementById('summaryPromoCode');
    if (summaryCode) summaryCode.textContent = appliedCode || (promoDiscount > 0 ? root.dataset.memberLevel || '' : '');
    const discountLabel = document.getElementById('summaryDiscountLabel');
    if (discountLabel) discountLabel.textContent = appliedCode ? 'Khuyến mãi' : 'Ưu đãi thành viên';
    const pointsEstimate = document.getElementById('pointsEstimate');
    if (pointsEstimate) pointsEstimate.textContent = new Intl.NumberFormat('vi-VN').format(Math.floor(Math.max(0, baseTotal - promoDiscount) / Math.max(1, Number(root.dataset.earnUnit || 1000))));
    if (grandTotalEl) grandTotalEl.textContent = fmt(Math.max(0, baseTotal - promoDiscount));
    const field = document.getElementById('payPromo');
    if (field) field.value = appliedCode;
    if (appliedVoucher) appliedVoucher.hidden = !appliedCode;
    updatePromoControls();
  };

  const tick = () => {
    if (!countdownEl || !btnPay) {
      return;
    }
    const remaining = Math.max(0, holdExpiresAt - Math.floor(Date.now() / 1000));
    if (remaining === 0) {
      btnPay.disabled = true;
      btnPay.textContent = msgTimeout;
      window.location.href = expiredUrl;
      return;
    }
    const m = String(Math.floor(remaining / 60)).padStart(2, '0');
    const s = String(remaining % 60).padStart(2, '0');
    countdownEl.textContent = `${m}:${s}`;
  };
  tick();
  setInterval(tick, 1000);

  document.querySelectorAll('input[name="payment_method"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      updatePromoControls();
    });
  });

  const applyPromo = async () => {
    if (applyingPromo) return;
    const code = (promoCode?.value || '').trim().toUpperCase();
    if (!code) {
      setFeedback(msgEnterCode);
      promoCode?.focus();
      return;
    }
    if (code === appliedCode) return;
    if (promoCode) promoCode.value = code;
    applyingPromo = true;
    setFeedback('');
    updatePromoControls();
    try {
      const res = await fetch(promoUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json'
        },
        body: JSON.stringify({ code, showtime_id: Number(root.dataset.showtimeId) })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        setFeedback((data.message || msgApplyFail) + (appliedCode ? ' Mã ' + appliedCode + ' vẫn đang được áp dụng.' : ''));
      } else {
        const discount = Number(data.discount);
        if (!Number.isFinite(discount) || discount < 0) throw new Error('Invalid discount');
        promoDiscount = Math.min(baseTotal, discount);
        appliedCode = (data.code || code).toUpperCase();
        const name = document.getElementById('appliedPromoName');
        const codeLabel = document.getElementById('appliedPromoCode');
        const discountLabel = document.getElementById('appliedPromoDiscount');
        if (name) name.textContent = data.name || code;
        if (codeLabel) codeLabel.textContent = appliedCode;
        if (discountLabel) discountLabel.textContent = fmt(promoDiscount);
        setFeedback('Đã áp dụng ưu đãi. Bạn tiết kiệm ' + fmt(promoDiscount) + '.', true);
      }
    } catch (e) {
      setFeedback(msgApplyError + (appliedCode ? ' Mã ' + appliedCode + ' vẫn đang được áp dụng.' : ' Vui lòng thử lại.'));
    } finally {
      applyingPromo = false;
      renderDiscount();
    }
  };

  promoForm?.addEventListener('submit', event => { event.preventDefault(); applyPromo(); });
  promoCode?.addEventListener('input', updatePromoControls);
  voucherButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (promoCode) {
        promoCode.value = btn.dataset.code || '';
      }
      applyPromo();
    });
  });
  removePromo?.addEventListener('click', async () => {
    if (applyingPromo) return;
    applyingPromo = true;
    updatePromoControls();
    try {
      const removeUrl = new URL(promoRemoveUrl, window.location.href);
      removeUrl.searchParams.set('showtime_id', root.dataset.showtimeId);
      const response = await fetch(removeUrl, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
      if (!response.ok) throw new Error('Remove failed');
      const data = await response.json();
      if (Number.isFinite(Number(data.discount))) memberDiscount = Number(data.discount);
      appliedCode = '';
      promoDiscount = memberDiscount;
      if (promoCode) promoCode.value = '';
      setFeedback('Đã bỏ mã khuyến mãi. Bạn có thể chọn ưu đãi khác.', true);
    } catch {
      setFeedback('Chưa thể bỏ mã. Vui lòng thử lại.');
    } finally {
      applyingPromo = false;
      renderDiscount();
      promoCode?.focus();
    }
  });
  document.getElementById('voucherSearch')?.addEventListener('input', event => {
    const normalize = value => value.normalize('NFD').replace(/\p{M}/gu, '').replace(/đ/gi, 'd').toLowerCase();
    const query = normalize(event.target.value.trim());
    let visible = 0;
    document.querySelectorAll('[data-promo-search]').forEach(card => {
      card.hidden = !normalize(card.dataset.promoSearch).includes(query);
      if (!card.hidden) visible++;
    });
    document.getElementById('voucherNoResults').hidden = visible > 0;
  });
  renderDiscount();

  const showModal = () => {
    const demoNote = document.getElementById('modalDemoNote');
    if (demoNote) demoNote.hidden = document.querySelector('input[name="payment_method"]:checked')?.dataset.demo !== 'true';
    const promoLine = document.getElementById('modalPromoRow');
    if (promoLine) promoLine.hidden = !appliedCode && promoDiscount <= 0;
    const promoCodeLabel = document.getElementById('modalPromoCode');
    if (promoCodeLabel) promoCodeLabel.textContent = appliedCode || (promoDiscount > 0 ? root.dataset.memberLevel || '' : '');
    const modalDiscountLabel = document.getElementById('modalDiscountLabel');
    if (modalDiscountLabel) modalDiscountLabel.textContent = appliedCode ? 'Khuyến mãi' : 'Ưu đãi thành viên';
    const promoDiscountLabel = document.getElementById('modalPromoValue');
    if (promoDiscountLabel) promoDiscountLabel.textContent = '-' + fmt(promoDiscount);
    const modalTotal = document.getElementById('modalTotal');
    if (modalTotal) {
      modalTotal.textContent = fmt(baseTotal - promoDiscount);
    }
    if (modalSeatsDisplay && paySeats) {
      modalSeatsDisplay.textContent = paySeats.value || 'N/A';
    }
    if (overlay) {
      overlay.style.display = 'flex';
    }
  };
  const hideModal = () => {
    if (overlay) {
      overlay.style.display = 'none';
    }
  };

  document.getElementById('closeModal')?.addEventListener('click', hideModal);
  document.getElementById('cancelModal')?.addEventListener('click', hideModal);

  btnPay?.addEventListener('click', () => {
    if (!btnPay.disabled) {
      showModal();
    }
  });

  btnConfirm?.addEventListener('click', () => {
    if (!agreeConfirm?.checked) {
      window.TDNotify.warning(msgAgree);
      return;
    }
    const promoField = document.getElementById('payPromo');
    const totalField = document.getElementById('payTotal');
    if (promoField) {
      promoField.value = appliedCode;
    }
    if (totalField) {
      totalField.value = baseTotal - promoDiscount;
    }
    const methodField = document.getElementById('payMethod');
    if (methodField) methodField.value = document.querySelector('input[name="payment_method"]:checked')?.value || '';
    hideModal();
    document.getElementById('payForm')?.submit();
  });
})();
