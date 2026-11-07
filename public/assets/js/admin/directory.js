document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-record-preview]').forEach(button => {
        const dialog = document.getElementById(button.dataset.recordPreview);
        if (!dialog?.showModal) return;
        button.addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-record-close]').forEach(close => close.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('click', event => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
    });
    const menus = [...document.querySelectorAll('.ad-menu')];
    menus.forEach(menu => menu.addEventListener('toggle', () => { if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; }); }));
    document.addEventListener('click', event => menus.forEach(menu => { if (!menu.contains(event.target)) menu.open = false; }));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') menus.forEach(menu => menu.open = false); });
    document.querySelectorAll('img[data-fallback]').forEach(img => {
        const fallback = () => { if (img.dataset.didFallback) return; img.dataset.didFallback = '1'; img.src = img.dataset.fallback; };
        img.addEventListener('error',fallback);
        if (img.complete && !img.naturalWidth) fallback();
    });
    const tickets = document.querySelector('[data-ticket-filter]');
    if (tickets) {
        const clearSeat = () => { tickets.elements.seat_id.value = ''; };
        tickets.elements.movie_id.addEventListener('change', () => { tickets.elements.showtime_id.value = ''; clearSeat(); });
        tickets.elements.cinema_id.addEventListener('change', () => { tickets.elements.showtime_id.value = ''; clearSeat(); });
        tickets.elements.showtime_id.addEventListener('change',clearSeat);
    }
});
