document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-gift-tier-picker]');
    const stars = document.querySelector('[data-gift-min-stars]');
    if (!picker || !stars) return;
    const select = picker.querySelector('[data-gift-tier-preset]');
    const hint = picker.querySelector('[data-gift-tier-hint]');
    const tiers = [...select.options].filter(option => option.value !== '');
    const update = () => {
        const points = Number(stars.value);
        if (stars.value === '' || !Number.isInteger(points) || points < 0 || points > 100000000) {
            select.value = '';
            hint.textContent = 'Nhập mốc Stars hợp lệ để xác định nhóm cấp bậc.';
            return;
        }
        select.value = tiers.find(option => Number(option.value) === points)?.value ?? '';
        const tier = tiers.filter(option => Number(option.value) <= points).at(-1);
        hint.textContent = `Nhóm: ${tier?.dataset.tierName ?? 'Mốc Stars khác'}. Khách cần ít nhất ${points.toLocaleString('vi-VN')} Stars; hạng cao hơn cũng dùng được quà.`;
    };
    select.addEventListener('change', () => {
        if (select.value !== '') {
            stars.value = select.value;
            update();
        } else {
            stars.focus();
        }
    });
    stars.addEventListener('input', update);
    update();
});
