(() => {
    if (window.TDNotify) return;
    const root = document.getElementById('td-notifications');
    if (!root) return;
    const list = root.querySelector('[data-notifications-list]');
    const toolbar = root.querySelector('.td-notifications__toolbar');
    const titles = { success: 'Thành công', error: 'Có lỗi xảy ra', warning: 'Lưu ý', info: 'Thông tin' };
    const durations = { success: 5000, error: 0, warning: 8000, info: 6000 };
    const icons = { success: 'm5 12 4 4L19 6', error: 'm8 8 8 8m0-8-8 8', warning: 'M12 7v6m0 4h.01', info: 'M12 11v6m0-10h.01' };
    const entries = new Map();
    let nextId = 0;
    const updateRoot = () => { root.hidden = entries.size === 0; toolbar.hidden = entries.size < 2; };
    const close = id => {
        const entry = entries.get(id);
        if (!entry) return;
        clearTimeout(entry.timer);
        entry.animation?.cancel();
        entry.element.remove();
        entries.delete(id);
        updateRoot();
    };
    const closeAll = () => [...entries.keys()].forEach(close);
    const resume = entry => {
        if (!entry.remaining || entry.paused.size || entry.timer) return;
        entry.startedAt = performance.now();
        entry.animation?.play();
        entry.timer = setTimeout(() => close(entry.id), entry.remaining);
    };
    const pause = (entry, reason) => {
        entry.paused.add(reason);
        if (entry.timer) {
            clearTimeout(entry.timer);
            entry.timer = null;
            entry.remaining = Math.max(1, entry.remaining - (performance.now() - entry.startedAt));
        }
        entry.animation?.pause();
    };
    const unpause = (entry, reason) => { entry.paused.delete(reason); resume(entry); };
    const textElement = (tag, className, value) => {
        const element = document.createElement(tag);
        element.className = className;
        element.textContent = value;
        return element;
    };
    const show = (message, options = {}) => {
        if (typeof options === 'string') options = { type: options };
        if (typeof message !== 'string' || !message.trim()) return null;
        const type = titles[options.type] ? options.type : 'info';
        const title = options.title || titles[type];
        const details = Array.isArray(options.details) ? options.details.filter(value => typeof value === 'string') : [];
        const key = JSON.stringify([type, title, message, details]);
        for (const entry of entries.values()) if (entry.key === key) return entry.id;
        const element = document.createElement('div');
        element.className = `td-notice td-notice--${type}`;
        element.setAttribute('role', type === 'error' ? 'alert' : 'status');
        element.setAttribute('aria-atomic', 'true');
        const icon = document.createElement('span');
        icon.className = 'td-notice__icon';
        icon.setAttribute('aria-hidden', 'true');
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        const circle = document.createElementNS(svg.namespaceURI, 'circle');
        circle.setAttribute('cx', '12'); circle.setAttribute('cy', '12'); circle.setAttribute('r', '9');
        const path = document.createElementNS(svg.namespaceURI, 'path');
        path.setAttribute('d', icons[type]);
        svg.append(circle, path); icon.append(svg);
        const content = document.createElement('div');
        content.className = 'td-notice__content';
        content.append(textElement('p', 'td-notice__title', title), textElement('p', 'td-notice__message', message));
        const entry = { id: ++nextId, key, element, remaining: Math.max(0, Number(options.duration ?? durations[type]) || 0), paused: new Set() };
        if (details.length) {
            const disclosure = document.createElement('details');
            disclosure.className = 'td-notice__details';
            disclosure.append(textElement('summary', '', `Xem chi tiết (${details.length})`));
            const items = document.createElement('ul');
            details.forEach(detail => items.append(textElement('li', '', detail)));
            disclosure.append(items); content.append(disclosure);
            disclosure.addEventListener('toggle', () => disclosure.open ? pause(entry, 'details') : unpause(entry, 'details'));
        }
        const button = textElement('button', 'td-notice__close', '×');
        button.type = 'button';
        button.setAttribute('aria-label', `Đóng thông báo: ${title}`);
        button.addEventListener('click', () => close(entry.id));
        element.append(icon, content, button);
        if (entry.remaining) {
            const progress = document.createElement('span');
            progress.className = 'td-notice__progress';
            progress.setAttribute('aria-hidden', 'true');
            element.append(progress);
            entry.animation = progress.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], { duration: entry.remaining, fill: 'forwards' });
            entry.animation.pause();
        }
        element.addEventListener('mouseenter', () => pause(entry, 'hover'));
        element.addEventListener('mouseleave', () => unpause(entry, 'hover'));
        element.addEventListener('focusin', () => pause(entry, 'focus'));
        element.addEventListener('focusout', () => { if (!element.contains(document.activeElement)) unpause(entry, 'focus'); });
        entries.set(entry.id, entry);
        list.append(element); updateRoot();
        if (document.hidden) pause(entry, 'hidden');
        resume(entry);
        return entry.id;
    };
    root.querySelector('[data-notifications-clear]').addEventListener('click', closeAll);
    document.addEventListener('visibilitychange', () => entries.forEach(entry => document.hidden ? pause(entry, 'hidden') : unpause(entry, 'hidden')));

    const dialog = document.getElementById('td-confirm');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    const accept = dialog.querySelector('[data-confirm-accept]');
    const confirmations = [];
    let activeConfirmation;
    const openNextConfirmation = () => {
        if (activeConfirmation || !confirmations.length) return;
        activeConfirmation = confirmations.shift();
        const { message, options } = activeConfirmation;
        dialog.querySelector('h2').textContent = options.title || 'Xác nhận thao tác';
        dialog.querySelector('p').textContent = message;
        cancel.textContent = options.cancelLabel || 'Hủy';
        accept.textContent = options.confirmLabel || 'Đồng ý';
        dialog.classList.toggle('is-danger', options.danger !== false);
        dialog.returnValue = '';
        dialog.showModal(); cancel.focus();
    };
    const confirm = (message, options = {}) => new Promise(resolve => {
        confirmations.push({ message, options, resolve }); openNextConfirmation();
    });
    cancel.addEventListener('click', () => dialog.close('cancel'));
    accept.addEventListener('click', () => dialog.close('accept'));
    dialog.addEventListener('click', event => { if (event.target === dialog && (event.clientX < dialog.getBoundingClientRect().left || event.clientX > dialog.getBoundingClientRect().right || event.clientY < dialog.getBoundingClientRect().top || event.clientY > dialog.getBoundingClientRect().bottom)) dialog.close('cancel'); });
    dialog.addEventListener('close', () => {
        const current = activeConfirmation;
        activeConfirmation = null;
        current?.resolve(dialog.returnValue === 'accept');
        openNextConfirmation();
    });
    const approvedForms = new WeakSet();
    const pendingForms = new WeakSet();
    document.addEventListener('submit', async event => {
        const form = event.target;
        const message = event.submitter?.dataset.confirmSubmit || form.dataset.confirm;
        if (!message) return;
        if (approvedForms.has(form)) { approvedForms.delete(form); return; }
        event.preventDefault();
        if (pendingForms.has(form)) return;
        pendingForms.add(form);
        const submitter = event.submitter;
        const approved = await confirm(message, { confirmLabel: form.dataset.confirmLabel || 'Đồng ý', danger: form.dataset.confirmDanger !== 'false' });
        pendingForms.delete(form);
        if (!approved || !form.isConnected || !form.reportValidity()) return;
        approvedForms.add(form);
        try { form.requestSubmit(submitter?.isConnected ? submitter : undefined); }
        finally { approvedForms.delete(form); }
    });
    let invalidFields = [], invalidNotice;
    document.addEventListener('invalid', event => {
        const input = event.target;
        if (!input.form) return;
        event.preventDefault();
        invalidFields.push(input);
        if (invalidFields.length !== 1) return;
        setTimeout(() => {
            const fields = [...new Set(invalidFields)]; invalidFields = [];
            const details = fields.map(field => {
                const label = (field.labels?.[0]?.textContent || field.getAttribute('aria-label') || field.placeholder || '').replace(/\s+/g, ' ').trim();
                const validity = field.validity;
                let message = field.validationMessage;
                if (!validity.customError) {
                    if (validity.valueMissing) message = 'Vui lòng điền thông tin này.';
                    else if (validity.typeMismatch) message = field.type === 'email' ? 'Địa chỉ email chưa hợp lệ.' : 'Thông tin chưa đúng định dạng yêu cầu.';
                    else if (validity.tooShort) message = `Vui lòng nhập ít nhất ${field.minLength} ký tự.`;
                    else if (validity.tooLong) message = `Vui lòng nhập tối đa ${field.maxLength} ký tự.`;
                    else if (validity.rangeUnderflow) message = `Giá trị phải lớn hơn hoặc bằng ${field.min}.`;
                    else if (validity.rangeOverflow) message = `Giá trị phải nhỏ hơn hoặc bằng ${field.max}.`;
                    else if (validity.patternMismatch || validity.badInput || validity.stepMismatch) message = 'Thông tin chưa đúng định dạng yêu cầu.';
                }
                return `${label ? label + ': ' : ''}${message}`;
            });
            close(invalidNotice);
            invalidNotice = show(details.length === 1 ? details[0] : `Có ${details.length} mục cần điều chỉnh.`, { type: 'error', title: 'Vui lòng kiểm tra thông tin', details: details.length > 1 ? details : [] });
            fields[0]?.focus();
        }, 0);
    }, true);
    const flash = (message, options = {}) => {
        try { sessionStorage.setItem('td-notification-next', JSON.stringify({ message, options, path: location.pathname, expires: Date.now() + 15000 })); }
        catch { show(message, options); }
    };
    window.TDNotify = { show, close, closeAll, confirm, flash };
    for (const type of Object.keys(titles)) window.TDNotify[type] = (message, options = {}) => show(message, { ...options, type });
    window.addEventListener('td:notify', event => { if (event.detail) show(event.detail.message, event.detail); });
    document.querySelectorAll('[data-share-ticket]').forEach(button => button.addEventListener('click', async () => {
        try {
            if (navigator.share) await navigator.share({ title: 'TD Cinema', text: 'Thông tin vé', url: location.href });
            else {
                await navigator.clipboard.writeText(location.href);
                window.TDNotify.success('Đã sao chép đường dẫn vé.');
            }
        } catch (error) {
            if (error.name !== 'AbortError') window.TDNotify.warning('Không thể chia sẻ tự động. Bạn có thể sao chép đường dẫn trên thanh địa chỉ.');
        }
    }));
    document.documentElement.classList.add('td-notifications-ready');
    try {
        const data = JSON.parse(document.getElementById('td-notification-data')?.textContent || '[]');
        data.forEach(notice => show(notice.message, notice));
    } catch (error) { console.error('Không đọc được thông báo.', error); }
    try {
        const saved = JSON.parse(sessionStorage.getItem('td-notification-next') || 'null');
        sessionStorage.removeItem('td-notification-next');
        if (saved?.path === location.pathname && saved.expires > Date.now()) show(saved.message, saved.options);
    } catch { sessionStorage.removeItem('td-notification-next'); }
})();
