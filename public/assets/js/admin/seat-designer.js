(() => {
  const root = document.getElementById('seatDesigner');
  if (!root) return;
  const $ = (id) => document.getElementById(id);
  const saved = $('savedAuditorium'), preview = $('previewAuditorium'), viewport = $('designerViewport');
  const tabs = [...root.querySelectorAll('[data-designer-tab]')];
  const seats = [...root.querySelectorAll('#savedSeatGrid [data-seat-id]')];
  const selected = new Set();
  const format = new Intl.NumberFormat('vi-VN');
  let mode = 'edit', anchor = null, zoom = 1, requestId = 0, previewController, previewTimer, saving = false;
  const notify = (type, message) => window.TDNotify?.[type](message);
  const json = async (response) => {
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Không thể thực hiện. Vui lòng thử lại.');
    return data;
  };
  function selectionChanged() {
    seats.forEach((seat) => { const active = selected.has(seat.dataset.seatId); seat.classList.toggle('seat-selected', active); seat.setAttribute('aria-pressed', String(active)); });
    $('seatSelectionCount').textContent = selected.size;
    $('designerApply').disabled = !selected.size || saving;
    const codes = seats.filter(s => selected.has(s.dataset.seatId)).map(s => s.dataset.code);
    $('designerSelectedCodes').textContent = codes.length ? codes.slice(0, 12).join(', ') + (codes.length > 12 ? ` và ${codes.length - 12} ghế khác` : '') : 'Chọn ghế trên sơ đồ để bắt đầu.';
  }
  seats.forEach((seat) => seat.addEventListener('click', (event) => {
    if (saving) return;
    const index = seats.indexOf(seat);
    if (event.shiftKey && anchor !== null) seats.slice(Math.min(index, anchor), Math.max(index, anchor) + 1).forEach(s => selected.add(s.dataset.seatId));
    else { const id = seat.dataset.seatId; selected.has(id) ? selected.delete(id) : selected.add(id); }
    anchor = index; selectionChanged();
  }));
  root.querySelectorAll('[data-select-row]').forEach(button => button.addEventListener('click', () => {
    if (saving) return;
    const row = seats.filter(s => s.dataset.row === button.dataset.selectRow), remove = row.every(s => selected.has(s.dataset.seatId));
    row.forEach(s => remove ? selected.delete(s.dataset.seatId) : selected.add(s.dataset.seatId)); selectionChanged();
  }));
  $('designerSelectAll').addEventListener('click', () => { if (!saving) { seats.forEach(s => selected.add(s.dataset.seatId)); selectionChanged(); } });
  $('designerClear').addEventListener('click', () => { if (!saving) { selected.clear(); anchor = null; selectionChanged(); } });
  root.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !saving) { selected.clear(); selectionChanged(); } });
  $('designerEditForm').addEventListener('submit', async (e) => {
    e.preventDefault(); if (saving || !selected.size) return;
    const form = e.currentTarget, fields = {};
    for (const key of ['seat_type', 'status', 'extra_price']) if (form.elements[key].value !== '') fields[key] = key === 'seat_type' ? form.elements[key].value : Number(form.elements[key].value);
    if (!Object.keys(fields).length) { notify('warning', 'Chọn loại ghế, trạng thái hoặc nhập phụ thu cần thay đổi.'); return; }
    const ids = [...selected]; saving = true; selectionChanged(); $('designerSaveState').textContent = 'Đang lưu…';
    try {
      await json(await fetch(root.dataset.updateUrl, { method:'PATCH', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({seats:ids.map(id => ({id,...fields}))}) }));
      seats.filter(s => ids.includes(s.dataset.seatId)).forEach(s => {
        if ('seat_type' in fields) s.dataset.type = fields.seat_type;
        if ('status' in fields) s.dataset.status = fields.status;
        if ('extra_price' in fields) s.dataset.fee = fields.extra_price;
        s.classList.remove('seat-normal','seat-vip','seat-couple'); s.classList.add(s.dataset.type === 'vip' ? 'seat-vip' : s.dataset.type === 'sweetbox' ? 'seat-couple' : 'seat-normal');
        s.classList.toggle('seat-inactive', s.dataset.status === '0');
        s.title = `${s.dataset.code} · ${s.dataset.type === 'vip' ? 'VIP' : s.dataset.type === 'sweetbox' ? 'Đôi' : 'Thường'} · ${s.dataset.status === '0' ? 'Bảo trì' : 'Hoạt động'} · Phụ thu ${format.format(Number(s.dataset.fee))} đ`;
        s.setAttribute('aria-label', s.title);
      });
      // Refresh derived pricing, counts and cross aisles from authoritative room data without reloading the page.
      try {
        const data = await json(await fetch(root.dataset.dataUrl, {headers:{Accept:'application/json'}}));
        const all = Object.values(data.rows).flat();
        $('designerMapCount').textContent = `${all.filter(s => s.status === 'active').length} đang hoạt động`;
        for (const [row, layout] of Object.entries(data.layout.rows)) saved.querySelector(`[data-select-row="${CSS.escape(row)}"]`)?.closest('.seat-row')?.classList.toggle('seat-row--cross-aisle', layout.cross_aisle);
        ['normal','vip','sweetbox'].forEach(type => {
          const typed = all.filter(s => s.type === type), fees = [...new Set(typed.map(s => Number(s.extra_price)))], input = $(`seatFee-${type}`);
          if (input) {
            input.value = fees.length <= 1 ? fees[0] || 0 : ''; input.dispatchEvent(new Event('input'));
            const card = input.closest('.seat-price-card'), small = card?.querySelector('.seat-price-title small');
            if (small) small.textContent = `${typed.length} ghế trong phòng`;
            card?.querySelector('.seat-price-mixed')?.remove();
            if (fees.length > 1) { const note=document.createElement('p'); note.className='seat-price-mixed';note.textContent=`Hiện có nhiều mức: ${format.format(Math.min(...fees))}–${format.format(Math.max(...fees))} đ. Nhập một mức để đồng bộ loại ghế này.`;input.after(note); }
          }
        });
      } catch { /* The confirmed change remains successful if only the statistics refresh fails. */ }
      $('designerSaveState').textContent = 'Đã lưu'; notify('success', `Đã cập nhật ${ids.length} ghế.`);
    } catch (error) { $('designerSaveState').textContent = 'Chưa lưu'; notify('error', error.message); }
    finally { saving = false; selectionChanged(); }
  });
  function setZoom(value) { zoom = Math.max(.2, Math.min(1.5, value)); $('designerCanvas').style.setProperty('--designer-zoom', zoom); $('designerZoomValue').textContent = `${Math.round(zoom * 100)}%`; }
  function fit() { const map = mode === 'generate' || (mode === 'copy' && !preview.hidden) ? $('previewSeatGrid') : $('savedSeatGrid'); const width = map ? parseFloat(map.style.getPropertyValue('--seat-map-min-width')) + 32 : viewport.clientWidth; setZoom(Math.min(1, Math.max(.2, (viewport.clientWidth - 24) / width))); }
  root.querySelectorAll('[data-zoom]').forEach(button => button.addEventListener('click', () => button.dataset.zoom === 'fit' ? fit() : setZoom(zoom + (button.dataset.zoom === 'in' ? .1 : -.1))));
  function renderPreview(data) {
    const grid = $('previewSeatGrid'), layout = data.layout;
    grid.replaceChildren(); grid.style.setProperty('--seat-layout', layout.template); grid.style.setProperty('--seat-map-width', `${layout.width}px`); grid.style.setProperty('--seat-map-min-width', `${layout.min_width}px`);
    const fragment = document.createDocumentFragment();
    for (const [label, seats] of Object.entries(data.rows)) {
      const row = document.createElement('div'); row.className = 'seat-row' + (layout.rows[label]?.cross_aisle ? ' seat-row--cross-aisle' : '');
      const left = document.createElement('div'); left.className = 'seat-row-label'; left.textContent = label; row.append(left);
      const inner = document.createElement('div'); inner.className = 'seat-row-seats';
      layout.aisles.forEach(column => { const gap = document.createElement('span'); gap.className='seat-aisle'; gap.style.gridColumn=column; gap.setAttribute('aria-hidden','true'); inner.append(gap); });
      seats.forEach(seat => { const el=document.createElement('span'); el.className='seat seat-preview '+(seat.type==='vip'?'seat-vip':seat.type==='sweetbox'?'seat-couple':'seat-normal')+(seat.status==='inactive'?' seat-inactive':''); el.textContent=seat.number; el.style.gridColumn=layout.columns[seat.number]; el.title=seat.code; inner.append(el); });
      row.append(inner, left.cloneNode(true)); fragment.append(row);
    }
    grid.append(fragment); saved.hidden=true; preview.hidden=false; fit();
  }
  const generateForm = $('designerGenerateForm');
  function cancelPreview() { clearTimeout(previewTimer); previewController?.abort(); requestId++; }
  async function generatePreview() {
    cancelPreview(); const id = requestId; const status = $('designerPreviewStatus'); $('designerGenerateSave').disabled = true;
    if (!generateForm.checkValidity()) { status.textContent='Nhập số hàng, số cột và phụ thu hợp lệ để xem trước.'; status.classList.add('is-error'); preview.hidden=true; return; }
    previewController = new AbortController(); status.textContent='Đang cập nhật bản xem trước…'; status.classList.remove('is-error');
    const params=new URLSearchParams(); ['rows_count','seats_per_row','aisles','default_type','default_fee'].forEach(k => params.set(k,generateForm.elements[k].value));
    try {
      const data=await json(await fetch(root.dataset.previewUrl+'?'+params,{signal:previewController.signal,headers:{Accept:'application/json'}}));
      if (id !== requestId || mode !== 'generate') return;
      renderPreview(data); status.textContent=`${data.total} vị trí · ${Object.keys(data.rows).length} hàng · Chưa lưu`; $('designerGenerateSave').disabled=false; $('designerMapCount').textContent=`${data.total} vị trí trong bản xem trước`;
    } catch(error) { if(error.name==='AbortError'||id!==requestId)return; status.textContent=error.message; status.classList.add('is-error'); preview.hidden=true; }
  }
  generateForm.addEventListener('input', () => { cancelPreview(); $('designerGenerateSave').disabled=true; previewTimer=setTimeout(generatePreview,300); });
  generateForm.addEventListener('submit', (e) => { if ($('designerGenerateSave').disabled) { e.preventDefault(); e.stopImmediatePropagation(); } });
  root.querySelectorAll('[data-preset]').forEach(button => button.addEventListener('click', () => {
    const [rows,columns,aisles]={compact:[6,10,'5'],standard:[10,16,'5,12'],large:[12,22,'6,17']}[button.dataset.preset];
    generateForm.elements.rows_count.value=rows;generateForm.elements.seats_per_row.value=columns;generateForm.elements.aisles.value=aisles;
    root.querySelectorAll('[data-preset]').forEach(b=>b.classList.toggle('is-active',b===button)); generatePreview();
  }));
  const copyForm = $('designerCopyForm'), copyCinema = $('designerCopyCinema'), copySource = $('designerCopySource');
  const copyRooms = JSON.parse(copyForm.dataset.roomOptions || '[]');
  copyCinema.addEventListener('change', () => {
    const rooms = copyRooms.filter(room => String(room.cinema_id) === copyCinema.value)
      .sort((a, b) => a.name.localeCompare(b.name, 'vi', {numeric:true}));
    copySource.replaceChildren(new Option(!copyCinema.value ? 'Chọn cụm rạp trước' : rooms.length ? 'Chọn phòng nguồn' : 'Rạp chưa có phòng nguồn', ''));
    rooms.forEach(room => copySource.add(new Option(`${room.name}${room.type ? ' · ' + room.type : ''}`, String(room.id))));
    copySource.disabled = !rooms.length;
    $('designerCopyRoomCount').textContent = `${rooms.length} phòng`;
    copyPreview();
  });
  copyForm.addEventListener('submit', event => {
    if ($('designerCopySave').disabled || !copySource.value) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  });
  async function copyPreview() {
    cancelPreview();const id=requestId, select=copySource, status=$('designerCopyStatus');$('designerCopySave').disabled=true;
    saved.hidden=false;preview.hidden=true;status.classList.remove('is-error');
    $('designerMapCount').textContent=`${seats.filter(s=>s.dataset.status==='1').length} đang hoạt động`;
    if(!select.value){status.textContent='Chưa chọn phòng nguồn.';fit();return;}
    previewController=new AbortController();status.textContent='Đang tải sơ đồ nguồn…';status.classList.remove('is-error');
    try {
      const data=await json(await fetch(select.dataset.urlTemplate.replace('__ROOM__',select.value),{signal:previewController.signal,headers:{Accept:'application/json'}}));if(id!==requestId||mode!=='copy')return;
      const total=Object.values(data.rows).flat().length;if(!total)throw new Error('Phòng nguồn chưa có ghế. Hãy chọn phòng khác.');
      renderPreview(data); status.textContent=`${data.room.name} · ${total} vị trí ghế`; $('designerCopySave').disabled=false; $('designerMapCount').textContent=`${total} vị trí của phòng nguồn`;
    }catch(error){if(error.name==='AbortError'||id!==requestId)return;status.textContent=error.message;status.classList.add('is-error');saved.hidden=false;preview.hidden=true;}
  }
  copySource.addEventListener('change',copyPreview);
  function changeTab(next) {
    cancelPreview();mode=next;tabs.forEach(t=>{const active=t.dataset.designerTab===mode;t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;});
    root.querySelectorAll('[data-designer-panel]').forEach(p=>p.hidden=p.dataset.designerPanel!==mode);$('designerPrices').hidden=mode!=='prices';
    saved.hidden=false;preview.hidden=true;$('designerMode').textContent=mode==='generate'?'Bản xem trước · chưa thay đổi sơ đồ đã lưu':mode==='copy'?'Xem trước sơ đồ nguồn trước khi sao chép':'Sơ đồ hiện tại · bấm ghế hoặc tên hàng để chọn';
    $('designerMapCount').textContent=`${seats.filter(s=>s.dataset.status==='1').length} đang hoạt động`;
    if(mode==='generate')generatePreview();else if(mode==='copy')copyPreview();else fit();
  }
  tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>changeTab(tab.dataset.designerTab));tab.addEventListener('keydown',e=>{if(['ArrowRight','ArrowLeft','Home','End'].includes(e.key)){e.preventDefault();const i=e.key==='Home'?0:e.key==='End'?tabs.length-1:(index+(e.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;tabs[i].focus();changeTab(tabs[i].dataset.designerTab);}});});
  root.querySelector('[data-open-generator]')?.addEventListener('click',()=>changeTab('generate'));
  new ResizeObserver(()=>fit()).observe(viewport);
  selectionChanged();changeTab(seats.length ? 'edit' : 'generate');
})();
