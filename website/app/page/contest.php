<?php
App::render('head', [
    'pageTitle'       => 'Reels Contest — Ride, Share & Win | Hazra',
    'pageDescription' => 'Post your Hazra reel, submit your entry, climb the leaderboard. Ride, Share & Win.',
    'extraCss'        => ['assets/css/styles/pages/contest.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- HERO -->
<section class="ct-hero">
  <div class="ct-hero__grid wrap">
    <div class="ct-hero__copy">
      <div class="ct-badges">
        <span class="ct-badge ct-badge--grad">Live contest</span>
        <span class="ct-badge">Weekly prizes</span>
        <span class="ct-badge">Open to all riders</span>
      </div>
      <h1 class="ct-hero__title">Ride, Share<br>&amp; <em>Win</em></h1>
      <p class="ct-hero__lead">
        Film your Hazra moment. Post the reel. Submit the link.
        Climb the leaderboard — top rides win gear, service credits and brand shout-outs.
      </p>
      <div class="ct-hero__actions">
        <a class="btn btn--ink ct-btn-primary" href="#enter">
          <span>Participate Now</span><i data-lucide="arrow-down"></i>
        </a>
        <a class="btn btn--ghost" href="#leaderboard">View Leaderboard</a>
      </div>
      <ul class="ct-hero__steps-inline">
        <li><i data-lucide="video"></i> Shoot</li>
        <li><i data-lucide="share-2"></i> Post</li>
        <li><i data-lucide="send"></i> Submit</li>
        <li><i data-lucide="trophy"></i> Rank</li>
      </ul>
    </div>

    <div class="ct-hero__reel" aria-hidden="true">
      <div class="ct-reel ct-reel--up" id="reelColA"></div>
      <div class="ct-reel ct-reel--down" id="reelColB"></div>
    </div>
  </div>
</section>

<!-- HOW TO CREATE & SUBMIT -->
<section class="ct-process" id="how">
  <div class="wrap">
    <header class="ct-sec-head">
      <p class="eyebrow"><i class="sq"></i>HOW IT WORKS</p>
      <h2 class="sec__title">How to create &amp;<br><span class="hl">submit your video</span></h2>
    </header>

    <div class="ct-process__stage">
      <p class="ct-vanish" id="vanishText" aria-live="polite"></p>
      <div class="ct-process__dots" id="vanishDots" role="tablist"></div>
    </div>

    <div class="ct-process__cards">
      <article class="ct-step-card" style="--i:0">
        <span class="ct-step-card__n">01</span>
        <div class="ct-step-card__icon"><i data-lucide="smartphone"></i></div>
        <h3>Film your ride</h3>
        <p>Vertical 9:16, 15–60 seconds. Show the scooter, the road, and your style — morning commute or weekend spin.</p>
      </article>
      <article class="ct-step-card" style="--i:1">
        <span class="ct-step-card__n">02</span>
        <div class="ct-step-card__icon"><i data-lucide="instagram"></i></div>
        <h3>Post on social</h3>
        <p>Upload as Instagram Reel, YouTube Short or Facebook video. Use hashtag <strong>#HazraRide</strong> and tag the brand.</p>
      </article>
      <article class="ct-step-card" style="--i:2">
        <span class="ct-step-card__n">03</span>
        <div class="ct-step-card__icon"><i data-lucide="link"></i></div>
        <h3>Submit the link</h3>
        <p>Paste the public URL below with your details. Optional: attach a screenshot if the link is private.</p>
      </article>
      <article class="ct-step-card" style="--i:3">
        <span class="ct-step-card__n">04</span>
        <div class="ct-step-card__icon"><i data-lucide="badge-check"></i></div>
        <h3>Get approved</h3>
        <p>We review every entry. Approved videos join the showcase and leaderboard — then engagement counts toward rank.</p>
      </article>
    </div>
  </div>
</section>

<!-- SUBMISSION FORM -->
<section class="ct-enter" id="enter">
  <div class="wrap ct-enter__grid">
    <div class="ct-enter__copy">
      <p class="eyebrow"><i class="sq"></i>ENTER THE CONTEST</p>
      <h2 class="sec__title">Submit your<br><span class="hl">Hazra reel</span></h2>
      <p class="ct-enter__lead">One entry per rider per week. Links must be public. Admin approval required before you appear on the board.</p>
      <ul class="ct-enter__tips">
        <li><i data-lucide="check"></i> Vertical video preferred</li>
        <li><i data-lucide="check"></i> Hazra scooter clearly visible</li>
        <li><i data-lucide="check"></i> Safe riding only — no stunts on public roads</li>
      </ul>
    </div>

    <form class="ct-form" id="contestForm" novalidate>
      <fieldset>
        <legend>Participant</legend>
        <div class="ct-form__row">
          <label>Full name<input type="text" name="fullName" required placeholder="As on invoice / ID" autocomplete="name"></label>
          <label>Phone<input type="tel" name="phone" required placeholder="10-digit mobile" pattern="[0-9]{10}" inputmode="numeric"></label>
        </div>
        <label>Email<input type="email" name="email" required placeholder="you@email.com" autocomplete="email"></label>
      </fieldset>

      <fieldset>
        <legend>Bike info</legend>
        <div class="ct-form__row">
          <label>Bike model
            <select name="bikeModel" required>
              <option value="">Select model</option>
              <option>CHALO 1000 V2</option>
              <option>CHALO SMART PRO</option>
              <option>CHALO SMART PLUS</option>
              <option>CHALO SMART ECO</option>
              <option>CHALO NEO</option>
              <option>NJA-7</option>
              <option>Other Hazra</option>
            </select>
          </label>
          <label>Reg. / chassis / purchase ref.
            <input type="text" name="bikeRef" placeholder="Optional but preferred">
          </label>
        </div>
      </fieldset>

      <fieldset>
        <legend>Social submission</legend>
        <div class="ct-form__row">
          <label>Platform
            <select name="platform" required>
              <option value="">Select</option>
              <option value="instagram">Instagram Reel</option>
              <option value="youtube">YouTube Short</option>
              <option value="facebook">Facebook Video</option>
            </select>
          </label>
          <label>Video URL
            <input type="url" name="videoUrl" required placeholder="https://…">
          </label>
        </div>
        <label class="ct-form__file">Optional media / screenshot
          <input type="file" name="media" accept="video/*,image/jpeg,image/png,image/webp">
          <span>Video or confirmation screenshot · max 25MB</span>
        </label>
      </fieldset>

      <label class="ct-form__check">
        <input type="checkbox" name="consent" required>
        <span>I agree to the contest <a href="#" target="_blank">Terms &amp; Conditions</a> and grant Hazra rights to feature my media in marketing.</span>
      </label>

      <button type="submit" class="btn btn--ink ct-form__submit">
        <span>Submit entry</span><i data-lucide="send"></i>
      </button>
      <p class="ct-form__note">You’ll see a confirmation when the entry is received. Status: pending admin review.</p>
    </form>
  </div>
</section>

<!-- APPROVED SHOWCASE -->
<section class="ct-showcase" id="showcase">
  <div class="wrap">
    <header class="ct-sec-head ct-sec-head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>APPROVED ENTRIES</p>
        <h2 class="sec__title">Live on the<br><span class="hl">showcase</span></h2>
      </div>
      <p class="ct-sec-note">Only approved reels appear here.</p>
    </header>
  </div>
  <div class="ct-marquee" aria-label="Approved video showcase">
    <div class="ct-marquee__track" id="showcaseTrack">
      <!-- cards injected -->
    </div>
  </div>
</section>

<!-- LEADERBOARD -->
<section class="ct-board" id="leaderboard">
  <div class="wrap">
    <header class="ct-sec-head">
      <p class="eyebrow"><i class="sq"></i>LEADERBOARD</p>
      <h2 class="sec__title">Top rides<br><span class="hl">this week</span></h2>
    </header>
    <div class="ct-board__table" id="leaderboardTable"></div>
  </div>
</section>

<!-- Success modal -->
<div class="ct-modal" id="successModal" hidden>
  <div class="ct-modal__card" role="dialog" aria-modal="true" aria-labelledby="successTitle">
    <button type="button" class="ct-modal__close" id="modalClose" aria-label="Close"><i data-lucide="x"></i></button>
    <div class="ct-modal__icon"><i data-lucide="circle-check"></i></div>
    <h3 id="successTitle">Submitted successfully!</h3>
    <p>Your entry is <strong>pending admin review</strong>. Once approved, it will appear in the showcase and leaderboard.</p>
    <button type="button" class="btn btn--ink" id="modalOk">Got it</button>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const baseUrl = <?= json_encode(rtrim(base_url(''), '/')) ?>;

  const REELS = [
    { name: 'Aarav', model: 'CHALO 1000 V2', likes: '1.2K', img: baseUrl + '/assets/scutie_light.webp', url: '#' },
    { name: 'Priya', model: 'CHALO Smart Pro', likes: '989', img: baseUrl + '/assets/dark_scutie.webp', url: '#' },
    { name: 'Kabir', model: 'CHALO Smart Eco', likes: '2.1K', img: baseUrl + '/assets/storm.webp', url: '#' },
    { name: 'Ananya', model: 'CHALO Neo', likes: '756', img: baseUrl + '/assets/scutie_light.webp', url: '#' },
    { name: 'Rohan', model: 'NJA-7', likes: '3.4K', img: baseUrl + '/assets/dark_scutie.webp', url: '#' },
    { name: 'Meera', model: 'CHALO Smart Plus', likes: '512', img: baseUrl + '/assets/storm.webp', url: '#' },
  ];

  function reelCard(r, i) {
    return `<article class="ct-vcard" style="--d:${i * 0.15}s">
      <div class="ct-vcard__media">
        <img src="${r.img}" alt="${r.name} reel" loading="lazy">
        <button type="button" class="ct-vcard__play" aria-label="Play"><i data-lucide="play"></i></button>
        <span class="ct-vcard__tag"><i data-lucide="heart"></i> ${r.likes}</span>
      </div>
      <div class="ct-vcard__meta">
        <b>${r.name}</b>
        <span>${r.model}</span>
      </div>
    </article>`;
  }

  function fillReel(el, reverse) {
    if (!el) return;
    const items = reverse ? [...REELS].reverse() : REELS;
    const html = items.concat(items).map((r, i) => reelCard(r, i)).join('');
    el.innerHTML = html;
  }
  fillReel($('#reelColA'), false);
  fillReel($('#reelColB'), true);

  const track = $('#showcaseTrack');
  if (track) {
    const doubled = REELS.concat(REELS).concat(REELS);
    track.innerHTML = doubled.map((r) => `
      <a class="ct-show-card" href="${r.url}" target="_blank" rel="noopener">
        <img src="${r.img}" alt="" loading="lazy">
        <span class="ct-show-card__play"><i data-lucide="play"></i></span>
        <span class="ct-show-card__cap"><b>${r.name}</b> · ${r.model}</span>
      </a>`).join('');
  }

  const board = [...REELS].sort((a, b) => parseFloat(b.likes) - parseFloat(a.likes));
  const table = $('#leaderboardTable');
  if (table) {
    table.innerHTML = board.map((r, i) => `
      <div class="ct-rank ${i < 3 ? 'ct-rank--top' : ''}">
        <span class="ct-rank__n">${i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '#' + (i + 1)}</span>
        <img src="${r.img}" alt="">
        <div class="ct-rank__info">
          <b>${r.name}</b>
          <span>${r.model}</span>
        </div>
        <div class="ct-rank__score"><i data-lucide="heart"></i> ${r.likes}</div>
        <a class="ct-rank__link" href="${r.url}" target="_blank" rel="noopener">View <i data-lucide="external-link"></i></a>
      </div>`).join('');
  }

  const lines = [
    'Open your camera. Go vertical.',
    'Capture the scooter in motion — city lights, open road, your rhythm.',
    'Post as a Reel or Short with #HazraRide.',
    'Copy the link. Paste it in the form. Hit submit.',
    'We review. You rank. Riders win.'
  ];
  const vanishEl = $('#vanishText');
  const dots = $('#vanishDots');
  let vi = 0;
  if (dots) {
    dots.innerHTML = lines.map((_, i) =>
      `<button type="button" class="${i === 0 ? 'is-on' : ''}" data-i="${i}" aria-label="Step ${i + 1}"></button>`
    ).join('');
  }
  function showLine(i) {
    if (!vanishEl) return;
    vanishEl.classList.remove('is-in');
    vanishEl.classList.add('is-out');
    setTimeout(() => {
      vi = i;
      vanishEl.textContent = lines[i];
      vanishEl.classList.remove('is-out');
      vanishEl.classList.add('is-in');
      $$('#vanishDots button').forEach((b, n) => b.classList.toggle('is-on', n === i));
    }, 320);
  }
  showLine(0);
  setInterval(() => showLine((vi + 1) % lines.length), 3200);
  dots?.addEventListener('click', e => {
    const b = e.target.closest('button[data-i]');
    if (b) showLine(Number(b.dataset.i));
  });

  const form = $('#contestForm');
  const modal = $('#successModal');
  form?.addEventListener('submit', e => {
    e.preventDefault();
    if (!form.checkValidity()) {
      form.reportValidity();
      form.querySelector(':invalid')?.focus();
      return;
    }
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    form.reset();
  });
  const closeModal = () => {
    modal.hidden = true;
    document.body.style.overflow = '';
  };
  $('#modalClose')?.addEventListener('click', closeModal);
  $('#modalOk')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
