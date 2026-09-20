<?php
App::render('head', [
    'pageTitle'       => 'Contact Us | Hazra Electrical Bike',
    'pageDescription' => 'Get in touch with Hazra Electrical Bike — head office, email, phone, and message form.',
    'extraCss'        => ['assets/css/styles/pages/contact.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<main class="ct">
  <!-- HERO -->
  <section class="ct-hero">
    <div class="ct-hero__bg" aria-hidden="true">
      <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Hazra Electric Scooter">
      <i class="ct-hero__veil"></i>
    </div>
    <div class="wrap ct-hero__in">
      <h1 class="ct-hero__title">Contact us</h1>
      <p class="ct-hero__lead">Hazra is ready to help with sales, service, dealership and ownership support.</p>
    </div>
  </section>

  <!-- OVERLAP CARD: info + form -->
  <section class="ct-panel-sec">
    <div class="wrap">
      <div class="ct-panel">
        <!-- left: get in touch -->
        <aside class="ct-info">
          <h2>Get in touch</h2>
          <p class="ct-info__intro">Reach the team for product questions, test rides, warranty help or dealer enquiries.</p>

          <ul class="ct-info__list">
            <li>
              <span class="ct-info__icon"><i data-lucide="map-pin"></i></span>
              <div>
                <strong>Showroom & Head Office</strong>
                <p>Ghordourchati More, Below LIC Division Office<br>Bardhaman, West Bengal 713103</p>
              </div>
            </li>
            <li>
              <span class="ct-info__icon"><i data-lucide="mail"></i></span>
              <div>
                <strong>Email us</strong>
                <p><a href="mailto:info@hazraev.com">info@hazraev.com</a></p>
              </div>
            </li>
            <li>
              <span class="ct-info__icon"><i data-lucide="phone"></i></span>
              <div>
                <strong>Call us</strong>
                <p><a href="tel:+919002921509">+91 90029 21509</a> / <a href="tel:+919242752316">+91 92427 52316</a></p>
              </div>
            </li>
          </ul>

          <div class="ct-info__social">
            <p>Follow our social media</p>
            <div class="ct-socials">
              <a href="https://www.facebook.com/hazraelectricalbike/" target="_blank" rel="noopener" aria-label="Facebook">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
              </a>
              <a href="https://www.instagram.com/hazraelectrical/" target="_blank" rel="noopener" aria-label="Instagram">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
              </a>
              <a href="https://wa.me/919002921509" target="_blank" rel="noopener" aria-label="WhatsApp">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              </a>
              <a href="https://www.google.com/maps/place/Hazra+Electrical+Bike/@23.2198619,87.8848343,17z" target="_blank" rel="noopener" aria-label="Google Maps" title="Google Maps">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
              </a>
            </div>
          </div>
        </aside>

        <!-- right: form -->
        <div class="ct-form-wrap">
          <h2>Send us a message</h2>
          <form class="ct-form" id="contactForm" method="post" action="" novalidate>
            <div class="ct-form__grid">
              <label class="ct-field">
                <span>Name</span>
                <input type="text" name="name" placeholder="Name" required autocomplete="name">
              </label>
              <label class="ct-field">
                <span>Company</span>
                <input type="text" name="company" placeholder="Company" autocomplete="organization">
              </label>
              <label class="ct-field">
                <span>Phone</span>
                <input type="tel" name="phone" placeholder="Phone" required autocomplete="tel">
              </label>
              <label class="ct-field">
                <span>Email</span>
                <input type="email" name="email" placeholder="Email" required autocomplete="email">
              </label>
              <label class="ct-field ct-field--full">
                <span>Subject</span>
                <input type="text" name="subject" placeholder="Subject" required>
              </label>
              <label class="ct-field ct-field--full">
                <span>Message</span>
                <textarea name="message" rows="5" placeholder="Message" required></textarea>
              </label>
            </div>
            <button type="submit" class="ct-form__submit">Send</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- MAP -->
  <section class="ct-map" aria-label="Location map">
    <iframe
      title="Hazra location map"
      loading="lazy"
      referrerpolicy="no-referrer-when-downgrade"
      src="https://www.google.com/maps?q=India&output=embed">
    </iframe>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
  document.getElementById('contactForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    if (!f.reportValidity()) return;
    const body = {
      type: 'contact',
      name: f.name.value.trim(),
      company: f.company ? f.company.value.trim() : '',
      phone: f.phone.value.trim(),
      email: f.email.value.trim(),
      subject: f.subject ? f.subject.value.trim() : '',
      message: f.message.value.trim(),
    };
    try {
      const apiPath = <?= json_encode(base_url('api/v1/website/leads')) ?>;
      const res = await fetch(apiPath, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();
      alert(data.data?.message || data.message || ('Thanks ' + f.name.value.trim() + ' — your message was captured. We will get back to you shortly.'));
      f.reset();
    } catch(err) {
      alert('Thanks ' + f.name.value.trim() + ' — your message was captured. We will get back to you shortly.');
      f.reset();
    }
  });
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
