<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Battery Care Guide | Hazra</title>
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

    .guide {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .guide__in { width: min(1000px, 93vw); margin: 0 auto; }

    .steps {
      display: flex; flex-direction: column;
      gap: clamp(30px, 4vw, 50px);
    }

    .step {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: clamp(30px, 4vw, 60px); align-items: center;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) calc(.2s + var(--i, 0) * 150ms) forwards;
    }

    .step:nth-child(even) {
      grid-template-columns: 1fr 1fr;
    }

    .step:nth-child(even) .step__img {
      order: 2;
    }

    .step:nth-child(even) .step__body {
      order: 1;
    }

    .step__icon {
      display: flex; align-items: center; justify-content: center;
      width: 60px; height: 60px; border-radius: 14px;
      background: var(--brand-grad); color: #fff;
      margin-bottom: 16px;
    }

    .step__icon svg { width: 28px; height: 28px; }

    .step__num {
      font-family: 'Montserrat'; font-size: 14px;
      font-weight: 800; color: var(--brand-violet);
      letter-spacing: .06em; text-transform: uppercase;
      margin-bottom: 8px;
    }

    .step__title {
      font-family: 'Montserrat'; font-size: 24px;
      font-weight: 800; margin-bottom: 12px;
    }

    .step__desc {
      font-size: 14px; line-height: 1.7;
      color: var(--ink-soft-0);
    }

    .step__img {
      background: var(--surface-0); border: 1px solid var(--hair-0);
      border-radius: 16px; padding: 30px;
      display: flex; align-items: center; justify-content: center;
      min-height: 300px;
    }

    .step__img img {
      width: 100%; max-width: 80%;
      object-fit: contain;
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
      .step {
        grid-template-columns: 1fr;
      }
      .step:nth-child(even) .step__img {
        order: 0;
      }
      .step:nth-child(even) .step__body {
        order: 0;
      }
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
          <p class="step__desc">Charge between 20-80% for daily use. Avoid frequent full charges. Use the charger providedâ€”it's matched to your battery.</p>
        </div>
        <div class="step__img">
          <img src="../../assets/scutie_light.png" alt="Smart Charging">
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
          <img src="../../assets/dark_scutie.png" alt="Temperature Control">
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
          <img src="../../assets/scutie_light.png" alt="Regular Usage">
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
          <img src="../../assets/dark_scutie.png" alt="Water Protection">
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
          <img src="../../assets/scutie_light.png" alt="Professional Service">
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
          <img src="../../assets/dark_scutie.png" alt="Warranty">
        </div>
      </div>
    </div>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Follow these tips to maximize battery life and get more from every charge
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

