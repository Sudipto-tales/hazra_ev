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
        <li class="is-active" data-step-ind="1"><span>1</span> Mobile OTP</li>
        <li data-step-ind="2"><span>2</span> Vehicle Lookup</li>
        <li data-step-ind="3"><span>3</span> Select Plan</li>
        <li data-step-ind="4"><span>4</span> Done</li>
      </ol>

      <div class="wp-panel reveal-up" style="--d:.08s">
        <!-- STEP 1: MOBILE & OTP -->
        <div class="wp-step" id="step1" data-step="1">
          <h3 class="wp-step__title">Step 1 · Mobile Verification</h3>
          <p class="wp-step__lead">Verify your registered 10-digit mobile number via OTP first.</p>

          <form id="formStep1" class="wp-form" novalidate>
            <fieldset class="wp-fs">
              <legend>Mobile number</legend>
              <div class="wp-grid">
                <label class="wp-field">
                  <span>Registered mobile number <strong style="color:var(--brand-red)">*</strong></span>
                  <input type="tel" name="mobile" id="paidMobile" placeholder="10-digit mobile number" required pattern="[0-9]{10}" maxlength="10" autocomplete="tel">
                </label>
                <div class="wp-field wp-field--btn" style="display:flex;align-items:flex-end;">
                  <button type="button" class="btn btn--ink" id="paidSendOtpBtn" style="height:44px;width:100%;justify-content:center;">
                    <span id="paidSendOtpLabel">Send OTP Code</span>
                  </button>
                </div>
              </div>

              <!-- OTP input (shown after send) -->
              <div id="paidOtpGroup" style="display:none;margin-top:16px;">
                <div class="wp-grid wp-grid--otp">
                  <label class="wp-field">
                    <span>Enter 6-digit OTP <strong style="color:var(--brand-red)">*</strong></span>
                    <input type="text" name="otp" id="paidOtpInput" placeholder="6-digit code" maxlength="6" inputmode="numeric">
                  </label>
                  <div class="wp-field wp-field--btn" style="display:flex;align-items:flex-end;">
                    <button type="button" class="btn btn--ink" id="paidVerifyOtpBtn" style="height:44px;width:100%;justify-content:center;">
                      <span>Verify &amp; Continue</span>
                    </button>
                  </div>
                </div>
              </div>

              <div id="paidOtpMsg" style="margin-top:12px;font-size:13px;display:none;"></div>
            </fieldset>
          </form>
        </div>

        <!-- STEP 2: VEHICLE LOOKUP -->
        <div class="wp-step" id="step2" data-step="2" hidden>
          <h3 class="wp-step__title">Step 2 · Verify Free Warranty Registration</h3>
          <p class="wp-step__lead">Enter your vehicle's Chassis Number (VIN) or Free Warranty Reference Number.</p>

          <form id="formStep2" class="wp-form" novalidate>
            <fieldset class="wp-fs">
              <legend>Vehicle verification</legend>
              <div class="wp-grid">
                <label class="wp-field">
                  <span>Chassis number (VIN) <strong style="color:var(--brand-red)">*</strong></span>
                  <input type="text" name="chassis" id="paidChassisInput" placeholder="Chassis number (e.g. HZRA...)" required style="text-transform:uppercase;">
                </label>
                <div class="wp-field wp-field--btn" style="display:flex;align-items:flex-end;">
                  <button type="button" class="btn btn--ink" id="lookupVehicleBtn" style="height:44px;width:100%;justify-content:center;">
                    <span id="lookupBtnLabel">Verify Coverage</span>
                  </button>
                </div>
              </div>

              <!-- Lookup Status / Verified Vehicle Card -->
              <div id="lookupResultCard" style="display:none;margin-top:16px;background:var(--surface-0);border:1px solid var(--hair-0);border-radius:12px;padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                  <span style="color:#10b981;font-size:20px;"><i data-lucide="badge-check"></i></span>
                  <strong style="color:#10b981;">Eligible for Extended Warranty</strong>
                </div>
                <dl style="display:grid;grid-template-columns:130px 1fr;gap:6px;font-size:13px;margin:0;">
                  <dt style="color:var(--ink-soft-0);">Customer:</dt>
                  <dd id="lookupCustomer" style="margin:0;font-weight:600;">—</dd>
                  <dt style="color:var(--ink-soft-0);">Chassis No:</dt>
                  <dd id="lookupChassis" style="margin:0;font-family:monospace;font-weight:600;color:var(--brand-red);">—</dd>
                  <dt style="color:var(--ink-soft-0);">Motor No:</dt>
                  <dd id="lookupMotor" style="margin:0;font-family:monospace;">—</dd>
                  <dt style="color:var(--ink-soft-0);">Free Warranty Ref:</dt>
                  <dd id="lookupRef" style="margin:0;font-family:monospace;font-weight:600;">—</dd>
                  <dt style="color:var(--ink-soft-0);">Purchase Date:</dt>
                  <dd id="lookupPurchaseDate" style="margin:0;">—</dd>
                </dl>
              </div>

              <div id="lookupErrorMsg" style="display:none;margin-top:12px;color:var(--brand-red);font-size:13px;font-weight:600;"></div>
            </fieldset>

            <div class="wp-actions">
              <button type="button" class="btn btn--ghost" data-back="1">Back</button>
              <button type="submit" class="btn btn--ink" id="continueToPlanBtn" disabled>Continue to Plan <i data-lucide="arrow-right"></i></button>
            </div>
          </form>
        </div>

        <!-- STEP 3: PLAN SELECTION -->
        <div class="wp-step" id="step3" data-step="3" hidden>
          <h3 class="wp-step__title">Step 3 · Select Extended Warranty Plan</h3>
          <p class="wp-step__lead">Choose the extension period you want for your scooter.</p>

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

            <fieldset class="wp-fs" style="margin-top:16px;">
              <legend>Review &amp; Confirmation</legend>
              <label class="wf-check" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:13px;">
                <input type="checkbox" id="agreePaidTerms" required style="margin-top:3px;">
                <span>I confirm the vehicle details and agree to the <a href="#" target="_blank">Paid Warranty Extension Terms</a>.</span>
              </label>
            </fieldset>

            <div class="wp-actions">
              <button type="button" class="btn btn--ghost" data-back="2">Back</button>
              <button type="submit" class="btn btn--ink" id="submitPaidBtn">
                <span id="submitPaidLabel">Complete Registration</span>
                <i data-lucide="arrow-right"></i>
              </button>
            </div>
          </form>
        </div>

        <!-- STEP 4: SUCCESS -->
        <div class="wp-step wp-step--success" id="step4" data-step="4" hidden>
          <div class="wp-success">
            <div class="wp-success__icon" style="background:#e6f9ed;color:#10b981;"><i data-lucide="badge-check"></i></div>
            <h3>Extension Request Recorded</h3>
            <p>Your paid extended warranty request has been submitted with reference number:</p>
            <div style="background:rgb(var(--chip-rgb) / .4);border:1px dashed var(--hair-0);border-radius:12px;padding:16px 24px;margin:16px auto 20px;display:inline-block;">
              <strong id="paidSuccessRef" style="font-family:monospace;font-size:24px;letter-spacing:1px;color:var(--ink-0);">HZ-PW-20260926-XXXX</strong>
            </div>

            <dl class="wp-success__summary" id="successSummary"></dl>

            <div class="wp-actions wp-actions--center" style="margin-top:24px;">
              <button type="button" class="btn btn--ink" onclick="window.print()">Print Details <i data-lucide="printer"></i></button>
              <a class="btn btn--ghost" href="<?= e(base_url('/')) ?>">Back to Home</a>
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

  const state = {
    plan: 'basic',
    name: '',
    mobile: '',
    email: '',
    chassis: '',
    motor: '',
    controller: '',
    sessionToken: '',
    parentRegId: '',
    freeReference: '',
  };

  const steps = [1, 2, 3, 4].map(n => document.getElementById('step' + n));
  const inds = [...document.querySelectorAll('[data-step-ind]')];
  const STORAGE_KEY = 'hazra_warranty_otp_paid';

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

  // Restore stored session if exists
  (() => {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.sessionToken && parsed.mobile && new Date(parsed.expiresAt).getTime() > Date.now()) {
          state.sessionToken = parsed.sessionToken;
          state.mobile = parsed.mobile;
          document.getElementById('paidMobile').value = parsed.mobile;
          document.getElementById('paidMobile').readOnly = true;
          document.getElementById('paidSendOtpBtn').style.display = 'none';
          document.getElementById('paidOtpMsg').style.display = 'block';
          document.getElementById('paidOtpMsg').innerHTML = '<span style="color:#10b981;font-weight:700;">✓ Mobile Verified (' + parsed.mobile + ')</span>';
          showStep(2);
        }
      }
    } catch (e) {}
  })();

  // 1. Send OTP (Paid)
  const paidSendOtpBtn = document.getElementById('paidSendOtpBtn');
  const paidSendOtpLabel = document.getElementById('paidSendOtpLabel');
  const paidOtpGroup = document.getElementById('paidOtpGroup');
  const paidOtpInput = document.getElementById('paidOtpInput');
  const paidVerifyOtpBtn = document.getElementById('paidVerifyOtpBtn');
  const paidOtpMsg = document.getElementById('paidOtpMsg');

  paidSendOtpBtn.addEventListener('click', async () => {
    const mobile = document.getElementById('paidMobile').value.trim();
    if (!/^[5-9][0-9]{9}$/.test(mobile)) {
      alert('Please enter a valid 10-digit Indian mobile number.');
      document.getElementById('paidMobile').focus();
      return;
    }

    paidSendOtpBtn.disabled = true;
    paidSendOtpLabel.textContent = 'Sending...';

    try {
      const res = await fetch('/api/v1/warranty/otp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ mobile: mobile, purpose: 'warranty_paid' }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Failed to send OTP.');
      }

      state.mobile = mobile;
      paidOtpGroup.style.display = 'block';
      paidOtpInput.focus();

      paidOtpMsg.style.display = 'block';
      paidOtpMsg.innerHTML = '<span style="color:var(--brand-violet);font-weight:600;">' + (data.data?.message || 'OTP sent successfully.') + '</span>';
      if (data.data?.debugCode) {
        paidOtpMsg.innerHTML += ' <br><small style="color:#666;">(Dev Mode OTP: <strong>' + data.data.debugCode + '</strong>)</small>';
      }

      startPaidCooldown(data.data?.cooldownSeconds || 60);
    } catch (err) {
      alert(err.message || 'Error sending OTP.');
      paidSendOtpBtn.disabled = false;
      paidSendOtpLabel.textContent = 'Send OTP Code';
    }
  });

  function startPaidCooldown(sec) {
    let rem = sec;
    paidSendOtpBtn.disabled = true;
    const t = setInterval(() => {
      rem--;
      if (rem <= 0) {
        clearInterval(t);
        paidSendOtpBtn.disabled = false;
        paidSendOtpLabel.textContent = 'Resend OTP';
      } else {
        paidSendOtpLabel.textContent = 'Resend in ' + rem + 's';
      }
    }, 1000);
  }

  // 2. Verify OTP (Paid)
  paidVerifyOtpBtn.addEventListener('click', async () => {
    const code = paidOtpInput.value.trim();
    const mobile = document.getElementById('paidMobile').value.trim();

    if (!code || code.length < 4) {
      alert('Please enter the verification code.');
      paidOtpInput.focus();
      return;
    }

    paidVerifyOtpBtn.disabled = true;
    paidVerifyOtpBtn.textContent = 'Verifying...';

    try {
      const res = await fetch('/api/v1/warranty/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ mobile: mobile, code: code, purpose: 'warranty_paid' }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Invalid OTP code.');
      }

      state.sessionToken = data.data?.sessionToken;
      state.mobile = mobile;

      try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
          sessionToken: state.sessionToken,
          mobile: mobile,
          expiresAt: data.data?.expiresAt,
        }));
      } catch (e) {}

      showStep(2);
    } catch (err) {
      alert(err.message || 'OTP verification failed.');
      paidVerifyOtpBtn.disabled = false;
      paidVerifyOtpBtn.textContent = 'Verify & Continue';
    }
  });

  // 3. Vehicle Lookup
  const lookupBtn = document.getElementById('lookupVehicleBtn');
  const lookupBtnLabel = document.getElementById('lookupBtnLabel');
  const lookupCard = document.getElementById('lookupResultCard');
  const lookupError = document.getElementById('lookupErrorMsg');
  const continueToPlanBtn = document.getElementById('continueToPlanBtn');

  lookupBtn.addEventListener('click', async () => {
    const chassis = document.getElementById('paidChassisInput').value.trim();
    if (!chassis) {
      alert('Please enter your Chassis Number or Free Warranty Reference.');
      document.getElementById('paidChassisInput').focus();
      return;
    }

    lookupBtn.disabled = true;
    lookupBtnLabel.textContent = 'Checking...';
    lookupCard.style.display = 'none';
    lookupError.style.display = 'none';
    continueToPlanBtn.disabled = true;

    try {
      const url = '/api/v1/warranty/registrations/lookup?chassis=' + encodeURIComponent(chassis);
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'No approved free warranty found for this vehicle.');
      }

      const vehicle = data.data;
      state.parentRegId = vehicle.id;
      state.freeReference = vehicle.reference_no;
      state.chassis = vehicle.chassis_no;
      state.motor = vehicle.motor_no;
      state.controller = vehicle.controller_no;
      state.name = vehicle.customer_name;
      state.email = vehicle.email;

      document.getElementById('lookupCustomer').textContent = vehicle.customer_name;
      document.getElementById('lookupChassis').textContent = vehicle.chassis_no;
      document.getElementById('lookupMotor').textContent = vehicle.motor_no || '—';
      document.getElementById('lookupRef').textContent = vehicle.reference_no;
      document.getElementById('lookupPurchaseDate').textContent = vehicle.purchase_date;

      lookupCard.style.display = 'block';
      continueToPlanBtn.disabled = false;
      if (window.lucide) lucide.createIcons();
    } catch (err) {
      lookupError.style.display = 'block';
      lookupError.textContent = err.message;
    } finally {
      lookupBtn.disabled = false;
      lookupBtnLabel.textContent = 'Verify Coverage';
    }
  });

  document.getElementById('formStep2')?.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!state.parentRegId) {
      alert('Please verify your vehicle chassis first.');
      return;
    }
    showStep(3);
  });

  // 4. Plan Selection & Submit
  document.getElementById('formStep3')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const agree = document.getElementById('agreePaidTerms');
    if (!agree.checked) {
      alert('Please accept the Terms & Conditions.');
      return;
    }

    const plan = document.querySelector('input[name="plan"]:checked')?.value || 'basic';
    state.plan = plan;

    const submitBtn = document.getElementById('submitPaidBtn');
    const submitLabel = document.getElementById('submitPaidLabel');
    submitBtn.disabled = true;
    submitLabel.textContent = 'Submitting...';

    const payload = {
      type: 'paid',
      sessionToken: state.sessionToken,
      mobile: state.mobile,
      name: state.name,
      email: state.email,
      chassis: state.chassis,
      motor: state.motor,
      controller: state.controller,
      plan: plan,
      free_reference: state.freeReference,
    };

    try {
      const res = await fetch('/api/v1/warranty/registrations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Failed to submit extended warranty.');
      }

      sessionStorage.removeItem(STORAGE_KEY);

      const ref = data.data?.reference_no || 'HZ-PW-' + Date.now();
      document.getElementById('paidSuccessRef').textContent = ref;

      const planLabel = plan === 'premium' ? 'Premium · 2 Years · ₹3,500' : 'Basic · 1 Year · ₹2,000';
      const sum = document.getElementById('successSummary');
      sum.innerHTML = `
        <div><dt>Customer:</dt><dd>${state.name}</dd></div>
        <div><dt>Mobile:</dt><dd>${state.mobile}</dd></div>
        <div><dt>Chassis No:</dt><dd>${state.chassis}</dd></div>
        <div><dt>Selected Plan:</dt><dd>${planLabel}</dd></div>
        <div><dt>Payment Status:</dt><dd><span class="tag warn" style="font-size:11px;font-weight:700;">UNPAID / UNDER REVIEW</span></dd></div>`;

      showStep(4);
    } catch (err) {
      alert('Error: ' + err.message);
      submitBtn.disabled = false;
      submitLabel.textContent = 'Complete Registration';
    }
  });

  document.querySelectorAll('[data-back]').forEach(btn => {
    btn.addEventListener('click', () => showStep(+btn.dataset.back));
  });
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
