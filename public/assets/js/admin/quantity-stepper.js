document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-quantity-stepper]').forEach(stepper => {
        const input = stepper.querySelector('input[type="number"]');
        const minus = stepper.querySelector('[data-quantity-adjust="-1"]');
        const plus = stepper.querySelector('[data-quantity-adjust="1"]');
        if (!input || !minus || !plus) return;

        const min = Number(input.min || 0);
        const max = Number(input.max || 10);
        const normalize = value => Math.min(max, Math.max(min, Math.trunc(Number(value) || 0)));
        const updateButtons = () => {
            const value = normalize(input.value);
            minus.disabled = value <= min;
            plus.disabled = value >= max;
        };

        stepper.addEventListener('click', event => {
            const button = event.target.closest('[data-quantity-adjust]');
            if (!button || button.disabled) return;
            input.value = String(normalize(input.value) + Number(button.dataset.quantityAdjust));
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
        input.addEventListener('input', updateButtons);
        input.addEventListener('change', () => {
            input.value = String(normalize(input.value));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        updateButtons();
    });
});
