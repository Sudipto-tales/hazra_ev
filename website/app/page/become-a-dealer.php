<?php
App::render('head', [
    'pageTitle'       => 'Become a Dealer | Hazra Electrical Bike',
    'pageDescription' => 'Join the Hazra dealer network. Apply online and grow with electric mobility across India.',
    'extraCss'        => ['assets/css/styles/pages/become-a-dealer.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- HERO -->
<section class="bd-hero">
  <div class="bd-hero__grid wrap">
    <div class="bd-hero__copy">
      <div class="bd-pills">
        <span class="bd-pill bd-pill--live"><i class="bd-dot"></i> Partner programme open</span>
        <span class="bd-pill">Pan-India network</span>
      </div>
      <h1 class="bd-hero__title">Power the next<br><span class="bd-glow">kilometre</span><br>with Hazra.</h1>
      <p class="bd-hero__lead">
        Build a dealership that sells honest range, swappable energy and service that stays.
        Apply online — our channel team reviews every application.
      </p>
      <div class="bd-hero__cta">
        <a class="bd-btn bd-btn--primary" href="#apply">Become a Dealer <i data-lucide="arrow-right"></i></a>
        <a class="bd-btn bd-btn--ghost" href="<?= e(base_url('dealer-locator')) ?>">Explore live network</a>
      </div>
    </div>
    <div class="bd-hero__visual" id="heroVisual" aria-hidden="true">
      <div class="bd-reel bd-reel--up" id="reelUp"></div>
      <div class="bd-reel bd-reel--down" id="reelDown"></div>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section class="bd-how" id="how">
  <div class="wrap">
    <header class="bd-head">
      <p class="eyebrow"><i class="sq"></i>APPLY · ONBOARD · GROW</p>
      <h2 class="sec__title">Three steps to<br><span class="hl">your showroom</span></h2>
    </header>
    <div class="bd-timeline" aria-hidden="true">
      <svg class="bd-circuit" viewBox="0 0 900 8" preserveAspectRatio="none">
        <line x1="0" y1="4" x2="900" y2="4" class="bd-circuit__track"/>
        <line x1="0" y1="4" x2="900" y2="4" class="bd-circuit__pulse"/>
      </svg>
    </div>
    <div class="bd-how__grid">
      <article class="bd-card" data-tilt>
        <span class="bd-card__n">01</span>
        <div class="bd-card__icon"><i data-lucide="file-pen"></i></div>
        <h3>Submit application</h3>
        <p>Share city, space, investment band and experience. We respond after a structured review.</p>
      </article>
      <article class="bd-card" data-tilt>
        <span class="bd-card__n">02</span>
        <div class="bd-card__icon"><i data-lucide="handshake"></i></div>
        <h3>Channel discussion</h3>
        <p>Territory fit, brand standards, training path and commercial terms with the regional team.</p>
      </article>
      <article class="bd-card" data-tilt>
        <span class="bd-card__n">03</span>
        <div class="bd-card__icon"><i data-lucide="store"></i></div>
        <h3>Launch &amp; support</h3>
        <p>Fit-out guidance, product training, launch stock and ongoing marketing + service support.</p>
      </article>
    </div>
    <div class="bd-vanish-wrap">
      <p class="bd-vanish" id="vanishText"></p>
      <div class="bd-vanish-dots" id="vanishDots"></div>
    </div>
  </div>
</section>

<!-- BENEFITS / WHY -->
<section class="bd-why" id="benefits">
  <div class="wrap">
    <header class="bd-head">
      <p class="eyebrow"><i class="sq"></i>WHY HAZRA</p>
      <h2 class="sec__title">Partner benefits<br><span class="hl">that hold</span></h2>
    </header>
    <div class="bd-why__grid" id="benefitGrid">
      <article class="bd-perk" data-glow>
        <div class="bd-perk__icon"><i data-lucide="badge-percent"></i></div>
        <h3>Clear margins</h3>
        <p>Transparent dealer pricing and structured incentive ladders on volume.</p>
      </article>
      <article class="bd-perk" data-glow>
        <div class="bd-perk__icon"><i data-lucide="wrench"></i></div>
        <h3>Service backbone</h3>
        <p>Training, parts pipeline and warranty process designed for real workshops.</p>
      </article>
      <article class="bd-perk" data-glow>
        <div class="bd-perk__icon"><i data-lucide="megaphone"></i></div>
        <h3>Local marketing</h3>
        <p>Launch kits, digital assets and co-branded campaigns for your territory.</p>
      </article>
      <article class="bd-perk" data-glow>
        <div class="bd-perk__icon"><i data-lucide="battery-charging"></i></div>
        <h3>Product edge</h3>
        <p>Swappable packs, measured range claims and models tuned for Indian cities.</p>
      </article>
    </div>
  </div>
</section>

<!-- APPLICATION FORM -->
<section class="bd-apply" id="apply">
  <div class="wrap bd-apply__layout">
    <div class="bd-apply__side">
      <p class="eyebrow"><i class="sq"></i>DEALER APPLICATION</p>
      <h2 class="sec__title">Plug in your<br><span class="hl">partnership</span></h2>
      <p class="bd-apply__lead">Complete the form. Our channel desk reviews applications in order of territory priority.</p>
      <ul class="bd-apply__notes">
        <li><i data-lucide="map-pin"></i> Preferred: cities with service-ready space</li>
        <li><i data-lucide="building-2"></i> Showroom / workshop footprint required</li>
        <li><i data-lucide="clock"></i> Typical first response within 7–10 working days</li>
      </ul>
    </div>

    <form class="bd-dash" id="dealerForm" novalidate>
      <div class="bd-dash__glow" aria-hidden="true"></div>
      <div class="bd-field">
        <input type="text" name="fullName" id="dName" required placeholder=" ">
        <label for="dName">Full name</label>
      </div>
      <div class="bd-field-row">
        <div class="bd-field">
          <input type="tel" name="phone" id="dPhone" required pattern="[0-9]{10}" placeholder=" " inputmode="numeric">
          <label for="dPhone">Mobile number</label>
        </div>
        <div class="bd-field">
          <input type="email" name="email" id="dEmail" required placeholder=" ">
          <label for="dEmail">Email</label>
        </div>
      </div>
      <div class="bd-field-row">
        <div class="bd-field">
          <input type="text" name="city" id="dCity" required placeholder=" ">
          <label for="dCity">City / town</label>
        </div>
        <div class="bd-field">
          <select name="state" id="dState" required>
            <option value="" disabled selected></option>
            <option>West Bengal</option><option>Bihar</option><option>Jharkhand</option>
            <option>Odisha</option><option>Assam</option><option>Uttar Pradesh</option>
            <option>Maharashtra</option><option>Delhi</option><option>Karnataka</option>
            <option>Other</option>
          </select>
          <label for="dState">State</label>
        </div>
      </div>
      <div class="bd-field">
        <select name="investment" id="dInvest" required>
          <option value="" disabled selected></option>
          <option>Under ₹15 lakh</option>
          <option>₹15–30 lakh</option>
          <option>₹30–50 lakh</option>
          <option>₹50 lakh+</option>
        </select>
        <label for="dInvest">Investment band</label>
      </div>
      <div class="bd-field">
        <select name="space" id="dSpace" required>
          <option value="" disabled selected></option>
          <option>Under 500 sq ft</option>
          <option>500–1000 sq ft</option>
          <option>1000–2000 sq ft</option>
          <option>2000+ sq ft</option>
        </select>
        <label for="dSpace">Showroom / workshop space</label>
      </div>
      <div class="bd-field">
        <select name="experience" id="dExp" required>
          <option value="" disabled selected></option>
          <option>New to auto retail</option>
          <option>2-wheeler dealership experience</option>
          <option>EV dealership experience</option>
          <option>Multi-brand / multi-location</option>
        </select>
        <label for="dExp">Experience</label>
      </div>
      <div class="bd-field">
        <textarea name="message" id="dMsg" rows="3" placeholder=" "></textarea>
        <label for="dMsg">Notes (optional)</label>
      </div>
      <label class="bd-consent">
        <input type="checkbox" name="consent" required>
        <span>I agree to be contacted about dealership opportunities and accept the partner terms.</span>
      </label>
      <button type="submit" class="bd-btn bd-btn--primary bd-submit" id="submitBtn">
        <span class="bd-submit__label">Submit application</span>
        <span class="bd-submit__bar" aria-hidden="true"></span>
      </button>
    </form>
  </div>
</section>

<!-- NETWORK RIBBON -->
<section class="bd-network" id="network">
  <div class="wrap">
    <header class="bd-head bd-head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>LIVE NETWORK</p>
        <h2 class="sec__title">Dealers already<br><span class="hl">on the map</span></h2>
      </div>
      <a class="bd-btn bd-btn--ghost" href="<?= e(base_url('dealer-locator')) ?>">Open locator</a>
    </header>
  </div>
  <div class="bd-ribbon">
    <div class="bd-ribbon__track" id="ribbonTrack"></div>
  </div>
</section>

<!-- INVESTMENT TIERS -->
<section class="bd-tiers" id="tiers">
  <div class="wrap">
    <header class="bd-head">
      <p class="eyebrow"><i class="sq"></i>PARTNERSHIP LEVELS</p>
      <h2 class="sec__title">Choose the scale<br><span class="hl">that fits</span></h2>
    </header>
    <div class="bd-podium" id="podium">
      <div class="bd-pod bd-pod--2">
        <span class="bd-pod__tag">Studio</span>
        <h3>City Studio</h3>
        <p class="bd-pod__price">From ₹15L</p>
        <ul>
          <li>Compact showroom</li>
          <li>Core model lineup</li>
          <li>Digital lead support</li>
        </ul>
      </div>
      <div class="bd-pod bd-pod--1">
        <span class="bd-pod__tag">Flagship</span>
        <h3>Full Dealership</h3>
        <p class="bd-pod__price">From ₹30L</p>
        <ul>
          <li>Showroom + workshop</li>
          <li>Full product range</li>
          <li>Priority stock &amp; training</li>
        </ul>
      </div>
      <div class="bd-pod bd-pod--3">
        <span class="bd-pod__tag">Hub</span>
        <h3>Regional Hub</h3>
        <p class="bd-pod__price">Custom</p>
        <ul>
          <li>Multi-point territory</li>
          <li>Service excellence centre</li>
          <li>Co-marketing budget</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- Success modal -->
<div class="bd-modal" id="successModal" hidden>
  <div class="bd-modal__panel" role="dialog" aria-modal="true" aria-labelledby="okTitle">
    <button type="button" class="bd-modal__x" id="modalClose" aria-label="Close"><i data-lucide="x"></i></button>
    <div class="bd-modal__badge"><i data-lucide="circle-check"></i></div>
    <h3 id="okTitle">Application received</h3>
    <p>Our channel team will review your details and contact you on the mobile or email you provided.</p>
    <button type="button" class="bd-btn bd-btn--primary" id="modalOk">Continue</button>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseUrl = <?= json_encode(rtrim(base_url(''), '/')) ?>;

  const SHOTS = [
    { title: 'Kolkata hub', img: baseUrl + '/assets/scutie_light.webp' },
    { title: 'Service bay', img: baseUrl + '/assets/dark_scutie.webp' },
    { title: 'City studio', img: baseUrl + '/assets/storm.webp' },
    { title: 'Launch floor', img: baseUrl + '/assets/scutie_light.webp' },
    { title: 'Workshop', img: baseUrl + '/assets/dark_scutie.webp' },
    { title: 'Display line', img: baseUrl + '/assets/storm.webp' },
  ];

  const card = (s) => `<article class="bd-vcard"><div class="bd-vcard__media"><img src="${s.img}" alt="" loading="lazy"><span>${s.title}</span></div></article>`;
  const fill = (el, list) => { if (el) el.innerHTML = list.concat(list).map(card).join(''); };
  fill($('#reelUp'), SHOTS);
  fill($('#reelDown'), [...SHOTS].reverse());

  const vis = $('#heroVisual');
  vis?.addEventListener('mouseenter', () => vis.classList.add('is-paused'));
  vis?.addEventListener('mouseleave', () => vis.classList.remove('is-paused'));

  const track = $('#ribbonTrack');
  if (track) {
    const trip = SHOTS.concat(SHOTS).concat(SHOTS);
    track.innerHTML = trip.map(s => `
      <div class="bd-rib-card">
        <img src="${s.img}" alt="" loading="lazy">
        <span>${s.title}</span>
      </div>`).join('');
  }

  const lines = [
    'Tell us your city and space.',
    'We match territory and standards.',
    'Train, stock, launch — then grow with the brand.'
  ];
  let vi = 0;
  const vt = $('#vanishText'), vd = $('#vanishDots');
  if (vd) vd.innerHTML = lines.map((_, i) => `<button type="button" data-i="${i}" class="${i ? '' : 'is-on'}"></button>`).join('');
  const show = (i) => {
    if (!vt) return;
    vt.classList.add('out');
    setTimeout(() => {
      vi = i; vt.textContent = lines[i]; vt.classList.remove('out'); vt.classList.add('in');
      $$('#vanishDots button').forEach((b, n) => b.classList.toggle('is-on', n === i));
    }, 280);
  };
  show(0);
  setInterval(() => show((vi + 1) % lines.length), 3200);
  vd?.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) show(+b.dataset.i); });

  const form = $('#dealerForm'), modal = $('#successModal');
  form?.addEventListener('submit', e => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const btn = $('#submitBtn');
    btn.classList.add('is-charging'); btn.disabled = true;
    setTimeout(() => {
      btn.classList.remove('is-charging'); btn.disabled = false;
      form.reset();
      modal.hidden = false;
      document.body.style.overflow = 'hidden';
    }, 1100);
  });
  const closeM = () => { modal.hidden = true; document.body.style.overflow = ''; };
  $('#modalClose')?.addEventListener('click', closeM);
  $('#modalOk')?.addEventListener('click', closeM);
  modal?.addEventListener('click', e => { if (e.target === modal) closeM(); });

  $$('[data-glow]').forEach(card => {
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
      card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
    });
  });
  $$('[data-tilt]').forEach(card => {
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - .5;
      const y = (e.clientY - r.top) / r.height - .5;
      card.style.transform = `perspective(900px) rotateY(${x * 5}deg) rotateX(${-y * 5}deg)`;
    });
    card.addEventListener('pointerleave', () => { card.style.transform = ''; });
  });

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (window.gsap && window.ScrollTrigger && !reduceMotion) {
    gsap.registerPlugin(ScrollTrigger);
    gsap.from('.bd-hero__copy > *', { opacity: 0, y: 28, duration: 0.7, stagger: 0.08, ease: 'power2.out', clearProps: 'all' });
    gsap.from('.bd-hero__visual', { opacity: 0, x: 40, duration: 0.9, delay: 0.15, ease: 'power2.out', clearProps: 'all' });
    gsap.utils.toArray('.bd-head').forEach((el) => {
      gsap.from(el, { scrollTrigger: { trigger: el, start: 'top 85%', toggleActions: 'play none none none' }, opacity: 0, y: 30, duration: 0.65, ease: 'power2.out', clearProps: 'all' });
    });
    gsap.from('.bd-how__grid .bd-card', { scrollTrigger: { trigger: '.bd-how__grid', start: 'top 80%', toggleActions: 'play none none none' }, opacity: 0, y: 36, duration: 0.6, stagger: 0.12, ease: 'power2.out', clearProps: 'all' });
    gsap.from('.bd-why__grid .bd-perk', { scrollTrigger: { trigger: '.bd-why__grid', start: 'top 80%', toggleActions: 'play none none none' }, opacity: 0, y: 32, duration: 0.55, stagger: 0.1, ease: 'power2.out', clearProps: 'all' });
    gsap.from('.bd-apply__side', { scrollTrigger: { trigger: '.bd-apply', start: 'top 75%', toggleActions: 'play none none none' }, opacity: 0, x: -28, duration: 0.7, ease: 'power2.out', clearProps: 'all' });
    gsap.from('.bd-dash', { scrollTrigger: { trigger: '.bd-apply', start: 'top 75%', toggleActions: 'play none none none' }, opacity: 0, x: 28, duration: 0.7, ease: 'power2.out', clearProps: 'all' });
    gsap.from('.bd-podium .bd-pod', { scrollTrigger: { trigger: '.bd-podium', start: 'top 80%', toggleActions: 'play none none none' }, opacity: 0, y: 40, duration: 0.65, stagger: 0.14, ease: 'power2.out', clearProps: 'all' });
  }

  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
