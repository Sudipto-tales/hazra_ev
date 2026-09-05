<?php
$pageTitle = "Careers — Hazra Electrical Bike";
$pageDescription = "Join the Hazra EV team. We are hiring engineers, designers, and managers to shape the future of Indian mobility.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(850px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">JOIN OUR TEAM</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Build the Future of Indian EV
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        We are hiring passionate engineers, creators, and leaders building next-generation electric mobility.
      </p>
    </div>
  </section>

  <section class="jobs" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div style="width: min(1000px, 93vw); margin: 0 auto;">
      <h2 style="font-family: 'Montserrat', sans-serif; font-size: 26px; font-weight: 800; color: var(--ink-0); margin-bottom: 32px;">
        Open Positions
      </h2>

      <div style="display: flex; flex-direction: column; gap: 20px;">
        
        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">Senior Hardware & Embedded Engineer</h3>
            <div style="display: flex; gap: 12px; font-size: 12.5px; color: var(--ink-soft-0); font-weight: 600;">
              <span>R&D / Hardware</span>
              <span>•</span>
              <span>Bardhaman / Remote</span>
              <span>•</span>
              <span>Full-time</span>
            </div>
          </div>
          <a href="<?= base_url('dealership-enquiry') ?>" style="padding: 12px 24px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700;">Apply Now</a>
        </div>

        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">Regional Sales & Network Manager</h3>
            <div style="display: flex; gap: 12px; font-size: 12.5px; color: var(--ink-soft-0); font-weight: 600;">
              <span>Sales & Expansion</span>
              <span>•</span>
              <span>West Bengal / East</span>
              <span>•</span>
              <span>Full-time</span>
            </div>
          </div>
          <a href="<?= base_url('dealership-enquiry') ?>" style="padding: 12px 24px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700;">Apply Now</a>
        </div>

        <div style="padding: 24px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
          <div>
            <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">EV Battery Service Technician</h3>
            <div style="display: flex; gap: 12px; font-size: 12.5px; color: var(--ink-soft-0); font-weight: 600;">
              <span>Service Operations</span>
              <span>•</span>
              <span>Multiple Locations</span>
              <span>•</span>
              <span>Full-time</span>
            </div>
          </div>
          <a href="<?= base_url('dealership-enquiry') ?>" style="padding: 12px 24px; background: var(--ink-0); color: var(--surface-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700;">Apply Now</a>
        </div>

      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
