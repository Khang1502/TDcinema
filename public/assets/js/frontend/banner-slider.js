(() => {
    document.querySelectorAll('[data-banner-slider]').forEach(hero => {
    // Slider
    const slides = Array.from(hero.querySelectorAll('.hero-slide'));
    const dots = Array.from(hero.querySelectorAll('.slider-dots button'));
    const previousSlideButton = hero.querySelector('[data-slider-prev]');
    const nextSlideButton = hero.querySelector('[data-slider-next]');
    const heroStage = hero?.querySelector('.hero-stage');
    const heroTrack = hero?.querySelector('.hero-track');
    const previousPreview = hero?.querySelector('[data-banner-preview="prev"]');
    const nextPreview = hero?.querySelector('[data-banner-preview="next"]');
    let current = 0;
    let sliderTimer = null;
    let touchStartX = null;
    const reducedSliderMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const cloneCount = slides.length > 1 ? Math.min(2, slides.length) : 0;
    let requestedSlide = 0;
    let requestedDirection = 1;
    let trackIndex = cloneCount;
    let sliderMoving = false;
    let sliderTransitionTimer = null;

    // Two neighbours at each end keep both previews visible while wrapping.
    function cloneBanner(slide) {
        const clone = slide.cloneNode(true);
        clone.classList.remove('active');
        clone.classList.add('hero-slide--clone');
        clone.setAttribute('aria-hidden', 'true');
        clone.setAttribute('inert', '');
        clone.querySelector('img')?.setAttribute('alt', '');
        return clone;
    }
    if (heroTrack && cloneCount) {
        heroTrack.prepend(...slides.slice(-cloneCount).map(cloneBanner));
        heroTrack.append(...slides.slice(0, cloneCount).map(cloneBanner));
    }

    function positionBannerTrack(animate = false) {
        if (!heroTrack || !heroStage || !slides.length) return;
        const width = slides[0].getBoundingClientRect().width;
        const gap = parseFloat(getComputedStyle(heroTrack).columnGap) || 0;
        // CSS centers the first card before JS runs, using the actual page width.
        const offset = -trackIndex * (width + gap);
        heroTrack.classList.toggle('is-animating', animate && !reducedSliderMotion.matches);
        heroTrack.style.transform = `translate3d(${offset}px,0,0)`;
    }

    function updateBannerPreview(button, index) {
        if (!button || !slides.length) return;
        const image = slides[index].querySelector('img');
        button.setAttribute('aria-label', `Chuyển đến banner ${image?.alt || index + 1}`);
    }

    function paintBannerState(index) {
        slides.forEach((s, idx) => {
            const isActive = idx === index;
            s.classList.toggle('active', isActive);
            s.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            s.querySelector('.hero-slide-link')?.setAttribute('tabindex', isActive ? '0' : '-1');
        });
        dots.forEach((d, idx) => {
            const isActive = idx === index;
            d.classList.toggle('active', isActive);
            d.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
        updateBannerPreview(previousPreview, (index - 1 + slides.length) % slides.length);
        updateBannerPreview(nextPreview, (index + 1) % slides.length);
    }

    function finishBannerTransition() {
        if (!sliderMoving) return;
        window.clearTimeout(sliderTransitionTimer);
        sliderMoving = false;
        trackIndex = cloneCount + current;
        positionBannerTrack();
        if (requestedSlide !== current) animateRequestedBanner();
    }

    function animateRequestedBanner() {
        if (sliderMoving || requestedSlide === current || !heroTrack) return;
        const nextIndex = requestedSlide;
        if (current === slides.length - 1 && nextIndex === 0 && requestedDirection > 0) {
            trackIndex = cloneCount + slides.length;
        } else if (current === 0 && nextIndex === slides.length - 1 && requestedDirection < 0) {
            trackIndex = cloneCount - 1;
        } else {
            trackIndex = cloneCount + nextIndex;
        }
        // Commit the starting position before enabling a new transition.
        heroTrack.getBoundingClientRect();
        current = nextIndex;
        paintBannerState(current);
        sliderMoving = true;
        positionBannerTrack(true);
        if (reducedSliderMotion.matches) finishBannerTransition();
        else sliderTransitionTimer = window.setTimeout(finishBannerTransition, 750);
    }

    function showSlide(index, direction = 1) {
        if (!slides.length) return;
        requestedSlide = ((index % slides.length) + slides.length) % slides.length;
        requestedDirection = direction;
        animateRequestedBanner();
    }

    function stopSlider() {
        if (sliderTimer) window.clearInterval(sliderTimer);
        sliderTimer = null;
    }

    function startSlider() {
        stopSlider();
        if (slides.length > 1 && !document.hidden && !hero?.matches(':hover') && !hero?.contains(document.activeElement) && !reducedSliderMotion.matches) {
            sliderTimer = window.setInterval(() => moveSlide(1), 5000);
        }
    }

    function moveSlide(offset) {
        showSlide(requestedSlide + offset, Math.sign(offset));
        startSlider();
    }

    dots.forEach(d => d.addEventListener('click', () => {
        const index = Number(d.dataset.slide);
        showSlide(index, index >= current ? 1 : -1);
        startSlider();
    }));
    previousSlideButton?.addEventListener('click', () => moveSlide(-1));
    nextSlideButton?.addEventListener('click', () => moveSlide(1));
    previousPreview?.addEventListener('click', () => moveSlide(-1));
    nextPreview?.addEventListener('click', () => moveSlide(1));
    heroTrack?.addEventListener('transitionend', event => {
        if (event.target === heroTrack && event.propertyName === 'transform') finishBannerTransition();
    });
    if (heroStage) new ResizeObserver(() => {
        window.clearTimeout(sliderTransitionTimer);
        sliderMoving = false;
        trackIndex = cloneCount + current;
        positionBannerTrack();
        if (requestedSlide !== current) animateRequestedBanner();
    }).observe(heroStage);

    hero?.addEventListener('mouseenter', stopSlider);
    hero?.addEventListener('mouseleave', startSlider);
    hero?.addEventListener('focusin', stopSlider);
    hero?.addEventListener('focusout', event => {
        if (!hero.contains(event.relatedTarget)) startSlider();
    });
    hero?.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moveSlide(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            moveSlide(1);
        }
    });
    hero?.addEventListener('touchstart', event => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    hero?.addEventListener('touchend', event => {
        if (touchStartX === null) return;
        const distance = (event.changedTouches[0]?.clientX ?? touchStartX) - touchStartX;
        touchStartX = null;
        if (Math.abs(distance) >= 45) moveSlide(distance > 0 ? -1 : 1);
    }, { passive: true });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopSlider();
        else startSlider();
    });
    paintBannerState(0);
    positionBannerTrack();
    startSlider();

    });
})();
