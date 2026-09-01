<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Careers â€” Hazra Electrical Bike</title>
  <link rel="icon" href="../../assets/hazraev.png" type="image/png">
  <link rel="apple-touch-icon" href="../../assets/hazraev.png">
  <meta name="description" content="Join the Hazra team. We're hiring talented builders, engineers, and creators who want to change how India rides.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <style>
    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       CAREERS â€” Animated Job Cards
       â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

    :root {
      --brand-indigo:  #3a1a6b;
      --brand-violet:  #7b2ff7;
      --brand-magenta: #a41fbf;
      --brand-blue:    #12a5e0;
      --brand-orange:  #f7941d;
      --brand-flame:   #f0532b;
      --brand-grad: linear-gradient(96deg,
          var(--brand-violet) 0%,
          var(--brand-magenta) 30%,
          var(--brand-flame) 66%,
          var(--brand-orange) 100%);

      --ink-0:        #180f2c;
      --ink-soft-0:   #6b6480;
      --hair-0:       #e3ddec;
      --surface-0:    #faf8fd;
      --surface-rgb:  250 248 253;
      --chip-rgb:     240 235 249;
      --accent:       var(--brand-orange);
      --accent-ink:   #180f2c;
      --glass-bd:     rgba(255,255,255,.28);
      --glass-bg:     rgba(255,255,255,.16);
      --veil:         rgba(26,14,48,.32);
      --sh-a:         .45;
      --ease: cubic-bezier(.22,1,.36,1);
    }

    html[data-theme="dark"] {
      --ink-0:        #f6f3fb;
      --ink-soft-0:   #9a93ad;
      --hair-0:       #2f2743;
      --surface-0:    #120e1c;
      --surface-rgb:  18 14 28;
      --chip-rgb:     33 26 50;
      --accent:       #ff8f2e;
      --accent-ink:   #150c26;
      --glass-bd:     rgba(255,255,255,.16);
      --glass-bg:     rgba(255,255,255,.08);
      --veil:         rgba(9,4,20,.52);
      --sh-a:         .8;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: var(--surface-0);
      color: var(--ink-0);
      -webkit-font-smoothing: antialiased;
    }

    .header {
      position: sticky; top: 0; z-index: 100;
      background: rgb(var(--surface-rgb) / .92);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--hair-0);
      padding: 20px 0;
    }

    .header__top {
      display: flex; align-items: center; justify-content: space-between;
      width: min(1520px, 93vw); margin: 0 auto; padding: 0 26px;
    }

    .brand {
      display: inline-flex; align-items: center; gap: 9px;
      text-decoration: none;
    }

    .brand__mark {
      width: 32px; height: 32px;
      object-fit: contain;
    }

    .brand__txt {
      font-family: 'Montserrat', sans-serif;
      font-size: 12.5px; font-weight: 700;
      color: var(--ink-soft-0);
    }

    .theme {
      display: grid; place-items: center;
      width: 40px; height: 40px; border-radius: 50%;
      background: none; border: 1px solid var(--hair-0);
      cursor: pointer; color: var(--ink-0);
    }

    .theme svg { width: 17px; height: 17px; position: absolute; }
    .theme__moon { display: none; }
    html[data-theme="dark"] .theme__sun { display: none; }
    html[data-theme="dark"] .theme__moon { display: block; }

    /* Hero Section */
    .hero {
      position: relative;
      background: var(--surface-0);
      padding: clamp(80px, 12vw, 160px) 26px;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute; inset: 0;
      background: radial-gradient(120% 78% at 50% 12%,
        color-mix(in srgb, var(--brand-violet) 8%, transparent) 0%, transparent 68%);
      pointer-events: none;
    }

    .hero__in {
      position: relative; z-index: 1;
      width: min(900px, 93vw); margin: 0 auto;
      text-align: center;
    }

    .hero__tag {
      display: inline-flex; align-items: center; gap: 8px;
      font-size: 10.5px; font-weight: 800; letter-spacing: .18em;
      color: var(--ink-soft-0); margin-bottom: 24px;
      opacity: 0; transform: translateY(20px);
      animation: reveal-up .8s var(--ease) .2s forwards;
    }

    .hero__tag::before {
      content: ''; width: 10px; height: 10px;
      background: var(--brand-grad);
    }

    .hero__title {
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(40px, 4.2vw, 72px);
      font-weight: 900; line-height: 1.05;
      letter-spacing: -.035em; margin-bottom: 24px;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up 1s var(--ease) .3s forwards;
    }

    .hero__lead {
      font-size: clamp(16px, 1.3vw, 20px);
      line-height: 1.6; color: var(--ink-soft-0);
      max-width: 600px; margin: 0 auto;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up 1s var(--ease) .4s forwards;
    }

    @keyframes reveal-up {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: none; }
    }

    /* Jobs Section */
    .jobs {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: var(--surface-0);
      position: relative;
    }

    .jobs__in {
      width: min(1000px, 93vw); margin: 0 auto;
    }

    .jobs__head {
      margin-bottom: clamp(50px, 6vw, 80px);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) .1s forwards;
    }

    .jobs__eyebrow {
      display: flex; align-items: center; gap: 8px;
      font-size: 10.5px; font-weight: 800; letter-spacing: .18em;
      color: var(--ink-soft-0); margin-bottom: 16px;
    }

    .jobs__eyebrow::before {
      content: ''; width: 10px; height: 10px;
      background: var(--brand-grad);
    }

    .jobs__title {
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(28px, 3vw, 48px);
      font-weight: 800; line-height: 1.06;
      letter-spacing: -.035em;
    }

    /* Job Cards */
    .job__list {
      list-style: none;
      display: flex; flex-direction: column;
      gap: clamp(20px, 2.5vw, 32px);
    }

    .job__card {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 24px;
      padding: clamp(24px, 3vw, 36px);
      background: rgb(var(--chip-rgb) / .35);
      border: 1px solid var(--hair-0);
      border-radius: 20px;
      cursor: pointer;
      transition: all .35s var(--ease);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) calc(.2s + var(--i, 0) * 100ms) forwards;
    }

    .job__card:hover {
      border-color: var(--brand-violet);
      background: var(--surface-0);
      box-shadow: 0 30px 60px -20px rgba(123, 47, 247, .25);
      transform: translateY(-4px);
    }

    .job__body {
      display: flex; flex-direction: column;
      gap: 14px;
    }

    .job__header {
      display: flex; align-items: flex-start;
      gap: 16px;
    }

    .job__icon {
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      width: 48px; height: 48px;
      border-radius: 12px;
      background: var(--brand-grad);
      color: #fff;
    }

    .job__icon svg {
      width: 24px; height: 24px;
    }

    .job__title {
      font-family: 'Montserrat', sans-serif;
      font-size: 18px; font-weight: 800;
      color: var(--ink-0);
    }

    .job__category {
      display: inline-flex; align-items: center;
      padding: 6px 12px;
      background: rgb(var(--surface-rgb) / .5);
      border: 1px solid var(--hair-0);
      border-radius: 6px;
      font-size: 10px; font-weight: 700;
      letter-spacing: .06em;
      color: var(--ink-soft-0);
      width: fit-content;
      margin-top: 8px;
    }

    .job__desc {
      font-size: 13px; line-height: 1.7;
      color: var(--ink-soft-0);
    }

    .job__tags {
      display: flex; flex-wrap: wrap;
      gap: 8px;
      margin-top: 12px;
    }

    .job__tag {
      display: inline-flex; align-items: center;
      padding: 6px 12px;
      background: var(--surface-0);
      border: 1px solid var(--hair-0);
      border-radius: 999px;
      font-size: 11px; font-weight: 600;
      color: var(--ink-soft-0);
    }

    .job__meta {
      display: flex; flex-direction: column;
      align-items: flex-end;
      justify-content: space-between;
      min-width: 140px;
    }

    .job__location {
      font-size: 12px;
      color: var(--ink-soft-0);
      display: flex; align-items: center;
      gap: 6px;
    }

    .job__location svg {
      width: 14px; height: 14px;
    }

    .job__cta {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 10px 16px;
      background: var(--brand-grad);
      color: #fff;
      border: none; border-radius: 999px;
      font-size: 11px; font-weight: 700;
      letter-spacing: .04em;
      cursor: pointer;
      transition: all .35s var(--ease);
      white-space: nowrap;
    }

    .job__card:hover .job__cta {
      transform: translateX(2px);
    }

    .job__cta svg {
      width: 12px; height: 12px;
    }

    /* Culture Section */
    .culture {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .culture__in {
      width: min(1000px, 93vw); margin: 0 auto;
    }

    .culture__head {
      margin-bottom: clamp(50px, 6vw, 80px);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) .1s forwards;
    }

    .culture__title {
      font-family: 'Montserrat', sans-serif;
      font-size: clamp(28px, 3vw, 48px);
      font-weight: 800; letter-spacing: -.035em;
    }

    .culture__grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: clamp(20px, 2.5vw, 36px);
    }

    .culture__item {
      text-align: center;
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) calc(.15s + var(--i, 0) * 80ms) forwards;
    }

    @keyframes pop {
      from { opacity: 0; transform: scale(.95); }
      to { opacity: 1; transform: none; }
    }

    .culture__icon {
      display: flex; align-items: center; justify-content: center;
      width: 56px; height: 56px;
      margin: 0 auto 16px;
      border-radius: 14px;
      background: var(--brand-grad);
      color: #fff;
    }

    .culture__icon svg {
      width: 28px; height: 28px;
    }

    .culture__label {
      font-family: 'Montserrat', sans-serif;
      font-size: 15px; font-weight: 800;
      color: var(--ink-0);
      margin-bottom: 8px;
    }

    .culture__desc {
      font-size: 12px; line-height: 1.6;
      color: var(--ink-soft-0);
    }

    /* Footer */
    .footer {
      padding: clamp(50px, 7vw, 100px) 26px;
      background: var(--surface-0);
      border-top: 1px solid var(--hair-0);
      text-align: center;
    }

    .footer__link {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 14px 26px;
      background: var(--brand-grad);
      color: #fff;
      border: none; border-radius: 999px;
      text-decoration: none;
      font-size: 12px; font-weight: 700; letter-spacing: .06em;
      cursor: pointer;
      transition: all .35s var(--ease);
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) .5s forwards;
    }

    .footer__link:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 34px -12px rgba(240, 83, 43, .55);
    }

    .footer__link svg {
      width: 15px; height: 15px;
    }

    @media (max-width: 768px) {
      .job__card {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .job__meta {
        flex-direction: row;
        gap: 16px;
        align-items: center;
        justify-content: space-between;
        min-width: auto;
      }

      .job__cta {
        flex-shrink: 0;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      * {
        animation: none !important;
        transition-duration: .01ms !important;
      }
    }
  </style>
</head>
<body>

<header class="header">
  <div class="header__top">
    <a class="brand" href="index.php">
      <img class="brand__mark" src="../../assets/hazraev.png" alt="Hazra">
      <span class="brand__txt">Hazra Electrical Bike</span>
    </a>
    <button class="theme" id="theme" aria-label="Toggle theme">
      <i data-lucide="sun-medium" class="theme__sun"></i>
      <i data-lucide="moon" class="theme__moon"></i>
    </button>
  </div>
</header>

<section class="hero">
  <div class="hero__in">
    <p class="hero__tag">Careers</p>
    <h1 class="hero__title">Build the future of<br>electric mobility.</h1>
    <p class="hero__lead">
      We're hiring talented builders, engineers, and creators
      who want to change how India rides.
    </p>
  </div>
</section>

<section class="jobs">
  <div class="jobs__in">
    <div class="jobs__head">
      <p class="jobs__eyebrow">Open Positions</p>
      <h2 class="jobs__title">Join the team</h2>
    </div>

    <ul class="job__list">
      <li class="job__card" style="--i: 0">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="cpu"></i>
            </div>
            <div>
              <h3 class="job__title">Senior Hardware Engineer</h3>
              <span class="job__category">Engineering</span>
            </div>
          </div>
          <p class="job__desc">
            Design and optimize battery management systems and motor controllers
            for the next generation of electric scooters.
          </p>
          <div class="job__tags">
            <span class="job__tag">Bangalore</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">5+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Bangalore
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>

      <li class="job__card" style="--i: 1">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="code"></i>
            </div>
            <div>
              <h3 class="job__title">Full Stack Developer</h3>
              <span class="job__category">Software</span>
            </div>
          </div>
          <p class="job__desc">
            Build the web and mobile platforms that connect riders with their scooters.
            Work on APIs, diagnostics, and user-facing applications.
          </p>
          <div class="job__tags">
            <span class="job__tag">Remote</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">3+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Remote
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>

      <li class="job__card" style="--i: 2">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="users"></i>
            </div>
            <div>
              <h3 class="job__title">Customer Success Manager</h3>
              <span class="job__category">Operations</span>
            </div>
          </div>
          <p class="job__desc">
            Own the rider experience across our dealer network.
            Build relationships and drive satisfaction in your assigned region.
          </p>
          <div class="job__tags">
            <span class="job__tag">Delhi NCR</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">2+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Delhi NCR
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>

      <li class="job__card" style="--i: 3">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="palette"></i>
            </div>
            <div>
              <h3 class="job__title">Product Designer</h3>
              <span class="job__category">Design</span>
            </div>
          </div>
          <p class="job__desc">
            Shape the visual and interaction design of rider-facing products.
            Own design systems and craft beautiful, intuitive interfaces.
          </p>
          <div class="job__tags">
            <span class="job__tag">Bangalore</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">4+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Bangalore
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>

      <li class="job__card" style="--i: 4">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="factory"></i>
            </div>
            <div>
              <h3 class="job__title">Manufacturing Engineer</h3>
              <span class="job__category">Operations</span>
            </div>
          </div>
          <p class="job__desc">
            Optimize our manufacturing processes and quality control.
            Lead initiatives to scale production without compromising on craftsmanship.
          </p>
          <div class="job__tags">
            <span class="job__tag">Ghaziabad</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">5+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Ghaziabad
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>

      <li class="job__card" style="--i: 5">
        <div class="job__body">
          <div class="job__header">
            <div class="job__icon">
              <i data-lucide="megaphone"></i>
            </div>
            <div>
              <h3 class="job__title">Growth Marketing Manager</h3>
              <span class="job__category">Marketing</span>
            </div>
          </div>
          <p class="job__desc">
            Drive rider acquisition and engagement through data-driven campaigns.
            Own growth initiatives across all channels and markets.
          </p>
          <div class="job__tags">
            <span class="job__tag">Bangalore</span>
            <span class="job__tag">Full-time</span>
            <span class="job__tag">3+ years</span>
          </div>
        </div>
        <div class="job__meta">
          <span class="job__location">
            <i data-lucide="map-pin"></i>Bangalore
          </span>
          <button class="job__cta">
            <span>Apply</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </li>
    </ul>
  </div>
</section>

<section class="culture">
  <div class="culture__in">
    <div class="culture__head">
      <p class="jobs__eyebrow">Our Culture</p>
      <h2 class="culture__title">Why you'll love working here</h2>
    </div>

    <div class="culture__grid">
      <div class="culture__item" style="--i: 0">
        <div class="culture__icon">
          <i data-lucide="target"></i>
        </div>
        <h3 class="culture__label">Mission-Driven</h3>
        <p class="culture__desc">Every line of code, every part, every decision moves us closer to cleaner cities.</p>
      </div>

      <div class="culture__item" style="--i: 1">
        <div class="culture__icon">
          <i data-lucide="users"></i>
        </div>
        <h3 class="culture__label">Collaborative</h3>
        <p class="culture__desc">Small teams, big impact. You'll work directly with founders and across disciplines.</p>
      </div>

      <div class="culture__item" style="--i: 2">
        <div class="culture__icon">
          <i data-lucide="lightbulb"></i>
        </div>
        <h3 class="culture__label">Innovative</h3>
        <p class="culture__desc">We solve real problems. Your ideas matter and get built into products.</p>
      </div>

      <div class="culture__item" style="--i: 3">
        <div class="culture__icon">
          <i data-lucide="trending-up"></i>
        </div>
        <h3 class="culture__label">Growing Fast</h3>
        <p class="culture__desc">Wear multiple hats. Learn new skills. Grow faster than you ever thought possible.</p>
      </div>

      <div class="culture__item" style="--i: 4">
        <div class="culture__icon">
          <i data-lucide="heart"></i>
        </div>
        <h3 class="culture__label">Care First</h3>
        <p class="culture__desc">Competitive salary, health insurance, flexible work, and genuine work-life balance.</p>
      </div>

      <div class="culture__item" style="--i: 5">
        <div class="culture__icon">
          <i data-lucide="building"></i>
        </div>
        <h3 class="culture__label">Remote-Friendly</h3>
        <p class="culture__desc">Work from anywhere. We believe great work happens where you're most productive.</p>
      </div>
    </div>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px; font-size: 14px;">
    Don't see the perfect role? Send us your resume anyway.
  </p>
  <a class="footer__link" href="index.php">
    <span>Back to home</span>
    <i data-lucide="arrow-right"></i>
  </a>
</section>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
  lucide.createIcons();

  const themeBtn = document.getElementById('theme');
  themeBtn.addEventListener('click', () => {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
  });

  const savedTheme = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', savedTheme);
</script>

</body>
</html>

