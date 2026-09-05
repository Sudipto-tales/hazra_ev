<?php
$pageTitle = "Extended Warranty Plans | Hazra Electrical Bike";
$pageDescription = "Explore Hazra EV extended warranty plans for maximum coverage, battery protection, and roadside assistance.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">PEACE OF MIND</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Extended Warranty Plans
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Protect your ride beyond standard coverage with optional battery health replacement and roadside assistance plans.
      </p>
    </div>
  </section>

  <section class="plans" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div class="plans__in" style="width: min(1200px, 93vw); margin: 0 auto;">
      <div class="plans__grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">
        
        <div style="background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 24px; padding: 36px; display: flex; flex-direction: column;">
          <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: var(--brand-violet); background: var(--surface-0); padding: 4px 12px; border-radius: 999px; align-self: flex-start; margin-bottom: 16px;">POPULAR</span>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">Standard Plus</h3>
          <div style="font-size: 32px; font-weight: 900; color: var(--brand-violet); margin-bottom: 20px;">₹8,999</div>
          <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px;">
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-violet);"></i> 6-year total vehicle coverage</li>
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-violet);"></i> Extended battery degradation cover</li>
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-violet);"></i> 1x free annual comprehensive service</li>
          </ul>
          <a href="<?= base_url('index.php#contact') ?>" style="margin-top: auto; padding: 14px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: 700; text-align: center;">Contact Dealer to Buy</a>
        </div>

        <div style="background: rgb(var(--chip-rgb) / .3); border: 2px solid var(--brand-flame); border-radius: 24px; padding: 36px; display: flex; flex-direction: column; position: relative;">
          <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .1em; color: #fff; background: var(--brand-flame); padding: 4px 12px; border-radius: 999px; align-self: flex-start; margin-bottom: 16px;">BEST VALUE</span>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 22px; font-weight: 800; color: var(--ink-0); margin-bottom: 8px;">Premium Pro</h3>
          <div style="font-size: 32px; font-weight: 900; color: var(--brand-flame); margin-bottom: 20px;">₹14,999</div>
          <ul style="list-style: none; display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px;">
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-flame);"></i> 8-year total vehicle coverage</li>
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-flame);"></i> Full battery replacement assurance</li>
            <li style="display: flex; gap: 8px; font-size: 13.5px; color: var(--ink-0);"><i data-lucide="check" style="width: 18px; height: 18px; color: var(--brand-flame);"></i> 24/7 Roadside breakdown assistance</li>
          </ul>
          <a href="<?= base_url('index.php#contact') ?>" style="margin-top: auto; padding: 14px; background: var(--brand-grad); color: #fff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: 700; text-align: center;">Contact Dealer to Buy</a>
        </div>

      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
