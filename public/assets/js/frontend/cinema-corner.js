(() => {
    const config = document.body.dataset;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const tabs = [...document.querySelectorAll('[data-corner-tab]')];
    const corner = document.getElementById('cinema-corner');
    if (corner) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                corner.querySelectorAll('img').forEach(img => { img.loading = 'eager'; });
                observer.disconnect();
            }
        }, {rootMargin:'250px'});
        observer.observe(corner);
    }
    const selectTab = (tab, focus = false) => {
        tabs.forEach(button => {
            const active = button === tab;
            button.setAttribute('aria-selected', String(active));
            button.tabIndex = active ? 0 : -1;
        });
        document.querySelectorAll('[data-corner-panel]').forEach(panel => { panel.hidden = panel.dataset.cornerPanel !== tab.dataset.cornerTab; });
        if (focus) tab.focus();
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab));
        tab.addEventListener('keydown', event => {
            let target;
            if (event.key === 'ArrowRight') target = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft') target = tabs[(index + tabs.length - 1) % tabs.length];
            if (event.key === 'Home') target = tabs[0];
            if (event.key === 'End') target = tabs[tabs.length - 1];
            if (target) { event.preventDefault(); selectTab(target, true); }
        });
    });

    document.querySelectorAll('[data-corner-like]').forEach(button => {
        button.addEventListener('click', async () => {
            const message = document.querySelector(`[data-like-message="${button.dataset.cornerLike}"]`);
            if (config.isAuthenticated !== '1') {
                if (window.AuthModal) window.AuthModal.open('login');
                else window.location.href = '/login';
                return;
            }
            button.disabled = true;
            if (message) message.textContent = '';
            try {
                const response = await fetch(button.dataset.url, {
                    method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},
                    body:JSON.stringify({liked:button.getAttribute('aria-pressed') !== 'true'})
                });
                if (!response.ok) throw new Error(response.status === 419 ? 'Phiên đã hết. Hãy tải lại trang.' : 'Chưa thể lưu lượt thích. Vui lòng thử lại.');
                const data = await response.json();
                document.querySelectorAll(`[data-corner-like="${button.dataset.cornerLike}"]`).forEach(element => {
                    element.setAttribute('aria-pressed', String(data.liked));
                    element.classList.toggle('is-liked', data.liked);
                    element.querySelector('[data-like-label]').textContent = data.liked ? 'Đã thích' : 'Thích';
                    element.setAttribute('aria-label', element.getAttribute('aria-label').replace(/^(Thích|Bỏ thích)/, data.liked ? 'Bỏ thích' : 'Thích'));
                    element.querySelector('[data-like-count]').textContent = data.likes || '';
                });
            } catch (error) { window.TDNotify.error(error.message); }
            finally { button.disabled = false; }
        });
    });

    const booking = document.querySelector('[data-editorial-booking]');
    if (booking) {
        const movie = booking.elements.movie_id, cinema = booking.elements.cinema_id, date = booking.elements.date, showtime = booking.elements.showtime_id;
        const submit = booking.querySelector('button[type="submit"]');
        const message = booking.querySelector('.editorial-booking-message');
        let cinemas = [], pending;
        function options(select, values, placeholder) {
            select.replaceChildren(new Option(placeholder, ''));
            values.forEach(value => select.add(new Option(value.label, value.value)));
            select.disabled = !values.length;
        }
        function chooseCinema() {
            const selected = cinemas.find(item => String(item.id) === cinema.value);
            options(date, (selected?.dates || []).map(item => ({value:item.date,label:item.label})), 'Chọn ngày');
            options(showtime, [], 'Chọn suất chiếu'); submit.disabled = true;
        }
        function chooseDate() {
            const selected = cinemas.find(item => String(item.id) === cinema.value)?.dates?.find(item => item.date === date.value);
            options(showtime, (selected?.showtimes || []).map(item => ({value:item.id,label:`${item.time} · ${item.room}`})), 'Chọn suất chiếu'); submit.disabled = true;
        }
        async function chooseMovie() {
            pending?.abort(); pending = new AbortController();
            cinemas = []; options(cinema, [], 'Chọn rạp'); options(date, [], 'Chọn ngày'); options(showtime, [], 'Chọn suất chiếu'); submit.disabled = true; message.textContent = '';
            if (!movie.value) return;
            message.textContent = 'Đang tải lịch chiếu...';
            try {
                const response = await fetch(`${config.bookingOptionsUrl}?movie_id=${encodeURIComponent(movie.value)}`, {signal:pending.signal,headers:{Accept:'application/json'}});
                if (!response.ok) throw new Error('Không tải được lịch chiếu. Hãy chọn lại phim.');
                const data = await response.json(); cinemas = data.cinemas || [];
                options(cinema, cinemas.map(item => ({value:item.id,label:item.name})), 'Chọn rạp');
                message.textContent = '';
                if (!cinemas.length) window.TDNotify.info('Phim này chưa có lịch chiếu.');
            } catch (error) { if (error.name !== 'AbortError') { message.textContent = ''; window.TDNotify.error(error.message); } }
        }
        movie.addEventListener('change', chooseMovie); cinema.addEventListener('change', chooseCinema); date.addEventListener('change', chooseDate);
        showtime.addEventListener('change', () => { submit.disabled = !(movie.value && cinema.value && date.value && showtime.value); });
        booking.addEventListener('submit', async event => {
            event.preventDefault(); if (!booking.reportValidity() || submit.disabled) return;
            if (config.isCustomer === '1') { window.location.href = `${config.bookingCheckoutUrl}/${showtime.value}`; return; }
            if (config.isAuthenticated === '1') { window.TDNotify.warning('Vui lòng dùng tài khoản khách hàng để đặt vé.'); return; }
            submit.disabled = true; message.textContent = 'Đang lưu lựa chọn...';
            try {
                const response = await fetch(config.quickBookingSaveUrl, {method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':csrf()},body:JSON.stringify(Object.fromEntries(new FormData(booking)))});
                if (!response.ok) throw new Error('Không thể lưu lựa chọn. Vui lòng tải lại trang và thử lại.');
                message.textContent = '';
                window.TDNotify.info('Đã lưu lựa chọn. Đăng nhập để tiếp tục chọn ghế.');
                window.AuthModal?.open('login');
            } catch (error) { message.textContent = ''; window.TDNotify.error(error.message); }
            finally { submit.disabled = false; }
        });
        (async () => {
            let selection; try { selection = JSON.parse(config.quickSelection || 'null'); } catch (_) {}
            if (!selection || ![...movie.options].some(option => option.value === String(selection.movie_id))) return;
            movie.value = String(selection.movie_id); await chooseMovie();
            cinema.value = String(selection.cinema_id); chooseCinema(); date.value = selection.date; chooseDate(); showtime.value = String(selection.showtime_id);
            submit.disabled = !(movie.value && cinema.value && date.value && showtime.value);
        })();
    }

    const trailer = document.getElementById('trailerOverlay');
    if (document.body.classList.contains('editorial-page') && trailer) {
        const frame = document.getElementById('trailerFrame'), video = document.getElementById('trailerVideo');
        function closeTrailer() { trailer.classList.remove('active'); frame.src = ''; video.pause(); video.removeAttribute('src'); frame.hidden = true; video.hidden = true; }
        document.querySelectorAll('[data-corner-trailer]').forEach(button => button.addEventListener('click', () => {
            const source = button.dataset.cornerTrailer; const link = document.getElementById('trailerLink'); link.href = source;
            closeTrailer();
            if (/\.(mp4|webm|mov)(?:\?|$)/i.test(source)) { video.src = source; video.hidden = false; }
            else {
                let url; try { url = new URL(source, location.origin); } catch (_) { return; }
                let embed;
                if (['youtube.com','www.youtube.com','m.youtube.com','youtu.be'].includes(url.hostname)) {
                    const id = url.hostname === 'youtu.be' ? url.pathname.slice(1) : url.searchParams.get('v') || url.pathname.split('/').pop();
                    if (/^[\w-]{11}$/.test(id || '')) embed = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1`;
                } else if (['vimeo.com','www.vimeo.com'].includes(url.hostname) && /^\/\d+$/.test(url.pathname)) embed = `https://player.vimeo.com/video${url.pathname}`;
                if (!embed) { window.open(source, '_blank', 'noopener'); return; }
                frame.src = embed; frame.hidden = false;
            }
            trailer.classList.add('active'); document.getElementById('closeTrailer').focus();
        }));
        document.getElementById('closeTrailer').addEventListener('click', closeTrailer);
        trailer.addEventListener('click', event => { if (event.target === trailer) closeTrailer(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeTrailer(); });
    }
})();
