<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dealership Enquiry | Hazra</title>
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

    .form-section {
      padding: clamp(60px, 9vw, 140px) 26px;
      background: rgb(var(--chip-rgb) / .35);
      border-top: 1px solid var(--hair-0);
    }

    .form-section__in { width: min(700px, 93vw); margin: 0 auto; }

    .form {
      background: var(--surface-0);
      padding: clamp(40px, 5vw, 60px);
      border-radius: 20px;
      border: 1px solid var(--hair-0);
      opacity: 0; transform: translateY(30px);
      animation: reveal-up .8s var(--ease) .2s forwards;
    }

    .fields {
      display: flex; flex-direction: column;
      gap: 20px;
    }

    .field {
      display: flex; flex-direction: column; gap: 8px;
    }

    .field label {
      font-size: 12px; font-weight: 700;
      letter-spacing: .06em;
      color: var(--ink-soft-0);
    }

    .field input,
    .field select,
    .field textarea {
      padding: 12px 16px;
      background: rgb(var(--chip-rgb) / .35);
      border: 1px solid var(--hair-0);
      border-radius: 8px;
      font-family: 'Inter';
      color: var(--ink-0);
      font-size: 14px;
      transition: border-color .35s var(--ease);
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      outline: none;
      border-color: var(--brand-violet);
    }

    .field textarea {
      resize: vertical; min-height: 120px;
    }

    .form__group {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form__submit {
      padding: 14px 26px;
      background: var(--brand-grad);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 12px; font-weight: 700;
      cursor: pointer;
      transition: all .35s var(--ease);
      width: 100%;
      letter-spacing: .06em;
    }

    .form__submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 16px 34px -12px rgba(240, 83, 43, .55);
    }

    .success {
      text-align: center;
      padding: 40px;
      background: rgb(123, 47, 247, .1);
      border-radius: 16px;
      border: 1px solid var(--brand-violet);
      margin-top: 20px;
      opacity: 0;
    }

    .success.show {
      animation: pop .7s cubic-bezier(.18,.9,.32,1.28) forwards;
    }

    .success__icon {
      font-size: 48px; margin-bottom: 16px;
    }

    .success__title {
      font-family: 'Montserrat'; font-weight: 800;
      margin-bottom: 8px;
    }

    .success__desc {
      font-size: 13px; color: var(--ink-soft-0);
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
      .form__group { grid-template-columns: 1fr; }
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
  <h1 class="hero__title">Dealership Enquiry</h1>
  <p class="hero__lead">
    Interested in partnering with Hazra? Submit your enquiry and let's talk.
  </p>
</section>

<section class="form-section">
  <div class="form-section__in">
    <form class="form" id="enquiryForm" onsubmit="handleSubmit(event)">
      <div class="fields">
        <div class="form__group">
          <div class="field">
            <label>Business Name *</label>
            <input type="text" name="business" placeholder="Your business name" required>
          </div>
          <div class="field">
            <label>Contact Person *</label>
            <input type="text" name="contact" placeholder="Full name" required>
          </div>
        </div>

        <div class="form__group">
          <div class="field">
            <label>City *</label>
            <input type="text" name="city" placeholder="City" required>
          </div>
          <div class="field">
            <label>State *</label>
            <input type="text" name="state" placeholder="State" required>
          </div>
        </div>

        <div class="form__group">
          <div class="field">
            <label>Phone Number *</label>
            <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required>
          </div>
          <div class="field">
            <label>Email *</label>
            <input type="email" name="email" placeholder="your@email.com" required>
          </div>
        </div>

        <div class="form__group">
          <div class="field">
            <label>Years of Experience *</label>
            <input type="number" name="experience" placeholder="5" required>
          </div>
          <div class="field">
            <label>Business Type *</label>
            <select name="type" required>
              <option value="">Select type</option>
              <option value="retail">Retail Store</option>
              <option value="service">Service Center</option>
              <option value="both">Both</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label>About Your Business Proposal</label>
          <textarea name="proposal" placeholder="Tell us about your business plan, current operations, and why you want to partner with Hazra"></textarea>
        </div>

        <button type="submit" class="form__submit">
          Submit Enquiry
        </button>

        <div class="success" id="successMsg">
          <div class="success__icon">âœ“</div>
          <div class="success__title">Thank You!</div>
          <div class="success__desc">Your enquiry has been received. Our team will contact you within 24 hours.</div>
        </div>
      </div>
    </form>
  </div>
</section>

<section class="footer">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    For immediate assistance, call us at +91 98290 16542
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

  function handleSubmit(e) {
    e.preventDefault();
    const successMsg = document.getElementById('successMsg');
    successMsg.classList.add('show');
    e.target.reset();
    setTimeout(() => {
      successMsg.classList.remove('show');
    }, 3000);
  }
</script>

</body>
</html>

