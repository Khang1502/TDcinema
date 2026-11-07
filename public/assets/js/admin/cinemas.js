(() => {
    document.querySelectorAll('[data-cinema-image-editor]').forEach(editor => {
        const input = editor.querySelector('input[type="file"]');
        const preview = editor.querySelector('[data-cinema-image-preview]');
        const badge = editor.querySelector('[data-cinema-image-badge]');
        const message = editor.querySelector('[data-cinema-image-message]');
        const reset = editor.querySelector('[data-cinema-image-reset]');
        const remove = editor.querySelector('[data-cinema-image-remove]');
        const original = editor.dataset.originalImage;
        const imageSource = editor.querySelector('[data-image-source="cinema"]');
        const storedPath = imageSource.querySelector('[data-image-source-path]').value;
        let objectUrl;
        const release = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        };
        const restore = () => {
            release();
            preview.src = remove?.checked ? editor.dataset.placeholder : original;
            badge.textContent = remove?.checked ? 'Ảnh sẽ được gỡ khi lưu' : editor.dataset.hasImage === '1' ? 'Ảnh hiện tại' : 'Chưa có ảnh rạp';
            reset.hidden = true;
            window.AdminImageSource.update(imageSource, remove?.checked ? '' : storedPath, !remove?.checked && editor.dataset.hasImage === '1' ? original : '', remove?.checked ? 'Ảnh sẽ được gỡ khi lưu' : 'Đường dẫn ảnh đã lưu');
        };
        input.addEventListener('change', () => {
            const file = input.files[0];
            message.textContent = '';
            input.setCustomValidity('');
            if (!file) { restore(); return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 4 * 1024 * 1024) {
                restore();
                message.textContent = file.size > 4 * 1024 * 1024 ? 'Ảnh không được vượt quá 4 MB.' : 'Vui lòng chọn ảnh JPG, PNG hoặc WEBP.';
                input.value = '';
                return;
            }
            release();
            if (remove) remove.checked = false;
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            window.AdminImageSource.update(imageSource, file.name, objectUrl, 'Tệp mới chọn — chưa lưu');
            badge.textContent = 'Ảnh mới · Chưa lưu';
            message.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(2)} MB`;
            reset.hidden = false;
        });
        reset.addEventListener('click', () => {
            input.value = '';
            message.textContent = '';
            restore();
            input.focus();
        });
        remove?.addEventListener('change', () => {
            input.value = '';
            message.textContent = '';
            restore();
        });
        window.addEventListener('pagehide', release);
        restore();
    });
})();
