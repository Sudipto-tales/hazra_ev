<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog â€” Electric Mobility Insights | Hazra</title>
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

    .blog {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .blog__in { width: min(1200px, 93vw); margin: 0 auto; }

    .blog__grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: clamp(24px, 2.5vw, 40px);
    }

    .post__card {
      background: var(--surface-0); border: 1px solid var(--hair-0);
      border-radius: 20px; overflow: hidden;
      transition: all .35s var(--ease);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) calc(.2s + var(--i, 0) * 100ms) forwards;
    }

    .post__card:hover {
      border-color: var(--brand-violet);
      box-shadow: 0 30px 60px -20px rgba(123, 47, 247, .25);
      transform: translateY(-6px);
    }

    .post__img {
      width: 100%; height: 200px; object-fit: cover;
      transition: transform .35s var(--ease);
    }

    .post__card:hover .post__img {
      transform: scale(1.05);
    }

    .post__body {
      padding: clamp(20px, 2.5vw, 28px);
    }

    .post__cat {
      display: inline-block;
      padding: 6px 12px;
      background: rgb(var(--chip-rgb) / .5);
      border-radius: 6px;
      font-size: 10px; font-weight: 700;
      letter-spacing: .06em;
      color: var(--ink-soft-0);
      margin-bottom: 12px;
    }

    .post__title {
      font-family: 'Montserrat'; font-size: 18px;
      font-weight: 800; margin-bottom: 12px;
      line-height: 1.3;
    }

    .post__desc {
      font-size: 13px; line-height: 1.6;
      color: var(--ink-soft-0); margin-bottom: 16px;
    }

    .post__read {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--brand-violet); font-weight: 700;
      font-size: 12px; text-decoration: none;
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
  <h1 class="hero__title">Blog</h1>
  <p class="hero__lead">
    Insights on electric mobility, rider stories, and the future of urban transportation.
  </p>
</section>

<section class="blog">
  <div class="blog__in">
    <div class="blog__grid">
      <article class="post__card" style="--i: 0">
        <img src="../../assets/dark_scutie.png" alt="Urban Mobility" class="post__img">
        <div class="post__body">
          <span class="post__cat">EV Trends</span>
          <h3 class="post__title">The Future of Urban Mobility is Electric</h3>
          <p class="post__desc">Indian cities are shifting to lighter, smarter two-wheelers. Learn how the change is happening street by street.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>

      <article class="post__card" style="--i: 1">
        <img src="../../assets/scutie_light.png" alt="Battery Care" class="post__img">
        <div class="post__body">
          <span class="post__cat">Ownership</span>
          <h3 class="post__title">Battery Care Guide for EV Riders</h3>
          <p class="post__desc">Six routines that add years to your battery pack. Charge windows, storage, and habits that matter.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>

      <article class="post__card" style="--i: 2">
        <img src="../../assets/dark_scutie.png" alt="Dealer Network" class="post__img">
        <div class="post__body">
          <span class="post__cat">Dealership</span>
          <h3 class="post__title">Why EV Dealerships Are High-Growth Business</h3>
          <p class="post__desc">Demand signals, service revenue, and local trust. What actually makes an EV counter work.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>

      <article class="post__card" style="--i: 3">
        <img src="../../assets/scutie_light.png" alt="Commute Tips" class="post__img">
        <div class="post__body">
          <span class="post__cat">Riding</span>
          <h3 class="post__title">5 Tips for Safer City Riding</h3>
          <p class="post__desc">Make your daily commute safer and smarter. Expert advice from our riding community.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>

      <article class="post__card" style="--i: 4">
        <img src="../../assets/dark_scutie.png" alt="Cost Calculator" class="post__img">
        <div class="post__body">
          <span class="post__cat">Economics</span>
          <h3 class="post__title">How Much You Actually Save With EV</h3>
          <p class="post__desc">A detailed breakdown of running costs vs petrol scooters. Numbers that speak for themselves.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>

      <article class="post__card" style="--i: 5">
        <img src="../../assets/scutie_light.png" alt="Rider Story" class="post__img">
        <div class="post__body">
          <span class="post__cat">Stories</span>
          <h3 class="post__title">Rider Story: From Petrol to Electric</h3>
          <p class="post__desc">Meet Priya who switched to Hazra and never looked back. Her journey to clean mobility.</p>
          <a href="#" class="post__read">Read more <i data-lucide="arrow-right"></i></a>
        </div>
      </article>
    </div>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Subscribe to our newsletter for weekly updates
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

