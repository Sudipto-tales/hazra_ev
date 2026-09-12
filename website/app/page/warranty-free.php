<?php
App::render('head', [
    'pageTitle'       => 'Free Warranty Registration | Hazra Electrical Bike',
    'pageDescription' => 'Register your Hazra scooter on the day of purchase to activate standard warranty and free extended coverage on chassis, motor and controller.',
    'extraCss'        => ['assets/css/styles/pages/warranty-free.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<main class="wf">
  <!-- HERO -->
  <section class="wf-hero">
    <div class="wrap wf-hero__grid">
      <div class="wf-hero__copy reveal-up">
        <p class="eyebrow"><i class="sq"></i>SERVICE · WARRANTY</p>
        <h1 class="wf-hero__title">Register your scooter<br><span class="hl">for free warranty</span></h1>
        <p class="wf-hero__lead">Activate standard cover and unlock free extended warranty on chassis, motor and controller. Registration on the day of purchase is required.</p>
        <a class="btn btn--ink" href="#register"><span>Start registration</span><i data-lucide="arrow-down"></i></a>
      </div>
      <div class="wf-hero__media reveal-up" style="--d:.12s">
        <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Hazra electric scooter" loading="eager">
        <div class="wf-hero__float">
          <i data-lucide="shield-check"></i>
          <span>Free extended cover</span>
        </div>
      </div>
    </div>
  </section>

  <!-- WHY + COVERAGE -->
  <section class="wf-info">
    <div class="wrap wf-info__grid">
      <article class="wf-card reveal-up">
        <h2>Why register?</h2>
        <p>Online registration validates your standard warranty and unlocks exclusive free extended benefits at no extra cost. Support is available over phone or through personal visits.</p>
        <p class="wf-note"><strong>Important:</strong> Warranty activation requires registration on the <em>day of purchase</em>. Without registration, warranty may not apply.</p>
      </article>
      <article class="wf-card wf-card--split reveal-up" style="--d:.1s">
        <div>
          <h3>Standard warranty</h3>
          <p class="wf-muted">Coverage from date of purchase</p>
          <ul class="wf-list">
            <li><span>Chassis, Motor, Controller, Tyres, Graphene battery &amp; chargers</span><b>1 year</b></li>
            <li><span>Lithium battery</span><b>3 years</b></li>
          </ul>
        </div>
        <div>
          <h3>Free extended warranty</h3>
          <p class="wf-muted">Additional period up to 1 year</p>
          <ul class="wf-list">
            <li><span>Chassis</span><b>Covered</b></li>
            <li><span>Motor</span><b>Covered</b></li>
            <li><span>Controller</span><b>Covered</b></li>
          </ul>
          <p class="wf-muted" style="margin-top:10px">As per prevailing company policy.</p>
        </div>
      </article>
    </div>
  </section>

  <!-- visual band -->
  <section class="wf-band" aria-hidden="true">
    <div class="wf-band__track">
      <figure class="wf-band__shot reveal-up"><img src="<?= e(base_url('assets/storm.webp')) ?>" alt="" loading="lazy"></figure>
      <figure class="wf-band__shot reveal-up" style="--d:.08s"><img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="" loading="lazy"></figure>
      <figure class="wf-band__shot reveal-up" style="--d:.16s"><img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="" loading="lazy"></figure>
      <figure class="wf-band__shot reveal-up" style="--d:.24s"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80" alt="" loading="lazy"></figure>
    </div>
  </section>

  <!-- PAID EXTEND INFO -->
  <section class="wf-extend">
    <div class="wrap">
      <div class="wf-extend__head reveal-up">
        <h2>Need more years?</h2>
        <p>After activating <strong>free warranty</strong>, you can buy extra years. Apply within <strong>3 months</strong> of free activation — later applications are not accepted. Free registration must be completed on the purchase day first.</p>
      </div>
      <div class="wf-extend__plans">
        <a class="wf-plan reveal-up" style="--d:.08s" href="<?= e(base_url('warranty-paid')) ?>">
          <span class="wf-plan__yrs">+1 year</span>
          <span class="wf-plan__price">₹2,000</span>
          <span class="wf-plan__cta">Extend warranty <i data-lucide="arrow-up-right"></i></span>
        </a>
        <a class="wf-plan reveal-up" style="--d:.16s" href="<?= e(base_url('warranty-paid')) ?>">
          <span class="wf-plan__yrs">+2 years</span>
          <span class="wf-plan__price">₹3,500</span>
          <span class="wf-plan__cta">Extend warranty <i data-lucide="arrow-up-right"></i></span>
        </a>
      </div>
    </div>
  </section>

  <!-- FORM -->
  <section class="wf-form-sec" id="register">
    <div class="wrap">
      <header class="wf-form-sec__head reveal-up">
        <p class="eyebrow"><i class="sq"></i>FREE WARRANTY REGISTRATION</p>
        <h2>Complete all fields to register</h2>
        <p>Customer, vehicle, battery and dealer details — plus invoice upload.</p>
      </header>

      <form class="wf-form reveal-up" style="--d:.1s" id="warrantyForm" novalidate>
        <!-- Customer -->
        <fieldset class="wf-fs">
          <legend>Customer information</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>Customer full name <small>(as per invoice)</small></span>
              <input type="text" name="name" placeholder="Enter your full name" required autocomplete="name">
            </label>
            <label class="wf-field">
              <span>Email address</span>
              <input type="email" name="userEmail" placeholder="Your email address" required autocomplete="email">
            </label>
            <label class="wf-field">
              <span>Contact number</span>
              <input type="tel" name="userMobile" placeholder="10-digit mobile number" required pattern="[0-9]{10}" maxlength="10" autocomplete="tel">
            </label>
            <div class="wf-field wf-otp">
              <span>OTP verification</span>
              <div class="wf-otp__row">
                <input type="text" name="otp" placeholder="4-digit OTP" maxlength="4" inputmode="numeric">
                <button type="button" class="btn btn--ghost" id="sendOtp">Send OTP</button>
              </div>
            </div>
          </div>
        </fieldset>

        <!-- Vehicle -->
        <fieldset class="wf-fs">
          <legend>Vehicle information</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>Chassis number (VIN)</span>
              <input type="text" name="chassis" placeholder="Enter chassis number" required>
            </label>
            <label class="wf-field">
              <span>Motor number</span>
              <input type="text" name="motor" placeholder="Enter motor number" required>
            </label>
            <label class="wf-field">
              <span>Controller number</span>
              <input type="text" name="controller" placeholder="Enter controller number" required>
            </label>
          </div>
        </fieldset>

        <!-- Battery -->
        <fieldset class="wf-fs">
          <legend>Battery &amp; charger information</legend>
          <p class="wf-hint">If a battery type is selected, all applicable battery and charger serial numbers are mandatory.</p>
          <div class="wf-grid">
            <label class="wf-field">
              <span>Battery type</span>
              <select name="batteryType" id="batteryType" required>
                <option value="">Select battery type</option>
                <option value="na">Not applicable</option>
                <option value="lithium">Lithium battery</option>
                <option value="graphene">Graphene battery</option>
              </select>
            </label>
            <label class="wf-field" id="voltField" hidden>
              <span>Graphene battery voltage</span>
              <select name="batteryVolt" id="batteryVolt">
                <option value="">Select voltage</option>
                <option value="48">48V</option>
                <option value="60">60V</option>
                <option value="72">72V</option>
              </select>
            </label>
            <label class="wf-field wf-batt-extra" hidden>
              <span>Battery serial number</span>
              <input type="text" name="batterySerial" placeholder="Battery serial">
            </label>
            <label class="wf-field wf-batt-extra" hidden>
              <span>Charger serial number</span>
              <input type="text" name="chargerSerial" placeholder="Charger serial">
            </label>
          </div>
        </fieldset>

        <!-- Dealer -->
        <fieldset class="wf-fs">
          <legend>Dealer information</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>State</span>
              <select name="state" id="state" required>
                <option value="">Select state</option>
                <option>Andaman and Nicobar</option>
                <option>Andhra Pradesh</option>
                <option>Arunachal Pradesh</option>
                <option>Assam</option>
                <option>Bihar</option>
                <option>Chandigarh</option>
                <option>Chhattisgarh</option>
                <option>Delhi</option>
                <option>Goa</option>
                <option>Gujarat</option>
                <option>Haryana</option>
                <option>Himachal Pradesh</option>
                <option>Jammu and Kashmir</option>
                <option>Jharkhand</option>
                <option>Karnataka</option>
                <option>Kerala</option>
                <option>Ladakh</option>
                <option>Lakshadweep</option>
                <option>Madhya Pradesh</option>
                <option>Maharashtra</option>
                <option>Manipur</option>
                <option>Meghalaya</option>
                <option>Mizoram</option>
                <option>Nagaland</option>
                <option>Odisha</option>
                <option>Puducherry</option>
                <option>Punjab</option>
                <option>Rajasthan</option>
                <option>Sikkim</option>
                <option>Tamil Nadu</option>
                <option>Telangana</option>
                <option>Tripura</option>
                <option>Uttar Pradesh</option>
                <option>Uttarakhand</option>
                <option>West Bengal</option>
              </select>
            </label>
            <label class="wf-field">
              <span>District</span>
              <select name="dist" id="dist" required>
                <option value="">Select district</option>
              </select>
            </label>
            <label class="wf-field">
              <span>Dealer name</span>
              <select name="dealer" id="dealer" required>
                <option value="">Select dealer</option>
              </select>
            </label>
            <label class="wf-field">
              <span>Dealer email</span>
              <input type="email" name="dealerEmail" id="dealerEmail" placeholder="Dealer email" required>
            </label>
            <label class="wf-field">
              <span>Registration / purchase date</span>
              <input type="date" name="purchase_date" required>
            </label>
            <label class="wf-field">
              <span>Upload purchase invoice</span>
              <input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" required>
              <small class="wf-file-hint">pdf, jpg, jpeg, png · max 500KB</small>
            </label>
          </div>
        </fieldset>

        <!-- T&C -->
        <fieldset class="wf-fs wf-fs--terms">
          <legend>Additional information</legend>
          <label class="wf-check">
            <input type="checkbox" name="agreeTerms" id="agreeTerms" required>
            <span>I agree to the <a href="#" target="_blank" rel="noopener">Terms &amp; Conditions</a> for extended warranty.</span>
          </label>
          <p class="wf-hint">Read and accept the terms to enable Submit.</p>
        </fieldset>

        <div class="wf-actions">
          <p class="wf-secure"><i data-lucide="shield-check"></i> Secure &amp; protected</p>
          <button type="submit" class="btn btn--ink" id="submitBtn" disabled>
            <span>Submit registration</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const form = document.getElementById('warrantyForm');
  const agree = document.getElementById('agreeTerms');
  const submitBtn = document.getElementById('submitBtn');
  const batteryType = document.getElementById('batteryType');
  const voltField = document.getElementById('voltField');
  const battExtras = document.querySelectorAll('.wf-batt-extra');

  agree?.addEventListener('change', () => {
    submitBtn.disabled = !agree.checked;
  });

  batteryType?.addEventListener('change', () => {
    const v = batteryType.value;
    const showBatt = v === 'lithium' || v === 'graphene';
    voltField.hidden = v !== 'graphene';
    battExtras.forEach(el => { el.hidden = !showBatt; });
  });

  document.getElementById('sendOtp')?.addEventListener('click', () => {
    const mobile = form.userMobile.value.trim();
    if (!/^[0-9]{10}$/.test(mobile)) {
      form.userMobile.focus();
      alert('Enter a valid 10-digit contact number first.');
      return;
    }
    alert('OTP would be sent to ' + mobile + ' (connect backend to enable).');
  });

  const dist = document.getElementById('dist');
  const dealer = document.getElementById('dealer');
  document.getElementById('state')?.addEventListener('change', e => {
    const st = e.target.value;
    dist.innerHTML = '<option value="">Select district</option>';
    dealer.innerHTML = '<option value="">Select dealer</option>';
    document.getElementById('dealerEmail').value = '';
    if (!st) return;
    ['Central', 'North', 'South', 'East', 'West'].forEach(d => {
      const o = document.createElement('option');
      o.value = d; o.textContent = d + ' ' + st;
      dist.appendChild(o);
    });
  });
  dist?.addEventListener('change', () => {
    dealer.innerHTML = '<option value="">Select dealer</option>';
    if (!dist.value) return;
    ['Hazra Authorised Dealer', 'City EV Hub', 'Green Mobility Point'].forEach((name, i) => {
      const o = document.createElement('option');
      o.value = name;
      o.textContent = name;
      o.dataset.email = 'dealer' + (i + 1) + '@hazra.example';
      dealer.appendChild(o);
    });
  });
  dealer?.addEventListener('change', () => {
    const opt = dealer.selectedOptions[0];
    document.getElementById('dealerEmail').value = opt?.dataset?.email || '';
  });

  form?.addEventListener('submit', e => {
    e.preventDefault();
    if (!agree.checked) return;
    if (!form.reportValidity()) return;
    const file = form.invoice_file.files[0];
    if (file && file.size > 500 * 1024) {
      alert('Invoice must be 500KB or smaller.');
      return;
    }
    alert('Registration details captured. Connect this form to your warranty backend to save records.');
  });

  /* Page scroll reveals */
  (() => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const els = [...document.querySelectorAll('.wf .reveal-up')];
    if (!els.length) return;
    if (reduce || !('IntersectionObserver' in window)) {
      els.forEach(el => el.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        const el = e.target;
        const d = parseFloat(getComputedStyle(el).getPropertyValue('--d')) || 0;
        setTimeout(() => el.classList.add('is-in'), d * 1000);
        io.unobserve(el);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    els.forEach(el => io.observe(el));
  })();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
