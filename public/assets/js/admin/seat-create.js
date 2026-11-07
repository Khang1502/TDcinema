document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-seat-create]');
    if (!form) return;

    const rooms = JSON.parse(form.dataset.roomOptions);
    const cinema = form.elements.cinema_id;
    const room = form.elements.room_id;
    const save = form.querySelector('[data-seat-save]');
    const cancel = form.querySelector('[data-seat-cancel]');
    const status = document.getElementById('seat-location-status');
    const indexUrl = new URL(cancel.href);
    const updateActions = () => {
        save.disabled = !cinema.value || !room.value || room.disabled;
        const url = new URL(indexUrl);
        url.searchParams.delete('cinema_id');
        url.searchParams.delete('room_id');
        if (cinema.value) url.searchParams.set('cinema_id', cinema.value);
        if (room.value) url.searchParams.set('room_id', room.value);
        cancel.href = url.href;
    };
    const updateRooms = () => {
        const available = rooms.filter(item => String(item.cinema_id) === cinema.value);
        const placeholder = !cinema.value ? 'Chọn rạp trước' : (available.length ? 'Chọn phòng chiếu' : 'Rạp chưa có phòng chiếu');
        room.replaceChildren(new Option(placeholder, ''));
        available.forEach(item => room.add(new Option(`${item.name}${item.type ? ` · ${item.type}` : ''}`, item.id)));
        room.disabled = !cinema.value || !available.length;
        status.textContent = !cinema.value
            ? 'Danh sách phòng chiếu sẽ hiển thị sau khi bạn chọn rạp.'
            : (available.length ? `${available.length} phòng chiếu thuộc rạp đã chọn.` : 'Rạp này chưa có phòng chiếu. Hãy thêm phòng chiếu hoặc chọn rạp khác.');
        updateActions();
    };

    cinema.addEventListener('change', updateRooms);
    room.addEventListener('change', updateActions);
    window.addEventListener('pageshow', () => {
        const selectedRoomId = room.value;
        // Reconcile form values restored by the browser with the current cinema.
        updateRooms();
        if ([...room.options].some(option => option.value === selectedRoomId)) room.value = selectedRoomId;
        updateActions();
        if (cinema.disabled) status.textContent = 'Chưa có rạp. Hãy thêm rạp và phòng chiếu trước khi thêm ghế.';
    });
    updateActions();
});
