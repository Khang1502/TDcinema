document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-counter-form]');
    if (!form) return;
    const maxSeats = Number(form.dataset.maxSeats || 8);

    const filters = Object.fromEntries(
        ['movie', 'cinema', 'room', 'date', 'showtime'].map(key => [key, form.querySelector(`[data-filter="${key}"]`)])
    );
    const originalShowtimeId = filters.showtime.value;
    const initialFilters = Object.fromEntries(Object.entries(filters).map(([key, select]) => [key, select.value]));
    const stages = [
        { key: 'cinema', stage: 'cinemas', placeholder: 'Chọn rạp' },
        { key: 'room', stage: 'rooms', placeholder: 'Chọn phòng' },
        { key: 'date', stage: 'dates', placeholder: 'Chọn ngày' },
        { key: 'showtime', stage: 'showtimes', placeholder: 'Chọn giờ chiếu' },
    ];
    const loadError = form.querySelector('[data-showtime-load-error]');
    let filterVersion = 0;

    function fillSelect(select, placeholder, options, selected = '') {
        select.replaceChildren(new Option(placeholder, ''));
        options.forEach(({ value, label }) => select.add(new Option(label, value)));
        select.value = options.some(option => String(option.value) === String(selected)) ? String(selected) : '';
        select.disabled = options.length === 0;
    }

    function updateSeatVisibility() {
        const seatContent = form.querySelector('[data-seat-content]');
        const seatPlaceholder = form.querySelector('[data-seat-placeholder]');
        const seatAvailability = form.querySelector('[data-seat-availability]');
        if (seatContent && seatPlaceholder) {
            const showingOriginal = !originalShowtimeId || filters.showtime.value === originalShowtimeId;
            seatContent.hidden = !showingOriginal;
            seatPlaceholder.hidden = showingOriginal;
            if (seatAvailability) seatAvailability.hidden = !showingOriginal;
        }
    }

    async function loadFrom(index, selected = {}) {
        const version = ++filterVersion;
        loadError.hidden = true;
        for (const { key, placeholder } of stages.slice(index)) fillSelect(filters[key], placeholder, []);
        updateSeatVisibility();

        for (const { key, stage, placeholder } of stages.slice(index)) {
            if (!filters.movie.value || (key !== 'cinema' && !filters.cinema.value)
                || (['date', 'showtime'].includes(key) && !filters.room.value)
                || (key === 'showtime' && !filters.date.value)) break;
            const url = new URL(form.dataset.showtimeOptionsUrl, window.location.href);
            url.searchParams.set('stage', stage);
            url.searchParams.set('movie_id', filters.movie.value);
            if (filters.cinema.value) url.searchParams.set('cinema_id', filters.cinema.value);
            if (filters.room.value) url.searchParams.set('room_id', filters.room.value);
            if (filters.date.value) url.searchParams.set('date', filters.date.value);
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to load showtimes');
                const data = await response.json();
                if (version !== filterVersion) return;
                fillSelect(filters[key], placeholder, data.options || [], selected[key]);
                updateSeatVisibility();
                if (!filters[key].value) break;
            } catch (error) {
                if (version !== filterVersion) return;
                loadError.hidden = false;
                break;
            }
        }
    }

    filters.movie.disabled = filters.movie.options.length <= 1;
    if (filters.movie.value) loadFrom(0, initialFilters);
    else stages.forEach(({ key, placeholder }) => fillSelect(filters[key], placeholder, []));
    filters.movie.addEventListener('change', () => loadFrom(0));
    filters.cinema.addEventListener('change', () => loadFrom(1));
    filters.room.addEventListener('change', () => loadFrom(2));
    filters.date.addEventListener('change', () => loadFrom(3));
    filters.showtime.addEventListener('change', () => {
        updateSeatVisibility();
        if (filters.showtime.value) {
            const url = new URL(form.dataset.createUrl, window.location.href);
            url.searchParams.set('showtime_id', filters.showtime.value);
            window.location.assign(url);
        }
    });

    const moviePicker = form.querySelector('[data-movie-picker]');
    const movieMenu = form.querySelector('[data-movie-menu]');
    const movieSearch = form.querySelector('[data-movie-search]');
    const movieResults = form.querySelector('[data-movie-results]');
    const movieEmpty = form.querySelector('[data-movie-empty]');
    if (moviePicker && movieMenu && movieSearch && movieResults && movieEmpty) {
        const movieOptions = [...filters.movie.options].filter(option => option.value);
        const normalizeMovieName = name => name.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/gi, 'd').toLocaleLowerCase('vi').trim();
        const visibleMovieOptions = () => [...movieResults.querySelectorAll('[role="option"]:not([hidden])')];
        let restoringFocus = false;
        const filterMovieOptions = () => {
            const query = normalizeMovieName(movieSearch.value);
            movieResults.querySelectorAll('[role="option"]').forEach(option => {
                option.hidden = !normalizeMovieName(option.textContent).includes(query);
            });
            movieEmpty.hidden = visibleMovieOptions().length > 0;
        };
        const closeMovieMenu = () => {
            movieMenu.hidden = true;
            movieSearch.setAttribute('aria-expanded', 'false');
        };
        const openMovieMenu = () => {
            if (movieSearch.disabled) return;
            movieMenu.hidden = false;
            movieSearch.setAttribute('aria-expanded', 'true');
            filterMovieOptions();
        };
        const chooseMovie = value => {
            filters.movie.value = value;
            movieSearch.value = filters.movie.selectedOptions[0]?.textContent.trim() || '';
            movieResults.querySelectorAll('[role="option"]').forEach(option => {
                option.setAttribute('aria-selected', String(option.dataset.value === value));
            });
            closeMovieMenu();
            restoringFocus = true;
            movieSearch.focus();
            restoringFocus = false;
            filters.movie.dispatchEvent(new Event('change', { bubbles: true }));
        };
        movieOptions.forEach((option, index) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.id = `counter-movie-option-${index}`;
            item.className = 'counter-movie-option';
            item.dataset.value = option.value;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.value === filters.movie.value));
            item.textContent = option.textContent.trim();
            item.addEventListener('click', () => chooseMovie(option.value));
            item.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeMovieMenu();
                    restoringFocus = true;
                    movieSearch.focus();
                    restoringFocus = false;
                } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    const items = visibleMovieOptions();
                    const current = items.indexOf(item);
                    if (items.length) items[(current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
                }
            });
            movieResults.append(item);
        });
        movieSearch.addEventListener('input', () => {
            const selectedName = filters.movie.selectedOptions[0]?.textContent.trim() || '';
            if (filters.movie.value && normalizeMovieName(movieSearch.value) !== normalizeMovieName(selectedName)) {
                filters.movie.value = '';
                filters.movie.dispatchEvent(new Event('change', { bubbles: true }));
            }
            openMovieMenu();
        });
        movieSearch.addEventListener('keydown', event => {
            const items = visibleMovieOptions();
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                items[event.key === 'ArrowDown' ? 0 : items.length - 1]?.focus();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (items.length) chooseMovie(items[0].dataset.value);
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeMovieMenu();
                movieSearch.value = filters.movie.value ? filters.movie.selectedOptions[0]?.textContent.trim() || '' : '';
            }
        });
        movieSearch.value = filters.movie.value ? filters.movie.selectedOptions[0]?.textContent.trim() || '' : '';
        movieSearch.disabled = movieOptions.length === 0;
        moviePicker.classList.add('is-enhanced');
        movieSearch.hidden = false;
        movieSearch.addEventListener('focus', () => {
            if (restoringFocus) return;
            openMovieMenu();
            if (filters.movie.value) movieSearch.select();
        });
        movieSearch.addEventListener('click', () => {
            openMovieMenu();
            if (filters.movie.value) movieSearch.select();
        });
        moviePicker.addEventListener('focusout', () => {
            window.setTimeout(() => { if (!moviePicker.contains(document.activeElement)) closeMovieMenu(); }, 0);
        });
        document.addEventListener('click', event => {
            if (!moviePicker.contains(event.target)) closeMovieMenu();
        });
    }

    const seatInputs = [...form.querySelectorAll('input[name="seat_ids[]"]')];
    const foodInputs = [...form.querySelectorAll('[data-food-quantity]')];
    const money = value => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
    let seatTotal = 0;
    let foodTotal = 0;
    let promotions = [];
    let membershipBenefit = null;
    function updateGrandTotal() {
        const subtotal = seatTotal + foodTotal;
        const isMember = form.querySelector('input[name="customer_type"][value="member"]')?.checked
            && form.querySelector('[data-member-id]')?.value;
        const code = form.querySelector('input[name="promo_code"]:checked')?.value || '';
        const promotion = isMember ? promotions.find(item => item.code === code) : null;
        const discount = promotion
            ? Math.round(Math.min(subtotal, promotion.type === 'percent' ? subtotal * promotion.value / 100 : promotion.value))
            : isMember ? Math.round(seatTotal * (membershipBenefit?.discount_percent || 0) / 100) : 0;
        const line = form.querySelector('[data-counter-discount-line]');
        line.hidden = discount <= 0;
        form.querySelector('[data-counter-discount-label]').textContent = promotion
            ? `Mã ${promotion.code}` : `Ưu đãi hạng ${membershipBenefit?.level || 'thành viên'}`;
        form.querySelector('[data-counter-discount]').textContent = `−${money(discount)}`;
        form.querySelector('[data-counter-grand-total]').textContent = money(Math.max(0, subtotal - discount));
    }
    function updateSeats() {
        const selected = seatInputs.filter(input => input.checked && !input.disabled);
        const names = selected.map(input => input.dataset.code);
        seatTotal = selected.reduce((sum, input) => sum + Number(input.dataset.price), 0);
        const selectedText = form.querySelector('[data-counter-selected-seats]');
        const countText = form.querySelector('[data-counter-seat-count]');
        const totalText = form.querySelector('[data-counter-total]');
        if (selectedText) selectedText.textContent = names.length ? names.join(', ') : 'Chưa chọn ghế';
        if (countText) countText.textContent = `${selected.length} ghế`;
        if (totalText) totalText.textContent = money(seatTotal);
        updateGrandTotal();
    }
    seatInputs.forEach(input => input.addEventListener('change', () => {
        if (seatInputs.filter(seat => seat.checked).length > maxSeats) {
            input.checked = false;
            window.alert(`Chỉ có thể chọn tối đa ${maxSeats} ghế.`);
        }
        updateSeats();
    }));
    updateSeats();
    function updateFood() {
        foodTotal = foodInputs.reduce((sum, input) => sum + Math.max(0, Number(input.value) || 0) * Number(input.dataset.price), 0);
        form.querySelector('[data-counter-food-total]').textContent = money(foodTotal);
        updateGrandTotal();
    }
    foodInputs.forEach(input => input.addEventListener('input', updateFood));
    updateFood();

    const guestRadio = form.querySelector('input[name="customer_type"][value="guest"]');
    const memberRadio = form.querySelector('input[name="customer_type"][value="member"]');
    const memberPanel = form.querySelector('[data-member-panel]');
    const memberInput = form.querySelector('[data-member-input]');
    const memberId = form.querySelector('[data-member-id]');
    const results = form.querySelector('[data-member-results]');
    const status = form.querySelector('[data-member-status]');
    const promotionPanel = form.querySelector('[data-member-promotions]');
    const promotionList = form.querySelector('[data-promo-list]');
    const promoEmpty = form.querySelector('[data-promo-empty]');
    const promoLoading = form.querySelector('[data-promo-loading]');
    const promoLoadError = form.querySelector('[data-promo-load-error]');
    let searchTimer;
    let searchController;
    let promotionController;
    let activeIndex = -1;

    function closeResults() {
        results.hidden = true;
        memberInput.setAttribute('aria-expanded', 'false');
        memberInput.removeAttribute('aria-activedescendant');
        activeIndex = -1;
    }

    function setStatus(message, isError = false) {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    }

    function clearPromotions() {
        promotionController?.abort();
        promotions = [];
        membershipBenefit = null;
        promotionList.replaceChildren();
        promotionPanel.hidden = true;
        promoEmpty.hidden = true;
        promoLoading.hidden = true;
        promoLoadError.hidden = true;
        updateGrandTotal();
    }

    function promotionChoice(code, name, detail, badge = '') {
        const label = document.createElement('label');
        label.className = 'counter-promo-choice';
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = 'promo_code';
        input.value = code;
        const text = document.createElement('span');
        const title = document.createElement('strong');
        title.textContent = name;
        const description = document.createElement('small');
        description.textContent = detail;
        text.append(title, description);
        label.append(input, text);
        if (badge) {
            const tag = document.createElement('b');
            tag.textContent = badge;
            label.append(tag);
        }
        return label;
    }

    function renderPromotions(preferredCode = '') {
        promotionList.replaceChildren();
        const tierText = membershipBenefit?.discount_percent > 0
            ? `Tự động giảm ${membershipBenefit.discount_percent}% theo hạng ${membershipBenefit.level}.`
            : 'Thanh toán theo giá vé và combo hiện tại.';
        promotionList.append(promotionChoice('', 'Không dùng mã khuyến mãi', tierText));
        promotions.forEach(promotion => {
            promotionList.append(promotionChoice(promotion.code, promotion.name,
                `${promotion.code} · Hạn ${promotion.end_date}${promotion.description ? ' · '+promotion.description : ''}`,
                `Giảm ${promotion.discount_label}`));
        });
        const preferred = [...promotionList.querySelectorAll('input[name="promo_code"]')]
            .find(input => input.value === preferredCode);
        (preferred || promotionList.querySelector('input[name="promo_code"]')).checked = true;
        promoEmpty.hidden = promotions.length > 0;
        updateGrandTotal();
    }

    async function loadPromotions(userId, preferredCode = '') {
        promotionController?.abort();
        promotionController = new AbortController();
        const controller = promotionController;
        promotions = [];
        membershipBenefit = null;
        promotionPanel.hidden = false;
        promotionList.replaceChildren();
        promoEmpty.hidden = true;
        promoLoadError.hidden = true;
        promoLoading.hidden = false;
        updateGrandTotal();
        try {
            const url = new URL(form.dataset.memberPromotionsUrl, window.location.href);
            url.searchParams.set('user_id', userId);
            const response = await fetch(url, { signal: controller.signal,
                headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Unable to load promotions');
            const data = await response.json();
            if (controller.signal.aborted || memberId.value !== String(userId) || !memberRadio.checked) return;
            promotions = data.promotions || [];
            membershipBenefit = data.membership || null;
            promoLoading.hidden = true;
            renderPromotions(preferredCode);
        } catch (error) {
            if (controller.signal.aborted) return;
            promoLoading.hidden = true;
            promoLoadError.hidden = false;
            updateGrandTotal();
        }
    }
    promotionList.addEventListener('change', updateGrandTotal);

    function selectMember(member) {
        memberId.value = member.id;
        memberInput.value = member.name;
        setStatus(`Đã chọn: ${member.name} · ${member.email}`);
        closeResults();
        loadPromotions(member.id);
        memberInput.focus();
    }

    function renderMembers(members) {
        results.replaceChildren();
        if (!members.length) {
            closeResults();
            setStatus('Không tìm thấy thành viên phù hợp.');
            return;
        }
        members.forEach((member, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.id = `counter-member-option-${index}`;
            button.className = 'counter-member-option';
            button.setAttribute('role', 'option');
            const name = document.createElement('strong');
            name.textContent = member.name;
            const details = document.createElement('span');
            details.textContent = [member.phone, member.email].filter(Boolean).join(' · ');
            button.append(name, details);
            button.addEventListener('click', () => selectMember(member));
            results.append(button);
        });
        results.hidden = false;
        memberInput.setAttribute('aria-expanded', 'true');
        activeIndex = -1;
        setStatus(`${members.length} thành viên phù hợp. Chọn một khách trong danh sách.`);
    }

    function searchMembers() {
        window.clearTimeout(searchTimer);
        searchController?.abort();
        closeResults();
        const query = memberInput.value.trim();
        memberId.value = '';
        clearPromotions();
        if (!memberRadio.checked || query.length < 2) {
            setStatus('Nhập ít nhất 2 ký tự để tìm thành viên.');
            return;
        }
        setStatus('Đang tìm thành viên...');
        searchTimer = window.setTimeout(async () => {
            searchController = new AbortController();
            try {
                const url = new URL(form.dataset.memberSearchUrl, window.location.href);
                url.searchParams.set('q', query);
                const response = await fetch(url, { signal: searchController.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Search failed');
                const data = await response.json();
                if (memberInput.value.trim() === query && memberRadio.checked) renderMembers(data.members || []);
            } catch (error) {
                if (error.name !== 'AbortError') setStatus('Không thể tìm thành viên. Vui lòng thử lại.', true);
            }
        }, 250);
    }

    memberInput.addEventListener('input', searchMembers);
    memberInput.addEventListener('keydown', event => {
        const options = [...results.querySelectorAll('[role="option"]')];
        if (results.hidden || !options.length) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = (activeIndex + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.classList.toggle('is-active', index === activeIndex));
            memberInput.setAttribute('aria-activedescendant', options[activeIndex].id);
            options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            options[activeIndex].click();
        } else if (event.key === 'Escape') {
            closeResults();
        }
    });
    document.addEventListener('click', event => {
        if (!memberPanel.contains(event.target)) closeResults();
    });
    guestRadio.addEventListener('change', () => {
        if (!guestRadio.checked) return;
        memberPanel.hidden = true;
        memberId.value = '';
        memberInput.value = '';
        clearPromotions();
        searchController?.abort();
        window.clearTimeout(searchTimer);
        closeResults();
        setStatus('Chọn một khách trong danh sách gợi ý.');
    });
    memberRadio.addEventListener('change', () => {
        memberPanel.hidden = !memberRadio.checked;
        if (memberRadio.checked) {
            if (memberId.value) loadPromotions(memberId.value);
            memberInput.focus();
        }
    });
    if (memberRadio.checked && memberId.value) loadPromotions(memberId.value, form.dataset.initialPromoCode);

    form.addEventListener('submit', event => {
        if (!seatInputs.some(input => input.checked && !input.disabled) || filters.showtime.value !== originalShowtimeId) {
            event.preventDefault();
            window.alert('Vui lòng chọn suất chiếu và ít nhất một ghế.');
            return;
        }
        if (memberRadio.checked && !memberId.value) {
            event.preventDefault();
            setStatus('Vui lòng chọn thành viên từ danh sách gợi ý.', true);
            memberInput.focus();
            return;
        }
        form.querySelector('[data-counter-submit]').disabled = true;
    });
});
