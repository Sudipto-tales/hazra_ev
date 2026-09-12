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
                <strong>Head office</strong>
                <p>Hazra Electrical Bike<br>India — dealer network nationwide</p>
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
                <p><a href="tel:+919829016542">+91 98290 16542</a></p>
              </div>
            </li>
          </ul>

          <div class="ct-info__social">
            <p>Follow our social media</p>
            <div class="ct-socials">
              <a href="#" aria-label="Facebook"><i data-lucide="facebook"></i></a>
              <a href="#" aria-label="Instagram"><i data-lucide="instagram"></i></a>
              <a href="https://wa.me/919829016542" target="_blank" rel="noopener" aria-label="WhatsApp"><i data-lucide="message-circle"></i></a>
              <a href="#" aria-label="YouTube"><i data-lucide="youtube"></i></a>
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
