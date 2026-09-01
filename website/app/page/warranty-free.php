<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Free Warranty Registration | Hazra</title>
  <link rel="icon" href="../../assets/hazraev.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --brand-violet:  #7b2ff7;
      --brand-magenta: #a41fbf;
      --brand-blue:    #12a5e0;
      --brand-flame:   #f0532b;
      --brand-grad: linear-gradient(96deg, var(--brand-violet) 0%, var(--brand-magenta) 30%, var(--brand-flame) 66%, #f7941d 100%);
      --ink-0:        #180f2c;
      --ink-soft-0:   #6b6480;
      --hair-0:       #e3ddec;
      --surface-0:    #faf8fd;
      --surface-rgb:  250 248 253;
      --chip-rgb:     240 235 249;
      --ease: cubic-bezier(.22,1,.36,1);
    }

    html[data-theme="dark"] {
      --ink-0:        #f6f3fb;
      --ink-soft-0:   #9a93ad;
      --hair-0:       #2f2743;
      --surface-0:    #120e1c;
      --surface-rgb:  18 14 28;
      --chip-rgb:     33 26 50;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', system-ui; background: var(--surface-0); color: var(--ink-0); }

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

    .brand { display: inline-flex; align-items: center; gap: 9px; text-decoration: none; }
    .brand__mark { width: 32px; height: 32px; object-fit: contain; }
    .brand__txt { font-family: 'Montserrat'; font-size: 12.5px; font-weight: 700; color: var(--ink-soft-0); }

    .theme {
      display: grid; place-items: center; width: 40px; height: 40px;
      border-radius: 50%; background: none; border: 1px solid var(--hair-0);
      cursor: pointer; color: var(--ink-0);
    }

    .theme svg { width: 17px; height: 17px; position: absolute; }
    .theme__moon { display: none; }
    html[data-theme="dark"] .theme__sun { display: none; }
    html[data-theme="dark"] .theme__moon { display: block; }

    .hero {
      text-align: center; padding: clamp(80px, 12vw, 160px) 26px;
      background: var(--surface-0);
    }

    .hero__title {
      font-family: 'Montserrat'; font-size: clamp(40px, 4.2vw, 72px);
      font-weight: 900; margin-bottom: 24px;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up 1s var(--ease) .2s forwards;
    }

    .hero__lead {
      font-size: 16px; line-height: 1.6; color: var(--ink-soft-0);
      max-width: 600px; margin: 0 auto;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up 1s var(--ease) .3s forwards;
    }

    .content {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .content__in { width: min(900px, 93vw); margin: 0 auto; }

    .process {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 24px;
      margin-bottom: 60px;
    }

    .step {
      text-align: center;
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) calc(.2s + var(--i, 0) * 100ms) forwards;
    }

    .step__num {
      display: flex; align-items: center; justify-content: center;
      width: 48px; height: 48px;
      margin: 0 auto 16px;
      background: var(--brand-grad);
      color: #fff;
      border-radius: 50%;
      font-family: 'Montserrat';
      font-weight: 800; font-size: 18px;
    }

    .step__title {
      font-family: 'Montserrat'; font-weight: 800;
      font-size: 14px; margin-bottom: 8px;
    }

    .step__desc {
      font-size: 12px; line-height: 1.6;
      color: var(--ink-soft-0);
    }

    .benefits {
      background: var(--surface-0);
      padding: 40px;
      border-radius: 20px;
      border: 1px solid var(--hair-0);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) .3s forwards;
    }

    .benefits__title {
      font-family: 'Montserrat'; font-size: 20px;
      font-weight: 800; margin-bottom: 24px;
    }

    .benefits__list {
      list-style: none;
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .benefits__item {
      display: flex; gap: 12px;
    }

    .benefits__check {
      color: var(--brand-violet);
      flex-shrink: 0;
    }

    .benefits__text {
      font-size: 13px; line-height: 1.6;
      color: var(--ink-soft-0);
    }

    .cta {
      text-align: center; padding-top: 40px;
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) .5s forwards;
    }

    .cta__btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 14px 26px;
      background: var(--brand-grad);
      color: #fff; border: none; border-radius: 999px;
      font-size: 12px; font-weight: 700;
      cursor: pointer;
      transition: all .35s var(--ease);
    }

    .cta__btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 34px -12px rgba(240, 83, 43, .55);
    }

    .footer {
      padding: clamp(50px, 7vw, 100px) 26px;
      background: var(--surface-0);
      border-top: 1px solid var(--hair-0);
      text-align: center;
    }

    .footer__link {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 14px 26px; background: var(--brand-grad);
      color: #fff; border: none; border-radius: 999px;
      text-decoration: none; font-size: 12px; font-weight: 700;
      cursor: pointer; transition: all .35s var(--ease);
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) .5s forwards;
    }

    .footer__link:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 34px -12px rgba(240, 83, 43, .55);
    }

    @keyframes reveal-up {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: none; }
    }

    @keyframes pop {
      from { opacity: 0; transform: scale(.95); }
      to { opacity: 1; transform: none; }
    }

    @media (max-width: 768px) {
      .process { grid-template-columns: repeat(2, 1fr); }
      .benefits__list { grid-template-columns: 1fr; }
    }

    @media (prefers-reduced-motion: reduce) {
      * { animation: none !important; transition-duration: .01ms !important; }
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
  <h1 class="hero__title">Free Warranty Registration</h1>
  <p class="hero__lead">
    Register your Hazra scooter instantly and unlock complete warranty coverage.
  </p>
</section>

<section class="content">
  <div class="content__in">
    <div class="process">
      <div class="step" style="--i: 0">
        <div class="step__num">1</div>
        <h3 class="step__title">Visit Dealer</h3>
        <p class="step__desc">Take your scooter to any Hazra dealer</p>
      </div>

      <div class="step" style="--i: 1">
        <div class="step__num">2</div>
        <h3 class="step__title">Fill Form</h3>
        <p class="step__desc">Complete warranty registration</p>
      </div>

      <div class="step" style="--i: 2">
        <div class="step__num">3</div>
        <h3 class="step__title">Get Certificate</h3>
        <p class="step__desc">Receive warranty certificate instantly</p>
      </div>

      <div class="step" style="--i: 3">
        <div class="step__num">4</div>
        <h3 class="step__title">Enjoy Coverage</h3>
        <p class="step__desc">Full warranty benefits active immediately</p>
      </div>
    </div>

    <div class="benefits">
      <h3 class="benefits__title">What's Covered</h3>
      <ul class="benefits__list">
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Frame and body manufacturing defects</span>
        </li>
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Electric motor and drivetrain</span>
        </li>
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Battery pack (4-5 years)</span>
        </li>
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Suspension and braking system</span>
        </li>
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Electrical components</span>
        </li>
        <li class="benefits__item">
          <span class="benefits__check"><i data-lucide="check"></i></span>
          <span class="benefits__text">Free periodic service checks</span>
        </li>
      </ul>
    </div>

    <div class="cta">
      <button class="cta__btn">
        <span>Register Your Scooter</span>
        <i data-lucide="arrow-right"></i>
      </button>
    </div>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Questions about warranty? Check FAQ or contact your dealer
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

