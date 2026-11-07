document.querySelectorAll('[data-footer-links]').forEach(section => {
    const list = section.querySelector('[data-link-list]');
    const add = section.querySelector('[data-add-link]');
    let index = Math.max(-1, ...Array.from(list.querySelectorAll('input')).map(input => Number(input.name.match(/\[(\d+)\]/)?.[1]) || 0)) + 1;
    const update = () => { add.disabled = list.children.length >= 8; };
    list.addEventListener('click', event => {
        if (!event.target.closest('[data-remove-link]')) return;
        event.target.closest('.settings-link-row').remove(); update();
    });
    add.addEventListener('click', () => {
        if (list.children.length >= 8) return;
        const row = section.querySelector('template').content.cloneNode(true);
        const prefix = section.dataset.linkPrefix || `${section.dataset.footerLinks}_links`;
        row.querySelectorAll('[data-field]').forEach(input => { input.name = `${prefix}[${index}][${input.dataset.field}]`; });
        index++; list.append(row); update(); list.lastElementChild.querySelector('input').focus();
    });
    update();
});
