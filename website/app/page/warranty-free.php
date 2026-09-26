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
        <div>
          <p class="eyebrow"><i class="sq"></i>FREE WARRANTY REGISTRATION</p>
          <h2>Register your scooter</h2>
          <p>Verify your mobile number via OTP first to unlock customer, vehicle, battery, and dealer details.</p>
        </div>
      </header>

      <!-- Stepped Progress Indicator -->
      <div class="wf-stepper reveal-up" style="--d:.06s;margin-bottom:24px;">
        <div class="wf-stepper__track">
          <div class="wf-step is-active" id="stepInd1"><span class="wf-step__num">1</span> <span class="wf-step__txt">Mobile OTP</span></div>
          <div class="wf-step" id="stepInd2"><span class="wf-step__num">2</span> <span class="wf-step__txt">Customer</span></div>
          <div class="wf-step" id="stepInd3"><span class="wf-step__num">3</span> <span class="wf-step__txt">Vehicle</span></div>
          <div class="wf-step" id="stepInd4"><span class="wf-step__num">4</span> <span class="wf-step__txt">Dealer &amp; Invoice</span></div>
          <div class="wf-step" id="stepInd5"><span class="wf-step__num">5</span> <span class="wf-step__txt">Complete</span></div>
        </div>
      </div>

      <!-- SUCCESS CONTAINER (Hidden by default) -->
      <div id="registrationSuccess" class="wf-success-card" hidden style="background:var(--surface-0);border:1px solid var(--hair-0);border-radius:var(--wf-radius);padding:clamp(28px, 4vw, 48px);text-align:center;box-shadow:0 18px 48px rgba(24,15,44,.06);max-width:720px;margin:0 auto;">
        <div style="width:68px;height:68px;border-radius:50%;background:#e6f9ed;color:#10b981;display:grid;place-items:center;margin:0 auto 18px;font-size:32px;">
          <i data-lucide="shield-check"></i>
        </div>
        <h2 style="font-family:'Montserrat', sans-serif;font-size:clamp(24px, 3vw, 32px);font-weight:800;margin:0 0 8px;">Registration Submitted!</h2>
        <p style="color:var(--ink-soft-0);font-size:15px;margin:0 0 24px;">Your free warranty registration has been recorded and submitted for administrative approval.</p>

        <div style="background:rgb(var(--chip-rgb) / .4);border:1px dashed var(--hair-0);border-radius:12px;padding:18px 24px;margin-bottom:28px;display:inline-block;min-width:280px;">
          <span style="font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:var(--brand-violet);display:block;margin-bottom:4px;">Warranty Reference Number</span>
          <strong id="successRefNo" style="font-family:monospace;font-size:24px;letter-spacing:1px;color:var(--ink-0);user-select:all;">HZ-FW-20260926-XXXX</strong>
        </div>

        <div style="text-align:left;background:var(--surface-0);border:1px solid var(--hair-0);border-radius:12px;padding:20px;margin-bottom:28px;">
          <h4 style="margin:0 0 12px;font-size:13px;text-transform:uppercase;color:var(--ink-soft-0);letter-spacing:0.5px;">Summary of Details</h4>
          <dl style="display:grid;grid-template-columns:140px 1fr;gap:8px;margin:0;font-size:14px;">
            <dt style="color:var(--ink-soft-0);">Customer:</dt>
            <dd id="successCustomer" style="margin:0;font-weight:600;">—</dd>
            <dt style="color:var(--ink-soft-0);">Mobile:</dt>
            <dd id="successMobile" style="margin:0;font-weight:600;">—</dd>
            <dt style="color:var(--ink-soft-0);">Chassis No:</dt>
            <dd id="successChassis" style="margin:0;font-family:monospace;font-weight:600;color:var(--brand-red);">—</dd>
            <dt style="color:var(--ink-soft-0);">Status:</dt>
            <dd style="margin:0;"><span class="tag warn" style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:4px;background:#fef3c7;color:#b45309;">PENDING APPROVAL</span></dd>
          </dl>
        </div>

        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
          <button type="button" class="btn btn--ink" onclick="window.print()"><span>Print / Save Copy</span><i data-lucide="printer"></i></button>
          <a class="btn btn--ghost" href="<?= e(base_url('warranty-paid')) ?>"><span>Need Paid Extension?</span><i data-lucide="arrow-right"></i></a>
          <a class="btn btn--ghost" href="<?= e(base_url('/')) ?>"><span>Back to Home</span></a>
        </div>
      </div>

      <!-- MAIN REGISTRATION FORM -->
      <form class="wf-form reveal-up" style="--d:.1s" id="warrantyForm" novalidate enctype="multipart/form-data">
        <input type="hidden" name="sessionToken" id="sessionToken" value="">
        <input type="hidden" name="type" value="free">

        <!-- STEP 1: MOBILE & OTP GATE (ALWAYS ACCESSIBLE FIRST) -->
        <fieldset class="wf-fs" id="fsOtp" style="border:2px solid color-mix(in srgb, var(--brand-violet) 40%, var(--hair-0));">
          <legend style="color:var(--brand-violet);font-weight:900;">Step 1 · Mobile Verification (OTP Required)</legend>
          <p class="wf-hint" style="margin-top:2px;">Government and warranty policy require mobile number verification before entering vehicle and customer details.</p>

          <div class="wf-grid" style="grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));align-items:end;">
            <label class="wf-field">
              <span>Mobile number <strong style="color:var(--brand-red)">*</strong></span>
              <input type="tel" name="userMobile" id="userMobile" placeholder="10-digit mobile number" required pattern="[0-9]{10}" maxlength="10" autocomplete="tel">
            </label>

            <div class="wf-field">
              <span>&nbsp;</span>
              <button type="button" class="btn btn--ink" id="sendOtpBtn" style="height:44px;display:flex;align-items:center;justify-content:center;">
                <span id="sendOtpLabel">Send OTP Code</span>
              </button>
            </div>

            <div class="wf-field wf-otp-entry" id="otpEntryGroup" style="display:none;">
              <span>Enter 6-digit OTP code <strong style="color:var(--brand-red)">*</strong></span>
              <div class="wf-otp__row" style="display:flex;gap:8px;">
                <input type="text" name="otpCode" id="otpCode" placeholder="6-digit OTP" maxlength="6" inputmode="numeric" style="flex:1;">
                <button type="button" class="btn btn--ink" id="verifyOtpBtn" style="white-space:nowrap;">
                  <span>Verify OTP</span>
                </button>
              </div>
            </div>
          </div>

          <div id="otpStatusMsg" style="margin-top:12px;font-size:13px;display:none;"></div>
        </fieldset>

        <!-- STEP 2: CUSTOMER INFORMATION (GATED) -->
        <fieldset class="wf-fs wf-gated" id="fsCustomer" disabled style="opacity:0.6;transition:opacity .3s ease;">
          <legend>Step 2 · Customer Information</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>Customer full name <small>(as per invoice)</small> <strong style="color:var(--brand-red)">*</strong></span>
              <input type="text" name="name" id="customerName" placeholder="Enter your full name" required autocomplete="name">
            </label>
            <label class="wf-field">
              <span>Email address <strong style="color:var(--brand-red)">*</strong></span>
              <input type="email" name="userEmail" id="userEmail" placeholder="Your email address" required autocomplete="email">
            </label>
            <label class="wf-field">
              <span>Vehicle Model</span>
              <input type="text" name="model" id="vehicleModel" placeholder="e.g. Striker, Wind Pro, Max E4">
            </label>
          </div>
        </fieldset>

        <!-- STEP 3: VEHICLE & BATTERY INFORMATION (GATED) -->
        <fieldset class="wf-fs wf-gated" id="fsVehicle" disabled style="opacity:0.6;transition:opacity .3s ease;">
          <legend>Step 3 · Vehicle &amp; Battery Information</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>Chassis number (VIN) <strong style="color:var(--brand-red)">*</strong></span>
              <input type="text" name="chassis" id="chassis" placeholder="Enter chassis number (e.g. HZRA...)" required style="text-transform:uppercase;">
            </label>
            <label class="wf-field">
              <span>Motor number <strong style="color:var(--brand-red)">*</strong></span>
              <input type="text" name="motor" id="motor" placeholder="Enter motor number" required style="text-transform:uppercase;">
            </label>
            <label class="wf-field">
              <span>Controller number</span>
              <input type="text" name="controller" id="controller" placeholder="Enter controller number" style="text-transform:uppercase;">
            </label>
            <label class="wf-field">
              <span>Battery type <strong style="color:var(--brand-red)">*</strong></span>
              <select name="batteryType" id="batteryType" required>
                <option value="">Select battery type</option>
                <option value="na">Not applicable / Vehicle only</option>
                <option value="lithium">Lithium battery (3 Years)</option>
                <option value="graphene">Graphene battery (1 Year)</option>
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
              <input type="text" name="batterySerial" placeholder="Battery serial number">
            </label>
            <label class="wf-field wf-batt-extra" hidden>
              <span>Charger serial number</span>
              <input type="text" name="chargerSerial" placeholder="Charger serial number">
            </label>
          </div>
        </fieldset>

        <!-- STEP 4: DEALER & INVOICE DETAILS (GATED) -->
        <fieldset class="wf-fs wf-gated" id="fsDealer" disabled style="opacity:0.6;transition:opacity .3s ease;">
          <legend>Step 4 · Dealer &amp; Purchase Invoice</legend>
          <div class="wf-grid">
            <label class="wf-field">
              <span>State <strong style="color:var(--brand-red)">*</strong></span>
              <select name="state" id="state" required>
                <option value="">Select state</option>
                <option>West Bengal</option>
                <option>Andhra Pradesh</option>
                <option>Assam</option>
                <option>Bihar</option>
                <option>Chhattisgarh</option>
                <option>Delhi</option>
                <option>Gujarat</option>
                <option>Haryana</option>
                <option>Jharkhand</option>
                <option>Karnataka</option>
                <option>Madhya Pradesh</option>
                <option>Maharashtra</option>
                <option>Odisha</option>
                <option>Punjab</option>
                <option>Rajasthan</option>
                <option>Tamil Nadu</option>
                <option>Telangana</option>
                <option>Uttar Pradesh</option>
              </select>
            </label>
            <label class="wf-field">
              <span>District <strong style="color:var(--brand-red)">*</strong></span>
              <select name="dist" id="dist" required>
                <option value="">Select district</option>
              </select>
            </label>
            <label class="wf-field">
              <span>Dealer name <strong style="color:var(--brand-red)">*</strong></span>
              <select name="dealer" id="dealer" required>
                <option value="">Select dealer</option>
              </select>
            </label>
            <label class="wf-field">
              <span>Dealer email</span>
              <input type="email" name="dealerEmail" id="dealerEmail" placeholder="Dealer email" readonly style="background:rgba(0,0,0,0.03);">
            </label>
            <label class="wf-field">
              <span>Purchase date <strong style="color:var(--brand-red)">*</strong></span>
              <input type="date" name="purchase_date" id="purchase_date" required max="<?= date('Y-m-d') ?>">
            </label>
            <label class="wf-field">
              <span>Upload purchase invoice <strong style="color:var(--brand-red)">*</strong></span>
              <input type="file" name="invoice_file" id="invoice_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
              <small class="wf-file-hint">PDF, JPG, PNG or WebP · Max 5MB</small>
            </label>
          </div>
        </fieldset>

        <!-- STEP 5: TERMS & SUBMIT -->
        <fieldset class="wf-fs wf-fs--terms wf-gated" id="fsTerms" disabled style="opacity:0.6;transition:opacity .3s ease;">
          <legend>Step 5 · Confirmation &amp; Policy Acceptance</legend>
          <label class="wf-check" style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
            <input type="checkbox" name="agreeTerms" id="agreeTerms" required style="margin-top:3px;">
            <span>I declare that all entered vehicle details, chassis number, and uploaded invoice are genuine. I agree to the <a href="#" target="_blank" rel="noopener">Warranty Terms &amp; Conditions</a>.</span>
          </label>
        </fieldset>

        <div class="wf-actions" style="margin-top:10px;">
          <p class="wf-secure"><i data-lucide="shield-check"></i> 256-bit encrypted &amp; verified submission</p>
          <button type="submit" class="btn btn--ink" id="submitBtn" disabled>
            <span id="submitLabel">Submit Free Registration</span>
            <i data-lucide="arrow-right"></i>
          </button>
        </div>
      </form>
    </div>
  </section>
</main>

<style>
.wf-stepper__track {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 4px 0 10px;
}
.wf-step {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 700;
  background: rgb(var(--chip-rgb) / .4);
  color: var(--ink-soft-0);
  border: 1px solid var(--hair-0);
  white-space: nowrap;
}
.wf-step__num {
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--surface-0);
  font-size: 11px;
}
.wf-step.is-active {
  background: color-mix(in srgb, var(--brand-violet) 12%, var(--surface-0));
  border-color: var(--brand-violet);
  color: var(--brand-violet);
}
.wf-step.is-active .wf-step__num {
  background: var(--brand-violet);
  color: #fff;
}
.wf-step.is-done {
  background: #e6f9ed;
  border-color: #10b981;
  color: #10b981;
}
.wf-step.is-done .wf-step__num {
  background: #10b981;
  color: #fff;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const form          = document.getElementById('warrantyForm');
  const userMobile    = document.getElementById('userMobile');
  const sendOtpBtn    = document.getElementById('sendOtpBtn');
  const sendOtpLabel  = document.getElementById('sendOtpLabel');
  const otpEntryGroup = document.getElementById('otpEntryGroup');
  const otpCode       = document.getElementById('otpCode');
  const verifyOtpBtn  = document.getElementById('verifyOtpBtn');
  const otpStatusMsg  = document.getElementById('otpStatusMsg');
  const sessionToken  = document.getElementById('sessionToken');
  const agreeTerms    = document.getElementById('agreeTerms');
  const submitBtn     = document.getElementById('submitBtn');
  const submitLabel   = document.getElementById('submitLabel');
  const gatedFs       = document.querySelectorAll('.wf-gated');

  // Stepper indicators
  const stepInd1 = document.getElementById('stepInd1');
  const stepInd2 = document.getElementById('stepInd2');
  const stepInd3 = document.getElementById('stepInd3');
  const stepInd4 = document.getElementById('stepInd4');
  const stepInd5 = document.getElementById('stepInd5');

  let cooldownTimer = null;
  const STORAGE_KEY = 'hazra_warranty_otp_free';

  // 1. Check for existing verified OTP session in sessionStorage
  (() => {
    try {
      const saved = sessionStorage.getItem(STORAGE_KEY);
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.sessionToken && parsed.mobile && parsed.expiresAt) {
          if (new Date(parsed.expiresAt).getTime() > Date.now()) {
            unlockGatedSteps(parsed.sessionToken, parsed.mobile);
            userMobile.value = parsed.mobile;
          } else {
            sessionStorage.removeItem(STORAGE_KEY);
          }
        }
      }
    } catch (e) {}
  })();

  function unlockGatedSteps(token, mobile) {
    sessionToken.value = token;
    userMobile.value = mobile;
    userMobile.readOnly = true;
    userMobile.style.background = 'rgba(16, 185, 129, 0.08)';
    userMobile.style.borderColor = '#10b981';

    sendOtpBtn.style.display = 'none';
    otpEntryGroup.style.display = 'none';

    otpStatusMsg.style.display = 'block';
    otpStatusMsg.innerHTML = '<span style="color:#10b981;font-weight:700;"><i data-lucide="check-circle" style="vertical-align:middle;width:16px;height:16px;"></i> Mobile Verified (' + mobile + ')</span> · You can now fill in the remaining details.';
    if (window.lucide) lucide.createIcons();

    // Enable gated fieldsets
    gatedFs.forEach(fs => {
      fs.disabled = false;
      fs.style.opacity = '1';
    });

    stepInd1.classList.remove('is-active');
    stepInd1.classList.add('is-done');
    stepInd2.classList.add('is-active');

    checkSubmitReadiness();
  }

  // 2. Send OTP
  sendOtpBtn.addEventListener('click', async () => {
    const mobile = userMobile.value.trim();
    if (!/^[5-9][0-9]{9}$/.test(mobile)) {
      alert('Please enter a valid 10-digit Indian mobile number.');
      userMobile.focus();
      return;
    }

    sendOtpBtn.disabled = true;
    sendOtpLabel.textContent = 'Sending...';
    otpStatusMsg.style.display = 'none';

    try {
      const res = await fetch('/api/v1/warranty/otp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ mobile: mobile, purpose: 'warranty_free' }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Failed to send OTP.');
      }

      otpEntryGroup.style.display = 'block';
      otpCode.focus();

      otpStatusMsg.style.display = 'block';
      otpStatusMsg.innerHTML = '<span style="color:var(--brand-violet);font-weight:600;">' + (data.data?.message || 'OTP code dispatched to ' + mobile) + '</span>';

      // If debugCode returned in dev mode, prefill or inform
      if (data.data?.debugCode) {
        otpStatusMsg.innerHTML += ' <br><small style="color:#666;">(Dev Mode OTP: <strong>' + data.data.debugCode + '</strong>)</small>';
      }

      startCooldown(data.data?.cooldownSeconds || 60);
    } catch (err) {
      alert(err.message || 'Error sending OTP. Please try again.');
      sendOtpBtn.disabled = false;
      sendOtpLabel.textContent = 'Send OTP Code';
    }
  });

  function startCooldown(seconds) {
    clearInterval(cooldownTimer);
    let rem = seconds;
    sendOtpBtn.disabled = true;

    cooldownTimer = setInterval(() => {
      rem--;
      if (rem <= 0) {
        clearInterval(cooldownTimer);
        sendOtpBtn.disabled = false;
        sendOtpLabel.textContent = 'Resend OTP';
      } else {
        sendOtpLabel.textContent = 'Resend in ' + rem + 's';
      }
    }, 1000);
  }

  // 3. Verify OTP
  verifyOtpBtn.addEventListener('click', async () => {
    const code = otpCode.value.trim();
    const mobile = userMobile.value.trim();

    if (!code || code.length < 4) {
      alert('Please enter the verification code sent to your phone.');
      otpCode.focus();
      return;
    }

    verifyOtpBtn.disabled = true;
    verifyOtpBtn.textContent = 'Verifying...';

    try {
      const res = await fetch('/api/v1/warranty/otp/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ mobile: mobile, code: code, purpose: 'warranty_free' }),
      });
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Invalid or expired OTP.');
      }

      const token = data.data?.sessionToken;
      const expiresAt = data.data?.expiresAt;

      // Save to sessionStorage
      try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
          sessionToken: token,
          mobile: mobile,
          expiresAt: expiresAt,
        }));
      } catch (e) {}

      clearInterval(cooldownTimer);
      unlockGatedSteps(token, mobile);
    } catch (err) {
      alert(err.message || 'OTP verification failed.');
      verifyOtpBtn.disabled = false;
      verifyOtpBtn.textContent = 'Verify OTP';
    }
  });

  // 4. Battery selector toggle
  const batteryType = document.getElementById('batteryType');
  const voltField   = document.getElementById('voltField');
  const battExtras  = document.querySelectorAll('.wf-batt-extra');

  batteryType?.addEventListener('change', () => {
    const v = batteryType.value;
    const showBatt = v === 'lithium' || v === 'graphene';
    if (voltField) voltField.hidden = v !== 'graphene';
    battExtras.forEach(el => { el.hidden = !showBatt; });
  });

  // 5. Dealer cascade
  const dist = document.getElementById('dist');
  const dealer = document.getElementById('dealer');
  const dealerEmail = document.getElementById('dealerEmail');

  const dealerDirectory = {
    'West Bengal': {
      'Bardhaman': [
        { name: 'Hazra Electric Bike — Bardhaman Main', email: 'sales@hazraev.com' },
        { name: 'City Green Mobility Hub', email: 'service@hazraev.com' },
      ],
      'Kolkata': [
        { name: 'Hazra EV Hub Kolkata', email: 'kolkata@hazraev.com' },
        { name: 'EcoRide Mobility Point', email: 'support@hazraev.com' },
      ],
      'Hooghly': [
        { name: 'Hazra EV Hooghly Showroom', email: 'hooghly@hazraev.com' },
      ],
      'Howrah': [
        { name: 'Hazra EV Howrah Point', email: 'howrah@hazraev.com' },
      ],
    },
  };

  document.getElementById('state')?.addEventListener('change', e => {
    const st = e.target.value;
    dist.innerHTML = '<option value="">Select district</option>';
    dealer.innerHTML = '<option value="">Select dealer</option>';
    dealerEmail.value = '';

    if (!st) return;
    const stateData = dealerDirectory[st];
    if (stateData) {
      Object.keys(stateData).forEach(d => {
        const o = document.createElement('option');
        o.value = d; o.textContent = d;
        dist.appendChild(o);
      });
    } else {
      ['Central', 'North', 'South', 'East', 'West'].forEach(d => {
        const o = document.createElement('option');
        o.value = d + ' ' + st; o.textContent = d + ' ' + st;
        dist.appendChild(o);
      });
    }
  });

  dist?.addEventListener('change', () => {
    dealer.innerHTML = '<option value="">Select dealer</option>';
    dealerEmail.value = '';
    const st = document.getElementById('state').value;
    const dVal = dist.value;
    if (!dVal) return;

    const list = (dealerDirectory[st] && dealerDirectory[st][dVal]) || [
      { name: 'Hazra Authorised Dealer ' + dVal, email: 'dealer.' + dVal.toLowerCase().replace(/\s+/g, '') + '@hazraev.com' },
      { name: 'City EV Hub ' + dVal, email: 'info@hazraev.com' },
    ];

    list.forEach(item => {
      const o = document.createElement('option');
      o.value = item.name;
      o.textContent = item.name;
      o.dataset.email = item.email;
      dealer.appendChild(o);
    });
  });

  dealer?.addEventListener('change', () => {
    const opt = dealer.selectedOptions[0];
    dealerEmail.value = opt?.dataset?.email || '';
  });

  // 6. Agreement & Submit Readiness
  agreeTerms?.addEventListener('change', checkSubmitReadiness);

  function checkSubmitReadiness() {
    if (sessionToken.value && agreeTerms.checked) {
      submitBtn.disabled = false;
    } else {
      submitBtn.disabled = true;
    }
  }

  // 7. Form Submission
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!sessionToken.value) {
      alert('You must verify your mobile number with OTP first.');
      userMobile.focus();
      return;
    }

    if (!form.reportValidity()) return;

    const fileInput = document.getElementById('invoice_file');
    const file = fileInput?.files[0];
    if (!file) {
      alert('Please upload your purchase invoice file.');
      fileInput.focus();
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      alert('Invoice file size must not exceed 5MB.');
      fileInput.focus();
      return;
    }

    submitBtn.disabled = true;
    submitLabel.textContent = 'Submitting Registration...';

    const formData = new FormData(form);

    try {
      const res = await fetch('/api/v1/warranty/registrations', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData,
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data?.error?.message || data?.message || 'Submission failed.');
      }

      // Clear stored OTP session upon successful registration
      sessionStorage.removeItem(STORAGE_KEY);

      // Populate & show success screen
      const ref = data.data?.reference_no || 'HZ-FW-' + Date.now();
      document.getElementById('successRefNo').textContent = ref;
      document.getElementById('successCustomer').textContent = form.name.value;
      document.getElementById('successMobile').textContent = userMobile.value;
      document.getElementById('successChassis').textContent = form.chassis.value.toUpperCase();

      form.style.display = 'none';
      const successCard = document.getElementById('registrationSuccess');
      successCard.hidden = false;
      successCard.scrollIntoView({ behavior: 'smooth', block: 'start' });

      stepInd2.classList.remove('is-active'); stepInd2.classList.add('is-done');
      stepInd3.classList.add('is-done');
      stepInd4.classList.add('is-done');
      stepInd5.classList.add('is-active', 'is-done');
      if (window.lucide) lucide.createIcons();

    } catch (err) {
      alert('Error: ' + err.message);
      submitBtn.disabled = false;
      submitLabel.textContent = 'Submit Free Registration';
    }
  });

  /* Scroll reveal animations */
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
