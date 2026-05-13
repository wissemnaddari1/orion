/**
 * Orion Hero Carousel — auto-scroll, pause on hover/focus, dots + arrows
 * Lightweight vanilla JS, no external libs.
 */
(function () {
  const CAROUSEL_ID = 'heroCarousel';
  const AUTO_INTERVAL_MS = 5000;
  const TRANSITION_MS = 400;

  function init() {
  const carousel = document.getElementById(CAROUSEL_ID);
  if (!carousel || !carousel.querySelector('.hero-carousel__viewport')) return;

  const viewport = carousel.querySelector('.hero-carousel__viewport');
  const track = carousel.querySelector('.hero-carousel__track');
  const cards = Array.from(track.querySelectorAll('.hero-carousel__card'));
  const prevBtn = carousel.querySelector('.hero-carousel__btn--prev');
  const nextBtn = carousel.querySelector('.hero-carousel__btn--next');
  const dotsContainer = carousel.querySelector('.hero-carousel__dots');

  const totalSlides = cards.length;

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) return;

  function getCardsPerView() {
    const w = viewport.clientWidth || 400;
    if (w >= 1024) return 3;
    if (w >= 640) return 2;
    return 1;
  }

  function getMaxIndex() {
    return Math.max(0, totalSlides - getCardsPerView());
  }

  function getScrollAmount() {
    return viewport.clientWidth / getCardsPerView();
  }

  function getCurrentIndex() {
    const amount = getScrollAmount();
    const pos = viewport.scrollLeft;
    return Math.round(pos / amount);
  }

  function scrollToIndex(index, smooth = true) {
    const maxIdx = getMaxIndex();
    const i = Math.max(0, Math.min(index, maxIdx));
    const amount = getScrollAmount();
    viewport.style.scrollBehavior = smooth ? 'smooth' : 'auto';
    viewport.scrollLeft = i * amount;
    if (smooth) {
      setTimeout(() => { viewport.style.scrollBehavior = ''; }, TRANSITION_MS);
    }
    updateDots(i);
  }

  function updateDots(index) {
    const maxIdx = getMaxIndex();
    const dots = dotsContainer.querySelectorAll('button');
    dots.forEach((dot, i) => {
      dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
      dot.setAttribute('aria-current', i === index ? 'true' : 'false');
    });
  }

  function goNext() {
    const curr = getCurrentIndex();
    const maxIdx = getMaxIndex();
    scrollToIndex(curr >= maxIdx ? 0 : curr + 1);
  }

  function goPrev() {
    const curr = getCurrentIndex();
    const maxIdx = getMaxIndex();
    scrollToIndex(curr <= 0 ? maxIdx : curr - 1);
  }

  // Build dots
  for (let i = 0; i <= getMaxIndex(); i++) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('role', 'tab');
    btn.setAttribute('aria-label', 'Go to slide ' + (i + 1));
    btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
    btn.addEventListener('click', () => scrollToIndex(i));
    dotsContainer.appendChild(btn);
  }

  // Rebuild dots on resize (max index can change)
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      const maxIdx = getMaxIndex();
      const currentDots = dotsContainer.querySelectorAll('button');
      const needed = maxIdx + 1;
      if (currentDots.length !== needed) {
        dotsContainer.innerHTML = '';
        for (let i = 0; i < needed; i++) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.setAttribute('role', 'tab');
          btn.setAttribute('aria-label', 'Go to slide ' + (i + 1));
          btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
          btn.addEventListener('click', () => scrollToIndex(i));
          dotsContainer.appendChild(btn);
        }
      }
    }, 150);
  });

  // Auto-scroll
  let autoTimer;
  function startAuto() {
    stopAuto();
    autoTimer = setInterval(goNext, AUTO_INTERVAL_MS);
  }
  function stopAuto() {
    if (autoTimer) clearInterval(autoTimer);
    autoTimer = null;
  }

  function setupPauseHandlers() {
    const container = carousel;
    container.addEventListener('mouseenter', stopAuto);
    container.addEventListener('mouseleave', startAuto);
    container.addEventListener('focusin', stopAuto);
    container.addEventListener('focusout', (e) => {
      if (!container.contains(e.relatedTarget)) startAuto();
    });
  }

  prevBtn.addEventListener('click', () => { goPrev(); startAuto(); });
  nextBtn.addEventListener('click', () => { goNext(); startAuto(); });
  setupPauseHandlers();
  startAuto();

  // Sync dots on scroll (user swipe/scroll)
  viewport.addEventListener('scroll', () => updateDots(getCurrentIndex()));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
