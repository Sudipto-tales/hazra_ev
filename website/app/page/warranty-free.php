<?php
$pageTitle = "Free Warranty Policy | Hazra Electrical Bike";
$pageDescription = "Learn about Hazra EV standard free warranty coverage for vehicle, battery, and motor components.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">WARRANTY COVERAGE</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Standard Free Warranty
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Every Hazra EV scooter comes with comprehensive manufacturer warranty coverage included with your purchase.
      </p>
    </div>
  </section>

  <section style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div style="width: min(900px, 93vw); margin: 0 auto;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 50px;">
        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border-radius: 20px; text-align: center;">
          <div style="font-size: 32px; font-weight: 900; color: var(--brand-violet); margin-bottom: 4px;">3 Years</div>
          <div style="font-size: 13px; font-weight: 700; color: var(--ink-0);">Vehicle Chassis & Frame</div>
        </div>
        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border-radius: 20px; text-align: center;">
          <div style="font-size: 32px; font-weight: 900; color: var(--brand-flame); margin-bottom: 4px;">3-5 Years</div>
          <div style="font-size: 13px; font-weight: 700; color: var(--ink-0);">Li-ion Battery Pack</div>
        </div>
        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .4); border-radius: 20px; text-align: center;">
          <div style="font-size: 32px; font-weight: 900; color: var(--brand-blue); margin-bottom: 4px;">3 Years</div>
          <div style="font-size: 13px; font-weight: 700; color: var(--ink-0);">BLDC Motor & Controller</div>
        </div>
      </div>

      <div style="background: var(--surface-0); padding: 36px; border: 1px solid var(--hair-0); border-radius: 24px;">
        <h3 style="font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 800; color: var(--ink-0); margin-bottom: 20px;">
          What is Covered under Free Warranty
        </h3>
        <ul style="list-style: none; display: flex; flex-direction: column; gap: 14px;">
          <li style="display: flex; gap: 12px; font-size: 14px; color: var(--ink-0);">
            <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--brand-violet); flex-shrink: 0;"></i>
            <span>Manufacturing defects in electric motor and internal coils.</span>
          </li>
          <li style="display: flex; gap: 12px; font-size: 14px; color: var(--ink-0);">
            <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--brand-violet); flex-shrink: 0;"></i>
            <span>Battery pack performance degradation below specified thresholds.</span>
          </li>
          <li style="display: flex; gap: 12px; font-size: 14px; color: var(--ink-0);">
            <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--brand-violet); flex-shrink: 0;"></i>
            <span>Digital instrument cluster and wiring harness defects.</span>
          </li>
          <li style="display: flex; gap: 12px; font-size: 14px; color: var(--ink-0);">
            <i data-lucide="check-circle" style="width: 20px; height: 20px; color: var(--brand-violet); flex-shrink: 0;"></i>
            <span>Complimentary periodic inspection at any authorized service center.</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
