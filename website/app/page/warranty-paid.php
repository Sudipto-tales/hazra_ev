<?php
App::render('head', [
    'pageTitle'       => 'Paid Extended Warranty | Hazra Electrical Bike',
    'pageDescription' => 'Upgrade to paid extended warranty for chassis, motor and controller. Basic ₹2,000 (1 year) or Premium ₹3,500 (2 years).',
    'extraCss'        => ['assets/css/styles/pages/warranty-paid.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<main class="wp">
  <!-- HERO -->
  <section class="wp-hero">
    <div class="wrap wp-hero__grid">
      <div class="wp-hero__copy reveal-up">
        <p class="eyebrow"><i class="sq"></i>SERVICE · PAID WARRANTY</p>
        <h1 class="wp-hero__title">Upgrade to paid<br><span class="hl">extended warranty</span></h1>
        <p class="wp-hero__lead">Extra protection beyond free cover — chassis, motor and controller. Choose 1 or 2 extra years, verify your vehicle, then complete registration.</p>
        <div class="wp-hero__acts">
          <a class="btn btn--ink" href="#plans"><span>Choose a plan</span><i data-lucide="arrow-down"></i></a>
          <a class="btn btn--ghost" href="<?= e(base_url('warranty-free')) ?>">Free registration first</a>
        </div>
      </div>
      <div class="wp-hero__media reveal-up" style="--d:.12s">
        <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Hazra scooter with extended protection" loading="eager">
        <div class="wp-hero__float"><i data-lucide="shield-plus"></i><span>Paid extended protection</span></div>
      </div>
    </div>
  </section>

  <!-- PLANS -->
  <section class="wp-plans" id="plans">
    <div class="wrap">
      <header class="wp-sec-head reveal-up">
        <p class="eyebrow"><i class="sq"></i>CHOOSE YOUR PLAN</p>
        <h2>Extended warranty plans</h2>
        <p>Coverage applies only to <strong>motor, controller and chassis</strong>. Free warranty must already be activated; apply within 3 months of free activation.</p>
      </header>

      <div class="wp-plans__grid">
        <article class="wp-plan reveal-up" data-plan="basic" style="--d:.06s">
          <h3 class="wp-plan__name">Basic plan</h3>
          <div class="wp-plan__price"><b>₹2,000</b><span>/ 1 year</span></div>
          <p class="wp-plan__meta">12 months of extra coverage</p>
          <ul class="wp-plan__list">
            <li><i data-lucide="check"></i> Motor coverage</li>
            <li><i data-lucide="check"></i> Controller coverage</li>
            <li><i data-lucide="check"></i> Chassis coverage</li>
          </ul>
          <button type="button" class="btn btn--ghost wp-plan__btn" data-select-plan="basic">Select Basic</button>
        </article>

        <article class="wp-plan wp-plan--popular reveal-up" data-plan="premium" style="--d:.12s">
          <span class="wp-plan__badge">Most popular</span>
          <h3 class="wp-plan__name">Premium plan</h3>
          <div class="wp-plan__price"><b>₹3,500</b><span>/ 2 years</span></div>
          <p class="wp-plan__meta">24 months of extra coverage</p>
          <ul class="wp-plan__list">
            <li><i data-lucide="check"></i> Motor coverage</li>
            <li><i data-lucide="check"></i> Controller coverage</li>
            <li><i data-lucide="check"></i> Chassis coverage</li>
          </ul>
          <button type="button" class="btn btn--ink wp-plan__btn" data-select-plan="premium">Select Premium</button>
        </article>
      </div>
    </div>
  </section>

  <!-- COVERAGE NOTE -->
  <section class="wp-note-sec">
    <div class="wrap wp-note-card reveal-up">
      <div>
        <h3>Before you continue</h3>
        <ul>
          <li>Free warranty registration must be completed on the <strong>day of purchase</strong>.</li>
          <li>Paid extension must be applied within <strong>3 months</strong> of free warranty activation.</li>
          <li>Extended cover is limited to <strong>chassis, motor and controller</strong>.</li>
        </ul>
      </div>
      <a class="btn btn--ghost" href="<?= e(base_url('warranty-free')) ?>">Go to free registration</a>
    </div>
  </section>

  <!-- MULTI-STEP FLOW -->
  <section class="wp-flow" id="verify">
    <div class="wrap">
      <header class="wp-sec-head reveal-up">
        <p class="eyebrow"><i class="sq"></i>VERIFY &amp; REGISTER</p>
        <h2>Verify your vehicle</h2>
        <p>Enter vehicle and customer details, verify OTP, confirm plan, then complete registration.</p>
      </header>

      <!-- steps indicator -->
      <ol class="wp-steps reveal-up" id="wpSteps" aria-label="Registration steps">
        <li class="is-active" data-step-ind="1"><span>1</span> Vehicle</li>
        <li data-step-ind="2"><span>2</span> OTP</li>
        <li data-step-ind="3"><span>3</span> Plan</li>
        <li data-step-ind="4"><span>4</span> Done</li>
      </ol>

      <div class="wp-panel reveal-up" style="--d:.08s">
        <!-- STEP 1 -->
        <div class="wp-step" id="step1" data-step="1">
          <h3 class="wp-step__title">Step 1 · Vehicle verification</h3>
          <p class="wp-step__lead">Enter your vehicle details to continue</p>

          <form id="formStep1" class="wp-form" novalidate>
            <fieldset class="wp-fs">
              <legend>Vehicle information</legend>
              <div class="wp-grid">
                <label class="wp-field">
                  <span>Chassis number (VIN)</span>
                  <input type="text" name="chassis" placeholder="Chassis number" required>
                </label>
                <label class="wp-field">
                  <span>Motor number</span>
                  <input type="text" name="motor" placeholder="Motor number" required>
                </label>
                <label class="wp-field">
                  <span>Controller number</span>
                  <input type="text" name="controller" placeholder="Controller number" required>
                </label>
              </div>
            </fieldset>

            <fieldset class="wp-fs">
              <legend>Customer information</legend>
              <div class="wp-grid">
                <label class="wp-field">
                  <span>Customer full name</span>
                  <input type="text" name="name" placeholder="Full name" required autocomplete="name">
                </label>
                <label class="wp-field">
                  <span>Contact number</span>
                  <input type="tel" name="mobile" placeholder="10-digit mobile" required pattern="[0-9]{10}" maxlength="10" autocomplete="tel">
                </label>
                <label class="wp-field">
                  <span>Email address</span>
                  <input type="email" name="email" placeholder="Email address" required autocomplete="email">
                </label>
              </div>
            </fieldset>

            <div class="wp-actions">
              <span class="wp-secure"><i data-lucide="shield-check"></i> Details stay secure</span>
              <button type="submit" class="btn btn--ink">Continue to OTP <i data-lucide="arrow-right"></i></button>
            </div>
          </form>
        </div>

        <!-- STEP 2 -->
        <div class="wp-step" id="step2" data-step="2" hidden>
          <h3 class="wp-step__title">Step 2 · OTP verification</h3>
          <p class="wp-step__lead">We sent a code to <strong id="otpTarget">your mobile</strong></p>

          <form id="formStep2" class="wp-form" novalidate>
            <fieldset class="wp-fs">
              <legend>Mobile verification</legend>
              <div class="wp-grid wp-grid--otp">
                <label class="wp-field">
                  <span>Enter 4-digit OTP</span>
                  <input type="text" name="otp" id="otpInput" placeholder="••••" maxlength="4" inputmode="numeric" required>
                </label>
                <div class="wp-field wp-field--btn">
                  <span>&nbsp;</span>
                  <button type="button" class="btn btn--ghost" id="resendOtp">Resend OTP</button>
                </div>
              </div>
            </fieldset>
            <div class="wp-actions">
              <button type="button" class="btn btn--ghost" data-back="1">Back</button>
              <button type="submit" class="btn btn--ink">Verify OTP <i data-lucide="arrow-right"></i></button>
            </div>
          </form>
        </div>

        <!-- STEP 3 -->
        <div class="wp-step" id="step3" data-step="3" hidden>
          <h3 class="wp-step__title">Step 3 · Select extended warranty plan</h3>
          <p class="wp-step__lead">Confirm the plan you want to purchase</p>

          <form id="formStep3" class="wp-form" novalidate>
            <div class="wp-plan-pick">
              <label class="wp-pick">
                <input type="radio" name="plan" value="basic" checked>
                <span class="wp-pick__card">
                  <b>Basic · 1 year</b>
                  <strong>₹2,000</strong>
                  <small>Motor · Controller · Chassis</small>
                </span>
              </label>
              <label class="wp-pick">
                <input type="radio" name="plan" value="premium">
                <span class="wp-pick__card">
                  <b>Premium · 2 years</b>
                  <strong>₹3,500</strong>
                  <small>Motor · Controller · Chassis</small>
                </span>
              </label>
            </div>
            <div class="wp-actions">
              <button type="button" class="btn btn--ghost" data-back="2">Back</button>
              <button type="submit" class="btn btn--ink">Complete registration <i data-lucide="arrow-right"></i></button>
            </div>
          </form>
        </div>

        <!-- STEP 4 success -->
        <div class="wp-step wp-step--success" id="step4" data-step="4" hidden>
          <div class="wp-success">
            <div class="wp-success__icon"><i data-lucide="badge-check"></i></div>
            <h3>Registration successful</h3>
            <p>Your paid extended warranty request is recorded. Proceed to payment to activate the plan.</p>
            <dl class="wp-success__summary" id="successSummary"></dl>
            <div class="wp-actions wp-actions--center">
              <button type="button" class="btn btn--ink" id="proceedPay">Proceed to payment <i data-lucide="credit-card"></i></button>
              <a class="btn btn--ghost" href="<?= e(base_url('index')) ?>">Back to home</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  (() => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const els = [...document.querySelectorAll('.wp .reveal-up')];
    if (!els.length) return;
    if (reduce || !('IntersectionObserver' in window)) {
      els.forEach(el => el.classList.add('is-in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        const d = parseFloat(getComputedStyle(e.target).getPropertyValue('--d')) || 0;
        setTimeout(() => e.target.classList.add('is-in'), d * 1000);
        io.unobserve(e.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
    els.forEach(el => io.observe(el));
  })();

  const state = { plan: 'basic', name: '', mobile: '', email: '', chassis: '', motor: '', controller: '' };

  const steps = [1, 2, 3, 4].map(n => document.getElementById('step' + n));
  const inds = [...document.querySelectorAll('[data-step-ind]')];

  function showStep(n) {
    steps.forEach((el, i) => {
      if (!el) return;
      el.hidden = i + 1 !== n;
    });
    inds.forEach(li => {
      const s = +li.dataset.stepInd;
      li.classList.toggle('is-active', s === n);
      li.classList.toggle('is-done', s < n);
    });
    document.getElementById('verify')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (window.lucide) lucide.createIcons();
  }

  document.querySelectorAll('[data-select-plan]').forEach(btn => {
    btn.addEventListener('click', () => {
      state.plan = btn.dataset.selectPlan;
      document.querySelectorAll('.wp-plan').forEach(p => {
        p.classList.toggle('is-selected', p.dataset.plan === state.plan);
      });
      const radio = document.querySelector(`input[name="plan"][value="${state.plan}"]`);
      if (radio) radio.checked = true;
      showStep(1);
    });
  });

  document.getElementById('formStep1')?.addEventListener('submit', e => {
    e.preventDefault();
    const f = e.target;
    if (!f.reportValidity()) return;
    state.chassis = f.chassis.value.trim();
    state.motor = f.motor.value.trim();
    state.controller = f.controller.value.trim();
    state.name = f.name.value.trim();
    state.mobile = f.mobile.value.trim();
    state.email = f.email.value.trim();
    document.getElementById('otpTarget').textContent = state.mobile;
    showStep(2);
  });

  document.getElementById('resendOtp')?.addEventListener('click', () => {
    alert('OTP would be resent to ' + state.mobile + ' (connect backend).');
  });

  document.getElementById('formStep2')?.addEventListener('submit', e => {
    e.preventDefault();
    const otp = e.target.otp.value.trim();
    if (!/^\d{4}$/.test(otp)) {
      alert('Enter a valid 4-digit OTP.');
      return;
    }
    showStep(3);
  });

  document.getElementById('formStep3')?.addEventListener('submit', e => {
    e.preventDefault();
    const plan = e.target.plan.value;
    state.plan = plan;
    const label = plan === 'premium' ? 'Premium · 2 years · ₹3,500' : 'Basic · 1 year · ₹2,000';
    const sum = document.getElementById('successSummary');
    sum.innerHTML = `
      <div><dt>Customer</dt><dd>${state.name}</dd></div>
      <div><dt>Mobile</dt><dd>${state.mobile}</dd></div>
      <div><dt>Chassis</dt><dd>${state.chassis}</dd></div>
      <div><dt>Plan</dt><dd>${label}</dd></div>`;
    showStep(4);
  });

  document.querySelectorAll('[data-back]').forEach(btn => {
    btn.addEventListener('click', () => showStep(+btn.dataset.back));
  });

  document.getElementById('proceedPay')?.addEventListener('click', () => {
    alert('Connect your payment gateway here for plan: ' + state.plan);
  });
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
