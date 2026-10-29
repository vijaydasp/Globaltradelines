(function () {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const backToTop = document.getElementById("backToTop");
  const header = document.getElementById("siteHeader") || document.getElementById("mainNavbar");
  const overlay = document.getElementById("searchOverlay");
  const searchInput = document.getElementById("searchInput");

  window.addEventListener("scroll", function () {
    if (backToTop) backToTop.classList.toggle("show", window.scrollY > 400);
    if (header) header.classList.toggle("scrolled", window.scrollY > 8);
  });
  if (backToTop) backToTop.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
  document.getElementById("searchOpen")?.addEventListener("click", function () {
    overlay?.classList.add("active");
    searchInput?.focus();
  });
  document.querySelector(".search-close")?.addEventListener("click", () => overlay.classList.remove("active"));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (overlay?.classList.contains("active")) document.getElementById("searchOpen")?.focus();
      overlay?.classList.remove("active");
      const activeDropdown = document.activeElement?.closest('.dropdown');
      activeDropdown?.querySelector(':scope > .nav-link')?.focus();
      closeNavDropdowns(activeDropdown?.parentElement.closest('.dropdown'));
      activeDropdown?.classList.add('cms-keyboard-dismissed');
    }
  });

  const dropdownLinks = document.querySelectorAll(".navbar-nav .dropdown > .nav-link, .primary-nav .dropdown > .nav-link");
  function closeNavDropdowns(exceptItem) {
    document.querySelectorAll(".navbar-nav .dropdown, .primary-nav .dropdown").forEach((item) => {
      if (item === exceptItem || item.contains(exceptItem)) return;
      item.classList.remove("show", "cms-keyboard-open");
      item.querySelector(":scope > .nav-link")?.setAttribute("aria-expanded", "false");
    });
  }
  dropdownLinks.forEach((link) => {
    link.setAttribute("aria-expanded", "false");
    const item = link.parentElement;
    item.addEventListener('mouseenter', () => item.classList.remove('cms-keyboard-dismissed'));
    item.addEventListener('focusin', () => {
      item.classList.remove('cms-keyboard-dismissed');
      link.setAttribute('aria-expanded', 'true');
    });
    item.addEventListener('focusout', (event) => {
      if (!item.contains(event.relatedTarget)) {
        item.classList.remove('show', 'cms-keyboard-open', 'cms-keyboard-dismissed');
        link.setAttribute('aria-expanded', 'false');
      }
    });
    link.addEventListener('keydown', (event) => {
      if (!['ArrowDown', 'ArrowUp', ' '].includes(event.key)) return;
      const menu = item.querySelector(':scope > .mega-menu');
      if (!menu) return;
      event.preventDefault();
      closeNavDropdowns(item);
      item.classList.remove('cms-keyboard-dismissed');
      item.classList.add('show');
      link.setAttribute('aria-expanded', 'true');
      const links = Array.from(menu.querySelectorAll('a[href]')).filter((entry) => entry.getClientRects().length && getComputedStyle(entry).visibility === 'visible');
      (event.key === 'ArrowUp' ? links[links.length - 1] : links[0])?.focus();
    });
    link.addEventListener("click", function (event) {
      const item = this.parentElement;
      if (!item?.querySelector(".mega-menu")) return;
      event.preventDefault();
      closeNavDropdowns(item);
      item.classList.remove('cms-keyboard-dismissed');
      const isOpen = item.classList.toggle("show");
      if (!isOpen) {
        item.classList.remove('cms-keyboard-open');
        item.classList.add('cms-keyboard-dismissed');
      }
      this.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });
  });
  document.addEventListener("click", (event) => {
    if (!event.target.closest(".navbar-nav .dropdown, .primary-nav .dropdown")) closeNavDropdowns();
  });

  const navSearch = document.querySelector(".nav-product-search");
  const navSearchPlaceholder = document.querySelector(".nav-search-placeholder");
  const productSearchInput = document.getElementById("product-search");
  const navSearchTerms = Array.from(document.querySelectorAll("[data-nav-search-term]"))
    .map((item) => item.textContent.trim())
    .filter(Boolean);
  const searchPlaceholders = navSearchTerms.length
    ? navSearchTerms.map((term) => (navSearch?.dataset.searchTemplate || 'Search {term}...').replace('{term}', term))
    : [navSearchPlaceholder?.textContent.trim() || "Search products..."];
  if (navSearch && navSearchPlaceholder) {
    let placeholderIndex = 0;
    const cyclePlaceholder = () => {
      placeholderIndex = (placeholderIndex + 1) % searchPlaceholders.length;
      navSearchPlaceholder.classList.remove("slide-up");
      void navSearchPlaceholder.offsetWidth;
      navSearchPlaceholder.textContent = searchPlaceholders[placeholderIndex];
      navSearchPlaceholder.classList.add("slide-up");
    };
    navSearchPlaceholder.textContent = searchPlaceholders[placeholderIndex];
    if (searchPlaceholders.length > 1 && !reducedMotion.matches) setInterval(cyclePlaceholder, 1800);
  }
  navSearch?.addEventListener("click", (event) => {
    if (!productSearchInput) return;
    event.preventDefault();
    productSearchInput.focus({ preventScroll: true });
    productSearchInput.scrollIntoView({ behavior: "smooth", block: "center" });
    window.history.replaceState(null, "", "#product-search");
  });
  if (productSearchInput && window.location.hash === "#product-search") {
    window.setTimeout(() => {
      productSearchInput.focus();
      productSearchInput.scrollIntoView({ behavior: "smooth", block: "center" });
    }, 150);
  }

  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  document.querySelectorAll(".reveal").forEach((element) => observer.observe(element));

  document.querySelectorAll(".benefits").forEach((benefitsSection) => {
    const benefitsObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            const grid = benefitsSection.querySelector(".benefits-grid");
            if (grid) grid.classList.add("revealed");
            const cards = benefitsSection.querySelectorAll(".benefit");
            cards.forEach(function (card, index) {
              card.style.animationDelay = index * 0.18 + "s";
            });
            benefitsObserver.unobserve(benefitsSection);
          }
        });
      },
      { threshold: 0.25 }
    );
    benefitsObserver.observe(benefitsSection);
  });

  document.querySelectorAll(".gtl-solution-card.reveal").forEach(function (card, index) {
    card.style.setProperty("--card-delay", (index * 100) + "ms");
  });

  // document.querySelectorAll(".gtl-solution-card").forEach(function (card) {
  //   card.addEventListener("mousemove", function (e) {
  //     var rect = card.getBoundingClientRect();
  //     var x = e.clientX - rect.left;
  //     var y = e.clientY - rect.top;
  //     var centerX = rect.width / 2;
  //     var centerY = rect.height / 2;
  //     var rotateX = ((y - centerY) / centerY) * -8;
  //     var rotateY = ((x - centerX) / centerX) * 8;
  //     card.style.transform = "perspective(1400px) rotateX(" + rotateX + "deg) rotateY(" + rotateY + "deg) translateY(-14px)";
  //     var icon = card.querySelector(".gtl-card-icon-wrap");
  //     if (icon) {
  //       icon.style.transform = "translate(" + ((x - centerX) * 0.04) + "px, " + ((y - centerY) * 0.04) + "px) scale(1.05)";
  //     }
  //   });
  //   card.addEventListener("mouseleave", function () {
  //     card.style.transform = "";
  //     var icon = card.querySelector(".gtl-card-icon-wrap");
  //     if (icon) icon.style.transform = "";
  //   });
  //   card.addEventListener("mouseenter", function (e) {
  //     var ripple = document.createElement("span");
  //     ripple.className = "gtl-card-ripple";
  //     var rect = card.getBoundingClientRect();
  //     var size = Math.max(rect.width, rect.height);
  //     ripple.style.width = size + "px";
  //     ripple.style.height = size + "px";
  //     ripple.style.left = (e.clientX - rect.left - size / 2) + "px";
  //     ripple.style.top = (e.clientY - rect.top - size / 2) + "px";
  //     card.appendChild(ripple);
  //     ripple.addEventListener("animationend", function () { ripple.remove(); });
  //   });
  // });

  const counterObserver = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.getAttribute("data-count"), 10);
        if (!Number.isFinite(target)) { counterObserver.unobserve(el); return; }
        const prefix = el.dataset.prefix ?? '';
        const suffix = el.dataset.suffix ?? '+';
        const format = (value) => prefix + value.toLocaleString() + suffix;
        if (reducedMotion.matches) {
          el.textContent = format(target);
          counterObserver.unobserve(el);
          return;
        }
        const duration = 2000;
        const startTime = performance.now();

        function easeOut(t) {
          return 1 - Math.pow(1 - t, 3);
        }

        function updateCounter(currentTime) {
          const elapsed = currentTime - startTime;
          const progress = Math.min(elapsed / duration, 1);
          const current = Math.floor(easeOut(progress) * target);
          el.textContent = format(current);
          if (progress < 1) {
            requestAnimationFrame(updateCounter);
          } else {
            el.textContent = format(target);
          }
        }

        requestAnimationFrame(updateCounter);
        counterObserver.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  document.querySelectorAll("[data-count]").forEach(function (el) {
    counterObserver.observe(el);
  });

  const slides = document.querySelectorAll(".hero-slide");
  const indicators = document.querySelectorAll(".hero-indicators button");
  let current = 0;
  let timer;
  function goTo(index) {
    slides.forEach((slide) => slide.classList.remove("active"));
    indicators.forEach((indicator) => indicator.classList.remove("active"));
    slides[index]?.classList.add("active");
    indicators[index]?.classList.add("active");
    current = index;
  }
  function resetTimer() {
    clearInterval(timer);
    if (slides.length > 1 && !reducedMotion.matches) timer = setInterval(() => goTo((current + 1) % slides.length), 6000);
  }
  document.getElementById("heroNext")?.addEventListener("click", () => { goTo((current + 1) % slides.length); resetTimer(); });
  document.getElementById("heroPrev")?.addEventListener("click", () => { goTo((current - 1 + slides.length) % slides.length); resetTimer(); });
  indicators.forEach((item) => item.addEventListener("click", function () { goTo(Number(this.dataset.slide)); resetTimer(); }));
  resetTimer();

  document.querySelectorAll("[data-gtl-hero]").forEach((gtlHero) => {
    const gtlSlides = gtlHero.querySelectorAll(".gtl-hero-slide");
    const gtlIndicators = gtlHero.querySelectorAll(".gtl-hero-indicators button");
    if (!gtlSlides.length) return;
    let gtlCurrent = Math.max(0, Array.from(gtlSlides).findIndex((slide) => slide.classList.contains('active')));
    let gtlTimer;
    let hovered = false;
    const autoplay = !['0', 'false'].includes(gtlHero.dataset.autoplay);
    const resetGtlTimer = () => {
      clearTimeout(gtlTimer);
      if (!autoplay || reducedMotion.matches || document.hidden || hovered || gtlHero.contains(document.activeElement) || gtlSlides.length < 2) return;
      const configuredDuration = Number(gtlSlides[gtlCurrent].dataset.duration);
      const duration = Number.isFinite(configuredDuration) && configuredDuration > 0 ? configuredDuration * 1000 : 7000;
      gtlTimer = setTimeout(() => goToGtlSlide(gtlCurrent + 1), duration);
    };
    const goToGtlSlide = (index) => {
      if (!Number.isInteger(index)) return;
      gtlCurrent = ((index % gtlSlides.length) + gtlSlides.length) % gtlSlides.length;
      gtlSlides.forEach((slide, slideIndex) => {
        const active = slideIndex === gtlCurrent;
        slide.classList.toggle('active', active);
        slide.setAttribute('aria-hidden', String(!active));
        slide.querySelectorAll('video').forEach((video) => {
          if (!active || reducedMotion.matches || document.hidden) video.pause();
          else if (video.autoplay) video.play()?.catch(() => {});
        });
      });
      gtlIndicators.forEach((indicator, slideIndex) => {
        indicator.classList.toggle('active', slideIndex === gtlCurrent);
        indicator.setAttribute('aria-pressed', String(slideIndex === gtlCurrent));
      });
      resetGtlTimer();
    };
    gtlIndicators.forEach((item) => {
      item.addEventListener("click", function () {
        goToGtlSlide(Number(this.dataset.gtlSlide));
      });
      item.addEventListener('keydown', (event) => {
        const index = event.key === 'Home' ? 0 : event.key === 'End' ? gtlSlides.length - 1 :
          event.key === 'ArrowRight' ? (gtlCurrent + 1) % gtlSlides.length :
          event.key === 'ArrowLeft' ? (gtlCurrent - 1 + gtlSlides.length) % gtlSlides.length : null;
        if (index === null) return;
        event.preventDefault();
        goToGtlSlide(index);
        gtlIndicators[index]?.focus();
      });
    });
    gtlHero.addEventListener('mouseenter', () => { hovered = true; resetGtlTimer(); });
    gtlHero.addEventListener('mouseleave', () => { hovered = false; resetGtlTimer(); });
    gtlHero.addEventListener('focusin', resetGtlTimer);
    gtlHero.addEventListener('focusout', () => setTimeout(resetGtlTimer, 0));
    document.addEventListener('visibilitychange', () => goToGtlSlide(gtlCurrent));
    reducedMotion.addEventListener('change', () => goToGtlSlide(gtlCurrent));
    goToGtlSlide(gtlCurrent);
  });

  const menuToggle = document.getElementById('menuToggle');
  const primaryNav = document.querySelector('.primary-nav');
  if (menuToggle && primaryNav) {
    menuToggle.addEventListener('click', () => {
      primaryNav.classList.toggle('open');
    });
  }
})();

function setTopbarHeight() {
  const topbar = document.querySelector('#siteHeaderGroup .topbar') || document.querySelector('#siteHeaderGroup .topbar-pro');

  document.documentElement.style.setProperty('--topbar-h', (topbar ? topbar.offsetHeight : 0) + 'px');
}
setTopbarHeight();
window.addEventListener('resize', setTopbarHeight);

// Toggle white background on the nav bar as soon as scrolling starts,
// and revert to transparent automatically when back at the top.
(function () {
  const headerGroup = document.getElementById('siteHeaderGroup');
  if (!headerGroup) return;

  // The transparent-to-white scroll transition is exclusive to the Home/Landing page.
  // Inner pages (Careers, Contact, About, Services, etc.) are always solid white via
  // CSS (body:not(.home-page) / body.career-page), so no scroll logic should touch them.
  if (!document.body.classList.contains('home-page')) return;
  function updateHeaderState() {
    headerGroup.classList.toggle('scrolled', window.scrollY > 0);
  }

  updateHeaderState(); // run once on load in case page is refreshed mid-scroll
  window.addEventListener('scroll', updateHeaderState, { passive: true });
})();

/* ------------------------------------------------------------------
   "Trusted by Leading Brands" — seamless, GPU-accelerated marquee
   - Continuous infinite scroll via translate3d (no layout thrash)
   - Identical loop width across duplicated sets (no gap/jump)
   - Pauses on hover (desktop), manual wheel/pointer on all devices
   - Inertia on release, then auto-scroll resumes smoothly
------------------------------------------------------------------ */
(function () {
  document.querySelectorAll('.gtl-brand-viewport').forEach((viewport) => {
  const slider = viewport.querySelector('.gtl-brand-slider');
  if (!slider) return;

  const configuredSpeed = Number(viewport.dataset.speed);
  const SPEED = viewport.hasAttribute('data-speed') && Number.isFinite(configuredSpeed) ? Math.max(0, configuredSpeed) / 1000 : 0.06;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const isHoverDevice = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  let unitWidth = 0;                               // exact loop distance in px for one full repetition
  let offset = 0;                                  // current translateX (<= 0)
  let manualVel = 0;                               // px/ms, for inertia
  let rafId = null;
  let lastTs = 0;
  let hoverPaused = false;
  let focusPaused = false;
  let interacting = false;
  let resumeTimer = null;

  // Avoid desync due to resize/rebuild by keeping phase as a fraction.
  let phase = 0;                                   // offset / unitWidth in [0,1)


  let dragging = false;
  let startX = 0;
  let startOffset = 0;
  let lastX = 0;
  let lastT = 0;
  let pointerVel = 0;
  let moved = 0;

  function measureAndBuild() {
    const firstSet = slider.querySelector('.gtl-brand-set');
    if (!firstSet) return;
    const gap = parseFloat(getComputedStyle(slider).columnGap) || 0;

    // Keep only the original set, then clone to fill the viewport + one loop.
    const existing = slider.querySelectorAll('.gtl-brand-set');
    for (let i = existing.length - 1; i >= 1; i--) existing[i].remove();

    const measuredSetWidth = firstSet.getBoundingClientRect().width;
    if (!measuredSetWidth) return;

    // Keep phase before DOM rebuild; avoids visible reset/flicker.
    const prevUnitWidth = unitWidth || (measuredSetWidth + gap);
    phase = prevUnitWidth ? ((-offset % prevUnitWidth) + prevUnitWidth) % prevUnitWidth / prevUnitWidth : 0;

    unitWidth = measuredSetWidth + gap;           // one full repetition distance
    offset = -(phase * unitWidth);


    const vpWidth = viewport.getBoundingClientRect().width || window.innerWidth;
    const needed = Math.max(2, Math.ceil(vpWidth / unitWidth) + 1);

    for (let i = 1; i < needed; i++) {
      const clone = firstSet.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.querySelectorAll('a, button, input, select, textarea, [tabindex]').forEach((element) => element.setAttribute('tabindex', '-1'));
      slider.appendChild(clone);
    }
    wrap();
  }

  // Keep offset within (-unitWidth, 0] so content is always seamlessly repeated.
  function wrap() {
    if (unitWidth <= 0) return;
    offset = offset % unitWidth;
    if (offset > 0) offset -= unitWidth;
  }


  function render() {
    slider.style.transform = 'translate3d(' + offset.toFixed(2) + 'px,0,0)';
  }

  function frame(ts) {
    if (!lastTs) lastTs = ts;
    let dt = ts - lastTs;
    lastTs = ts;
    if (dt > 100) dt = 16;                         // clamp after tab switch

    if (interacting) {
      // offset is driven directly by the active pointer/wheel
    } else if (!focusPaused && Math.abs(manualVel) > 0.002) {
      offset += manualVel * dt;
      manualVel *= Math.exp(-dt / 180);            // smooth inertia decay
      wrap();
    } else if (!hoverPaused && !focusPaused && !reducedMotion.matches) {
      offset -= SPEED * dt;                        // constant linear auto-scroll
      wrap();
    }
    render();
    rafId = document.hidden ? null : requestAnimationFrame(frame);
  }

  function beginInteraction() {
    interacting = true;
    if (resumeTimer) { clearTimeout(resumeTimer); resumeTimer = null; }
  }



  function scheduleResume(delay) {
    if (resumeTimer) clearTimeout(resumeTimer);
    resumeTimer = setTimeout(function () { interacting = false; }, delay || 120);
  }

  // ---- Mouse wheel / trackpad (horizontal, shift+wheel, vertical wheel) ----
  viewport.addEventListener('wheel', function (e) {
    // Preserve page scrolling; use horizontal trackpad or Shift+wheel for the brands.
    if (!e.shiftKey && Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return;
    const raw = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY;
    if (!raw) return;
    e.preventDefault();
    beginInteraction();
    offset -= raw;
    manualVel = Math.max(-3, Math.min(3, -raw / 16));
    wrap();
    render();
    scheduleResume(140);
  }, { passive: false });

  // ---- Pointer drag (mouse + touch + pen) with inertia ----
  slider.addEventListener('pointerdown', function (e) {
    if (!e.isPrimary || (e.pointerType === 'mouse' && e.button !== 0)) return;
    dragging = true;
    startX = e.clientX;
    startOffset = offset;
    lastX = e.clientX;
    lastT = performance.now();
    pointerVel = 0;
    moved = 0;
  });

  slider.addEventListener('pointermove', function (e) {
    if (!dragging) return;
    const dx = e.clientX - startX;
    moved = Math.max(moved, Math.abs(dx));
    if (moved > 4) {
      if (!interacting) beginInteraction();              // only genuine horizontal drag pauses auto
      try {
        if (!slider.hasPointerCapture(e.pointerId)) slider.setPointerCapture(e.pointerId);
      } catch (_) {}
    }
    if (moved <= 4) return;
    offset = startOffset + dx;
    const now = performance.now();
    const dtp = now - lastT;
    if (dtp > 0) pointerVel = (e.clientX - lastX) / dtp;
    lastX = e.clientX;
    lastT = now;
    wrap();
    render();
    if (e.cancelable) e.preventDefault();
  });

  function endDrag(e) {
    if (!dragging) return;
    dragging = false;
    try { slider.releasePointerCapture(e.pointerId); } catch (_) {}
    if (moved > 4) {
      manualVel = Math.max(-3, Math.min(3, pointerVel));   // released with velocity -> inertia
      scheduleResume(0);                                   // hand off to inertia, then auto
    } else if (interacting) {
      scheduleResume(120);
    }
  }
  slider.addEventListener('pointerup', endDrag);
  slider.addEventListener('pointercancel', endDrag);
  window.addEventListener('pointerup', endDrag);
  slider.addEventListener('dragstart', (event) => event.preventDefault());

  // Suppress click navigation only when the gesture was a real drag.
  slider.addEventListener('click', function (e) {
    if (moved > 6) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  // ---- Hover pause (desktop pointer devices only) ----
  if (isHoverDevice) {
    viewport.addEventListener('mouseenter', function () { hoverPaused = true; });
    viewport.addEventListener('mouseleave', function () { hoverPaused = false; });
  }
  viewport.addEventListener('focusin', (event) => {
    focusPaused = true;
    manualVel = 0;
    const card = event.target.closest('.gtl-brand-card');
    if (card) {
      const position = card.getBoundingClientRect().left - slider.getBoundingClientRect().left;
      offset = -Math.max(0, position - (viewport.clientWidth - card.offsetWidth) / 2);
      wrap();
      render();
    }
  });
  viewport.addEventListener('focusout', (event) => { focusPaused = viewport.contains(event.relatedTarget); });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden && rafId) { cancelAnimationFrame(rafId); rafId = null; }
    else if (!document.hidden && !rafId) { lastTs = 0; rafId = requestAnimationFrame(frame); }
  });

  // ---- Lifecycle ----
  let resizeTimer = null;
  window.addEventListener('resize', function () {
    if (resizeTimer) clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
// Keep the current phase; avoids flicker/jumps on resize.
      measureAndBuild();
      offset = unitWidth ? -(phase * unitWidth) : 0;
      // Re-wrap to guarantee the offset stays in the seamless range.
      wrap();
      manualVel = 0;
      render();

    }, 200);
  });

  function init() {
    measureAndBuild();
    render();
    if (!rafId) { lastTs = 0; rafId = requestAnimationFrame(frame); }
  }

  init();                                         // start immediately if layout is ready
  window.addEventListener('load', init);           // re-measure once images/fonts settle
  });
})();
