<?php
$pageTitle = "The EV Future | Hazra Electrical Bike";
$pageDescription = "Explore the future of electric mobility, battery technology, and green urban transportation in India.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">GREEN MOBILITY REVOLUTION</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        The Future of Indian EV
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        How electric two-wheelers are transforming daily commute, reducing carbon footprint, and powering sustainable growth.
      </p>
    </div>
  </section>

  <section class="trends" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div class="trends__in" style="width: min(1100px, 93vw); margin: 0 auto;">
      <div class="trends__grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
        
        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="battery-charging"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">Next-Gen Batteries</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Higher energy density cells promising 200+ km single charge range with fast-charging technology.</p>
        </div>

        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="cpu"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">Smart IoT Telematics</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Real-time GPS tracking, remote diagnostics, OTA updates, and theft-prevention geo-fencing.</p>
        </div>

        <div style="padding: 28px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin-bottom: 16px;">
            <i data-lucide="leaf"></i>
          </div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">Zero Carbon Emissions</h3>
          <p style="font-size: 13.5px; color: var(--ink-soft-0); line-height: 1.6;">Eliminating tailpipe pollution while saving up to 85% in daily fuel running costs.</p>
        </div>

      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
