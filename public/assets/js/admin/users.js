(() => {
  const roleField = document.getElementById('roleField');
  const cinemaWrapper = document.getElementById('cinemaWrapper');

  if (roleField && cinemaWrapper) {
    const toggleCinema = () => {
      const role = roleField.value;
      if (role === 'staff' || role === 'manager') {
        cinemaWrapper.classList.remove('hidden');
      } else {
        cinemaWrapper.classList.add('hidden');
      }
    };
    toggleCinema();
    roleField.addEventListener('change', toggleCinema);
  }

  const filterForm = document.getElementById('filterForm');
  const roleSelect = document.getElementById('roleSelect');
  const statusSelect = document.getElementById('statusSelect');

  if (filterForm) {
    document.querySelectorAll('.chip-role').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (roleSelect) {
          roleSelect.value = btn.dataset.role || '';
        }
        filterForm.submit();
      });
    });
    document.querySelectorAll('.chip-status').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (statusSelect) {
          statusSelect.value = btn.dataset.status || '';
        }
        filterForm.submit();
      });
    });
  }

  const detailModal = document.getElementById('detailModal');
  const detailClose = document.getElementById('detailClose');

  if (detailModal) {
    const fill = (id, value) => {
      const el = document.getElementById(id);
      if (el) {
        el.textContent = value || '';
      }
    };

    document.querySelectorAll('.btn-view').forEach((btn) => {
      btn.addEventListener('click', () => {
        fill('modalName', btn.dataset.name);
        fill('modalEmail', btn.dataset.email);
        fill('modalRole', btn.dataset.role);
        fill('modalPhone', btn.dataset.phone || '-');
        fill('modalCinema', btn.dataset.cinema || '-');
        fill('modalRegistered', btn.dataset.registered || '-');

        const statusEl = document.getElementById('modalStatus');
        if (statusEl) {
          const isActive = btn.dataset.status === 'active';
          const badge = document.createElement('span');
          badge.className = `status-badge ${isActive ? 'status-active' : 'status-blocked'}`;
          badge.textContent = isActive ? '● Hoạt động' : '● Bị khóa';
          statusEl.replaceChildren(badge);
        }

        const avatarEl = document.getElementById('modalAvatar');
        if (avatarEl) {
          const name = (btn.dataset.name || 'U').trim();
          avatarEl.textContent = name.charAt(0).toUpperCase();
        }

        detailModal.style.display = 'flex';
      });
    });

    detailClose?.addEventListener('click', () => {
      detailModal.style.display = 'none';
    });

    detailModal.addEventListener('click', (event) => {
      if (event.target === detailModal) {
        detailModal.style.display = 'none';
      }
    });
  }
})();

