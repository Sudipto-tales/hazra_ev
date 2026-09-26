<?php
App::render('head', [
    'pageTitle'       => 'Battery Care Guide | Hazra',
    'pageDescription' => 'Six simple routines that add years to your battery pack and maximize range on your Hazra electric scooter.',
    'extraCss'        => ['assets/css/styles/pages/battery-use.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="hero">
  <h1 class="hero__title">Battery Care Guide</h1>
  <p class="hero__lead">
    Six simple routines that add years to your battery pack and maximize range.
  </p>
</section>

<section class="guide">
  <div class="guide__in">
    <div class="steps">
      <div class="step" style="--i: 0">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="plug"></i>
          </div>
          <div class="step__num">Step 1</div>
          <h3 class="step__title">Charge Smart</h3>
          <p class="step__desc">Charge between 20-80% for daily use. Avoid frequent full charges. Use the charger provided—it's matched to your battery.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Smart Charging">
        </div>
      </div>

      <div class="step" style="--i: 1">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="thermometer"></i>
          </div>
          <div class="step__num">Step 2</div>
          <h3 class="step__title">Mind the Temperature</h3>
          <p class="step__desc">Charge in cool, dry environments. Avoid hot and humid places. If your scooter gets too hot during riding, let it cool before charging.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Temperature Control">
        </div>
      </div>

      <div class="step" style="--i: 2">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="calendar"></i>
          </div>
          <div class="step__num">Step 3</div>
          <h3 class="step__title">Regular Use</h3>
          <p class="step__desc">Ride regularly. Batteries thrive on regular use. If unused for over a month, charge to 50% and store in a cool place.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Regular Usage">
        </div>
      </div>

      <div class="step" style="--i: 3">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="droplet"></i>
          </div>
          <div class="step__num">Step 4</div>
          <h3 class="step__title">Keep it Dry</h3>
          <p class="step__desc">Avoid waterlogging. Don't ride through deep puddles or flooded roads. Water damage voids warranty and damages battery health.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Water Protection">
        </div>
      </div>

      <div class="step" style="--i: 4">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="wrench"></i>
          </div>
          <div class="step__num">Step 5</div>
          <h3 class="step__title">Professional Service</h3>
          <p class="step__desc">Get your battery checked annually at authorized service centers. Preventive maintenance catches issues early and extends life.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Professional Service">
        </div>
      </div>

      <div class="step" style="--i: 5">
        <div class="step__body">
          <div class="step__icon">
            <i data-lucide="shield-check"></i>
          </div>
          <div class="step__num">Step 6</div>
          <h3 class="step__title">Know Your Warranty</h3>
          <p class="step__desc">Register your battery immediately after purchase. Hazra batteries come with 4-5 year warranties. Know the terms and coverage.</p>
        </div>
        <div class="step__img">
          <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Warranty">
        </div>
      </div>
    </div>
  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Follow these tips to maximize battery life and get more from every charge
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
