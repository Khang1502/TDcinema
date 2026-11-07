    const pageConfig = document.body?.dataset || {};
    let moviesData = [];
    try {
        moviesData = JSON.parse(pageConfig.movies || '[]');
    } catch (err) {
        moviesData = [];
    }
    const isCustomer = pageConfig.isCustomer === '1';
    const isAuthenticated = pageConfig.isAuthenticated === '1';
    const bookingOptionsUrl = pageConfig.bookingOptionsUrl || '';
    const quickBookingSaveUrl = pageConfig.quickBookingSaveUrl || '';
    const bookingCheckoutUrl = pageConfig.bookingCheckoutUrl || '';
    let savedQuickSelection = null;
    try {
        savedQuickSelection = JSON.parse(pageConfig.quickSelection || 'null');
    } catch (err) {
        savedQuickSelection = null;
    }

    // Tabs
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tab = link.dataset.tab;
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            document.querySelectorAll('[data-tab-panel]').forEach(panel => {
                panel.style.display = (panel.dataset.tabPanel === tab) ? 'block' : 'none';
            });
        });
    });

    // Toast helper
    function showToast(message, type = 'info', duration = 3200) {
        return window.TDNotify.show(message, { type, duration: type === 'error' ? 0 : duration });
    }

    // Trailer modal
    const trailerOverlay = document.getElementById('trailerOverlay');
    const trailerFrame = document.getElementById('trailerFrame');
    const trailerVideo = document.getElementById('trailerVideo');
    const closeTrailerBtn = document.getElementById('closeTrailer');
    const trailerLink = document.getElementById('trailerLink');

    function toTrailerEmbed(url) {
        if (!url) return '';
        try {
            const u = new URL(url);
            if (u.hostname.includes('youtube.com')) {
                const vid = u.searchParams.get('v');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
                if (u.pathname.startsWith('/embed/')) {
                    return `${u.origin}${u.pathname}${u.search ? `${u.search}&autoplay=1` : '?autoplay=1'}`;
                }
            }
            if (u.hostname === 'youtu.be') {
                const vid = u.pathname.replace('/', '');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
            }
        } catch (err) {
            // giữ nguyên nếu URL không hợp lệ hoặc không parse được
        }
        return url;
    }

    function openTrailer(url) {
        if (!url) {
            window.TDNotify.info('Chưa có trailer cho phim này.');
            return;
        }
        if (!trailerOverlay || !trailerFrame || !trailerVideo) {
            window.open(url, '_blank');
            return;
        }
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
        if (trailerLink) {
            trailerLink.href = url;
            trailerLink.style.display = url ? 'inline-flex' : 'none';
        }
        trailerOverlay.classList.add('active');
        document.body.classList.add('modal-open');
    }

    function closeTrailer() {
        if (!trailerOverlay || !trailerFrame) return;
        trailerOverlay.classList.remove('active');
        trailerFrame.src = '';
        trailerFrame.hidden = true;
        trailerVideo?.pause();
        trailerVideo?.removeAttribute('src');
        if (trailerVideo) trailerVideo.hidden = true;
        if (trailerLink) {
            trailerLink.href = '#';
        }
        document.body.classList.remove('modal-open');
    }

    closeTrailerBtn?.addEventListener('click', closeTrailer);
    trailerOverlay?.addEventListener('click', (e) => {
        if (e.target === trailerOverlay) closeTrailer();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && trailerOverlay?.classList.contains('active')) {
            closeTrailer();
        }
    });

    // Booking bar logic (custom dropdown, tuần tự 1-2-3-4)
    const dropdowns = {
        movie: {box: document.querySelector('.qb-custom[data-target="movie"]'), panel: document.getElementById('moviePanel'), valueEl: document.getElementById('movieValue'), placeholder: '-- Chọn phim --'},
        cinema:{box: document.querySelector('.qb-custom[data-target="cinema"]'), panel: document.getElementById('cinemaPanel'), valueEl: document.getElementById('cinemaValue'), placeholder: '-- Chọn rạp --'},
        date:  {box: document.querySelector('.qb-custom[data-target="date"]'), panel: document.getElementById('datePanel'), valueEl: document.getElementById('dateValue'), placeholder: '-- Chọn ngày --'},
        showtime:{box: document.querySelector('.qb-custom[data-target="showtime"]'), panel: document.getElementById('showtimePanel'), valueEl: document.getElementById('showtimeValue'), placeholder: '-- Chọn suất --'},
    };
    const quickBuyBtn = document.getElementById('quickBuyBtn');

    let selectedMovie = '', selectedCinema = '', selectedDate = '', selectedShowtime = '';
    let cinemaOptions = [];
    const dateOptionsByCinema = {};
    const showtimesByCinemaDate = {};
    const stepIds = {
        movie: 'stepMovie',
        cinema: 'stepCinema',
        date: 'stepDate',
        showtime: 'stepShowtime',
    };

    function setDisabled(name, disabled, resetLabel=true) {
        const d = dropdowns[name];
        if (!d) return;
        d.box?.classList.toggle('qb-disabled', disabled);
        if (resetLabel && d.valueEl) {
            d.valueEl.textContent = d.placeholder;
            d.valueEl.title = d.placeholder;
        }
        if (d.panel) d.panel.innerHTML = '';
        const step = document.getElementById(stepIds[name] || '');
        if (step) {
            step.classList.toggle('disabled', disabled);
            if (!disabled && name !== 'movie') {
                step.classList.add('active');
            }
        }
    }

    function renderOptions(name, data, onSelect, selectedValue = '') {
        const d = dropdowns[name];
        if (!d || !d.panel) return;
        d.panel.innerHTML = '';
        if (!data || data.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'qb-option';
            empty.textContent = 'Không có lựa chọn';
            empty.style.opacity = '0.7';
            d.panel.appendChild(empty);
            return;
        }
        data.forEach(item => {
            const opt = document.createElement('div');
            opt.className = 'qb-option';
            opt.dataset.value = item.value;
            opt.textContent = item.label;
            if (selectedValue && String(selectedValue) === String(item.value)) {
                opt.classList.add('selected');
            }
            opt.addEventListener('click', () => {
                d.panel.querySelectorAll('.qb-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                d.valueEl.textContent = item.label;
                d.valueEl.title = item.label;
                d.panel.classList.add('hidden');
                onSelect(item);
            });
            d.panel.appendChild(opt);
        });
    }

    function closeAllPanels(except) {
        Object.values(dropdowns).forEach(d => {
            if (d.panel && d.panel !== except) d.panel.classList.add('hidden');
        });
    }

    // Toggle panels on click
    Object.keys(dropdowns).forEach(name => {
        const d = dropdowns[name];
        if (!d.box || !d.panel) return;
        const display = d.box.querySelector('.qb-display');
        display?.addEventListener('click', () => {
            if (d.box.classList.contains('qb-disabled')) return;
            closeAllPanels(d.panel);
            d.panel.classList.toggle('hidden');
        });
    });
    document.addEventListener('click', e => {
        const isInside = Object.values(dropdowns).some(d => d.box && d.box.contains(e.target));
        if (!isInside) closeAllPanels();
    });

    async function loadBookingData(movieId) {
        cinemaOptions = [];
        Object.keys(dateOptionsByCinema).forEach(k => delete dateOptionsByCinema[k]);
        Object.keys(showtimesByCinemaDate).forEach(k => delete showtimesByCinemaDate[k]);
        setDisabled('cinema', true);
        setDisabled('date', true);
        setDisabled('showtime', true);
        if (quickBuyBtn) quickBuyBtn.disabled = true;

        try {
            const url = bookingOptionsUrl ? `${bookingOptionsUrl}?movie_id=${movieId}` : '';
            if (!url) throw new Error('Missing booking options URL');
            const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
            if (!res.ok) throw new Error('Server error');
            const data = await res.json();
            cinemaOptions = (data.cinemas || []).map(c => ({
                value: c.id,
                label: c.name || 'Rạp',
                raw: c,
            }));
            cinemaOptions.forEach(c => {
                const dates = (c.raw.dates || []).map(d => ({ value: d.date, label: d.label }));
                dateOptionsByCinema[c.value] = dates;
                (c.raw.dates || []).forEach(d => {
                    const key = `${c.value}-${d.date}`;
                    showtimesByCinemaDate[key] = (d.showtimes || []).map(st => ({
                        value: st.id,
                        label: `${st.time} · ${st.room}`,
                    }));
                });
            });

            setDisabled('cinema', cinemaOptions.length === 0, true);
            renderOptions('cinema', cinemaOptions, cinemaSelected, selectedCinema);
            if (cinemaOptions.length === 0 && dropdowns.cinema?.panel) {
                dropdowns.cinema.panel.innerHTML = '<div class="qb-option" style="opacity:.7;">Không có suất cho phim này</div>';
                showToast('Phim này chưa có suất chiếu khả dụng.', 'info');
            }
        } catch (err) {
            console.error(err);
            setDisabled('cinema', true);
            if (dropdowns.cinema?.panel) {
                dropdowns.cinema.panel.innerHTML = '<div class="qb-option" style="opacity:.7;">Không tải được dữ liệu</div>';
            }
            showToast('Không tải được dữ liệu suất chiếu, vui lòng thử lại sau.', 'error');
        }
    }

    // Flows
    renderOptions('movie', moviesData, (item) => {
        selectedMovie = item.value;
        selectedCinema = selectedDate = selectedShowtime = '';
        loadBookingData(selectedMovie);
    }, selectedMovie);

    function cinemaSelected(item) {
        selectedCinema = item.value;
        selectedDate = selectedShowtime = '';
        const dates = dateOptionsByCinema[selectedCinema] || [];
        setDisabled('date', dates.length === 0, true);
        setDisabled('showtime', true);
        renderOptions('date', dates, dateSelected, selectedDate);
        if (quickBuyBtn) quickBuyBtn.disabled = true;
        if (dates.length === 0) {
            showToast('Rạp này chưa có lịch chiếu cho phim đã chọn.', 'info');
        }
    }
    function dateSelected(item) {
        selectedDate = item.value;
        selectedShowtime = '';
        const key = `${selectedCinema}-${selectedDate}`;
        const times = showtimesByCinemaDate[key] || [];
        setDisabled('showtime', times.length === 0, true);
        renderOptions('showtime', times, showtimeSelected, selectedShowtime);
        if (quickBuyBtn) quickBuyBtn.disabled = true;
        if (times.length === 0) {
            showToast('Ngày này chưa có suất chiếu.', 'info');
        }
    }
    function showtimeSelected(item) {
        selectedShowtime = item.value;
        if (quickBuyBtn) quickBuyBtn.disabled = !(selectedMovie && selectedCinema && selectedDate && selectedShowtime);
    }

    function setDropdownValue(name, item) {
        const valueEl = dropdowns[name]?.valueEl;
        if (!valueEl || !item) return;
        valueEl.textContent = item.label;
        valueEl.title = item.label;
    }

    async function restoreQuickSelection(selection) {
        if (!selection) return;

        const movie = moviesData.find(item => String(item.value) === String(selection.movie_id));
        if (!movie) return;

        selectedMovie = movie.value;
        setDropdownValue('movie', movie);
        await loadBookingData(selectedMovie);

        const cinema = cinemaOptions.find(item => String(item.value) === String(selection.cinema_id));
        if (!cinema) return;
        setDropdownValue('cinema', cinema);
        cinemaSelected(cinema);

        const date = (dateOptionsByCinema[selectedCinema] || [])
            .find(item => String(item.value) === String(selection.date));
        if (!date) return;
        setDropdownValue('date', date);
        dateSelected(date);

        const showtime = (showtimesByCinemaDate[`${selectedCinema}-${selectedDate}`] || [])
            .find(item => String(item.value) === String(selection.showtime_id));
        if (!showtime) return;
        setDropdownValue('showtime', showtime);
        showtimeSelected(showtime);
    }

    function selectMovie(movieId, label) {
        selectedMovie = movieId;
        selectedCinema = selectedDate = selectedShowtime = '';
        if (dropdowns.movie.valueEl) {
            dropdowns.movie.valueEl.textContent = label || dropdowns.movie.placeholder;
            dropdowns.movie.valueEl.title = label || dropdowns.movie.placeholder;
        }
        setDisabled('cinema', true);
        setDisabled('date', true);
        setDisabled('showtime', true);
        loadBookingData(movieId);
        closeAllPanels();
        const bar = document.querySelector('.booking-bar');
        if (bar) bar.scrollIntoView({ behavior: 'smooth' });
    }

    // Buy buttons on cards
    document.querySelectorAll('.btn-buy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const detailUrl = btn.dataset.detail;
            if (detailUrl) {
                window.location.href = detailUrl;
                return false;
            }
            const id = btn.dataset.movieId;
            const title = btn.dataset.movieTitle;
            if (id) selectMovie(id, title);
        });
    });

    // Trailer buttons on cards
    document.querySelectorAll('.btn-trailer').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openTrailer(btn.dataset.trailer);
        });
    });

    // Ngăn overlay click truyền xuống poster link
    document.querySelectorAll('.overlay').forEach(ov => {
        ov.addEventListener('click', (e) => e.stopPropagation());
    });

    quickBuyBtn?.addEventListener('click', async () => {
        if (quickBuyBtn.disabled) return;
        if (!isCustomer) {
            if (isAuthenticated) {
                showToast('Tai khoan nay khong duoc dat ve.', 'info');
                return;
            }
            closeAllPanels();
            const originalLabel = quickBuyBtn.textContent;
            quickBuyBtn.disabled = true;
            quickBuyBtn.textContent = 'Đang lưu...';
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const response = await fetch(quickBookingSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        movie_id: selectedMovie,
                        cinema_id: selectedCinema,
                        date: selectedDate,
                        showtime_id: selectedShowtime,
                    }),
                });
                if (!response.ok) throw new Error('Unable to save quick booking');
                window.AuthModal?.open('login');
            } catch (err) {
                console.error(err);
                showToast('Không thể lưu lựa chọn đặt vé. Vui lòng thử lại.', 'error');
            } finally {
                quickBuyBtn.textContent = originalLabel;
                quickBuyBtn.disabled = !(selectedMovie && selectedCinema && selectedDate && selectedShowtime);
            }
            return;
        }
        if (bookingCheckoutUrl) {
            window.location.href = `${bookingCheckoutUrl}/${selectedShowtime}`;
        }
    });

    restoreQuickSelection(savedQuickSelection);
