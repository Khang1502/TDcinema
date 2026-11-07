(() => {
    document.querySelectorAll('[data-offer-carousel]').forEach(carousel => {
        const track = carousel.querySelector('[data-offer-track]');
        const cards = Array.from(track.querySelectorAll('.offer-card'));
        const controls = carousel.querySelector('[data-offer-controls]');
        const previous = carousel.querySelector('[data-offer-prev]');
        const next = carousel.querySelector('[data-offer-next]');
        const count = carousel.querySelector('[data-offer-count]');
        const toggle = carousel.querySelector('[data-offer-toggle]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const autoplay = carousel.dataset.autoplay === '1';
        const interval = Math.min(20000, Math.max(3000, Number(carousel.dataset.interval) || 5000));
        let paused = false, hovered = false, dragging = false, visible = false, autoTimer;
        let frame;
        const canRun = () => autoplay && !paused && !hovered && !dragging && visible && !document.hidden &&
            !reducedMotion.matches && !carousel.querySelector(':focus:not([data-offer-toggle])') && track.scrollWidth - track.clientWidth > 2;
        const restart = () => {
            clearTimeout(autoTimer);
            if (!canRun()) return;
            autoTimer = setTimeout(() => {
                if (!canRun()) return;
                if (track.scrollLeft >= track.scrollWidth - track.clientWidth - 2) {
                    track.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    move(1, true);
                }
                restart();
            }, interval);
        };

        const update = () => {
            const overflow = track.scrollWidth - track.clientWidth > 2;
            controls.hidden = !overflow;
            if (toggle) toggle.hidden = !autoplay || !overflow || reducedMotion.matches;
            if (!overflow) clearTimeout(autoTimer);
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
            const bounds = track.getBoundingClientRect();
            const visible = cards.map((card, index) => {
                const rect = card.getBoundingClientRect();
                const overlap = Math.min(rect.right, bounds.right) - Math.max(rect.left, bounds.left);
                return overlap >= rect.width / 2 ? index + 1 : null;
            }).filter(Boolean);
            // Load the next image before it scrolls into view.
            const upcomingImage = cards[(visible.at(-1) || 1) % cards.length]?.querySelector('img');
            if (upcomingImage) upcomingImage.loading = 'eager';
            if (visible.length) {
                count.textContent = `${visible[0]}${visible.length > 1 ? '–' + visible.at(-1) : ''} / ${cards.length} ưu đãi`;
            }
        };
        const scheduleUpdate = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(update);
        };
        const move = (direction, automatic = false) => {
            const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
            const step = (cards[0]?.getBoundingClientRect().width || track.clientWidth) + gap;
            const visibleCards = automatic ? 1 : Math.max(1, Math.floor((track.clientWidth + gap) / step));
            track.scrollBy({ left: direction * step * visibleCards, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        };
        previous.addEventListener('click', () => { move(-1); restart(); });
        next.addEventListener('click', () => { move(1); restart(); });
        toggle?.addEventListener('click', () => {
            paused = !paused;
            toggle.setAttribute('aria-label', paused ? 'Tiếp tục tự chạy' : 'Tạm dừng tự chạy');
            toggle.setAttribute('aria-pressed', String(paused));
            toggle.querySelector('path').setAttribute('d', paused ? 'M7 4l9 6-9 6V4Z' : 'M7 5v10M13 5v10');
            restart();
        });
        carousel.addEventListener('mouseenter', () => { hovered = true; restart(); });
        carousel.addEventListener('mouseleave', () => { hovered = false; restart(); });
        carousel.addEventListener('focusin', restart);
        carousel.addEventListener('focusout', () => requestAnimationFrame(restart));
        carousel.addEventListener('pointerdown', () => { dragging = true; restart(); });
        window.addEventListener('pointerup', () => { dragging = false; restart(); });
        window.addEventListener('pointercancel', () => { dragging = false; restart(); });
        document.addEventListener('visibilitychange', restart);
        reducedMotion.addEventListener('change', () => { update(); restart(); });
        new IntersectionObserver(entries => { visible = entries[0].isIntersecting; restart(); }, { threshold: 0.25 }).observe(carousel);
        track.addEventListener('scroll', scheduleUpdate, { passive: true });
        track.addEventListener('keydown', event => {
            if (event.target !== track) return;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                move(event.key === 'ArrowRight' ? 1 : -1);
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                track.scrollTo({ left: event.key === 'Home' ? 0 : track.scrollWidth, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            }
        });
        new ResizeObserver(() => { scheduleUpdate(); restart(); }).observe(track);
        document.fonts.ready.then(scheduleUpdate);
        update();
    });

    let timer;
    let copiedButton;
    document.querySelectorAll('[data-copy-promo]').forEach(button => {
        button.addEventListener('click', async () => {
            clearTimeout(timer);
            if (copiedButton) copiedButton.textContent = 'Sao chép';
            copiedButton = null;
            try {
                await navigator.clipboard.writeText(button.dataset.copyPromo);
                window.TDNotify.success('Đã sao chép mã ' + button.dataset.copyPromo);
                button.textContent = 'Đã sao chép';
                copiedButton = button;
            } catch {
                window.TDNotify.warning('Không thể sao chép. Mã ưu đãi: ' + button.dataset.copyPromo + '. Bạn có thể nhập mã này khi thanh toán.');
            }
            timer = setTimeout(() => { button.textContent = 'Sao chép'; copiedButton = null; }, 3500);
        });
    });
})();
