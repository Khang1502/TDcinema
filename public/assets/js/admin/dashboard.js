(() => {
    const root = document.querySelector('[data-dashboard]');
    if (!root) return;
    const format = new Intl.NumberFormat('vi-VN', {maximumFractionDigits:0});
    const axisFormat = value => value >= 1e9 ? (value/1e9).toLocaleString('vi-VN',{maximumFractionDigits:1})+' tỷ' : value >= 1e6 ? (value/1e6).toLocaleString('vi-VN',{maximumFractionDigits:1})+' tr' : value >= 1000 ? format.format(value/1000)+'k' : format.format(value);
    const keyboardTabs = (tabs, select) => tabs.forEach((tab,index) => {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length-1 : (index+(event.key === 'ArrowRight' ? 1 : -1)+tabs.length)%tabs.length;
            tabs[next].focus(); select(tabs[next]);
        });
    });
    const chart = root.querySelector('[data-chart]');
    if (chart) {
        const tabs = [...chart.querySelectorAll('[data-chart-metric]')];
        const bars = [...chart.querySelectorAll('[data-label]')];
        const detail = chart.querySelector('[data-chart-detail]');
        let metric = 'revenue', selected = null;
        const point = bar => {
            selected = bar;
            bars.forEach(item => item.classList.toggle('is-selected', item === bar));
            detail.textContent = bar.dataset.label+' · '+format.format(Number(bar.dataset.revenue))+' ₫ · '+format.format(Number(bar.dataset.bookings))+' vé đã thanh toán';
        };
        bars.forEach(bar => {
            bar.addEventListener('pointerenter', () => point(bar));
            bar.addEventListener('focus', () => point(bar));
            bar.addEventListener('click', () => point(bar));
        });
        keyboardTabs(tabs, tab => {
            metric = tab.dataset.chartMetric;
            tabs.forEach(item => {const active=item === tab;item.classList.toggle('is-active',active);item.setAttribute('aria-selected',String(active));item.tabIndex=active?0:-1;});
            chart.classList.toggle('is-bookings',metric === 'bookings');
            chart.querySelector('[role="tabpanel"]').setAttribute('aria-labelledby',tab.id);
            const max = Number(chart.dataset[metric+'Max']), total = Number(chart.dataset[metric+'Total']);
            chart.querySelector('[data-chart-total]').textContent = format.format(total)+(metric === 'revenue' ? ' ₫' : ' vé');
            chart.querySelectorAll('[data-axis-fraction]').forEach(tick => {
                const value = max * Number(tick.dataset.axisFraction);
                tick.textContent = metric === 'revenue' ? axisFormat(value) : format.format(value);
            });
            bars.forEach(bar => bar.firstElementChild.style.setProperty('--db-bar-height', Number(bar.dataset[metric])/max*100+'%'));
            const empty = chart.querySelector('[data-chart-empty]');
            empty.hidden = total > 0;
            empty.querySelector('p').textContent = metric === 'revenue' ? 'Chưa có doanh thu trong kỳ này.' : 'Chưa có vé đã thanh toán trong kỳ này.';
            if (selected) point(selected);
        });
    }
    const ranking = root.querySelector('[data-ranking]');
    if (ranking) {
        const tabs = [...ranking.querySelectorAll('[data-rank-tab]')];
        keyboardTabs(tabs, tab => {
            tabs.forEach(item => {const active=item === tab;item.classList.toggle('is-active',active);item.setAttribute('aria-selected',String(active));item.tabIndex=active?0:-1;});
            ranking.querySelectorAll('[data-rank-panel]').forEach(panel => {panel.hidden=panel.dataset.rankPanel !== tab.dataset.rankTab;});
        });
    }
})();
