(() => {
  const form = document.querySelector('form[data-duration-map]');
  if (!form) {
    return;
  }

  let durationMap = {};
  try {
    durationMap = JSON.parse(form.dataset.durationMap || '{}');
  } catch (err) {
    durationMap = {};
  }

  const movieSelect = form.querySelector('select[name="movie_id"]');
  const startInput = form.querySelector('input[name="start_time"]');
  const endInput = form.querySelector('input[name="end_time"]');
  const bufferInput = form.querySelector('input[name="buffer_minutes"]');
  const toggle = form.querySelector('#auto-end-time');

  if (!movieSelect || !startInput || !endInput || !bufferInput || !toggle) {
    return;
  }

  const pad = (value) => String(value).padStart(2, '0');
  const formatDate = (date) => {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  };

  const recalc = () => {
    if (!toggle.checked) {
      return;
    }
    const duration = Number(durationMap[movieSelect.value] || 0);
    const buffer = Number(bufferInput.value || 0);
    if (!startInput.value || !duration) {
      return;
    }
    const start = new Date(startInput.value);
    if (Number.isNaN(start.getTime())) {
      return;
    }
    const end = new Date(start.getTime() + (duration + buffer) * 60000);
    endInput.value = formatDate(end);
  };

  movieSelect.addEventListener('change', recalc);
  startInput.addEventListener('change', recalc);
  bufferInput.addEventListener('input', recalc);
  toggle.addEventListener('change', recalc);

  recalc();
})();
