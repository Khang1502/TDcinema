(() => {
    const form = document.querySelector('[data-corner-editor]');
    if (!form) return;
    const movie = form.querySelector('#corner-movie');
    const placement = form.querySelector('#corner-placement');
    const source = form.querySelector('#corner-image-source');
    const file = form.querySelector('#corner-image');
    const title = form.querySelector('#corner-item-title');
    const excerpt = form.querySelector('#corner-excerpt');
    const trailer = form.querySelector('#corner-trailer');
    const category = form.querySelector('#corner-category');
    const content = form.querySelector('#corner-content');
    const image = form.querySelector('[data-corner-preview-image]');
    const placeholder = form.querySelector('[data-corner-preview-placeholder]');
    const imageSource = form.querySelector('[data-image-source="corner-preview"]');
    const storedPath = image.dataset.currentImage ? imageSource.querySelector('[data-image-source-path]').value : '';
    let objectUrl;
    let previewFile;
    let excerptInherited = excerpt.dataset.inherited === '1';
    let contentInherited = content.dataset.inherited === '1';
    const inheritedExcerpt = () => {
        const selected = movie.selectedOptions[0]?.dataset || {};
        return Array.from(content.value.trim() || selected.description || '').slice(0, 2000).join('');
    };
    function update() {
        const selected = movie.selectedOptions[0]?.dataset || {};
        source.querySelector('option[value="movie"]').disabled = !movie.value;
        if (!movie.value && source.value === 'movie') source.value = 'upload';
        const featured = placement.value === 'featured';
        const uploaded = source.value === 'upload';
        form.querySelector('[data-corner-upload]').hidden = !uploaded;
        file.disabled = !uploaded;
        file.required = uploaded && !image.dataset.currentImage;
        form.querySelector('[data-corner-file-name]').textContent = file.files[0]?.name || 'Chưa chọn tệp';
        form.querySelector('[data-corner-preview]').classList.toggle('is-list', !featured);
        form.querySelector('[data-corner-preview-label]').hidden = !featured;
        form.querySelector('[data-corner-preview-title]').textContent = title.value.trim() || selected.title || 'Tiêu đề nội dung';
        title.required = !movie.value;
        content.required = !movie.value;
        const description = excerpt.value.trim() || content.value.trim() || selected.description || 'Mô tả ngắn của bài viết sẽ xuất hiện tại đây.';
        const limit = featured ? 240 : 140;
        form.querySelector('[data-corner-preview-excerpt]').textContent = description.length > limit ? description.slice(0, limit) + '...' : description;
        const status = selected.status === 'upcoming' ? 'Sắp chiếu' : (selected.status === 'inactive' ? 'Ngừng chiếu' : 'Đang chiếu');
        form.querySelector('[data-corner-preview-meta]').textContent = category.selectedOptions[0].textContent;
        form.querySelector('[data-corner-action-label]').textContent = 'Đọc bài viết';
        form.querySelector('.corner-preview-actions span:last-child').hidden = !(trailer.value.trim() || selected.trailer);
        form.querySelector('[data-corner-preview-help]').textContent = category.value === 'review' && selected.status === 'inactive' ? 'Phim này đã ngừng chiếu. Bài bình luận sẽ chưa hiển thị trên trang chủ.' : (uploaded ? 'Ảnh riêng được dùng cho nội dung này.' : 'Ảnh banner của phim được dùng cho bài viết.');
        const currentFile = uploaded ? file.files[0] : null;
        if (currentFile !== previewFile) {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            previewFile = currentFile;
            objectUrl = currentFile ? URL.createObjectURL(currentFile) : null;
        }
        let url = uploaded ? image.dataset.currentImage : selected.banner;
        if (objectUrl) url = objectUrl;
        window.AdminImageSource.update(imageSource, objectUrl ? currentFile.name : (uploaded ? storedPath : selected.imagePath), url,
            objectUrl ? 'Tệp mới chọn — chưa lưu' : (uploaded ? 'Đường dẫn ảnh riêng đã lưu' : 'Đường dẫn ảnh từ phim'));
        image.hidden = !url;
        placeholder.hidden = !!url;
        if (url) {
            if (image.getAttribute('src') !== url) image.src = url;
        } else image.removeAttribute('src');
    }
    movie.addEventListener('change', () => {
        if (movie.value && !file.files[0] && !image.dataset.currentImage) source.value = 'movie';
        if (contentInherited) content.value = movie.selectedOptions[0]?.dataset.content || '';
        if (excerptInherited) excerpt.value = inheritedExcerpt();
        update();
    });
    for (const element of [placement, source, file, category]) element.addEventListener('change', update);
    for (const element of [title, trailer]) element.addEventListener('input', update);
    excerpt.addEventListener('input', () => { excerptInherited = false; update(); });
    content.addEventListener('input', () => {
        contentInherited = false;
        if (excerptInherited) excerpt.value = inheritedExcerpt();
        update();
    });
    image.addEventListener('error', () => { image.hidden = true; placeholder.hidden = false; placeholder.textContent = 'Không tải được ảnh. Hãy chọn ảnh khác.'; });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    update();
})();
