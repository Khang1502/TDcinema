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
        const foodOrders = JSON.parse(btn.dataset.foodOrders || '[]');
        const foodSection = document.getElementById('modalFoodOrders');
        const foodList = document.getElementById('modalFoodOrdersList');
        foodList.replaceChildren();
        foodOrders.forEach((order) => {
            const item = document.createElement('div');
            item.className = 'modal-food-orders__item';
            const code = document.createElement('strong');
            code.textContent = order.code;
            const status = document.createElement('span');
            status.textContent = order.received ? 'Đã nhận đủ' : order.fulfilled > 0
                ? `Đã nhận ${order.fulfilled}/${order.total} phần` : 'Chưa nhận';
            item.append(code, status);
            foodList.append(item);
        });
        foodSection.hidden = foodOrders.length === 0;
        document.querySelector('#ticketModal .modal-footer').textContent = btn.dataset.note || '';
        document.getElementById('modalPoster').src = btn.dataset.poster || '';
        document.getElementById('modalQr').src = btn.dataset.qr || '';
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
