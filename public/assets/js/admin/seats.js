(() => {
  const pricingForm = document.getElementById('seatPricingForm');
  const readResponse = async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Không thể cập nhật ghế. Vui lòng thử lại.');
    return data;
  };
  if (pricingForm) {
    const basePrice = pricingForm.dataset.basePrice === '' ? null : Number(pricingForm.dataset.basePrice);
    const priceFormat = new Intl.NumberFormat('vi-VN');
    pricingForm.querySelectorAll('[data-seat-fee]').forEach((input) => {
      const output = pricingForm.querySelector(`[data-seat-price="${input.dataset.seatFee}"]`);
      const updatePrice = () => {
        if (!output) return;
        output.textContent = basePrice === null ? 'Giá cơ bản + phụ thu'
          : (input.value !== '' && input.validity.valid ? `${priceFormat.format(basePrice + Number(input.value))} đ` : 'Nhập phụ thu hợp lệ');
      };
      input.addEventListener('input', updatePrice);
      updatePrice();
    });
  }
  const bulkConfig = document.getElementById('seatBulkConfig');
  if (bulkConfig) {
    const seats = document.querySelectorAll('.seat-grid .seat');
    const btnNormal = document.getElementById('btnSetNormal');
    const btnVip = document.getElementById('btnSetVip');
    const btnCouple = document.getElementById('btnSetCouple');
    const btnInactive = document.getElementById('btnSetInactive');
    const selectedIds = new Set();
    const selectedCount = document.getElementById('seatSelectionCount');

    const bulkUpdateUrl = bulkConfig.dataset.bulkUpdateUrl || '';
    const msgSelectSeat = bulkConfig.dataset.msgSelectSeat || 'Hay chon it nhat 1 ghe.';
    const msgUpdateError = bulkConfig.dataset.msgUpdateError || 'Co loi khi cap nhat ghe.';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const updateButtons = () => {
      const hasSel = selectedIds.size > 0;
      if (selectedCount) selectedCount.textContent = String(selectedIds.size);
      [btnNormal, btnVip, btnCouple, btnInactive].forEach((btn) => {
        if (btn) {
          btn.disabled = !hasSel;
        }
      });
    };

    const toggleSeat = (el) => {
      const id = el.dataset.seatId;
      if (!id) {
        return;
      }
      if (selectedIds.has(id)) {
        selectedIds.delete(id);
        el.classList.remove('seat-selected');
        el.setAttribute('aria-pressed', 'false');
      } else {
        selectedIds.add(id);
        el.classList.add('seat-selected');
        el.setAttribute('aria-pressed', 'true');
      }
      updateButtons();
    };

    seats.forEach((el) => {
      el.style.cursor = 'pointer';
      el.addEventListener('click', (event) => {
        toggleSeat(el);
        event.stopPropagation();
      });
    });

    const applyBulk = async (type = null, status = null) => {
      if (!selectedIds.size) {
        window.TDNotify.warning(msgSelectSeat);
        return;
      }
      const seatsPayload = Array.from(selectedIds).map((id) => {
        const item = { id };
        if (type) {
          item.seat_type = type;
        }
        if (status !== null) {
          item.status = status;
        }
        return item;
      });
      try {
        const res = await fetch(bulkUpdateUrl, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: JSON.stringify({ seats: seatsPayload })
        });
        await readResponse(res);
        window.TDNotify.flash('Đã cập nhật những ghế đã chọn.', { type: 'success' });
        location.reload();
      } catch (err) {
        window.TDNotify.error(err.message || msgUpdateError);
      }
    };

    btnNormal?.addEventListener('click', () => applyBulk('normal', 1));
    btnVip?.addEventListener('click', () => applyBulk('vip', 1));
    btnCouple?.addEventListener('click', () => applyBulk('sweetbox', 1));
    btnInactive?.addEventListener('click', () => applyBulk(null, 0));

    updateButtons();
  }

  const mapConfig = document.getElementById('seatMapConfig');
  if (mapConfig) {
    const dataUrl = mapConfig.dataset.dataUrl || '';
    const bulkUpdateUrl = mapConfig.dataset.bulkUpdateUrl || '';
    const bulkDeleteUrl = mapConfig.dataset.bulkDeleteUrl || '';
    const msgTypePrompt = mapConfig.dataset.msgTypePrompt || 'Loai ghe (normal/vip/sweetbox) - bo trong neu khong doi:';
    const msgStatusPrompt = mapConfig.dataset.msgStatusPrompt || 'Trang thai (active/inactive) - bo trong neu khong doi:';
    const msgFeePrompt = mapConfig.dataset.msgFeePrompt || 'Phu thu (bo trong neu khong doi):';
    const msgBulkDeleteConfirm = mapConfig.dataset.msgBulkDeleteConfirm || 'Tiep tuc?';
    const seatFeeLabel = mapConfig.dataset.seatFeeLabel || 'phi thu';
    const currencySuffix = mapConfig.dataset.currencySuffix || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let seatRows = {};
    let seatLayout = {};
    let selected = new Set();
    let isMouseDown = false;

    const seatGrid = document.getElementById('seatGrid');
    const filterTypeEl = document.getElementById('filterType');
    const filterStatusEl = document.getElementById('filterStatus');

    const seatClass = (type) => {
      if (type === 'vip') return 'seat-vip';
      if (type === 'sweetbox') return 'seat-couple';
      return 'seat-normal';
    };

    const updateBulkButtons = () => {
      const has = selected.size > 0;
      const selectedCount = document.getElementById('seatSelectionCount');
      if (selectedCount) selectedCount.textContent = String(selected.size);
      const btnBulkUpdate = document.getElementById('btnBulkUpdate');
      const btnBulkDelete = document.getElementById('btnBulkDelete');
      if (btnBulkUpdate) btnBulkUpdate.disabled = !has;
      if (btnBulkDelete) btnBulkDelete.disabled = !has;
    };

    const toggleSelect = (id, el) => {
      if (selected.has(id)) {
        selected.delete(id);
        el.classList.remove('seat-selected');
        el.setAttribute('aria-pressed', 'false');
      } else {
        selected.add(id);
        el.classList.add('seat-selected');
        el.setAttribute('aria-pressed', 'true');
      }
      updateBulkButtons();
    };

    const renderGrid = () => {
      if (!seatGrid) {
        return;
      }
      const filterType = filterTypeEl ? filterTypeEl.value : '';
      const filterStatus = filterStatusEl ? filterStatusEl.value : '';
      seatGrid.innerHTML = '';
      seatGrid.style.setProperty('--seat-layout', seatLayout.template || '');
      seatGrid.style.setProperty('--seat-map-width', `${seatLayout.width || 620}px`);
      seatGrid.style.setProperty('--seat-map-min-width', `${seatLayout.min_width || 620}px`);

      const rows = Object.keys(seatRows || {}).sort();
      rows.forEach((rowLabel) => {
        const rowDiv = document.createElement('div');
        rowDiv.className = 'seat-row';
        if (seatLayout.rows?.[rowLabel]?.cross_aisle) rowDiv.classList.add('seat-row--cross-aisle');

        const label = document.createElement('div');
        label.className = 'seat-row-label';
        label.textContent = rowLabel;
        rowDiv.appendChild(label);

        const rowSeats = document.createElement('div');
        rowSeats.className = 'seat-row-seats';
        (seatLayout.aisles || []).forEach((column) => {
          const aisle = document.createElement('span');
          aisle.className = 'seat-aisle';
          aisle.style.gridColumn = column;
          aisle.setAttribute('aria-hidden', 'true');
          rowSeats.appendChild(aisle);
        });

        seatRows[rowLabel].forEach((seat) => {
          if (filterType && seat.type !== filterType) return;
          if (filterStatus && seat.status !== filterStatus) return;

          const div = document.createElement('button');
          div.type = 'button';
          div.className = `seat ${seatClass(seat.type)}`;
          div.style.gridColumn = seatLayout.columns?.[seat.number] || 'auto';
          if (seat.status === 'inactive') div.classList.add('seat-inactive');
          if (selected.has(seat.id)) div.classList.add('seat-selected');
          div.textContent = seat.number;
          const feeText = `${seatFeeLabel}: ${seat.extra_price.toLocaleString()} ${currencySuffix}`.trim();
          div.title = `${seat.code} - ${feeText}`.trim();
          div.setAttribute('aria-label', div.title);
          div.setAttribute('aria-pressed', selected.has(seat.id) ? 'true' : 'false');

          div.dataset.id = seat.id;
          div.dataset.code = seat.code;
          div.dataset.type = seat.type;
          div.dataset.status = seat.status;
          div.dataset.fee = seat.extra_price;

          div.addEventListener('mousedown', () => {
            isMouseDown = true;
            toggleSelect(seat.id, div);
          });
          div.addEventListener('mouseover', () => {
            if (isMouseDown) toggleSelect(seat.id, div);
          });
          div.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey) {
              toggleSelect(seat.id, div);
            } else {
              openSeatModal(div);
            }
          });

          rowSeats.appendChild(div);
        });

        rowDiv.appendChild(rowSeats);
        const endLabel = label.cloneNode(true);
        endLabel.setAttribute('aria-hidden', 'true');
        rowDiv.appendChild(endLabel);
        seatGrid.appendChild(rowDiv);
      });
      updateBulkButtons();
    };

    const loadSeats = () => {
      fetch(dataUrl)
        .then(readResponse)
        .then((json) => {
          seatRows = json.rows || {};
          seatLayout = json.layout || {};
          selected.clear();
          renderGrid();
        }).catch(error => window.TDNotify.error(error.message || 'Không tải được sơ đồ ghế.'));
    };

    const openSeatModal = (el) => {
      const modal = document.getElementById('seatModal');
      if (!modal) return;
      document.getElementById('seatModalId').value = el.dataset.id;
      document.getElementById('seatModalCode').textContent = el.dataset.code;
      document.getElementById('seatModalType').value = el.dataset.type;
      document.getElementById('seatModalStatus').value = el.dataset.status;
      document.getElementById('seatModalFee').value = el.dataset.fee || 0;
      modal.classList.add('active');
    };

    const closeSeatModal = () => {
      document.getElementById('seatModal')?.classList.remove('active');
    };

    const saveSingleSeat = () => {
      const id = document.getElementById('seatModalId').value;
      const type = document.getElementById('seatModalType').value;
      const status = document.getElementById('seatModalStatus').value === 'active' ? 1 : 0;
      const fee = parseInt(document.getElementById('seatModalFee').value || 0, 10);
      fetch(bulkUpdateUrl, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ seats: [{ id, seat_type: type, status, extra_price: fee }] })
      }).then(readResponse).then(() => {
        closeSeatModal();
        loadSeats();
        window.TDNotify.success('Đã cập nhật ghế.');
      }).catch(error => window.TDNotify.error(error.message));
    };

    const openBulkUpdate = () => {
      if (!selected.size) return;
      const type = prompt(msgTypePrompt, '');
      if (type === null) return;
      const status = prompt(msgStatusPrompt, '');
      if (status === null) return;
      const fee = prompt(msgFeePrompt, '');
      if (fee === null) return;

      const payload = Array.from(selected).map((id) => {
        const item = { id };
        if (type) item.seat_type = type;
        if (status) item.status = status === 'active' ? 1 : (status === 'inactive' ? 0 : null);
        if (fee !== '') item.extra_price = parseInt(fee, 10) || 0;
        return item;
      });

      fetch(bulkUpdateUrl, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ seats: payload })
      }).then(readResponse).then(() => {
        selected.clear();
        loadSeats();
        window.TDNotify.success('Đã cập nhật những ghế đã chọn.');
      }).catch(error => window.TDNotify.error(error.message));
    };

    const bulkDelete = async () => {
      if (!selected.size) return;
      if (!await window.TDNotify.confirm(msgBulkDeleteConfirm, { confirmLabel: 'Xóa ghế' })) return;
      fetch(bulkDeleteUrl, {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ seat_ids: Array.from(selected) })
      }).then(readResponse).then((data) => {
        selected.clear();
        loadSeats();
        window.TDNotify.success(data.message || 'Đã xử lý những ghế đã chọn.');
      }).catch(error => window.TDNotify.error(error.message));
    };

    document.addEventListener('DOMContentLoaded', () => {
      loadSeats();
      document.addEventListener('mouseup', () => { isMouseDown = false; });
      filterTypeEl?.addEventListener('change', renderGrid);
      filterStatusEl?.addEventListener('change', renderGrid);
      document.getElementById('btnBulkUpdate')?.addEventListener('click', openBulkUpdate);
      document.getElementById('btnBulkDelete')?.addEventListener('click', bulkDelete);
      document.getElementById('saveSeatModal')?.addEventListener('click', saveSingleSeat);
      document.getElementById('closeSeatModal')?.addEventListener('click', closeSeatModal);
      document.getElementById('seatModal')?.addEventListener('click', (event) => {
        if (event.target && event.target.id === 'seatModal') {
          closeSeatModal();
        }
      });
    });
  }
})();

