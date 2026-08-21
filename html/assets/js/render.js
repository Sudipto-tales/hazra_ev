/* ============================================================
   Hazra EV — markup builders driven by HAZRA.PRODUCTS
   Runs as a deferred script, before the chrome mounts.
   ============================================================ */
(function () {
  'use strict';
  const { U, IMG, PRODUCTS, rupee } = window.HAZRA;
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  const stars = r => {
    const full = Math.floor(r);
    return Array.from({ length: 5 }, (_, i) =>
      `<span class="material-symbols-outlined" style="${i < full ? '' : 'opacity:.28'}">star</span>`).join('');
  };

  /* ---------- product card ---------- */
  window.productCard = (p, i = 0) => `
  <article class="product sheen" data-tags="${p.tag.toLowerCase().replace(' ', '-')} all"
           data-reveal="up" data-delay="${(i % 3) * 90}">
    <div class="product__media">
      <img src="${U(p.img[0], 800)}" alt="${p.name}" loading="lazy">
      <div class="product__flags">
        <span class="badge badge--dark"><span class="material-symbols-outlined"
          style="font-size:14px">verified_user</span>4-Year Warranty</span>
        <span class="badge badge--${p.tag === 'High Speed' ? 'amber' : 'eco'}">${p.tag}</span>
      </div>
      ${p.spin ? `<a class="product__360" href="index.html#viewer" title="360° view">360°</a>` : ''}
    </div>
    <div class="product__body">
      <div class="product__title">
        <div><h3>${p.name}</h3><small>${p.motor} · ${p.battery}</small></div>
        <span class="rating">${stars(p.rating)} ${p.rating}</span>
      </div>
      <div class="spec-row">
        <span class="spec-pill"><span class="material-symbols-outlined">route</span>${p.range}</span>
        <span class="spec-pill"><span class="material-symbols-outlined">speed</span>${p.top}</span>
        <span class="spec-pill"><span class="material-symbols-outlined">bolt</span>${p.charge}</span>
      </div>
      <div class="swatches" data-swatches="self">
        <small>Colours</small>
        ${p.colors.map(([n, hex], k) => `<button class="swatch${k ? '' : ' is-active'}"
           style="background:${hex}" title="${n}" aria-label="${n}"
           data-img="${U(p.img[k % p.img.length], 800)}"></button>`).join('')}
      </div>
      <div class="price-block">
        <span class="price-block__label">Ex-showroom · starts at</span>
        <div class="price-grid">
          <div><small>Graphene battery</small><b>${rupee(p.graphene)}</b></div>
          <div><small>Lithium-ion</small><b>${rupee(p.lithium)}</b></div>
        </div>
        <div class="product__actions">
          <a class="btn btn--sm" href="product.html?m=${p.slug}">Explore</a>
          <a class="btn btn--ghost btn--sm" href="contact.html#testride">
            <span class="material-symbols-outlined">two_wheeler</span>Test Ride</a>
        </div>
        <p class="product__note">*Prices exclude GST, insurance and state EV subsidy. Indicative only.</p>
      </div>
    </div>
  </article>`;

  /* ---------- 360 viewer frames ---------- */
  window.spinnerFrames = () =>
    IMG.spin.map((id, i) => `<div class="viewer__frame${i ? '' : ' is-on'}">
      <img src="${U(id, 900)}" alt="Hazra Urja ECO view ${i + 1}" loading="${i ? 'lazy' : 'eager'}"></div>`).join('');

  /* ---------- hero slides ---------- */
  window.heroSlides = () =>
    IMG.hero.map((id, i) => `<div class="hero__slide${i ? '' : ' is-active'}">
      <img src="${U(id, 1800)}" alt="" loading="${i ? 'lazy' : 'eager'}"></div>`).join('');
  window.heroDots = () =>
    IMG.hero.map((_, i) => `<button class="hero__dot${i ? '' : ' is-active'}"
      aria-label="Slide ${i + 1}"></button>`).join('');

  /* ---------- mount points ---------- */
  const put = (sel, html) => { const n = $(sel); if (n) n.innerHTML = html; };

  put('#productGrid', PRODUCTS.map(window.productCard).join(''));
  put('#viewerFrames', typeof spinnerFrames === 'function' ? spinnerFrames() : '');
  put('#heroSlides', window.heroSlides());
  put('#heroDots', window.heroDots());

  /* products page: also the compare table */
  const cmp = $('#compareBody');
  if (cmp) cmp.innerHTML = PRODUCTS.map(p => `<tr>
      <td>${p.name}</td><td><span class="badge badge--${p.tag === 'High Speed' ? 'amber' : 'eco'}">${p.tag}</span></td>
      <td>${p.range}</td><td>${p.top}</td><td>${p.charge}</td><td>${p.motor}</td>
      <td>${p.battery}</td><td><b>${rupee(p.graphene)}</b></td></tr>`).join('');

  /* product detail page */
  const detail = $('#productDetail');
  if (detail) {
    const slug = new URLSearchParams(location.search).get('m');
    const p = PRODUCTS.find(x => x.slug === slug) || PRODUCTS[0];
    document.title = `${p.name} — Hazra EV`;
    const specs = [
      ['Motor', p.motor], ['Battery', p.battery], ['Certified range', p.range],
      ['Top speed', p.top], ['Charging time', p.charge], ['Brakes', 'Combi disc / drum'],
      ['Suspension', 'Telescopic front, dual rear'], ['Kerb weight', p.slug === 'cargo-7' ? '96 kg' : '84 kg'],
      ['Payload', p.slug === 'cargo-7' ? '150 kg' : '150 kg'], ['Tyres', 'Tubeless 90/90-12'],
      ['Charger', '5 A smart charger, auto cut-off'], ['Warranty', '4 years / 60,000 km']
    ];
    detail.innerHTML = `
      <div class="split">
        <div data-reveal="left">
          <div class="split__media">
            <img id="detailImg" src="${U(p.img[0], 1100)}" alt="${p.name}" style="transition:opacity .2s ease">
          </div>
          <div class="swatches" data-swatches="#detailImg" style="margin-top:18px">
            <small>Choose colour</small>
            ${p.colors.map(([n, hex], k) => `<button class="swatch${k ? '' : ' is-active'}"
               style="background:${hex}" title="${n}" aria-label="${n}"
               data-img="${U(p.img[k % p.img.length], 1100)}"></button>`).join('')}
          </div>
        </div>
        <div data-reveal="right">
          <span class="badge badge--${p.tag === 'High Speed' ? 'amber' : 'eco'}">${p.tag}</span>
          <h1 style="margin:14px 0 10px">${p.name}</h1>
          <span class="rating">${stars(p.rating)} ${p.rating} · ${p.reviews} owner reviews</span>
          <p class="lead" style="margin-top:16px">${p.blurb}</p>
          <div class="spec-row" style="margin-bottom:22px">
            <span class="spec-pill"><span class="material-symbols-outlined">route</span>${p.range} range</span>
            <span class="spec-pill"><span class="material-symbols-outlined">speed</span>${p.top}</span>
            <span class="spec-pill"><span class="material-symbols-outlined">bolt</span>${p.charge} charge</span>
            <span class="spec-pill"><span class="material-symbols-outlined">verified_user</span>4-yr warranty</span>
          </div>
          <div class="card" style="padding:22px;margin-bottom:22px">
            <span class="price-block__label">Ex-showroom · Kolkata</span>
            <div class="price-grid" style="margin-top:10px">
              <div><small>Graphene battery</small><b>${rupee(p.graphene)}</b></div>
              <div><small>Lithium-ion</small><b>${rupee(p.lithium)}</b></div>
            </div>
            <p class="product__note" style="margin-top:12px">
              EMI from <b>${rupee(Math.round(p.graphene / 36))}</b>/month · 0% down payment available</p>
          </div>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <a class="btn btn--lg" href="contact.html#testride">
              <span class="material-symbols-outlined">two_wheeler</span>Book a Test Ride</a>
            <a class="btn btn--ghost btn--lg" href="dealers.html">
              <span class="material-symbols-outlined">store</span>Find a Dealer</a>
          </div>
        </div>
      </div>

      <div style="margin-top:clamp(48px,6vw,84px)" data-reveal="up">
        <div data-tabs class="tabs">
          <button class="tab is-active" data-tab="specs">Specifications</button>
          <button class="tab" data-tab="features">Features</button>
          <button class="tab" data-tab="range">Range calculator</button>
          <button class="tab" data-tab="service">Service &amp; warranty</button>
        </div>
        <div class="tab-panel is-active" data-panel="specs">
          <div class="spec-list">
            ${specs.map(([k, v]) => `<div><span>${k}</span><b>${v}</b></div>`).join('')}
          </div>
        </div>
        <div class="tab-panel" data-panel="features">
          <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
            ${[['smartphone','App connectivity','Live location, ride history and geo-fence alerts through the Hazra EV app.'],
               ['lock','Anti-theft','Motor lock, tilt alarm and remote immobiliser from your phone.'],
               ['water_drop','IP67 motor','Rides through a Kolkata monsoon without blinking.'],
               ['battery_charging_full','Smart BMS','Cell balancing, thermal cut-off and over-charge protection.'],
               ['light','Full LED','LED headlamp, DRL and sequential indicators as standard.'],
               ['settings_remote','Keyless','Remote start, find-my-scooter and reverse assist.']]
              .map(([i, t, d]) => `<div class="card card--hover">
                <div class="card__icon"><span class="material-symbols-outlined">${i}</span></div>
                <h3 style="font-size:1.05rem;margin-bottom:6px">${t}</h3>
                <p style="margin:0;color:var(--muted);font-size:.9rem">${d}</p></div>`).join('')}
          </div>
        </div>
        <div class="tab-panel" data-panel="range">
          <div class="split">
            <div>
              <h3 style="margin-bottom:12px">What will you actually get?</h3>
              <p class="lead">Certified range is measured on a flat test loop. Pick your real conditions
                 and we will show the number you should plan your week around.</p>
              <div class="field field--select"><select data-gauge-input>
                  <option data-factor="1">Single rider (65 kg)</option>
                  <option data-factor="0.9">Two riders</option>
                  <option data-factor="0.82">Two riders + luggage</option>
                </select><label>Load</label></div>
              <div class="field field--select"><select data-gauge-input>
                  <option data-factor="1">City, mostly flat</option>
                  <option data-factor="0.93">Mixed with flyovers</option>
                  <option data-factor="0.85">Hilly / heavy traffic</option>
                </select><label>Terrain</label></div>
              <div class="field field--select"><select data-gauge-input>
                  <option data-factor="1">Eco mode</option>
                  <option data-factor="0.88">City mode</option>
                  <option data-factor="0.76">Sport mode</option>
                </select><label>Ride mode</label></div>
            </div>
            <div class="card" style="text-align:center" data-gauge="${parseInt(p.range)}">
              <div class="gauge">
                <svg viewBox="0 0 200 110">
                  <defs><linearGradient id="gaugeGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#2F6BFF"/><stop offset="100%" stop-color="#16A34A"/>
                  </linearGradient></defs>
                  <path class="gauge__arc gauge__bg" d="M10,100 A90,90 0 0,1 190,100"/>
                  <path class="gauge__arc gauge__val" d="M10,100 A90,90 0 0,1 190,100"/>
                </svg>
                <div class="gauge__num"><b data-gauge-num>${parseInt(p.range)}</b><span>km expected</span></div>
              </div>
              <p style="margin-top:18px;color:var(--muted);font-size:.88rem">
                Based on ${p.battery} pack, 100% to 10% state of charge.</p>
            </div>
          </div>
        </div>
        <div class="tab-panel" data-panel="service">
          <div class="accordion" data-accordion-single>
            ${[['What does the 4-year warranty cover?',
                'Motor, controller and battery pack against manufacturing defects for 4 years or 60,000 km, whichever comes first. Consumables — tyres, brake pads, bulbs — are excluded.'],
               ['How often does it need service?',
                'First check at 500 km (free), then every 3,000 km or 6 months. A standard service takes about 90 minutes and costs ₹499 for labour.'],
               ['Can I get service at home?',
                'Yes. Doorstep service covers 18 km around any Hazra EV dealer. Book from the app or call the dealer directly.'],
               ['What if I move city?',
                'Warranty travels with the vehicle, not the dealer. Any Hazra EV service point in India can honour it once you transfer the registration in the app.']]
              .map(([q, a]) => `<div class="acc"><button class="acc__head">${q}
                <span class="material-symbols-outlined">add</span></button>
                <div class="acc__panel"><p>${a}</p></div></div>`).join('')}
          </div>
        </div>
      </div>`;
  }

  /* related products strip */
  const rel = $('#relatedProducts');
  if (rel) {
    const slug = new URLSearchParams(location.search).get('m');
    rel.innerHTML = PRODUCTS.filter(p => p.slug !== slug).slice(0, 3)
      .map((p, i) => `<div class="carousel__item">${window.productCard(p, i)}</div>`).join('');
  }
})();
