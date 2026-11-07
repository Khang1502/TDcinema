(() => {
    const dialog = document.querySelector('[data-cinema-photo-dialog]');
    if (!dialog) return;
    const image = dialog.querySelector('img');
    const title = dialog.querySelector('h2');
    let previousOverflow;
    document.querySelectorAll('[data-cinema-photo]').forEach(button => {
        button.addEventListener('click', () => {
            image.src = button.dataset.cinemaPhoto;
            image.alt = `Ảnh ${button.dataset.cinemaName}`;
            title.textContent = button.dataset.cinemaName;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-cinema-photo-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
        document.body.style.overflow = previousOverflow ?? '';
        image.removeAttribute('src');
    });
})();
