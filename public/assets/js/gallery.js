(() => {
  const gallery = document.querySelector('.gallery');
  if (!gallery) return;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const refreshers = [];
  const stopPreviews = [];
  const hoverPointer = window.matchMedia('(hover: hover) and (pointer: fine)');
  gallery.querySelectorAll('.gallery-preview').forEach(video => {
    const card = video.closest('.gallery-card');
    const panel = video.closest('.gallery-panel');
    let wanted = false;
    function stop() {
      wanted = false;
      video.pause();
      card.classList.remove('is-previewing');
      if (video.readyState) video.currentTime = 0;
    }
    function start() {
      if (!hoverPointer.matches || reducedMotion.matches || document.hidden || panel.hidden) return;
      wanted = true;
      if (!video.getAttribute('src')) video.src = video.dataset.src;
      video.muted = true;
      video.play().catch(stop);
    }
    video.addEventListener('playing', () => {
      if (wanted && !panel.hidden && !document.hidden) card.classList.add('is-previewing');
      else stop();
    });
    video.addEventListener('error', stop);
    card.addEventListener('mouseenter', start);
    card.addEventListener('mouseleave', stop);
    stopPreviews.push(stop);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(entries => {
        if (!entries[0].isIntersecting) stop();
      }).observe(card);
    }
  });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopPreviews.forEach(stop => stop());
  });
  reducedMotion.addEventListener('change', () => stopPreviews.forEach(stop => stop()));
  gallery.querySelectorAll('.gallery-carousel').forEach(carousel => {
    const track = carousel.querySelector('.gallery-grid');
    const cards = [...track.children];
    const controls = carousel.querySelector('.gallery-controls');
    const dots = controls.querySelector('.gallery-dots');
    const previous = controls.querySelector('[data-direction="-1"]');
    const next = controls.querySelector('[data-direction="1"]');
    const status = controls.querySelector('.gallery-status');
    let positions = [];
    let current = 0;
    function go(index) {
      track.scrollTo({ left: positions[Math.max(0, Math.min(index, positions.length - 1))], behavior: reducedMotion.matches ? 'instant' : 'smooth' });
    }
    function sync() {
      if (!positions.length) return;
      current = positions.reduce((best, value, index) => Math.abs(value - track.scrollLeft) < Math.abs(positions[best] - track.scrollLeft) ? index : best, 0);
      [...dots.children].forEach((dot, index) => dot.setAttribute('aria-current', String(index === current)));
      previous.disabled = current === 0;
      next.disabled = current === positions.length - 1;
      const text = `Page ${current + 1} of ${positions.length}`;
      if (status.textContent !== text) status.textContent = text;
    }
    function refresh() {
      if (!track.clientWidth) return;
      const columns = Number(getComputedStyle(track).getPropertyValue('--columns')) || 1;
      const max = Math.max(0, track.scrollWidth - track.clientWidth);
      const start = cards[0].getBoundingClientRect().left;
      positions = [];
      for (let index = 0; index < cards.length; index += columns) {
        const position = Math.min(max, cards[index].getBoundingClientRect().left - start);
        if (!positions.length || position > positions[positions.length - 1] + 1) positions.push(position);
      }
      // Keep existing dot buttons on resize where possible to preserve keyboard focus.
      if (dots.children.length !== positions.length) {
        dots.replaceChildren(...positions.map((_, index) => {
          const dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'gallery-dot';
          dot.setAttribute('aria-label', `Go to page ${index + 1}`);
          dot.setAttribute('aria-controls', track.id);
          dot.addEventListener('click', () => go(index));
          return dot;
        }));
      }
      controls.hidden = positions.length < 2;
      sync();
    }
    previous.addEventListener('click', () => go(current - 1));
    next.addEventListener('click', () => go(current + 1));
    track.addEventListener('scroll', sync, { passive: true });
    track.addEventListener('keydown', event => {
      if (event.target !== track) return;
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      go(event.key === 'Home' ? 0 : event.key === 'End' ? positions.length - 1 : current + (event.key === 'ArrowRight' ? 1 : -1));
    });
    if ('ResizeObserver' in window) new ResizeObserver(refresh).observe(track);
    else window.addEventListener('resize', refresh);
    refreshers.push(refresh);
  });
  gallery.querySelectorAll('.gallery-media img').forEach(img => {
    const hideBroken = () => { img.hidden = true; };
    img.addEventListener('error', hideBroken);
    if (img.complete && !img.naturalWidth) hideBroken();
  });
  const nav = gallery.querySelector('.gallery-tabs');
  const tabs = [...nav.querySelectorAll('a')];
  const panels = tabs.map(tab => gallery.querySelector(tab.hash));
  nav.setAttribute('role', 'tablist');
  function activate(index) {
    stopPreviews.forEach(stop => stop());
    tabs.forEach((tab, i) => {
      tab.setAttribute('aria-selected', String(i === index));
      tab.tabIndex = i === index ? 0 : -1;
      panels[i].hidden = i !== index;
    });
    refreshers.forEach(refresh => refresh());
  }
  tabs.forEach((tab, index) => {
    tab.id = `tab-${panels[index].id}`;
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', panels[index].id);
    panels[index].setAttribute('role', 'tabpanel');
    panels[index].setAttribute('aria-labelledby', tab.id);
    panels[index].tabIndex = 0;
    tab.addEventListener('click', event => {
      event.preventDefault();
      activate(index);
    });
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next === undefined) return;
      event.preventDefault();
      tabs[next].focus();
      activate(next);
    });
  });
  activate(0);
})();
