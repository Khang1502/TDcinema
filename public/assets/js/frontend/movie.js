    const pageConfig = document.body?.dataset || {};
    let cinemasByCity = {};
    try {
        cinemasByCity = JSON.parse(pageConfig.cinemasByCity || '{}');
    } catch (err) {
        cinemasByCity = {};
    }
    const isCustomer = pageConfig.isCustomer === '1';
    const isAuthenticated = pageConfig.isAuthenticated === '1';
    const trailerUrl = pageConfig.trailerUrl || '';

    const dateTabs = Array.from(document.querySelectorAll('.date-tab'));
    const panels = Array.from(document.querySelectorAll('[data-date-panel]'));
    const noShowtimeMsg = document.getElementById('noShowtimeMsg');
    const prevDate = document.getElementById('prevDate');
    const nextDate = document.getElementById('nextDate');
    const cityFilter = document.getElementById('cityFilter');
    const cinemaFilter = document.getElementById('cinemaFilter');
    dateTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const d = tab.dataset.date;
            dateTabs.forEach(t => {
                const active = t === tab;
                t.classList.toggle('active', active);
                t.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            let hasPanel = false;
            panels.forEach(p => {
                const match = p.dataset.datePanel === d;
                p.style.display = match ? '' : 'none';
                if (match) hasPanel = true;
            });
            if (noShowtimeMsg) {
                noShowtimeMsg.style.display = hasPanel ? 'none' : '';
            }
            applyCinemaFilter();
        });
    });
    if (noShowtimeMsg && panels.length === 0) noShowtimeMsg.style.display = '';
    function shiftDate(direction) {
        const currentIdx = dateTabs.findIndex(t => t.classList.contains('active'));
        if (currentIdx === -1) return;
        const nextIdx = currentIdx + direction;
        if (nextIdx < 0 || nextIdx >= dateTabs.length) return;
        dateTabs[nextIdx].click();
    }
    prevDate?.addEventListener('click', () => shiftDate(-1));
    nextDate?.addEventListener('click', () => shiftDate(1));

    function renderCinemaOptions(city) {
        if (!cinemaFilter) return;
        const opts = document.createDocumentFragment();
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Tất cả rạp';
        opts.appendChild(defaultOpt);
        if (city && cinemasByCity[city]) {
            cinemasByCity[city].forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                opts.appendChild(opt);
            });
        } else {
            Object.values(cinemasByCity).flat().forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                opts.appendChild(opt);
            });
        }
        cinemaFilter.innerHTML = '';
        cinemaFilter.appendChild(opts);
    }

    function applyCinemaFilter() {
        const cityVal = cityFilter?.value || '';
        const cinemaVal = cinemaFilter?.value || '';
        const activePanel = panels.find(panel => panel.style.display !== 'none');
        const blocks = document.querySelectorAll('.cinema-block');
        let visible = 0;
        blocks.forEach(block => {
            const cCity = block.dataset.city || '';
            const cId = block.dataset.cinemaId || '';
            const matchCity = !cityVal || cCity === cityVal;
            const matchCinema = !cinemaVal || cId === cinemaVal;
            const show = matchCity && matchCinema;
            block.style.display = show ? '' : 'none';
            if (show && activePanel?.contains(block)) visible++;
        });
        if (noShowtimeMsg) {
            noShowtimeMsg.style.display = visible === 0 ? '' : 'none';
        }
    }

    cityFilter?.addEventListener('change', () => {
        renderCinemaOptions(cityFilter.value);
        applyCinemaFilter();
    });
    cinemaFilter?.addEventListener('change', applyCinemaFilter);
    renderCinemaOptions(cityFilter?.value || '');
    applyCinemaFilter();

    document.querySelectorAll('.showtime-pill').forEach(pill => {
        pill.addEventListener('click', (e) => {
            if (!isCustomer) {
                e.preventDefault();
                if (isAuthenticated) {
                    window.TDNotify.warning('Vui lòng dùng tài khoản khách hàng để đặt vé.');
                    return;
                }
                window.AuthModal?.open('login');
            }
        });
    });

    const trailerOverlay = document.getElementById('trailerOverlay');
    const trailerFrame = document.getElementById('trailerFrame');
    const trailerVideo = document.getElementById('trailerVideo');
    const closeTrailer = document.getElementById('closeTrailer');
    const openTrailerInline = document.getElementById('openTrailerInline');
    const playHero = document.getElementById('playTrailer');

    function toTrailerEmbed(url) {
        try {
            const u = new URL(url);
            if (u.hostname.includes('youtube.com')) {
                const vid = u.searchParams.get('v');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
                if (u.pathname.startsWith('/embed/')) return `${u.origin}${u.pathname}${u.search ? `${u.search}&autoplay=1` : '?autoplay=1'}`;
            }
            if (u.hostname === 'youtu.be') {
                const vid = u.pathname.replace('/', '');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
            }
        } catch (e) {}
        return url;
    }

    function openTrailer(url) {
        if (!url || !trailerOverlay || !trailerFrame || !trailerVideo) return;
        const isVideoFile = /\.(mp4|webm|mov)(?:[?#].*)?$/i.test(url);
        if (isVideoFile) {
            trailerFrame.src = '';
            trailerFrame.hidden = true;
            trailerVideo.src = url;
            trailerVideo.hidden = false;
            trailerVideo.play().catch(() => {});
        } else {
            trailerVideo.pause();
            trailerVideo.removeAttribute('src');
            trailerVideo.hidden = true;
            trailerFrame.src = toTrailerEmbed(url);
            trailerFrame.hidden = false;
        }
        const trailerLink = document.getElementById('trailerLink');
        if (trailerLink) trailerLink.href = url;
        trailerOverlay.classList.add('active');
        document.body.classList.add('modal-open');
    }
    function hideTrailer() {
        if (!trailerOverlay || !trailerFrame) return;
        trailerOverlay.classList.remove('active');
        trailerFrame.src = '';
        trailerFrame.hidden = true;
        trailerVideo?.pause();
        trailerVideo?.removeAttribute('src');
        if (trailerVideo) trailerVideo.hidden = true;
        document.body.classList.remove('modal-open');
    }
    openTrailerInline?.addEventListener('click', () => openTrailer(trailerUrl));
    playHero?.addEventListener('click', () => openTrailer(trailerUrl));
    closeTrailer?.addEventListener('click', hideTrailer);
    trailerOverlay?.addEventListener('click', (e) => { if (e.target === trailerOverlay) hideTrailer(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideTrailer(); });
