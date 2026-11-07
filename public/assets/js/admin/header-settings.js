document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.header-settings');
    if (!root) return;
    const logoInput = root.querySelector('[name="logo"]');
    const thumbnail = root.querySelector('[data-logo-thumbnail]');
    const status = root.querySelector('[data-logo-status]');
    let imageUrl;
    const storedLogo = thumbnail.src;
    const logoSource = root.querySelector('[data-image-source="logo"]');
    const storedPath = logoSource.querySelector('[data-image-source-path]').value;
    const storedLabel = logoSource.querySelector('[data-image-source-label]').textContent;
    logoInput.addEventListener('change', event => {
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        const file = event.target.files[0];
        imageUrl = file ? URL.createObjectURL(file) : null;
        thumbnail.src = imageUrl || storedLogo;
        window.AdminImageSource.update(logoSource, file ? file.name : storedPath, imageUrl || storedLogo, file ? 'Logo mới chọn — chưa lưu' : storedLabel);
        status.textContent = file ? `${file.name} · Logo mới, chưa lưu` : 'PNG, JPG, WebP · tối đa 2MB. Chọn ảnh mới để thay logo.';
    });
    window.addEventListener('pagehide', () => { if (imageUrl) URL.revokeObjectURL(imageUrl); });
});
