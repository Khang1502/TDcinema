document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-promo-image-manager]');
    if (!root) return;
    const input = root.querySelector('[name=thumbnail]');
    const remove = root.querySelector('[name=remove_thumbnail]');
    const preview = root.querySelector('[data-promo-image-preview]');
    const stored = preview.getAttribute('src');
    const imageSource = root.querySelector('[data-image-source="promotion"]');
    const storedPath = imageSource.querySelector('[data-image-source-path]').value;
    let objectUrl;
    const update = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        objectUrl = file ? URL.createObjectURL(file) : null;
        const removed = remove?.checked && !file;
        const url = objectUrl || stored;
        if (url && !removed) preview.src = url;
        else preview.removeAttribute('src');
        preview.hidden = removed || (!file && !stored);
        root.querySelector('[data-promo-image-empty]').hidden = !preview.hidden;
        root.querySelector('[data-promo-image-empty]').textContent = removed ? 'Ảnh sẽ được gỡ khi lưu' : 'Chưa có ảnh';
        root.querySelector('[data-promo-image-label]').textContent = file ? 'Ảnh mới, chưa lưu' : 'Ảnh đã lưu';
        root.querySelector('[data-promo-image-status]').textContent = file ? `${file.name} · Lưu thay đổi để áp dụng ảnh mới.` : 'Chọn tệp để thay ảnh. Để trống để giữ ảnh đã lưu. JPG, PNG, WebP, tối đa 2MB.';
        window.AdminImageSource.update(imageSource, removed ? '' : (file ? file.name : storedPath), removed ? null : url,
            removed ? 'Ảnh sẽ được gỡ khi lưu' : file ? 'Tệp mới chọn — chưa lưu' : 'Đường dẫn ảnh đã lưu');
    };
    input.addEventListener('change', () => { if (input.files[0] && remove) remove.checked = false; update(); });
    remove?.addEventListener('change', () => { if (remove.checked) input.value = ''; update(); });
    preview.addEventListener('error', () => {
        preview.hidden = true;
        const empty = root.querySelector('[data-promo-image-empty]');
        empty.hidden = false;
        empty.textContent = 'Không tải được ảnh. Hãy chọn ảnh mới để thay thế.';
    });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    update();
});
