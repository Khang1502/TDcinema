(() => {
    const updateSource = (box, path, url, label = 'Đường dẫn ảnh đã lưu') => {
        if (!box) return;
        const input = box.querySelector('[data-image-source-path]');
        const link = box.querySelector('[data-image-source-link]');
        box.querySelector('[data-image-source-label]').textContent = label;
        input.value = path || '';
        input.title = path || '';
        input.setAttribute('aria-label', label);
        link.hidden = !url;
        if (url) link.href = url;
        else link.removeAttribute('href');
    };
    window.AdminImageSource = { update: updateSource };
    document.querySelectorAll('[data-image-upload]').forEach(box => {
        const file = box.querySelector('input[type="file"]');
        const image = box.querySelector('[data-image-upload-preview]');
        const empty = box.querySelector('[data-image-upload-empty]');
        const source = box.querySelector('[data-image-source]');
        const status = box.querySelector('[data-image-upload-status]');
        const originalPath = source.querySelector('[data-image-source-path]').value;
        const originalUrl = image.dataset.originalUrl;
        let objectUrl;
        file.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            const selected = file.files[0];
            objectUrl = selected ? URL.createObjectURL(selected) : null;
            const url = objectUrl || originalUrl;
            updateSource(source, selected ? selected.name : originalPath, url, selected ? 'Tệp mới chọn — chưa lưu' : 'Đường dẫn ảnh đã lưu');
            status.classList.remove('is-error');
            status.textContent = selected ? 'Bấm Lưu để tải ảnh lên và tạo đường dẫn.' : (originalPath ? 'Ảnh đã lưu' : 'Chọn tệp để xem trước.');
            image.hidden = !url;
            empty.hidden = !!url;
            empty.textContent = 'Chưa có ảnh';
            if (url) image.src = url;
            else image.removeAttribute('src');
        });
        image.addEventListener('error', () => {
            image.hidden = true;
            empty.hidden = false;
            empty.textContent = 'Không tải được ảnh';
            status.classList.add('is-error');
            status.textContent = 'Đường dẫn ảnh không tải được. Hãy chọn ảnh mới để thay thế.';
        });
        if (image.getAttribute('src') && image.complete && !image.naturalWidth) image.dispatchEvent(new Event('error'));
        window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    });
})();
