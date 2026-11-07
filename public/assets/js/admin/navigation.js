(() => {
    const body = document.body;
    document.addEventListener('click', event => {
        if (event.target.closest('[data-sidebar-open]')) body.classList.add('sidebar-open');
        if (event.target.closest('[data-sidebar-close]')) body.classList.remove('sidebar-open');
    });

    const sidebarNav = document.querySelector('.sidebar-nav');
    if (!sidebarNav) return;
    const storageKey = 'td-cinema-admin-sidebar-scroll';
    // Run immediately after the sidebar is parsed, before the main content paints.
    try {
        const savedPosition = Number.parseInt(sessionStorage.getItem(storageKey) || '0', 10);
        if (Number.isFinite(savedPosition)) sidebarNav.scrollTop = savedPosition;
    } catch (_) { /* Navigation also works when browser storage is unavailable. */ }

    const activeLink = sidebarNav.querySelector('.sidebar-link.is-active');
    if (activeLink) {
        const menuBounds = sidebarNav.getBoundingClientRect();
        const linkBounds = activeLink.getBoundingClientRect();
        if (linkBounds.top < menuBounds.top) sidebarNav.scrollTop += linkBounds.top - menuBounds.top - 12;
        else if (linkBounds.bottom > menuBounds.bottom) sidebarNav.scrollTop += linkBounds.bottom - menuBounds.bottom + 12;
    }
    const rememberPosition = () => {
        try { sessionStorage.setItem(storageKey, String(sidebarNav.scrollTop)); }
        catch (_) { /* Storage may be disabled in private browsing. */ }
    };
    sidebarNav.addEventListener('scroll', rememberPosition, { passive: true });
    sidebarNav.addEventListener('click', rememberPosition);
    window.addEventListener('pagehide', rememberPosition);
})();
