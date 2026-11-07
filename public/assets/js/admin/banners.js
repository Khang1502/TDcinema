(() => {
    const form = document.querySelector('[data-banner-editor]');
    if (!form) return;
    const source = form.querySelector('#banner-source');
    const movie = form.querySelector('#banner-movie');
    const cinema = form.querySelector('#banner-cinema');
    const placement = form.querySelector('#banner-placement');
    const file = form.querySelector('#banner-image');
    const upload = form.querySelector('[data-banner-upload]');
    const image = form.querySelector('[data-banner-image-preview]');
    const placeholder = form.querySelector('[data-banner-placeholder]');
    const info = form.querySelector('[data-banner-image-info]');
    const title = form.querySelector('#banner-title');
    const imageSource = form.querySelector('[data-image-source="banner-preview"]');
    const storedPath = imageSource.querySelector('[data-image-source-path]').value;
    let objectUrl = null;

    function preview() {
        const useMovie = source.value === 'movie';
        const useCinema = source.value === 'cinema';
        upload.hidden = useMovie || useCinema;
        file.disabled = useMovie || useCinema;
        file.required = !useMovie && !useCinema && !image.dataset.currentImage;
        const fileName = form.querySelector('[data-banner-file-name]');
        fileName.textContent = file.files[0]?.name || 'Chưa chọn tệp';
        fileName.title = file.files[0]?.name || '';
        movie.required = useMovie;
        movie.disabled = useCinema;
        cinema.required = useCinema;
        cinema.disabled = !useCinema;
        form.querySelector('[data-banner-movie]').hidden = useCinema;
        form.querySelector('[data-banner-cinema]').hidden = !useCinema;
        form.querySelector('.banner-image-preview').style.aspectRatio = placement.value === 'cinemas' ? '16/9' : '3/2';
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        let url = image.dataset.currentImage;
        let path = storedPath;
        let label = 'Đường dẫn ảnh đã lưu';
        if (useMovie || useCinema) {
            const selected = (useMovie ? movie : cinema).selectedOptions[0]?.dataset || {};
            url = selected.image || '';
            path = selected.imagePath || '';
            label = useMovie ? 'Đường dẫn ảnh từ phim' : 'Đường dẫn ảnh từ rạp';
        }
        else if (file.files[0]) {
            objectUrl = URL.createObjectURL(file.files[0]);
            url = objectUrl;
            path = file.files[0].name;
            label = 'Tệp mới chọn — chưa lưu';
        }
        window.AdminImageSource.update(imageSource, path, url, label);
        image.hidden = !url;
        placeholder.hidden = !!url;
        info.textContent = '';
        if (url) image.src = url;
        else {
            image.removeAttribute('src');
            placeholder.textContent = useMovie && movie.value ? 'Phim này chưa có ảnh banner. Hãy chọn phim khác hoặc tải ảnh lên.' : useCinema && cinema.value ? 'Rạp này chưa có ảnh. Hãy thêm ảnh cho rạp hoặc tải ảnh banner riêng.' : 'Chọn ảnh, phim hoặc rạp để xem trước';
        }
    }
    image.addEventListener('load', () => {
        info.textContent = `Kích thước ảnh: ${image.naturalWidth} × ${image.naturalHeight} px`;
        if (image.naturalWidth < 1000) info.textContent += ' · Nên dùng ảnh lớn hơn để banner rõ nét.';
    });
    image.addEventListener('error', () => {
        image.hidden = true;
        placeholder.hidden = false;
        placeholder.textContent = 'Không tải được ảnh. Vui lòng kiểm tra hoặc chọn ảnh khác.';
    });
    source.addEventListener('change', preview);
    movie.addEventListener('change', preview);
    cinema.addEventListener('change', preview);
    placement.addEventListener('change', preview);
    file.addEventListener('change', preview);
    title.addEventListener('input', () => {
        form.querySelector('[data-banner-title-preview]').textContent = title.value.trim() || 'Tên banner';
    });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
    preview();
})();
