<?php
$pageTitle = "Battery Care Guide | Hazra Electrical Bike";
$pageDescription = "Six essential battery routines to maximize range, lifespan, and safety of your Hazra EV Lithium-ion battery.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">BATTERY HEALTH</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Battery Care & Maintenance
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Simple daily habits to extend your Lithium-ion battery pack lifespan and maintain peak riding range.
      </p>
    </div>
  </section>

  <section class="guide" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div style="width: min(1000px, 93vw); margin: 0 auto;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
        
        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="plug"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">1. Charge Between 20%–80%</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Avoid draining the battery to 0%. Plugging in around 20% and unplugging near 80%–90% keeps chemical stress low and doubles cycle life.</p>
        </div>

        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="thermometer-snowflake"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">2. Thermal Management</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Never charge immediately after a long high-speed ride. Allow the pack to cool down for 20 minutes before plugging in.</p>
        </div>

        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="shield-check"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">3. Use Original Charger</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Only use official Hazra EV smart chargers fitted with auto-cut off and over-voltage BMS communication protection.</p>
        </div>

      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
