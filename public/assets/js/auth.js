(() => {
  'use strict';

  document.querySelectorAll('.reveal').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.target);
      if (!input) return;
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.textContent = visible ? 'Hide' : 'Show';
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed', String(visible));
    });
  });

  const card = document.querySelector('.auth-card');
  const wrapper = document.querySelector('.auth-wrapper');
  if (!card || !wrapper) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const pointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  let frame = null;
  const reset = () => {
    if (frame !== null) cancelAnimationFrame(frame);
    frame = null;
    card.style.removeProperty('transform');
  };

  wrapper.addEventListener('pointermove', event => {
    if (reducedMotion.matches || !pointer.matches || event.pointerType === 'touch') return;
    if (frame !== null) cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
      const bounds = wrapper.getBoundingClientRect();
      const x = (event.clientX - bounds.left) / bounds.width - 0.5;
      const y = (event.clientY - bounds.top) / bounds.height - 0.5;
      card.style.transform = `rotateY(${x * 4}deg) rotateX(${-y * 4}deg) translateZ(0)`;
      frame = null;
    });
  });
  wrapper.addEventListener('pointerleave', reset);
  reducedMotion.addEventListener('change', reset);
  pointer.addEventListener('change', reset);
})();
