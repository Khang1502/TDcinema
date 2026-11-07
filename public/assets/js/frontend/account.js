document.querySelectorAll('[data-copy-code]').forEach((button) => {
  button.addEventListener('click', async () => {
    const code = button.dataset.copyCode || '';
    try {
      await navigator.clipboard.writeText(code);
    } catch (error) {
      const input = document.createElement('textarea');
      input.value = code;
      document.body.appendChild(input);
      input.select();
      try {
        if (!document.execCommand('copy')) throw new Error('copy failed');
      } catch {
        window.TDNotify.warning(`Không thể sao chép. Bạn có thể dùng mã: ${code}`);
        return;
      } finally { input.remove(); }
    }
    window.TDNotify.success(`Đã sao chép mã ${code}`);
  });
});
