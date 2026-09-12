<?php
App::render('head', [
    'pageTitle'       => 'The EV Future — Electric Mobility Ahead | Hazra',
    'pageDescription' => 'Where India’s electric two-wheeler journey is heading: batteries, charging, cities and the riders who will shape the next decade.',
    'extraCss'        => ['assets/css/styles/pages/ev-future.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- HERO -->
<section class="fx-hero">
  <div class="fx-hero__bg" aria-hidden="true"></div>
  <div class="fx-hero__veil" aria-hidden="true"></div>
  <div class="fx-hero__content">
    <p class="fx-hero__eyebrow">The road ahead</p>
    <h1 class="fx-hero__title" aria-label="ELECTRIC">
      <span>E</span><span>L</span><span>E</span><span>C</span><span>T</span><span>R</span><span>I</span><span>C</span>
    </h1>
    <p class="fx-hero__lead">
      India’s two-wheeler future is already being written on city streets — quieter, cleaner, and more connected than ever.
    </p>
    <div class="fx-hero__actions">
      <a href="#explore" class="fx-btn fx-btn--primary">
        Explore the future <i data-lucide="arrow-down"></i>
      </a>
      <a href="<?= e(base_url('blog')) ?>" class="fx-btn fx-btn--ghost">
        <i data-lucide="book-open"></i> Read insights
      </a>
    </div>
  </div>
</section>

<!-- floating stats -->
<div class="fx-stats" id="explore">
  <div class="fx-stats__item">
    <strong>2.3M+</strong>
    <span>EVs in 2025</span>
  </div>
  <div class="fx-stats__item">
    <strong>30%</strong>
    <span>Target by 2030</span>
  </div>
  <div class="fx-stats__item">
    <strong>9%+</strong>
    <span>2W monthly share</span>
  </div>
  <div class="fx-stats__item">
    <strong>38%</strong>
    <span>TCO vs petrol</span>
  </div>
</div>

<!-- FEATURE ROWS -->
<section class="fx-section">
  <div class="fx-section__in">
    <div class="fx-section__head">
      <p class="eyebrow">What is changing</p>
      <h2>The next decade of electric mobility</h2>
      <p>From battery chemistry to city streets, four forces are reshaping how India moves.</p>
    </div>

    <!-- 01 Batteries -->
    <article class="fx-row">
      <div class="fx-row__media">
        <div class="fx-row__img-wrap">
          <img src="https://images.unsplash.com/photo-1593941707882-a5bba14938c7?auto=format&fit=crop&w=900&q=80" alt="Advanced EV battery technology" loading="lazy">
        </div>
        <span class="fx-row__num">01</span>
      </div>
      <div class="fx-row__copy">
        <span class="loc">Technology</span>
        <h3>Batteries get denser, safer, local</h3>
        <p>
          LFP already dominates Indian two-wheelers for cost and thermal stability. Next come LMFP cells (10–15% more energy) and sodium-ion packs that use abundant domestic materials. Solid-state remains a post-2030 story for mass volume, but pilot programmes are already running.
        </p>
        <a href="<?= e(base_url('battery-use')) ?>" class="fx-link">Battery care guide <i data-lucide="arrow-right"></i></a>
      </div>
    </article>

    <!-- 02 Charging & cities -->
    <article class="fx-row fx-row--rev">
      <div class="fx-row__media">
        <div class="fx-row__img-wrap">
          <img src="https://images.unsplash.com/photo-1617788138017-80ad40651399?auto=format&fit=crop&w=900&q=80" alt="Urban electric mobility and charging" loading="lazy">
        </div>
        <span class="fx-row__num">02</span>
      </div>
      <div class="fx-row__copy">
        <span class="loc">Infrastructure</span>
        <h3>Charging moves from rare to routine</h3>
        <p>
          Public chargers are still sparse relative to 2030 targets, yet neighbourhood battery swap points and workplace charging are closing the gap for daily riders. Standardisation of connectors and payments will decide how seamless the experience becomes.
        </p>
        <a href="<?= e(base_url('dealer-locator')) ?>" class="fx-link">Find dealers & service <i data-lucide="arrow-right"></i></a>
      </div>
    </article>

    <!-- 03 Urban riders -->
    <article class="fx-row">
      <div class="fx-row__media">
        <div class="fx-row__img-wrap">
          <img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?auto=format&fit=crop&w=900&q=80" alt="Electric scooter in Indian city traffic" loading="lazy">
        </div>
        <span class="fx-row__num">03</span>
      </div>
      <div class="fx-row__copy">
        <span class="loc">Riders</span>
        <h3>Two-wheelers lead the real transition</h3>
        <p>
          Electric scooters already deliver roughly 38% of the total cost of ownership of a petrol equivalent. With most urban trips under 30 km, range anxiety is fading. The next leap is software, connected features and residual-value confidence.
        </p>
        <a href="<?= e(base_url('blog-single')) ?>" class="fx-link">Urban mobility story <i data-lucide="arrow-right"></i></a>
      </div>
    </article>

    <!-- 04 Policy & industry -->
    <article class="fx-row fx-row--rev">
      <div class="fx-row__media">
        <div class="fx-row__img-wrap">
          <img src="https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?auto=format&fit=crop&w=900&q=80" alt="Future of clean mobility planning" loading="lazy">
        </div>
        <span class="fx-row__num">04</span>
      </div>
      <div class="fx-row__copy">
        <span class="loc">India</span>
        <h3>Policy meets local manufacturing</h3>
        <p>
          NITI Aayog’s EV30@30 ambition, PM E-DRIVE support and ACC PLI schemes are pushing domestic cell capacity. Localisation of packs, motors and BMS is accelerating; raw-material security remains the longer strategic challenge.
        </p>
        <a href="<?= e(base_url('our-story')) ?>" class="fx-link">Our story at Hazra <i data-lucide="arrow-right"></i></a>
      </div>
    </article>
  </div>
</section>

<!-- QUICK INSIGHTS -->
<section class="fx-section fx-section--alt">
  <div class="fx-section__in">
    <div class="fx-section__head">
      <p class="eyebrow">Signals</p>
      <h2>What to watch next</h2>
      <p>Three developments that will shape ownership in the next few years.</p>
    </div>
    <div class="fx-cards">
      <div class="fx-card">
        <div class="fx-card__icon"><i data-lucide="battery-charging"></i></div>
        <h4>Faster real-world range</h4>
        <p>LMFP and better thermal management are delivering usable range gains without jumping to solid-state. Expect clearer “real traffic” numbers from manufacturers.</p>
      </div>
      <div class="fx-card">
        <div class="fx-card__icon"><i data-lucide="recycle"></i></div>
        <h4>Second-life & recycling</h4>
        <p>India’s first large wave of end-of-life EV batteries arrives around 2027–28. Recovery of lithium, cobalt and nickel becomes both an environmental and strategic priority.</p>
      </div>
      <div class="fx-card">
        <div class="fx-card__icon"><i data-lucide="smartphone"></i></div>
        <h4>Connected ownership</h4>
        <p>App-based diagnostics, predictive service alerts and integrated navigation that accounts for charge stops will become expected features, not premiums.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="fx-cta">
  <h2>Ready for the electric everyday?</h2>
  <p>Explore the CHALO range, book a test ride, or find a dealer near you. The future is already on the road.</p>
  <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:12px;">
    <a href="<?= e(base_url('products')) ?>" class="fx-btn fx-btn--primary">View models</a>
    <a href="<?= e(base_url('dealer-locator')) ?>" class="fx-btn fx-btn--ghost">Locate dealers</a>
  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    The future of mobility is electric. The time is now.
  </p>
  <a class="footer__link" href="<?= e(base_url('index')) ?>">
    <span>Back to home</span>
    <i data-lucide="arrow-right"></i>
  </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
