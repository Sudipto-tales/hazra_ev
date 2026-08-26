(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const root = document.documentElement;

  /* ── theme ─────────────────────────────────── */
  const saved = localStorage.getItem('vm-theme');
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
  const setTheme = t => {
    root.dataset.theme = t;
    localStorage.setItem('vm-theme', t);
    if (pager) pager.textContent = t === 'dark' ? '02' : '01';
  };
  setTheme(root.dataset.theme);

  $('#theme')?.addEventListener('click', () =>
    setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'));

  $$('.arrow').forEach(b =>
    b.addEventListener('click', () =>
      setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark')));

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
  $$('.about .reveal-up, .coll .reveal-up').forEach(el =>
    once(el, e => e.classList.add('is-in'), .25));

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
