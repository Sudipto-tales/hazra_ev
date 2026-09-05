<?php
$pageTitle = "Blog — Electric Mobility Insights | Hazra Electrical Bike";
$pageDescription = "Read the latest news, maintenance guides, and rider stories from Hazra Electrical Bike.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">HAZRA JOURNAL</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Insights & Stories
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Exploring EV technology, maintenance guides, rider journeys, and green mobility trends.
      </p>
    </div>
  </section>

  <section class="blog" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div class="blog__in" style="width: min(1200px, 93vw); margin: 0 auto;">
      <div class="blog__grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">
        
        <article style="background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 24px; padding: 28px; display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--brand-violet); margin-bottom: 12px;">EV TECH</span>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 20px; font-weight: 800; color: var(--ink-0); margin-bottom: 10px;">Why BLDC Hub Motors Excel for Daily City Commutes</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6; margin-bottom: 20px;">Direct drive efficiency, zero maintenance gearboxes, and regenerative braking advantages in Indian stop-and-go traffic.</p>
          <a href="<?= base_url('ev-future') ?>" style="margin-top: auto; font-size: 13px; font-weight: 700; color: var(--brand-violet); text-decoration: none;">Read Article &rarr;</a>
        </article>

        <article style="background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 24px; padding: 28px; display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--brand-flame); margin-bottom: 12px;">SAVINGS GUIDE</span>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 20px; font-weight: 800; color: var(--ink-0); margin-bottom: 10px;">Fuel vs Electric: 3-Year Running Cost Analysis</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6; margin-bottom: 20px;">Comparing ₹105/L petrol costs against ₹0.15 per km electric charging over a 30,000 km riding cycle.</p>
          <a href="<?= base_url('products') ?>" style="margin-top: auto; font-size: 13px; font-weight: 700; color: var(--brand-flame); text-decoration: none;">Explore EV Lineup &rarr;</a>
        </article>

        <article style="background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 24px; padding: 28px; display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--brand-blue); margin-bottom: 12px;">MONSOON CARE</span>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 20px; font-weight: 800; color: var(--ink-0); margin-bottom: 10px;">Monsoon Riding & IP67 Battery Safety Tips</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6; margin-bottom: 20px;">How waterproof battery enclosures and sealed wiring harnesses protect your scooter during heavy rains.</p>
          <a href="<?= base_url('battery-use') ?>" style="margin-top: auto; font-size: 13px; font-weight: 700; color: var(--brand-blue); text-decoration: none;">View Care Guide &rarr;</a>
        </article>

      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
