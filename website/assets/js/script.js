(() => {
  'use strict';

  const $  = (s, r = document) => (r && typeof r.querySelector === 'function') ? r.querySelector(s) : null;
  const $$ = (s, r = document) => (r && typeof r.querySelectorAll === 'function') ? [...r.querySelectorAll(s)] : [];

  const root = document.documentElement;

  /* ── theme ─────────────────────────────────── */
  /* 'theme' is the key every page uses. index.html shipped with its own
     'vm-theme' key, so the preference was lost on every navigation — read
     the old key once and migrate it, then never write it again. */
  const saved = localStorage.getItem('theme') || localStorage.getItem('vm-theme');
  const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  root.dataset.theme = saved || (sysDark ? 'dark' : 'light');

  /* ── icons ─────────────────────────────────── */
  const drawIcons = () => window.lucide && window.lucide.createIcons();
  drawIcons();

  /* ── entrance ──────────────────────────────── */
  const canvas = $('#canvas');
  const step = (el, d) => el && setTimeout(() => el.classList.add('is-in'), d);

  requestAnimationFrame(() => {
    step(canvas, 60);
    step($('.title'), 460);
    $$('.reveal-pop').forEach((el, i) => step(el, 760 + i * 180));
    setTimeout(() => root.style.setProperty('--iz', '1'), 200);
    countTo(2, 900);
  });

  /* ── counter ───────────────────────────────── */
  function countTo(target, delay) {
    const el = $('#count');
    if (!el) return;
    setTimeout(() => {
      let n = 0;
      const tick = setInterval(() => {
        n += 1;
        el.textContent = String(n).padStart(2, '0');
        if (n >= target) clearInterval(tick);
      }, 130);
    }, delay);
  }

  /* ── finish switch (light shot ⇄ dark shot) ─ */
  const pager = $('.pager b');
  const getTheme = () => (root.getAttribute('data-theme') || root.dataset.theme || 'light') === 'dark' ? 'dark' : 'light';
  const setTheme = t => {
    const next = t === 'dark' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    root.dataset.theme = next;
    try {
      localStorage.setItem('theme', next);
      localStorage.removeItem('vm-theme');
    } catch (e) {}
    if (pager) pager.textContent = next === 'dark' ? '02' : '01';
    $$('.theme, #theme, #stickyTheme').forEach(b => {
      b.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      b.setAttribute('title', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
    });
  };
  setTheme(getTheme());

  $$('.arrow').forEach(b =>
    b.addEventListener('click', () =>
      setTheme(getTheme() === 'dark' ? 'light' : 'dark')));

  /* ── navbar ────────────────────────────────────
     Desktop opens a dropdown on hover AND on click (a click still works for
     touch laptops and keyboards); ≤1024 the same .is-open class drives the
     accordion, so there is one state to reason about, not two. */
  const nav          = $('#nav');
  const burger       = $('#burger');
  const stickyNav    = $('#stickyNav');
  const stickyBurger = $('#stickyBurger');
  const groups       = $$('.nav__grp');
  const touch        = window.matchMedia('(hover:none)');
  const wide         = window.matchMedia('(min-width:1025px)');

  const openGrp = (g, on) => {
    g.classList.toggle('is-open', on);
    $('.nav__i--t', g)?.setAttribute('aria-expanded', String(on));
  };
  const closeAll = except => groups.forEach(g => g !== except && openGrp(g, false));

  groups.forEach(g => {
    const trigger = $('.nav__i--t', g);

    trigger?.addEventListener('click', e => {
      e.preventDefault();
      e.stopPropagation();
      const on = !g.classList.contains('is-open');
      closeAll(g);
      openGrp(g, on);
    });

    /* hover only where there is a real pointer and room for a layer */
    g.addEventListener('pointerenter', () => {
      if (touch.matches || !wide.matches) return;
      closeAll(g);
      openGrp(g, true);
    });
    g.addEventListener('pointerleave', () => {
      if (touch.matches || !wide.matches) return;
      openGrp(g, false);
    });
  });

  const updateNavLock = () => {
    const isOpen = Boolean(nav?.classList.contains('is-open') || stickyNav?.classList.contains('is-open'));
    document.body.classList.toggle('nav-open', isOpen);
    const dockEl = $('.dock');
    if (dockEl) dockEl.classList.toggle('is-hidden', isOpen);
  };

  const setMenu = on => {
    if (nav) nav.classList.toggle('is-open', on);
    if (burger) {
      burger.classList.toggle('is-on', on);
      burger.setAttribute('aria-expanded', String(on));
      burger.setAttribute('aria-label', on ? 'Close menu' : 'Open menu');
    }
    if (on) drawIcons();
    if (!on) closeAll();
    updateNavLock();
  };

  burger?.addEventListener('click', e => {
    e.stopPropagation();
    const isOpen = nav ? nav.classList.contains('is-open') : false;
    setMenu(!isOpen);
  });

  /* picking a destination closes everything behind you */
  if (nav) {
    $$('a[href]', nav).forEach(a =>
      a.addEventListener('click', () => { closeAll(); setMenu(false); }));
  }

  const setStickyMenu = on => {
    if (stickyNav) stickyNav.classList.toggle('is-open', on);
    if (stickyBurger) {
      stickyBurger.classList.toggle('is-on', on);
      stickyBurger.setAttribute('aria-expanded', String(on));
      stickyBurger.setAttribute('aria-label', on ? 'Close menu' : 'Open menu');
    }
    if (on) drawIcons();
    if (!on) closeAll();
    updateNavLock();
  };

  stickyBurger?.addEventListener('click', e => {
    e.stopPropagation();
    const isOpen = stickyNav ? stickyNav.classList.contains('is-open') : false;
    setStickyMenu(!isOpen);
  });

  /* picking a destination closes everything */
  if (stickyNav) {
    $$('a[href]', stickyNav).forEach(a =>
      a.addEventListener('click', () => { closeAll(); setMenu(false); setStickyMenu(false); }));
  }

  /* Global click: handles theme toggles everywhere + outside-nav close */
  document.addEventListener('click', e => {
    const themeBtn = e.target.closest('.theme, #theme, #stickyTheme');
    if (themeBtn) {
      e.preventDefault();
      e.stopPropagation();
      setTheme(getTheme() === 'dark' ? 'light' : 'dark');
      return;
    }

    if (e.target.closest('.burger') || e.target.closest('.topbar') || e.target.closest('.sticky-bar')) {
      return;
    }

    closeAll();
    setMenu(false);
    setStickyMenu(false);
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeAll();
    setMenu(false);
    setStickyMenu(false);
  });

  /* crossing the breakpoint leaves stale open state behind — clear it */
  wide.addEventListener('change', () => { closeAll(); setMenu(false); setStickyMenu(false); });

  /* ══════════ SCROLL ZOOM ══════════
     .scroll is taller than the viewport; .stage is sticky inside it. The
     surplus scroll maps to --p (0…1), and every geometry/colour rule in the
     stylesheet is a calc() off that one number. HOLD keeps the last slice of
     the scroll at --p:1 so the full-bleed state has a beat to sit in. */
  const scroller = $('#scroll');
  const desktop = window.matchMedia('(min-width:1025px)');
  const reduce  = window.matchMedia('(prefers-reduced-motion:reduce)');
  const HOLD = .72;          /* --p hits 1 at 72% of the driver */

  let ticking = false, p = 0;

  const measure = () => {
    root.style.setProperty('--vw', root.clientWidth + 'px');
    root.style.setProperty('--vh', window.innerHeight + 'px');
  };

  const readScroll = () => {
    if (!scroller || !desktop.matches) { root.style.setProperty('--p', '0'); return; }
    const span = scroller.offsetHeight - window.innerHeight;
    const gone = Math.min(Math.max(-scroller.getBoundingClientRect().top, 0), span);
    p = span > 0 ? Math.min(gone / span / HOLD, 1) : 0;
    root.style.setProperty('--p', p.toFixed(4));
  };

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => { readScroll(); ticking = false; });
  };

  measure();
  readScroll();
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', () => { measure(); readScroll(); });
  desktop.addEventListener('change', () => { measure(); readScroll(); });

  /* ── sticky navbar: hide on scroll-down, show on scroll-up/stop ────
     Appears after the hero section ends; hidden by default, slides in
     with spring animation when scrolling up or idle. Glass morphism. */
  const stickyBar = $('#stickyBar');
  let lastStickyY = 0;
  let stickyTimer = null;

  const HERO_THRESHOLD = () => {
    const scroll = $('#scroll');
    return scroll?.offsetHeight || window.innerHeight * 2.5;
  };

  const showStickyBar = () => {
    stickyBar?.classList.add('is-visible');
    stickyBar?.setAttribute('aria-hidden', 'false');
  };

  const hideStickyBar = () => {
    stickyBar?.classList.remove('is-visible');
    stickyBar?.setAttribute('aria-hidden', 'true');
  };

  const onStickyScroll = () => {
    const y = window.scrollY;
    const isPage = Boolean(stickyBar?.classList.contains('is-page'));

    /* Never hide when mobile menu is open */
    if (stickyNav?.classList.contains('is-open') || nav?.classList.contains('is-open')) {
      showStickyBar();
      lastStickyY = y;
      return;
    }

    if (!isPage) {
      const threshold = HERO_THRESHOLD();
      if (y < threshold) {
        hideStickyBar();
        lastStickyY = y;
        return;
      }
    } else {
      /* On secondary pages, keep visible near top of page */
      if (y <= 40) {
        showStickyBar();
        lastStickyY = y;
        return;
      }
    }

    if (y < lastStickyY) {
      showStickyBar();
    } else if (y > lastStickyY && y > 80) {
      hideStickyBar();
    }
    lastStickyY = y;

    clearTimeout(stickyTimer);
    stickyTimer = setTimeout(showStickyBar, 350);
  };

  addEventListener('scroll', onStickyScroll, { passive: true });

  /* ── mouse parallax on the photo ───────────────
     Writes offsets as numbers; the transform itself lives in CSS so it can
     compose with the scroll zoom instead of overwriting it. Damped to zero
     as --p climbs, since a full-bleed photo shouldn't wobble. */
  if (desktop.matches && !reduce.matches) {
    const stage = $('.stage');
    stage?.addEventListener('mousemove', e => {
      const r = stage.getBoundingClientRect();
      const damp = 1 - p;
      root.style.setProperty('--px', (((e.clientX - r.left) / r.width  - .5) * -18 * damp).toFixed(2));
      root.style.setProperty('--py', (((e.clientY - r.top)  / r.height - .5) * -14 * damp).toFixed(2));
    });
    stage?.addEventListener('mouseleave', () => {
      root.style.setProperty('--px', '0');
      root.style.setProperty('--py', '0');
    });
  }
})();

(() => {
  'use strict';

  if (!document.querySelector('.dock')) {
    const dock = document.createElement('aside');
    dock.className = 'dock';
    dock.id = 'floatingDock';
    dock.setAttribute('aria-label', 'Quick actions');
    dock.innerHTML = `
      <a class="dock__i" href="#test-ride" aria-label="Book a test drive">
        <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/></svg>
        <span class="dock__lb">Test Drive</span>
      </a>
      <a class="dock__i" href="become-a-dealer" aria-label="Become a dealer">
        <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></svg>
        <span class="dock__lb">Dealership</span>
      </a>
      <a class="dock__i" href="tel:+919002921509" aria-label="Call Hazra Electrical Bike">
        <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <span class="dock__lb">Call Us</span>
      </a>
      <a class="dock__i" href="https://wa.me/919002921509" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
        <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
        <span class="dock__lb">WhatsApp</span>
      </a>`;
    document.body.appendChild(dock);
  }

  const slider = document.querySelector('#rangeSlider');
  const rangeOutput = document.querySelector('#rangeOutput');
  const rangeCoverage = document.querySelector('#rangeCoverage');
  const rangeDays = document.querySelector('#rangeDays');

  slider?.addEventListener('input', () => {
    const distance = Number(slider.value);
    const days = (120 / distance).toFixed(1);
    if (rangeOutput) rangeOutput.textContent = String(distance);
    if (rangeCoverage) rangeCoverage.textContent = days;
    if (rangeDays) rangeDays.textContent = `${days} days`;
  });
})();


/* ════════════════════════════════════════════════
   BELOW THE FOLD — mosaic reveal, counters, cards
   Separate IIFE: the hero block above owns --p and nothing here reads it.
   ════════════════════════════════════════════════ */
(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduce = window.matchMedia('(prefers-reduced-motion:reduce)').matches;

  /* fires cb once, the first time el crosses `ratio` into view */
  const once = (el, cb, ratio = .3) => {
    if (!el) return;
    if (reduce || !('IntersectionObserver' in window)) { cb(el); return; }
    const io = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return;
      io.disconnect();
      cb(el);
    }, { threshold: ratio });
    io.observe(el);
  };

  /* ══════════ MOSAIC REVEAL ══════════
     Lay a cols×rows grid of tiles over the video, each showing its own slice
     of the same background image, then flip them away on a diagonal. The
     delay is (col + row) plus a deterministic jitter, so the shatter reads
     as organic rather than as a clean wipe. */
  const mosaic = $('#mosaic');
  const video  = $('#mosaicVideo');
  const grid   = mosaic && $('.mosaic__grid', mosaic);

  const build = () => {
    if (!grid) return;
    const cols = innerWidth < 720 ? 5 : 8;
    const rows = innerWidth < 720 ? 4 : 5;
    grid.style.setProperty('--cols', cols);
    grid.style.setProperty('--rows', rows);
    grid.textContent = '';

    const span = (cols - 1) + (rows - 1);
    const frag = document.createDocumentFragment();

    for (let r = 0; r < rows; r++) {
      for (let c = 0; c < cols; c++) {
        const t = document.createElement('i');
        t.className = 'tile';
        /* background-size is cols×rows of the box, so 0…100% walks the grid */
        t.style.setProperty('--bx', (cols > 1 ? (c / (cols - 1)) * 100 : 0) + '%');
        t.style.setProperty('--by', (rows > 1 ? (r / (rows - 1)) * 100 : 0) + '%');
        /* diagonal sweep + a stable per-cell jitter (no Math.random: the
           layout must survive a resize rebuild without re-scrambling) */
        const jitter = ((c * 7 + r * 13) % 5) * 34;
        t.style.setProperty('--d', ((c + r) / span * 620 + jitter).toFixed(0) + 'ms');
        frag.appendChild(t);
      }
    }
    grid.appendChild(frag);
  };

  if (mosaic) {
    if (reduce) {
      mosaic.classList.add('is-open');           /* no shatter, no tiles */
    } else {
      build();
      /* rebuild only when the breakpoint actually flips, and only while the
         tiles still matter — once they are gone the grid is inert */
      let narrow = innerWidth < 720;
      addEventListener('resize', () => {
        const n = innerWidth < 720;
        if (n === narrow || mosaic.classList.contains('is-open')) return;
        narrow = n;
        build();
      });
      once(mosaic, el => el.classList.add('is-open'), .35);
    }

    /* play only while on screen; the reel is decoration, not content */
    const play = () => video?.play().then(
      () => mosaic.classList.add('is-playing'),
      () => {}                                   /* no file yet — poster stays */
    );

    $('#mosaicPlay')?.addEventListener('click', () => {
      if (!video) return;
      if (video.paused) play();
      else { video.pause(); mosaic.classList.remove('is-playing'); }
    });

    if ('IntersectionObserver' in window && video) {
      new IntersectionObserver(([e]) => {
        if (e.isIntersecting) { if (!reduce) play(); }
        else { video.pause(); mosaic.classList.remove('is-playing'); }
      }, { threshold: .45 }).observe(mosaic);
    }
  }

  /* ══════════ STAT COUNTERS ══════════
     One rAF ramp per number, eased out so it lands rather than stops. */
  const runCount = el => {
    const to  = Number(el.dataset.to || 0);
    const sfx = el.dataset.suffix || '';
    if (reduce) { el.textContent = to + sfx; return; }

    const dur = 1400;
    let t0 = null;
    const frame = ts => {
      if (t0 === null) t0 = ts;
      const k = Math.min((ts - t0) / dur, 1);
      const eased = 1 - Math.pow(1 - k, 3);
      el.textContent = Math.round(to * eased) + sfx;
      if (k < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  };
  once($('.stats'), list => $$('.stat__n', list).forEach(runCount), .4);

  /* ══════════ SECTION COPY REVEAL ══════════ */
  $$('.about .reveal-up, .coll .reveal-up, .feat .reveal-up, .why .reveal-up').forEach(el =>
    once(el, e => e.classList.add('is-in'), .25));

  /* ══════════ WHY CHOOSE ══════════
     Same stagger contract as .card — JS writes the index, CSS owns the
     transition-delay. Rows reveal in reading order, not in one slab. */
  $$('.why__item').forEach((row, i) => {
    row.style.setProperty('--i', i);
    once(row, el => el.classList.add('is-in'), .2);
  });

  /* ══════════ CARDS ══════════ */
  const cards = $$('.card');

  /* stagger index drives the CSS transition-delay */
  cards.forEach((card, i) => {
    card.style.setProperty('--i', i % 3);
    once(card, el => el.classList.add('is-in'), .18);
  });

  /* pointer-tracked sheen — CSS owns the gradient, JS only feeds it a point */
  if (!reduce && matchMedia('(hover:hover)').matches) {
    cards.forEach(card => {
      const media = $('.card__media', card);
      media?.addEventListener('mousemove', e => {
        const r = media.getBoundingClientRect();
        card.style.setProperty('--mx', (((e.clientX - r.left) / r.width)  * 100).toFixed(1) + '%');
        card.style.setProperty('--my', (((e.clientY - r.top)  / r.height) * 100).toFixed(1) + '%');
      });
    });
  }

  /* swatches regrade the shot: one photo, six finishes */
  $$('.card__colors').forEach(group => {
    const card = group.closest('.card');
    $$('.sw', group).forEach(sw => {
      sw.addEventListener('click', () => {
        $$('.sw', group).forEach(o => o.classList.remove('is-on'));
        sw.classList.add('is-on');
        card.style.setProperty('--c', sw.style.getPropertyValue('--c').trim());
      });
    });
  });

  /* variant buttons: pick one per card */
  $$('.card__vars').forEach(group => {
    $$('.var', group).forEach(v => {
      v.addEventListener('click', () => {
        $$('.var', group).forEach(o => o.style.borderColor = '');
        v.style.borderColor = 'var(--ink-0)';
      });
    });
  });

  /* the new markup arrived after the hero's first createIcons() pass */
  window.lucide && window.lucide.createIcons();
})();


/* ════════════════════════════════════════════════
   FEATURE WINDOW — writes --fi, nothing else

   Same shape as the hero driver: .feat__drive is taller than the viewport,
   .feat__pin is sticky inside it, and the surplus scroll maps to a single
   number — here the fractional slide index rather than a 0…1 ramp. Every
   position, fade and rotation in the stylesheet is a calc() off it.

   Below the pin breakpoint the CSS turns the deck into a snap carousel, so
   this block only keeps the dots in sync and does no scroll work at all.
   ════════════════════════════════════════════════ */
(() => {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const sect  = $('#features');
  const drive = $('#featDrive');
  const deck  = $('#featDeck');
  if (!sect || !drive || !deck) return;

  const slides = $$('.fslide', deck);
  const last   = Math.max(slides.length - 1, 1);
  const pin    = window.matchMedia('(min-width:901px)');

  /* ── arc ticks ────────────────────────────────
     Drawn here rather than in markup: the count is a function of the arc
     geometry, not of the content, so it has no business in the HTML. */
  const ticks = $('#arcTicks');
  if (ticks && !ticks.children.length) {
    const SPAN = 34, STEP = 2;               /* degrees each side, degrees apart */
    let html = '';
    for (let a = -SPAN; a <= SPAN; a += STEP) html += `<i style="--a:${a}deg"></i>`;
    ticks.innerHTML = html;
  }

  /* ── scroll → --fi ────────────────────────────
     HOLD reserves the last slice of the driver so the final slide gets a
     beat centred before the pin releases. */
  const HOLD = .88;
  let ticking = false;

  /* --fi is the number; .is-live is the nearest slide to it. The stylesheet
     reads --fi for position and .is-live for the float loop, so only one
     bike is ever animating. */
  let live = -1;
  const setFi = fi => {
    sect.style.setProperty('--fi', fi.toFixed(4));
    const near = Math.round(fi);
    if (near === live) return;
    slides[live]?.classList.remove('is-live');
    slides[near]?.classList.add('is-live');
    live = near;
  };

  /* ── Mobile Touch Swipe & Animated Scrub ── */
  let mobileFi = 0;
  let animId = null;
  let autoTimer = null;

  const animateToFi = target => {
    cancelAnimationFrame(animId);
    const dest = Math.max(0, Math.min(target, last));
    const start = mobileFi;
    const delta = dest - start;
    if (Math.abs(delta) < 0.001) {
      mobileFi = dest;
      setFi(dest);
      return;
    }
    const startTime = performance.now();
    const duration = 340; // ms

    const step = now => {
      const progress = Math.min((now - startTime) / duration, 1);
      // smooth cubic ease out
      const ease = 1 - Math.pow(1 - progress, 3);
      mobileFi = start + delta * ease;
      setFi(mobileFi);
      if (progress < 1) {
        animId = requestAnimationFrame(step);
      } else {
        mobileFi = dest;
        setFi(dest);
      }
    };
    animId = requestAnimationFrame(step);
  };

  const restartAuto = () => {
    clearInterval(autoTimer);
    if (pin.matches) return;
    autoTimer = setInterval(() => {
      if (pin.matches || document.hidden) return;
      const rect = sect.getBoundingClientRect();
      if (rect.top < window.innerHeight && rect.bottom > 0) {
        const next = (Math.round(mobileFi) + 1) % slides.length;
        animateToFi(next);
      }
    }, 4500);
  };

  const read = () => {
    if (!pin.matches) {
      setFi(mobileFi);
      return;
    }
    clearInterval(autoTimer);
    const span = drive.offsetHeight - window.innerHeight;
    const gone = Math.min(Math.max(-drive.getBoundingClientRect().top, 0), span);
    const p    = span > 0 ? Math.min(gone / span / HOLD, 1) : 0;
    setFi(p * last);
  };

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => { read(); ticking = false; });
  };

  read();
  addEventListener('scroll', onScroll, { passive: true });
  addEventListener('resize', read);
  pin.addEventListener('change', () => {
    if (!pin.matches) {
      mobileFi = 0;
      restartAuto();
    }
    read();
  });

  // Touch & Pointer drag handlers for mobile swipe
  let startX = 0, startY = 0, startFi = 0;
  let isDragging = false;
  let isHorizontal = null;

  const onPointerDown = e => {
    if (pin.matches) return;
    startX = e.clientX;
    startY = e.clientY;
    startFi = mobileFi;
    isDragging = true;
    isHorizontal = null;
    cancelAnimationFrame(animId);
    clearInterval(autoTimer);
  };

  const onPointerMove = e => {
    if (!isDragging || pin.matches) return;
    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (isHorizontal === null) {
      if (Math.abs(dx) > 7 || Math.abs(dy) > 7) {
        isHorizontal = Math.abs(dx) > Math.abs(dy);
      }
    }

    if (isHorizontal) {
      if (e.cancelable) e.preventDefault();
      const sensitivity = (deck.offsetWidth || window.innerWidth) * 0.78;
      const deltaFi = -dx / sensitivity;
      mobileFi = Math.max(-0.25, Math.min(last + 0.25, startFi + deltaFi));
      setFi(mobileFi);
    }
  };

  const onPointerUp = e => {
    if (!isDragging || pin.matches) return;
    isDragging = false;
    if (isHorizontal) {
      const dx = e.clientX - startX;
      if (dx < -32) {
        animateToFi(Math.min(last, Math.floor(startFi) + 1));
      } else if (dx > 32) {
        animateToFi(Math.max(0, Math.ceil(startFi) - 1));
      } else {
        animateToFi(Math.round(mobileFi));
      }
    }
    isHorizontal = null;
    restartAuto();
  };

  deck.addEventListener('pointerdown', onPointerDown);
  window.addEventListener('pointermove', onPointerMove, { passive: false });
  window.addEventListener('pointerup', onPointerUp);
  window.addEventListener('pointercancel', onPointerUp);

  // Arrow buttons
  const prevBtn = $('#featPrev');
  const nextBtn = $('#featNext');
  prevBtn?.addEventListener('click', e => {
    e.stopPropagation();
    animateToFi(Math.round(mobileFi) - 1);
    restartAuto();
  });
  nextBtn?.addEventListener('click', e => {
    e.stopPropagation();
    animateToFi(Math.round(mobileFi) + 1);
    restartAuto();
  });

  // Slide tap to select
  slides.forEach((slide, idx) => {
    slide.addEventListener('click', () => {
      if (pin.matches) return;
      if (Math.round(mobileFi) !== idx) {
        animateToFi(idx);
        restartAuto();
      }
    });
  });

  // Dots
  $$('.fdot').forEach(dot => {
    dot.addEventListener('click', e => {
      e.stopPropagation();
      const i = +dot.dataset.go;
      if (!pin.matches) {
        animateToFi(i);
        restartAuto();
        return;
      }
      const span = drive.offsetHeight - window.innerHeight;
      const top  = drive.offsetTop + (i / last) * HOLD * span;
      scrollTo({ top, behavior: 'smooth' });
    });
  });

  restartAuto();
})();


/* ════════════════════════════════════════════════
   PERFORMANCE · NEWS · TEST RIDE · FAQ · INSIGHTS · FOOTER

   All six blocks share one contract with the stylesheet: JS adds `is-in` or
   `is-open` and writes a stagger index; every transition, delay and easing
   lives in CSS. Nothing here measures a height — the FAQ panel animates on
   grid-template-rows, so the content sizes itself.
   ════════════════════════════════════════════════ */
(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion:reduce)').matches;

  const once = (el, cb, ratio = .25) => {
    if (!el) return;
    if (reduce || !('IntersectionObserver' in window)) { cb(el); return; }
    const io = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return;
      io.disconnect();
      cb(el);
    }, { threshold: ratio });
    io.observe(el);
  };

  /* copy reveal for the new blocks — same class the older sections use */
  $$('.perf .reveal-up, .news .reveal-up, .ride .reveal-up, .faq .reveal-up, .ins .reveal-up')
    .forEach(el => once(el, e => e.classList.add('is-in'), .2));

  /* ── performance cells + count-up ──────────────
     The cell reveals on its own threshold; the number only starts once its
     cell is in, so a fast scroll never lands on a half-run counter. */
  $$('.perf__cell').forEach((cell, i) => {
    cell.style.setProperty('--i', i);
    once(cell, el => {
      el.classList.add('is-in');
      $$('.perf__v', el).forEach(run);
    }, .35);
  });

  function run(el) {
    const to  = Number(el.dataset.to || 0);
    const pre = el.dataset.prefix || '';
    const sfx = el.dataset.suffix || '';
    const paint = n => { el.textContent = pre + n + sfx; };

    if (reduce) { paint(to); return; }

    const dur = 1300;
    let t0 = null;
    const frame = ts => {
      if (t0 === null) t0 = ts;
      const k = Math.min((ts - t0) / dur, 1);
      paint(Math.round(to * (1 - Math.pow(1 - k, 3))));
      if (k < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  }

  /* ── bento + insight cards: index in, CSS owns the delay ── */
  $$('.bento__c').forEach((c, i) => {
    c.style.setProperty('--i', i);
    once(c, el => el.classList.add('is-in'), .18);
  });
  $$('.post').forEach(p => once(p, el => el.classList.add('is-in'), .18));

  /* ── FAQ accordion ────────────────────────────
     One open at a time. aria-expanded is the source of truth for assistive
     tech; the class is what the stylesheet reads. */
  const items = $$('.faq__item');
  items.forEach(item => {
    const q = $('.faq__q', item);
    q?.addEventListener('click', () => {
      const open = item.classList.contains('is-open');
      items.forEach(o => {
        o.classList.remove('is-open');
        $('.faq__q', o)?.setAttribute('aria-expanded', 'false');
      });
      if (!open) {
        item.classList.add('is-open');
        q.setAttribute('aria-expanded', 'true');
      }
    });
  });

  /* ── test ride form ───────────────────────────
     No endpoint is wired yet: this validates, then prints the receipt the
     backend will replace. Nothing leaves the page. */
  const form = $('#rideForm');
  const ok   = $('#rideOk');
  form?.addEventListener('submit', async e => {
    e.preventDefault();
    if (!form.reportValidity()) return;
    const fd = new FormData(form);
    const body = {
      type: 'test_ride',
      name: fd.get('name'),
      phone: fd.get('phone'),
      city: fd.get('city'),
    };
    try {
      const apiPath = typeof base_url !== 'undefined' ? base_url('api/v1/website/leads') : '/api/v1/website/leads';
      const res = await fetch(apiPath, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      const nameStr = (fd.get('name') || '').toString().trim().split(' ')[0] || 'there';
      ok.textContent = data.data?.message || data.message || `Thanks ${nameStr} — a dealer will call you within one working day.`;
      form.reset();
    } catch(err) {
      const nameStr = (fd.get('name') || '').toString().trim().split(' ')[0] || 'there';
      ok.textContent = `Thanks ${nameStr} — a dealer will call you within one working day.`;
      form.reset();
    }
  });

  /* ── floating action dock ─────────────────────
     Two of the four items are in-page anchors. #test-ride exists; #dealership
     is a future form, so its click is swallowed rather than letting the
     browser fall back to jumping to the top of the document. tel: and wa.me
     are plain links and are left alone. */
  /* ── floating action dock ─────────────────────
     Smooth scroll for on-page anchors (e.g. #test-ride) when target exists;
     allow regular navigation for full URLs (e.g. become-a-dealer, tel:, wa.me). */
  $$('.dock__i').forEach(a => {
    const href = a.getAttribute('href') || '';
    if (href.startsWith('#') && href.length > 1) {
      a.addEventListener('click', e => {
        try {
          const target = document.querySelector(href);
          if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
            target.querySelector('input, select, textarea')?.focus({ preventScroll: true });
          }
        } catch (_) {}
      });
    }
  });

  /* ── footer reel ──────────────────────────────
     Decoration only: play while on screen, pause when it is not, and stay
     silent if the file is missing. */
  const fv = $('.foot__v');
  if (fv && !reduce && 'IntersectionObserver' in window) {
    new IntersectionObserver(([e]) => {
      if (e.isIntersecting) fv.play().catch(() => {});
      else fv.pause();
    }, { threshold: .15 }).observe($('.foot'));
  }
})();
