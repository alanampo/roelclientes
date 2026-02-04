/* assets/carrusel.js */
(function () {
  "use strict";

  function initCarousel(root) {
    const track = root.querySelector(".c-track");
    const slides = Array.from(root.querySelectorAll(".c-slide"));
    const btnPrev = root.querySelector(".c-nav.prev");
    const btnNext = root.querySelector(".c-nav.next");
    const dotsWrap = root.querySelector(".c-dots");

    if (!track || slides.length < 2 || !dotsWrap) return;

    const reducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    const intervalMs = Math.max(2000, parseInt(root.getAttribute("data-interval") || "5200", 10));
    const autoplay = root.getAttribute("data-autoplay") !== "0";

    let idx = 0;
    let timer = null;
    let isPaused = false;

    // Build dots
    dotsWrap.innerHTML = "";
    const dots = slides.map((_, i) => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "c-dot";
      b.setAttribute("role", "tab");
      b.setAttribute("aria-label", "Ir al slide " + (i + 1));
      b.setAttribute("aria-selected", i === 0 ? "true" : "false");
      b.addEventListener("click", () => goTo(i, true));
      dotsWrap.appendChild(b);
      return b;
    });

    function setAria() {
      slides.forEach((s, i) => s.setAttribute("aria-hidden", i === idx ? "false" : "true"));
      dots.forEach((d, i) => d.setAttribute("aria-selected", i === idx ? "true" : "false"));
    }

    function applyTransform(animate) {
      if (reducedMotion) animate = false;
      if (!animate) track.style.transition = "none";
      track.style.transform = "translate3d(" + (-idx * 100) + "%,0,0)";
      if (!animate) {
        // reflow then restore
        track.offsetHeight; // eslint-disable-line no-unused-expressions
        track.style.transition = "";
      }
    }

    function goTo(i, userAction) {
      idx = (i + slides.length) % slides.length;
      applyTransform(true);
      setAria();
      if (userAction) restartAuto();
    }

    function next(userAction) { goTo(idx + 1, userAction); }
    function prev(userAction) { goTo(idx - 1, userAction); }

    if (btnNext) btnNext.addEventListener("click", () => next(true));
    if (btnPrev) btnPrev.addEventListener("click", () => prev(true));

    // Autoplay
    function startAuto() {
      stopAuto();
      if (!autoplay || reducedMotion) return;

      timer = window.setInterval(() => {
        if (!isPaused && document.visibilityState === "visible") next(false);
      }, intervalMs);
    }

    function stopAuto() {
      if (timer) window.clearInterval(timer);
      timer = null;
    }

    function restartAuto() {
      if (timer) startAuto();
    }

    // Pause on hover/focus
    root.addEventListener("mouseenter", () => { isPaused = true; }, { passive: true });
    root.addEventListener("mouseleave", () => { isPaused = false; }, { passive: true });
    root.addEventListener("focusin", () => { isPaused = true; });
    root.addEventListener("focusout", () => { isPaused = false; });

    // Keyboard
    root.addEventListener("keydown", (e) => {
      if (e.key === "ArrowLeft") prev(true);
      if (e.key === "ArrowRight") next(true);
    });

    // Swipe / drag
    let startX = 0;
    let curX = 0;
    let dragging = false;

    // ===== EVITAR CLICK EN LINKS CUANDO HAY SWIPE/DRAG =====
    // Si el usuario arrastra, no debe abrir el href del slide.
    let dragged = false;

    // Marca "dragged" cuando realmente hay desplazamiento
    function markDragIfMoved(x) {
      if (!dragging) return;
      if (Math.abs(x - startX) > 6) dragged = true; // umbral pequeño
    }

    // Bloquea click en links si hubo arrastre
    root.querySelectorAll("a.c-link").forEach((a) => {
      a.addEventListener("click", (e) => {
        if (dragged) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    });
    // ======================================================

    function onDown(x) {
      dragging = true;
      dragged = false;      // reset por gesto
      startX = x;
      curX = x;
      isPaused = true;
      track.style.transition = "none";
    }

    function onMove(x) {
      if (!dragging) return;
      curX = x;
      markDragIfMoved(x);

      const dx = curX - startX;
      track.style.transform = "translate3d(calc(" + (-idx * 100) + "% + " + dx + "px),0,0)";
    }

    function onUp() {
      if (!dragging) return;
      dragging = false;

      const dx = curX - startX;
      const threshold = Math.max(50, root.clientWidth * 0.12);

      track.style.transition = "";
      if (dx > threshold) prev(true);
      else if (dx < -threshold) next(true);
      else applyTransform(true);

      isPaused = false;

      // pequeño delay para evitar que el "click" post-swipe abra link
      window.setTimeout(() => { dragged = false; }, 0);
    }

    root.addEventListener("touchstart", (e) => onDown(e.touches[0].clientX), { passive: true });
    root.addEventListener("touchmove", (e) => onMove(e.touches[0].clientX), { passive: true });
    root.addEventListener("touchend", onUp, { passive: true });

    root.addEventListener("pointerdown", (e) => {
      if (e.pointerType === "mouse" && e.button !== 0) return;
      try { root.setPointerCapture(e.pointerId); } catch (_) {}
      onDown(e.clientX);
    }, { passive: true });

    root.addEventListener("pointermove", (e) => onMove(e.clientX), { passive: true });
    root.addEventListener("pointerup", onUp, { passive: true });
    root.addEventListener("pointercancel", onUp, { passive: true });

    // Visibility change: pause CPU
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState !== "visible") isPaused = true;
      else isPaused = false;
    });

    // Init
    setAria();
    applyTransform(false);
    startAuto();

    window.addEventListener("resize", () => applyTransform(false), { passive: true });
  }

  function boot() {
    document.querySelectorAll("[data-carousel]").forEach(initCarousel);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
