document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-schedule-copy]');
    if (!form) return;
    const source = form.elements.source_date;
    const target = form.elements.target_date;
    const validate = () => target.setCustomValidity(source.value && source.value === target.value ? 'Ngày đích phải khác ngày nguồn.' : '');
    source.addEventListener('input',validate); target.addEventListener('input',validate); validate();
});
