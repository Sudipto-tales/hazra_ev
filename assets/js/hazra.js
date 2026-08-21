/* ============================================================
   Hazra EV — interaction + animation layer (vanilla, no deps)
   Replaces the reference site's AOS + Owl + jQuery + particles.js
   ============================================================ */
(function () {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------
     1 · Preloader
     --------------------------------------------------------- */
  function preloader() {
    const el = $('#preloader');
    if (!el) return;
    const hide = () => setTimeout(() => el.classList.add('is-done'), REDUCED ? 0 : 420);
    window.addEventListener('load', hide);
    setTimeout(hide, 2600); // never trap the page on a slow image
  }

  /* ---------------------------------------------------------
     2 · Scroll progress + sticky header + back-to-top
     --------------------------------------------------------- */
  function scrollChrome() {
    const bar = $('#scrollProgress'), header = $('#header'), top = $('#toTop');
    let ticking = false;
    const paint = () => {
      const y = window.scrollY;
      const max = document.documentElement.scrollHeight - innerHeight;
      if (bar) bar.style.width = (max > 0 ? (y / max) * 100 : 0) + '%';
      if (header) header.classList.toggle('is-stuck', y > 40);
      if (top) top.classList.toggle('is-on', y > 700);
      ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(paint); } },
      { passive: true });
    paint();
    top && top.addEventListener('click', () => scrollTo({ top: 0, behavior: REDUCED ? 'auto' : 'smooth' }));
  }

  /* ---------------------------------------------------------
     3 · Desktop mega-dropdowns (hover on pointer, tap on touch)
     --------------------------------------------------------- */
  function dropdowns() {
    const items = $$('[data-dropdown]');
    const closeAll = except => items.forEach(i => {
      if (i !== except) { i.classList.remove('is-open'); i.querySelector('button').setAttribute('aria-expanded','false'); }
    });
    items.forEach(item => {
      const btn = item.querySelector('.nav__link');
      const open = on => { item.classList.toggle('is-open', on); btn.setAttribute('aria-expanded', String(on)); };
      item.addEventListener('mouseenter', () => { if (matchMedia('(hover:hover)').matches) { closeAll(item); open(true); } });
      item.addEventListener('mouseleave', () => { if (matchMedia('(hover:hover)').matches) open(false); });
      btn.addEventListener('click', e => {
        e.preventDefault();
        const next = !item.classList.contains('is-open');
        closeAll(item); open(next);
      });
    });
    document.addEventListener('click', e => { if (!e.target.closest('[data-dropdown]')) closeAll(null); });
    addEventListener('keydown', e => e.key === 'Escape' && closeAll(null));
  }

  /* ---------------------------------------------------------
     4 · Mobile drawer + its accordions
     --------------------------------------------------------- */
  function drawer() {
    const d = $('#drawer'), scrim = $('#scrim');
    if (!d) return;
    const set = on => {
      d.classList.toggle('is-open', on);
      scrim.classList.toggle('is-open', on);
      document.body.style.overflow = on ? 'hidden' : '';
    };
    $('#burger') && $('#burger').addEventListener('click', () => set(true));
    $('#drawerClose') && $('#drawerClose').addEventListener('click', () => set(false));
    scrim.addEventListener('click', () => set(false));
    $$('.drawer__trigger', d).forEach(btn => {
      if (btn.tagName === 'A') return;
      btn.addEventListener('click', () => {
        const g = btn.parentElement, panel = g.querySelector('.drawer__panel');
        const on = !g.classList.contains('is-open');
        g.classList.toggle('is-open', on);
        panel.style.maxHeight = on ? panel.scrollHeight + 'px' : 0;
      });
    });
  }

  /* ---------------------------------------------------------
     5 · Scroll reveal  (our AOS)
        markup: data-reveal="up|down|left|right|zoom|flip" data-delay="120"
     --------------------------------------------------------- */
  function reveal() {
    const nodes = $$('[data-reveal]');
    if (!nodes.length) return;
    if (REDUCED || !('IntersectionObserver' in window)) {
      nodes.forEach(n => n.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        const d = +(en.target.dataset.delay || 0);
        setTimeout(() => en.target.classList.add('is-in'), d);
        if (en.target.dataset.revealOnce !== 'false') obs.unobserve(en.target);
      });
    }, { threshold: 0.14, rootMargin: '0px 0px -8% 0px' });
    nodes.forEach(n => io.observe(n));
  }

  /* ---------------------------------------------------------
     6 · Count-up numbers + progress bars on enter
        markup: <b data-count="12500" data-suffix="+">0</b>
     --------------------------------------------------------- */
  function counters() {
    const nodes = $$('[data-count]');
    const bars  = $$('.bar i[data-pct]');
    if (!nodes.length && !bars.length) return;

    const run = el => {
      const to = parseFloat(el.dataset.count);
      const dec = +(el.dataset.decimals || 0);
      const suffix = el.dataset.suffix || '';
      const prefix = el.dataset.prefix || '';
      if (REDUCED) { el.textContent = prefix + to.toLocaleString('en-IN') + suffix; return; }
      const dur = 1500, t0 = performance.now();
      const tick = now => {
        const p = Math.min((now - t0) / dur, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        const v = to * eased;
        el.textContent = prefix + (dec ? v.toFixed(dec) : Math.round(v).toLocaleString('en-IN')) + suffix;
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    };

    const io = new IntersectionObserver((entries, obs) => {
      entries.forEach(en => {
        if (!en.isIntersecting) return;
        en.target.dataset.count !== undefined ? run(en.target)
          : (en.target.style.width = en.target.dataset.pct + '%');
        obs.unobserve(en.target);
      });
    }, { threshold: 0.4 });
    [...nodes, ...bars].forEach(n => io.observe(n));
  }

  /* ---------------------------------------------------------
     7 · Hero slider — autoplay, arrows, dots, Ken Burns
     --------------------------------------------------------- */
  function heroSlider() {
    const root = $('[data-slider]');
    if (!root) return;
    const slides = $$('.hero__slide', root);
    const dots = $$('.hero__dot', root);
    if (slides.length < 2) return;
    let i = 0, timer;

    const go = n => {
      i = (n + slides.length) % slides.length;
      slides.forEach((s, k) => s.classList.toggle('is-active', k === i));
      dots.forEach((d, k) => d.classList.toggle('is-active', k === i));
    };
    const play = () => { if (!REDUCED) timer = setInterval(() => go(i + 1), 5200); };
    const stop = () => clearInterval(timer);
    const jump = n => { stop(); go(n); play(); };

    dots.forEach((d, k) => d.addEventListener('click', () => jump(k)));
    $('.hero__arrow--next', root) && $('.hero__arrow--next', root).addEventListener('click', () => jump(i + 1));
    $('.hero__arrow--prev', root) && $('.hero__arrow--prev', root).addEventListener('click', () => jump(i - 1));
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : play());

    // swipe
    let x0 = null;
    root.addEventListener('touchstart', e => { x0 = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', e => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 45) jump(i + (dx < 0 ? 1 : -1));
      x0 = null;
    });
    go(0); play();
  }

  /* ---------------------------------------------------------
     8 · Canvas particle field (particles.js replacement)
     --------------------------------------------------------- */
  function particles() {
    const host = $('[data-particles]');
    if (!host || REDUCED) return;
    const c = document.createElement('canvas');
    c.style.cssText = 'position:absolute;inset:0;width:100%;height:100%';
    host.appendChild(c);
    const ctx = c.getContext('2d');
    let w, h, pts, raf;

    const size = () => {
      const dpr = Math.min(devicePixelRatio || 1, 2);
      w = c.width = host.offsetWidth * dpr;
      h = c.height = host.offsetHeight * dpr;
      const count = Math.min(70, Math.round((host.offsetWidth * host.offsetHeight) / 16000));
      pts = Array.from({ length: count }, () => ({
        x: Math.random() * w, y: Math.random() * h,
        vx: (Math.random() - .5) * .28 * dpr, vy: (Math.random() - .5) * .28 * dpr,
        r: (Math.random() * 1.6 + .8) * dpr
      }));
    };

    const frame = () => {
      ctx.clearRect(0, 0, w, h);
      pts.forEach(p => {
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
        ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(143,179,255,.6)'; ctx.fill();
      });
      for (let a = 0; a < pts.length; a++) {
        for (let b = a + 1; b < pts.length; b++) {
          const dx = pts[a].x - pts[b].x, dy = pts[a].y - pts[b].y;
          const d2 = dx * dx + dy * dy, lim = 130 * 130;
          if (d2 > lim) continue;
          ctx.beginPath();
          ctx.moveTo(pts[a].x, pts[a].y); ctx.lineTo(pts[b].x, pts[b].y);
          ctx.strokeStyle = `rgba(34,197,94,${(1 - d2 / lim) * .3})`;
          ctx.lineWidth = 1; ctx.stroke();
        }
      }
      raf = requestAnimationFrame(frame);
    };

    size(); frame();
    addEventListener('resize', () => { cancelAnimationFrame(raf); size(); frame(); });
    document.addEventListener('visibilitychange', () => {
      document.hidden ? cancelAnimationFrame(raf) : (raf = requestAnimationFrame(frame));
    });
  }

  /* ---------------------------------------------------------
     9 · 360° drag-to-rotate viewer + wheel zoom
        (the reference site's signature module, rebuilt)
     --------------------------------------------------------- */
  function viewer360() {
    $$('[data-viewer]').forEach(root => {
      const frames = $$('.viewer__frame', root);
      const stage  = $('.viewer__stage', root);
      const label  = $('.viewer__counter', root);
      const sens   = $('[data-sens]', root.parentElement) || $('[data-sens]');
      const scrub  = $('[data-scrub]', root.parentElement) || $('[data-scrub]');
      if (!frames.length) return;

      let idx = 0, zoom = 1, dragging = false, lastX = 0, spin;
      const total = frames.length;

      const draw = () => {
        frames.forEach((f, k) => f.classList.toggle('is-on', k === idx));
        if (label) label.textContent = `Frame ${idx + 1} / ${total}`;
        if (scrub && +scrub.value !== idx) scrub.value = idx;
      };
      const step = n => { idx = ((idx + n) % total + total) % total; draw(); };

      const autoplay = () => { if (!REDUCED) spin = setInterval(() => step(1), 260); };
      const halt = () => clearInterval(spin);

      const down = e => {
        dragging = true; halt();
        root.classList.add('is-dragging');
        lastX = (e.touches ? e.touches[0].clientX : e.clientX);
        root.setPointerCapture && e.pointerId != null && root.setPointerCapture(e.pointerId);
      };
      const move = e => {
        if (!dragging) return;
        const x = (e.touches ? e.touches[0].clientX : e.clientX);
        const factor = sens ? +sens.value : 6;
        const dx = x - lastX;
        if (Math.abs(dx) >= factor) { step(dx > 0 ? 1 : -1); lastX = x; }
      };
      const up = () => { dragging = false; root.classList.remove('is-dragging'); };

      root.addEventListener('pointerdown', down);
      root.addEventListener('pointermove', move);
      addEventListener('pointerup', up);
      root.addEventListener('touchstart', down, { passive: true });
      root.addEventListener('touchmove', move, { passive: true });
      root.addEventListener('touchend', up);

      root.addEventListener('wheel', e => {
        e.preventDefault();
        zoom = Math.min(2.2, Math.max(1, zoom + (e.deltaY < 0 ? .12 : -.12)));
        stage.style.transform = `scale(${zoom})`;
      }, { passive: false });

      scrub && scrub.addEventListener('input', () => { halt(); idx = +scrub.value; draw(); });
      root.addEventListener('mouseenter', halt);
      root.addEventListener('mouseleave', () => { if (!dragging) autoplay(); });

      if (scrub) { scrub.min = 0; scrub.max = total - 1; }
      draw(); autoplay();
    });
  }

  /* ---------------------------------------------------------
     10 · Drag-scroll carousel with arrow buttons
     --------------------------------------------------------- */
  function carousels() {
    $$('[data-carousel]').forEach(root => {
      const track = $('.carousel__track', root);
      const prev = $('[data-car-prev]', root), next = $('[data-car-next]', root);
      if (!track) return;
      const amount = () => track.firstElementChild
        ? track.firstElementChild.offsetWidth + 24 : 320;

      next && next.addEventListener('click', () => track.scrollBy({ left: amount(), behavior: 'smooth' }));
      prev && prev.addEventListener('click', () => track.scrollBy({ left: -amount(), behavior: 'smooth' }));

      const sync = () => {
        if (!prev || !next) return;
        prev.disabled = track.scrollLeft < 8;
        next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;
      };
      track.addEventListener('scroll', sync, { passive: true });
      addEventListener('resize', sync); sync();

      let down = false, sx = 0, sl = 0;
      track.addEventListener('pointerdown', e => {
        down = true; sx = e.clientX; sl = track.scrollLeft; track.classList.add('is-dragging');
      });
      track.addEventListener('pointermove', e => { if (down) track.scrollLeft = sl - (e.clientX - sx); });
      const release = () => { down = false; track.classList.remove('is-dragging'); };
      addEventListener('pointerup', release);
      track.addEventListener('pointerleave', release);
    });
  }

  /* ---------------------------------------------------------
     11 · Accordion + tabs
     --------------------------------------------------------- */
  function accordions() {
    $$('.acc__head').forEach(head => {
      head.addEventListener('click', () => {
        const acc = head.parentElement;
        const panel = acc.querySelector('.acc__panel');
        const group = acc.closest('[data-accordion-single]');
        const on = !acc.classList.contains('is-open');
        if (group) $$('.acc', group).forEach(a => {
          a.classList.remove('is-open');
          a.querySelector('.acc__panel').style.maxHeight = 0;
        });
        acc.classList.toggle('is-open', on);
        panel.style.maxHeight = on ? panel.scrollHeight + 'px' : 0;
      });
    });
  }

  function tabs() {
    $$('[data-tabs]').forEach(root => {
      const btns = $$('.tab', root);
      btns.forEach(btn => btn.addEventListener('click', () => {
        btns.forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        const target = btn.dataset.tab;
        $$('.tab-panel', root.parentElement).forEach(p =>
          p.classList.toggle('is-active', p.dataset.panel === target));
      }));
    });
  }

  /* ---------------------------------------------------------
     12 · Colour swatches swap the product image
     --------------------------------------------------------- */
  function swatches() {
    $$('[data-swatches]').forEach(group => {
      const img = $(group.dataset.swatches === 'self'
        ? '.product__media img' : group.dataset.swatches,
        group.dataset.swatches === 'self' ? group.closest('.product') : document);
      $$('.swatch', group).forEach(sw => {
        sw.addEventListener('click', () => {
          $$('.swatch', group).forEach(s => s.classList.remove('is-active'));
          sw.classList.add('is-active');
          if (img && sw.dataset.img) {
            img.style.opacity = 0;
            setTimeout(() => { img.src = sw.dataset.img; img.style.opacity = 1; }, 160);
          }
        });
      });
    });
  }

  /* ---------------------------------------------------------
     13 · Live filtering (products / dealers / jobs / faq)
     --------------------------------------------------------- */
  function filters() {
    $$('[data-filter-group]').forEach(group => {
      const listSel = group.dataset.filterGroup;
      const chips = $$('.chip', group);
      chips.forEach(chip => chip.addEventListener('click', () => {
        chips.forEach(c => c.classList.remove('is-active'));
        chip.classList.add('is-active');
        const key = chip.dataset.filter;
        $$(`${listSel} [data-tags]`).forEach(card => {
          const show = key === 'all' || card.dataset.tags.split(' ').includes(key);
          card.style.display = show ? '' : 'none';
          if (show) { card.classList.remove('is-in'); requestAnimationFrame(() => card.classList.add('is-in')); }
        });
      }));
    });

    $$('[data-search]').forEach(input => {
      input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        const cards = $$(`${input.dataset.search} [data-tags]`);
        let hits = 0;
        cards.forEach(card => {
          const show = !q || card.textContent.toLowerCase().includes(q);
          card.style.display = show ? '' : 'none';
          if (show) hits++;
        });
        const empty = $(input.dataset.search + '-empty');
        if (empty) empty.style.display = hits ? 'none' : '';
      });
    });
  }

  /* ---------------------------------------------------------
     14 · Range calculator gauge (product page)
     --------------------------------------------------------- */
  function gauge() {
    const root = $('[data-gauge]');
    if (!root) return;
    const arc = $('.gauge__val', root);
    const num = $('[data-gauge-num]', root);
    const inputs = $$('[data-gauge-input]');
    const base = +root.dataset.gauge;

    const calc = () => {
      let km = base;
      inputs.forEach(i => { km *= +(i.selectedOptions ? i.selectedOptions[0].dataset.factor : i.dataset.factor || 1); });
      km = Math.round(km);
      const pct = Math.min(km / (base * 1.15), 1);
      arc.style.strokeDashoffset = 283 - 283 * pct;
      if (num) num.dataset.count = km, num.textContent = km;
      return km;
    };
    inputs.forEach(i => i.addEventListener('change', calc));
    calc();
  }

  /* ---------------------------------------------------------
     15 · Parallax layers
     --------------------------------------------------------- */
  function parallax() {
    const layers = $$('[data-parallax]');
    if (!layers.length || REDUCED) return;
    let ticking = false;
    const paint = () => {
      const y = scrollY;
      layers.forEach(l => {
        const speed = +l.dataset.parallax || .15;
        const rect = l.getBoundingClientRect();
        if (rect.bottom < -200 || rect.top > innerHeight + 200) return;
        l.style.transform = `translate3d(0,${(y - l.offsetTop) * speed}px,0)`;
      });
      ticking = false;
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(paint); } },
      { passive: true });
    paint();
  }

  /* ---------------------------------------------------------
     16 · Prototype forms → toast, never a real submit
     --------------------------------------------------------- */
  function forms() {
    const toast = $('#toast'), msg = $('#toastMsg');
    const say = text => {
      if (!toast) return;
      msg.textContent = text;
      toast.classList.add('is-on');
      setTimeout(() => toast.classList.remove('is-on'), 3200);
    };
    window.hazraToast = say;
    $$('[data-fake-form]').forEach(f => f.addEventListener('submit', e => {
      e.preventDefault();
      say(f.dataset.fakeForm || 'Thanks — our team will call you within 24 hours.');
      f.reset();
    }));
  }

  /* ---------------------------------------------------------
     boot
     --------------------------------------------------------- */
  function boot() {
    preloader(); scrollChrome(); dropdowns(); drawer();
    reveal(); counters(); heroSlider(); particles(); viewer360();
    carousels(); accordions(); tabs(); swatches(); filters();
    gauge(); parallax(); forms();
  }

  // The chrome mounts synchronously from a deferred script, so `hazra:chrome`
  // can fire before this file executes. Bind to DOMContentLoaded instead — it
  // always lands after every deferred script and after page-level inline setup.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
