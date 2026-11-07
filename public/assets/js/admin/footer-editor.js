document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-footer-editor]');
    if (!form) return;
    const tabs = [...form.querySelectorAll('[data-footer-tab]')];
    const panels = [...form.querySelectorAll('[data-footer-panel]')];
    const sections = [...form.querySelectorAll('[data-footer-links]')];
    const savebar = document.querySelector('[data-footer-savebar]');
    const save = savebar.querySelector('[data-footer-save]');
    const reset = savebar.querySelector('[data-footer-reset]');
    const status = savebar.querySelector('[data-footer-save-status]');
    let initial, submitting = false;
    const snapshot = () => JSON.stringify([...new FormData(form).entries()].filter(([key]) => !['_token','_method'].includes(key)));
    const changed = () => initial !== snapshot();
    const updateDirty = () => {
        if (!initial) return;
        const dirty = changed();
        save.disabled = !dirty;
        reset.hidden = !dirty;
        status.textContent = dirty ? 'Có thay đổi chưa lưu' : 'Nội dung đã lưu trên website';
        status.classList.toggle('is-dirty', dirty);
        form.querySelector('[data-footer-visibility]').textContent = form.elements.is_visible.value === '1' ? 'Đang hiển thị' : 'Đang tạm ẩn';
    };
    const show = (key, focus = false) => {
        if (!tabs.some(tab => tab.dataset.footerTab === key)) key = 'brand';
        tabs.forEach(tab => { const active = tab.dataset.footerTab === key; tab.classList.toggle('is-active',active); tab.setAttribute('role','tab'); tab.setAttribute('aria-selected',String(active)); tab.tabIndex = active ? 0 : -1; });
        panels.forEach(panel => { panel.hidden = panel.dataset.footerPanel !== key; panel.setAttribute('role','tabpanel'); });
        try { sessionStorage.setItem('td-footer-section',key); } catch {}
        if (focus) tabs.find(tab => tab.dataset.footerTab === key).focus();
    };
    form.querySelector('.fe-tabs').setAttribute('role','tablist');
    tabs.forEach((tab,index) => {
        tab.addEventListener('click', () => show(tab.dataset.footerTab));
        tab.addEventListener('keydown', event => {
            let next;
            if (['ArrowDown','ArrowRight'].includes(event.key)) next = (index+1)%tabs.length;
            if (['ArrowUp','ArrowLeft'].includes(event.key)) next = (index+tabs.length-1)%tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length-1;
            if (next !== undefined) { event.preventDefault(); show(tabs[next].dataset.footerTab,true); }
        });
    });
    sections.forEach(section => {
        const list = section.querySelector('[data-link-list]');
        const add = section.querySelector('[data-add-link]');
        section.originalRows = list.innerHTML;
        const refresh = () => {
            const rows = [...list.children];
            rows.forEach((row,index) => {
                row.querySelector('[data-link-number]').textContent = index+1;
                row.querySelectorAll('[data-field]').forEach(input => { input.name = `${section.dataset.footerLinks}_links[${index}][${input.dataset.field}]`; });
                row.querySelector('[data-move-link="up"]').disabled = index === 0;
                row.querySelector('[data-move-link="down"]').disabled = index === rows.length-1;
                row.querySelector('[data-remove-link]').setAttribute('aria-label',`Xóa liên kết ${index+1}`);
            });
            add.disabled = rows.length >= 8;
            section.querySelector('[data-link-count]').textContent = `${rows.length} / 8 liên kết`;
            section.querySelector('[data-links-empty]').hidden = rows.length !== 0;
            section.querySelector('[data-links-limit]').textContent = rows.length >= 8 ? 'Đã đạt tối đa 8 liên kết.' : 'Đường dẫn nội bộ bắt đầu bằng /, ví dụ /movies.';
            updateDirty();
        };
        section.refreshRows = refresh;
        list.addEventListener('click', event => {
            const button = event.target.closest('button');
            const row = button?.closest('.fe-link-row');
            if (!row) return;
            if (button.hasAttribute('data-remove-link')) { row.remove(); refresh(); add.focus(); return; }
            if (button.dataset.moveLink === 'up' && row.previousElementSibling) list.insertBefore(row,row.previousElementSibling);
            if (button.dataset.moveLink === 'down' && row.nextElementSibling) list.insertBefore(row.nextElementSibling,row);
            refresh(); button.focus();
        });
        add.addEventListener('click', () => {
            if (list.children.length >= 8) return;
            list.append(section.querySelector('template').content.cloneNode(true));
            refresh(); list.lastElementChild.querySelector('input').focus();
        });
        refresh();
    });
    let active = 'brand'; try { active = sessionStorage.getItem('td-footer-section') || 'brand'; } catch {}
    const invalid = form.querySelector('[aria-invalid="true"]');
    show(invalid?.closest('[data-footer-panel]')?.dataset.footerPanel || active);
    initial = snapshot(); updateDirty();
    form.addEventListener('input',updateDirty); form.addEventListener('change',updateDirty);
    form.addEventListener('invalid', event => { const panel = event.target.closest('[data-footer-panel]'); if (panel) show(panel.dataset.footerPanel); },true);
    reset.addEventListener('click', () => {
        form.reset(); sections.forEach(section => { section.querySelector('[data-link-list]').innerHTML = section.originalRows; section.refreshRows(); }); updateDirty();
    });
    form.addEventListener('submit', () => { submitting = true; save.disabled = true; status.textContent = 'Đang lưu thay đổi...'; });
    window.addEventListener('beforeunload', event => { if (!submitting && changed()) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pageshow', () => { submitting = false; updateDirty(); });
});
