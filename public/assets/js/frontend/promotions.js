(() => {
    document.querySelectorAll('[data-offer-carousel]').forEach(carousel => {
        const track = carousel.querySelector('[data-offer-track]');
        const cards = Array.from(track.querySelectorAll('.offer-card'));
        const controls = carousel.querySelector('[data-offer-controls]');
        const previous = carousel.querySelector('[data-offer-prev]');
        const next = carousel.querySelector('[data-offer-next]');
        const count = carousel.querySelector('[data-offer-count]');
        const bannerLoop = carousel.dataset.mode === 'banner' && cards.length > 1;
        if (bannerLoop) {
            const before = cards.at(-1).cloneNode(true);
            const after = cards[0].cloneNode(true);
            [before, after].forEach(clone => {
                clone.setAttribute('aria-hidden', 'true');
                clone.inert = true;
                clone.querySelectorAll('img').forEach(image => { image.loading = 'eager'; });
            });
            track.prepend(before);
            track.append(after);
        }
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const autoplay = bannerLoop || carousel.dataset.autoplay === '1';
        const interval = Math.min(20000, Math.max(3000, Number(carousel.dataset.interval) || 5000));
        let hovered = false, dragging = false, visible = false, autoTimer, settleTimer, frame;
        let activeBannerIndex = 0;
        const step = () => (cards[0]?.getBoundingClientRect().width || track.clientWidth)
            + (parseFloat(getComputedStyle(track).columnGap) || 0);
        const jumpTo = position => {
            track.style.scrollBehavior = 'auto';
            track.style.scrollSnapType = 'none';
            track.scrollLeft = position * step();
            track.style.scrollSnapType = '';
            track.style.scrollBehavior = '';
        };
        const normalizeLoop = () => {
            if (!bannerLoop) return;
            const width = step();
            if (Math.abs(track.scrollLeft) <= 2) jumpTo(cards.length);
            else if (Math.abs(track.scrollLeft - (cards.length + 1) * width) <= 2) jumpTo(1);
        };
        const canRun = () => autoplay && !hovered && !dragging && visible && !document.hidden &&
            !reducedMotion.matches && !track.contains(document.activeElement) && track.scrollWidth - track.clientWidth > 2;
        const move = (direction, automatic = false) => {
            if (bannerLoop) {
                normalizeLoop();
                const position = Math.round(track.scrollLeft / step()) + direction;
                track.scrollTo({ left: position * step(), behavior: reducedMotion.matches ? 'instant' : 'smooth' });
                return;
            }
            const visibleCards = automatic ? 1 : Math.max(1, Math.floor(track.clientWidth / step()));
            track.scrollBy({ left: direction * step() * visibleCards, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
        };
        const restart = () => {
            clearTimeout(autoTimer);
            if (!canRun()) return;
            autoTimer = setTimeout(() => {
                if (!canRun()) return;
                if (!bannerLoop && track.scrollLeft >= track.scrollWidth - track.clientWidth - 2) {
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
            if (!overflow) clearTimeout(autoTimer);
            previous.disabled = !bannerLoop && track.scrollLeft <= 2;
            next.disabled = !bannerLoop && track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
            if (bannerLoop) {
                activeBannerIndex = (Math.round(track.scrollLeft / step()) + cards.length - 1) % cards.length;
                count.textContent = `${activeBannerIndex + 1} / ${cards.length} ưu đãi`;
                const upcomingImage = cards[(activeBannerIndex + 1) % cards.length]?.querySelector('img');
                if (upcomingImage) upcomingImage.loading = 'eager';
                return;
            }
            const bounds = track.getBoundingClientRect();
            const visibleCards = cards.map((card, index) => {
                const rect = card.getBoundingClientRect();
                const overlap = Math.min(rect.right, bounds.right) - Math.max(rect.left, bounds.left);
                return overlap >= rect.width / 2 ? index + 1 : null;
            }).filter(Boolean);
            // Load the next image before it scrolls into view.
            const upcomingImage = cards[(visibleCards.at(-1) || 1) % cards.length]?.querySelector('img');
            if (upcomingImage) upcomingImage.loading = 'eager';
            if (visibleCards.length) {
                count.textContent = `${visibleCards[0]}${visibleCards.length > 1 ? '–' + visibleCards.at(-1) : ''} / ${cards.length} ưu đãi`;
            }
        };
        const scheduleUpdate = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(update);
        };
        previous.addEventListener('click', () => { move(-1); restart(); });
        next.addEventListener('click', () => { move(1); restart(); });
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
        track.addEventListener('scroll', () => {
            scheduleUpdate();
            if (bannerLoop) {
                clearTimeout(settleTimer);
                settleTimer = setTimeout(() => { normalizeLoop(); scheduleUpdate(); }, 180);
            }
        }, { passive: true });
        if (bannerLoop) track.addEventListener('scrollend', () => { normalizeLoop(); scheduleUpdate(); });
        track.addEventListener('keydown', event => {
            if (event.target !== track) return;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                move(event.key === 'ArrowRight' ? 1 : -1);
            } else if (event.key === 'Home' || event.key === 'End') {
                event.preventDefault();
                if (bannerLoop) jumpTo(event.key === 'Home' ? 1 : cards.length);
                else track.scrollTo({ left: event.key === 'Home' ? 0 : track.scrollWidth, behavior: reducedMotion.matches ? 'instant' : 'smooth' });
            }
        });
        new ResizeObserver(() => {
            if (bannerLoop) jumpTo(activeBannerIndex + 1);
            scheduleUpdate();
            restart();
        }).observe(track);
        document.fonts.ready.then(scheduleUpdate);
        if (bannerLoop) jumpTo(1);
        update();
    });

    document.querySelectorAll('form[data-promo-select-guest]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button?.disabled) return;
            if (button) button.disabled = true;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (response.redirected) {
                    window.location.assign(response.url);
                    return;
                }
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(Object.values(result.errors || {})[0]?.[0] || result.message || 'Không thể chọn ưu đãi. Vui lòng thử lại.');
                }
                if (result.requires_login) {
                    window.AuthModal.open('login');
                    window.TDNotify.warning(result.message);
                    document.querySelector('#loginBox input[name="email"]')?.focus();
                }
            } catch (error) {
                window.TDNotify.error(error.message || 'Không thể chọn ưu đãi. Vui lòng thử lại.');
            } finally {
                if (button) button.disabled = false;
            }
        });
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
