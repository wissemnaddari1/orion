/**
 * Orion Auth — Parallax + spotlight for Aurora background
 * - Subtle mouse parallax on aurora layers (max 12–18px)
 * - Optional cursor-following spotlight
 * - Respects prefers-reduced-motion (disables all)
 */
(function () {
  'use strict';

  const MAX_OFFSET = 15; // max 15px shift
  const EASING = 0.06;

  function init() {
    const bg = document.getElementById('auth-bg');
    if (!bg) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducedMotion) {
      bg.classList.add('auth-bg--reduced-motion');
      return;
    }

    const layers = bg.querySelectorAll('.auth-bg__aurora-wrapper[data-parallax]');
    const spotlight = document.getElementById('auth-bg-spotlight');
    if (!layers.length && !spotlight) return;

    let targetX = 0;
    let targetY = 0;
    let currentX = 0;
    let currentY = 0;
    let lastClientX = window.innerWidth / 2;
    let lastClientY = window.innerHeight / 2;
    let rafId = null;

    function handleMove(e) {
      const w = window.innerWidth;
      const h = window.innerHeight;
      targetX = ((e.clientX / w) - 0.5) * 2 * MAX_OFFSET;
      targetY = ((e.clientY / h) - 0.5) * 2 * MAX_OFFSET;
      lastClientX = e.clientX;
      lastClientY = e.clientY;
    }

    function updateSpotlight() {
      if (!spotlight) return;
      const x = (lastClientX / window.innerWidth) * 100;
      const y = (lastClientY / window.innerHeight) * 100;
      spotlight.style.setProperty('--spot-x', x + '%');
      spotlight.style.setProperty('--spot-y', y + '%');
    }

    function animate() {
      currentX += (targetX - currentX) * EASING;
      currentY += (targetY - currentY) * EASING;

      layers.forEach(function (el) {
        const layer = parseInt(el.getAttribute('data-layer') || '1', 10);
        const factor = 0.3 + (layer - 1) * 0.2;
        const dx = currentX * factor;
        const dy = currentY * factor;
        el.style.transform = `translate(${dx}px, ${dy}px)`;
      });

      updateSpotlight();
      rafId = requestAnimationFrame(animate);
    }

    document.addEventListener('mousemove', handleMove, { passive: true });
    rafId = requestAnimationFrame(animate);

    if (spotlight) {
      bg.classList.add('auth-bg--spotlight-active');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
