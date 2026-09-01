<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dealer Locator | Hazra</title>
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

    .search {
      padding: clamp(40px, 6vw, 80px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .search__in { width: min(1200px, 93vw); margin: 0 auto; }

    .search__box {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 40px;
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) .2s forwards;
    }

    .field {
      display: flex; flex-direction: column; gap: 8px;
    }

    .field label {
      font-size: 12px; font-weight: 700;
      letter-spacing: .06em; text-transform: uppercase;
      color: var(--ink-soft-0);
    }

    .field input, .field select {
      padding: 12px 16px;
      background: var(--surface-0);
      border: 1px solid var(--hair-0);
      border-radius: 8px;
      font-family: 'Inter';
      color: var(--ink-0);
      font-size: 14px;
    }

    .dealers {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: clamp(20px, 2.5vw, 36px);
    }

    .dealer__card {
      padding: clamp(24px, 3vw, 36px);
      background: var(--surface-0);
      border: 1px solid var(--hair-0);
      border-radius: 16px;
      transition: all .35s var(--ease);
      opacity: 0; transform: scale(.95);
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) calc(.2s + var(--i, 0) * 80ms) forwards;
    }

    .dealer__card:hover {
      border-color: var(--brand-violet);
      box-shadow: 0 30px 60px -20px rgba(123, 47, 247, .25);
      transform: translateY(-4px);
    }

    .dealer__name {
      font-family: 'Montserrat'; font-size: 16px;
      font-weight: 800; margin-bottom: 12px;
    }

    .dealer__info {
      font-size: 13px; line-height: 1.7;
      color: var(--ink-soft-0);
      display: flex; flex-direction: column;
      gap: 10px;
      margin-bottom: 16px;
    }

    .dealer__info span {
      display: flex; gap: 8px;
    }

    .dealer__cta {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 10px 16px;
      background: var(--brand-grad);
      color: #fff;
      border: none; border-radius: 8px;
      font-size: 11px; font-weight: 700;
      cursor: pointer;
      transition: all .35s var(--ease);
    }

    .dealer__cta:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px -4px rgba(123, 47, 247, .3);
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
      .search__box { grid-template-columns: 1fr; }
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
  <h1 class="hero__title">Find a Dealer</h1>
  <p class="hero__lead">
    Discover the nearest Hazra dealer and book your test ride today.
  </p>
</section>

<section class="search">
  <div class="search__in">
    <div class="search__box">
      <div class="field">
        <label>City</label>
        <input type="text" placeholder="Enter your city" id="cityInput">
      </div>
      <div class="field">
        <label>Model</label>
        <select id="modelSelect">
          <option>All models</option>
          <option>CHALO 1000 V2</option>
          <option>CHALO SMART PRO</option>
          <option>CHALO SMART PLUS</option>
        </select>
      </div>
    </div>

    <div class="dealers">
      <div class="dealer__card" style="--i: 0">
        <h3 class="dealer__name">Hazra Delhi NCR</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Delhi, Gurgaon, Noida</span>
          <span><i data-lucide="phone"></i">+91 98290 16542</span>
          <span><i data-lucide="mail"></i>delhi@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>

      <div class="dealer__card" style="--i: 1">
        <h3 class="dealer__name">Hazra Bangalore</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Bangalore, Whitefield</span>
          <span><i data-lucide="phone"></i>+91 80123 45678</span>
          <span><i data-lucide="mail"></i>bangalore@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>

      <div class="dealer__card" style="--i: 2">
        <h3 class="dealer__name">Hazra Mumbai</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Mumbai, Thane</span>
          <span><i data-lucide="phone"></i>+91 90012 34567</span>
          <span><i data-lucide="mail"></i>mumbai@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>

      <div class="dealer__card" style="--i: 3">
        <h3 class="dealer__name">Hazra Pune</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Pune, Hinjewadi</span>
          <span><i data-lucide="phone"></i>+91 78901 23456</span>
          <span><i data-lucide="mail"></i>pune@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>

      <div class="dealer__card" style="--i: 4">
        <h3 class="dealer__name">Hazra Kolkata</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Kolkata, Salt Lake</span>
          <span><i data-lucide="phone"></i>+91 67890 12345</span>
          <span><i data-lucide="mail"></i>kolkata@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>

      <div class="dealer__card" style="--i: 5">
        <h3 class="dealer__name">Hazra Jaipur</h3>
        <div class="dealer__info">
          <span><i data-lucide="map-pin"></i>Jaipur, C-Scheme</span>
          <span><i data-lucide="phone"></i>+91 56789 01234</span>
          <span><i data-lucide="mail"></i>jaipur@hazraev.com</span>
        </div>
        <button class="dealer__cta">
          <span>Book Test Ride</span>
          <i data-lucide="arrow-right"></i>
        </button>
      </div>
    </div>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Find your closest dealer and start your EV journey
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

