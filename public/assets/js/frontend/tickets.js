const detailButtons = document.querySelectorAll('.btn-detail');
detailButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
        const modal = document.getElementById('ticketModal');
        document.getElementById('modalTitle').textContent = btn.dataset.title || '';
        document.getElementById('modalFormat').textContent = (btn.dataset.format || '2D') + ' \u00B7 ' + (btn.dataset.age || 'T13');
        document.getElementById('modalCinema').textContent = btn.dataset.cinema || '';
        document.getElementById('modalTime').textContent = btn.dataset.time || '';
        document.getElementById('modalSeat').textContent = btn.dataset.seat || '';
        document.getElementById('modalCode').textContent = btn.dataset.code || '';
        document.getElementById('modalPrice').textContent = btn.dataset.price || '';
        document.getElementById('modalPoster').src = btn.dataset.poster || '';
        const qrData = encodeURIComponent(btn.dataset.code || '');
        document.getElementById('modalQr').src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${qrData}`;
        modal.style.display = 'flex';
    });
});

const modalClose = document.getElementById('modalClose');
if (modalClose) {
    modalClose.addEventListener('click', () => {
        document.getElementById('ticketModal').style.display = 'none';
    });
}

const modalBackdrop = document.getElementById('ticketModal');
if (modalBackdrop) {
    modalBackdrop.addEventListener('click', (e) => {
        if (e.target.id === 'ticketModal') {
            e.currentTarget.style.display = 'none';
        }
    });
}