(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  /* ── theme ─────────────────────────────────── */
  const root = document.documentElement;
  const saved = localStorage.getItem('vm-theme');
  const sysDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  root.dataset.theme = saved || (sysDark ? 'dark' : 'light');

  $('#theme')?.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('vm-theme', root.dataset.theme);
  });

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
    setTimeout(() => { const im = $('.photo__img'); if (im) im.style.transform = 'scale(1)'; }, 200);
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

  /* ── deck ──────────────────────────────────── */
  const thumbs = $$('.thumb');
  const hero   = $('.photo__img');
  const pager  = $('.pager b');
  let idx = thumbs.findIndex(t => t.classList.contains('is-on'));
  if (idx < 0) idx = 0;

  const select = i => {
    idx = (i + thumbs.length) % thumbs.length;
    thumbs.forEach((t, k) => t.classList.toggle('is-on', k === idx));
    if (pager) pager.textContent = String(idx + 1).padStart(2, '0').slice(-2);

    const src = thumbs[idx].querySelector('img')?.src;
    if (hero && src) {
      hero.style.opacity = '0';
      hero.style.transform = 'scale(1.06)';
      setTimeout(() => {
        hero.src = src.replace(/w=\d+/, 'w=1400');
        hero.style.opacity = '1';
        hero.style.transform = 'scale(1)';
      }, 280);
    }
    drawIcons();
  };

  thumbs.forEach((t, i) => t.addEventListener('click', () => select(i)));
  $$('.arrow').forEach(b =>
    b.addEventListener('click', () => select(idx + Number(b.dataset.dir)))
  );

  if (hero) hero.style.transition = 'opacity .28s ease, transform 1.4s cubic-bezier(.22,1,.36,1)';

  /* ── parallax on the photo ─────────────────── */
  const photo = $('.photo');
  if (photo && window.matchMedia('(min-width:1025px)').matches) {
    canvas?.addEventListener('mousemove', e => {
      const r = canvas.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - .5;
      const y = (e.clientY - r.top) / r.height - .5;
      hero.style.transform = `scale(1.05) translate3d(${x * -18}px, ${y * -14}px, 0)`;
    });
    canvas?.addEventListener('mouseleave', () => {
      hero.style.transform = 'scale(1)';
    });
  }
})();
