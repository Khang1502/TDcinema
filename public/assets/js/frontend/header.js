(() => {
  const header = document.querySelector('.td-site-header');
  if (!header || header.dataset.initialized) return;
  header.dataset.initialized = 'true';
  const user = header.querySelector('#userMenuToggle');
  const join = header.querySelector('#joinWrapper');
  const joinToggle = header.querySelector('#joinToggle');
  const setUserOpen = open => {
    user?.classList.toggle('is-open', open);
    user?.setAttribute('aria-expanded', String(open));
  };
  const setJoinOpen = open => {
    join?.classList.toggle('is-open', open);
    joinToggle?.setAttribute('aria-expanded', String(open));
  };
  user?.addEventListener('click', event => {
    if (event.target.closest('.user-menu')) return;
    setUserOpen(!user.classList.contains('is-open'));
  });
  user?.addEventListener('keydown', event => {
    if (event.target === user && ['Enter', ' '].includes(event.key)) {
      event.preventDefault();
      setUserOpen(!user.classList.contains('is-open'));
    }
  });
  user?.addEventListener('focusout', event => {
    if (!user.contains(event.relatedTarget)) setUserOpen(false);
  });
  joinToggle?.addEventListener('click', event => {
    event.preventDefault();
    setJoinOpen(!join.classList.contains('is-open'));
  });
  join?.addEventListener('focusout', event => {
    if (!join.contains(event.relatedTarget)) setJoinOpen(false);
  });
  header.querySelectorAll('#openLogin, #openRegister').forEach(button => {
    button.addEventListener('click', () => setJoinOpen(false));
  });

  header.querySelectorAll('[data-cinema-directory]').forEach(directory => {
    const tabs = [...directory.querySelectorAll('[data-cinema-region]')];
    const select = tab => {
      tabs.forEach(item => {
        const active = item === tab;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', String(active));
        item.tabIndex = active ? 0 : -1;
        const panel = directory.querySelector(`#${item.getAttribute('aria-controls')}`);
        if (panel) panel.hidden = !active;
      });
    };
    tabs.forEach((tab, index) => {
      tab.addEventListener('mouseenter', () => select(tab));
      tab.addEventListener('focus', () => select(tab));
      tab.addEventListener('click', () => select(tab));
      tab.addEventListener('keydown', event => {
        let target;
        if (event.key === 'ArrowDown') target = tabs[(index + 1) % tabs.length];
        if (event.key === 'ArrowUp') target = tabs[(index - 1 + tabs.length) % tabs.length];
        if (event.key === 'Home') target = tabs[0];
        if (event.key === 'End') target = tabs[tabs.length - 1];
        if (target) { event.preventDefault(); target.focus(); }
        if (event.key === 'ArrowRight') {
          const link = directory.querySelector('.cinema-region-panel:not([hidden]) a');
          if (link) { event.preventDefault(); link.focus(); }
        }
      });
    });
  });
  document.addEventListener('click', event => {
    if (!user?.contains(event.target)) setUserOpen(false);
    if (!join?.contains(event.target)) setJoinOpen(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (user?.classList.contains('is-open')) {
      setUserOpen(false);
      user.focus();
    }
    if (join?.classList.contains('is-open')) {
      setJoinOpen(false);
      joinToggle.focus();
    }
  });
})();
