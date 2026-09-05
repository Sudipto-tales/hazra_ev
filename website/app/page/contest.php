<?php
$pageTitle = "Reels Contest — Hazra Electrical Bike";
$pageDescription = "Join the Hazra EV Reels Contest! Share your riding moments on Instagram and win exciting prizes.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">COMMUNITY CONTEST</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Hazra Reels Contest
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Showcase your electric journey. Record a reel riding your Hazra EV, tag @hazra_ev on Instagram, and win cash prizes & accessories!
      </p>
    </div>
  </section>

  <section class="gallery" style="padding: clamp(60px, 9vw, 100px) 26px; background: var(--surface-0);">
    <div style="width: min(1000px, 93vw); margin: 0 auto; text-align: center;">
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px;">
        <div style="padding: 30px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="font-size: 28px; font-weight: 900; color: var(--brand-violet); margin-bottom: 8px;">Step 1</div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">Shoot Your Reel</h3>
          <p style="font-size: 13px; color: var(--ink-soft-0);">Capture your daily commute or scenic rides with your Hazra scooter.</p>
        </div>

        <div style="padding: 30px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="font-size: 28px; font-weight: 900; color: var(--brand-flame); margin-bottom: 8px;">Step 2</div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">Tag & Hashtag</h3>
          <p style="font-size: 13px; color: var(--ink-soft-0);">Post on Instagram tagging @hazra_ev with #HazraElectric and #FollowElegant.</p>
        </div>

        <div style="padding: 30px; background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 22px;">
          <div style="font-size: 28px; font-weight: 900; color: var(--brand-blue); margin-bottom: 8px;">Step 3</div>
          <h3 style="font-family: 'Montserrat', sans-serif; font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 6px;">Win Prizes</h3>
          <p style="font-size: 13px; color: var(--ink-soft-0);">Top 5 reels win cash rewards, helmet kits, and free service vouchers every month!</p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
