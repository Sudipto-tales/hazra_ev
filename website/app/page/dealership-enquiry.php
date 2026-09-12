<?php
App::render('head', [
    'pageTitle'       => 'Dealership Enquiry | Hazra',
    'pageDescription' => 'Interested in partnering with Hazra Electrical Bike? Submit your dealership enquiry.',
    'extraCss'        => ['assets/css/styles/pages/dealership-enquiry.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="hero">
  <h1 class="hero__title">Dealership Enquiry</h1>
  <p class="hero__lead">
    Interested in partnering with Hazra? Submit your enquiry and let's talk.
  </p>
</section>

<section class="form-section">
  <div class="form-section__in">
    <form class="form" id="enquiryForm">
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
          <div class="success__icon">✓</div>
          <div class="success__title">Thank You!</div>
          <div class="success__desc">Your enquiry has been received. Our team will contact you within 24 hours.</div>
        </div>
      </div>
    </form>
  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    For immediate assistance, call us at +91 98290 16542
  </p>
  <a class="footer__link" href="<?= e(base_url('index')) ?>">
    <span>Back to home</span>
    <i data-lucide="arrow-right"></i>
  </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  document.getElementById('enquiryForm')?.addEventListener('submit', (e) => {
    e.preventDefault();
    const successMsg = document.getElementById('successMsg');
    successMsg.classList.add('show');
    e.target.reset();
    setTimeout(() => {
      successMsg.classList.remove('show');
    }, 3000);
  });
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
