(() => {
  document.querySelectorAll('[data-quote-form]').forEach(form => {
    const button = form.querySelector('button[type="submit"]');
    const label = form.querySelector('[data-quote-label]');
    const spinner = form.querySelector('[data-quote-spinner]');
    const status = form.querySelector('[data-quote-status]');
    const originalLabel = label?.textContent || '';
    const sendingMessage = form.dataset.sending || 'Sending your quote request. Please wait.';
    let sending = false;
    form.addEventListener('submit', event => {
      if (sending) { event.preventDefault(); return; }
      if (form.querySelector('.cf-turnstile') && !form.querySelector('[name="cf-turnstile-response"]')?.value) {
        event.preventDefault();
        if (status) {
          status.classList.remove('visually-hidden');
          status.textContent = form.dataset.securityCheck || 'Please complete the security check before sending.';
        }
        return;
      }
      sending = true;
      if (button) button.disabled = true;
      if (label) label.textContent = sendingMessage;
      if (spinner) spinner.hidden = false;
      if (status) status.textContent = sendingMessage;
      form.setAttribute('aria-busy', 'true');
    });
    // Restore controls when returning through the browser back/forward cache.
    window.addEventListener('pageshow', () => {
      sending = false;
      if (button) button.disabled = false;
      if (label) label.textContent = originalLabel;
      if (spinner) spinner.hidden = true;
      if (status) { status.textContent = ''; status.classList.add('visually-hidden'); }
      form.removeAttribute('aria-busy');
    });
  });
  const success = document.querySelector('[data-quote-success]');
  const dialog = document.getElementById('quote-confirmation');
  if (success && dialog && typeof dialog.showModal === 'function') {
    dialog.querySelector('[data-quote-confirmation-message]').textContent = success.textContent;
    dialog.showModal();
  }
})();
