document.addEventListener('DOMContentLoaded', () => {
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLowerCase().trim();

    document.querySelectorAll('[data-genre-picker]').forEach(picker => {
        const summary = picker.querySelector('summary');
        const text = picker.querySelector('[data-genre-summary]');
        const count = picker.querySelector('[data-genre-count]');
        const search = picker.querySelector('[data-genre-search]');
        const options = [...picker.querySelectorAll('[data-genre-option]')];
        const inputs = options.map(option => option.querySelector('input'));
        const clear = picker.querySelector('[data-genre-clear]');
        const limit = picker.parentElement.querySelector('[data-genre-limit]');
        const update = () => {
            const selected = inputs.filter(input => input.checked).map(input => input.value);
            const value = selected.join(', ');
            text.textContent = value || 'Chọn thể loại phim';
            text.classList.toggle('is-placeholder', !selected.length);
            count.textContent = selected.length;
            count.hidden = !selected.length;
            clear.disabled = !selected.length;
            const tooLong = selected.length > 20 || Array.from(value).length > 100;
            limit.hidden = !tooLong;
            inputs[0]?.setCustomValidity(tooLong ? 'Bạn đã chọn quá nhiều thể loại. Vui lòng bỏ bớt để tiếp tục.' : '');
            summary.setAttribute('aria-invalid', String(tooLong));
        };
        const filter = () => {
            const term = normalize(search.value);
            options.forEach(option => { option.hidden = !normalize(option.textContent).includes(term); });
            picker.querySelector('[data-genre-empty]').hidden = options.some(option => !option.hidden);
        };
        inputs.forEach(input => input.addEventListener('change', update));
        search.addEventListener('input', filter);
        clear.addEventListener('click', () => { inputs.forEach(input => { input.checked = false; }); update(); search.focus({preventScroll:true}); });
        const close = () => { picker.open = false; summary.focus({preventScroll:true}); };
        picker.querySelector('[data-genre-done]').addEventListener('click', close);
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && picker.open) { event.preventDefault(); close(); } });
        picker.addEventListener('toggle', () => { if (picker.open) { search.value = ''; filter(); } });
        picker.addEventListener('invalid', () => { picker.open = true; search.value = ''; filter(); }, true);
        document.addEventListener('click', event => { if (!picker.contains(event.target)) picker.open = false; });
        picker.addEventListener('focusout', event => { if (event.relatedTarget && !picker.contains(event.relatedTarget)) picker.open = false; });
        inputs[0]?.form?.addEventListener('reset', () => queueMicrotask(update));
        update();
    });

    document.querySelectorAll('[data-city-picker]').forEach(picker => {
        const input = picker.querySelector('input[name="city"]');
        const toggle = picker.querySelector('[data-city-toggle]');
        const menu = picker.querySelector('[data-city-menu]');
        const options = [...picker.querySelectorAll('[data-city-option]')];
        let active = null;
        const highlight = option => {
            options.forEach(item => item.classList.toggle('is-highlighted', item === option));
            active = option;
            if (option) {
                input.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({block:'nearest'});
            } else input.removeAttribute('aria-activedescendant');
        };
        const setOpen = open => {
            menu.hidden = !open;
            picker.classList.toggle('is-open', open);
            input.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-expanded', String(open));
            toggle.setAttribute('aria-label', `${open ? 'Đóng' : 'Mở'} danh sách tỉnh/thành phố`);
            if (!open) highlight(null);
        };
        const citySearchKey = value => normalize(value).replace(/[\s.,-]/g, '');
        const refresh = filter => {
            const term = filter ? citySearchKey(input.value) : '';
            options.forEach(option => {
                option.hidden = !citySearchKey(option.dataset.search || option.dataset.value).includes(term);
                option.setAttribute('aria-selected', String(normalize(option.dataset.value) === normalize(input.value)));
            });
            const visible = options.filter(option => !option.hidden);
            picker.querySelector('[data-city-result-count]').textContent = `${visible.length} gợi ý`;
            picker.querySelector('[data-city-empty]').hidden = !!visible.length;
            highlight(null);
        };
        const choose = option => {
            input.value = option.dataset.value;
            input.dispatchEvent(new Event('input', {bubbles:true}));
            input.dispatchEvent(new Event('change', {bubbles:true}));
            setOpen(false);
            input.focus({preventScroll:true});
            setOpen(false);
        };
        input.addEventListener('focus', () => { refresh(false); setOpen(true); });
        input.addEventListener('input', () => { refresh(true); setOpen(true); });
        toggle.addEventListener('click', () => {
            const wasOpen = !menu.hidden;
            input.focus({preventScroll:true});
            refresh(false);
            setOpen(!wasOpen);
        });
        menu.addEventListener('pointerdown', event => { if (event.target.closest('[data-city-option]')) event.preventDefault(); });
        menu.addEventListener('click', event => { const option = event.target.closest('[data-city-option]'); if (option) choose(option); });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { setOpen(false); event.preventDefault(); return; }
            if (event.key === 'Enter' && !menu.hidden) {
                event.preventDefault();
                active ? choose(active) : setOpen(false);
                return;
            }
            if (event.key === 'Tab') { setOpen(false); return; }
            if (!['ArrowDown','ArrowUp'].includes(event.key)) return;
            event.preventDefault();
            if (menu.hidden) { refresh(false); setOpen(true); }
            const visible = options.filter(option => !option.hidden);
            if (!visible.length) return;
            const index = visible.indexOf(active);
            highlight(event.key === 'ArrowDown' ? visible[(index + 1) % visible.length] : visible[(index <= 0 ? visible.length : index) - 1]);
        });
        picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) setOpen(false); });
        document.addEventListener('click', event => { if (!picker.contains(event.target)) setOpen(false); });
        input.form?.addEventListener('reset', () => queueMicrotask(() => { refresh(false); setOpen(false); }));
    });
});
