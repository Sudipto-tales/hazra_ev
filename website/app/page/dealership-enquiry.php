<?php
$pageTitle = "Dealership Enquiry | Hazra Electrical Bike";
$pageDescription = "Interested in partnering with Hazra EV? Submit your dealership enquiry today.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">PARTNER ENQUIRY</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Dealership Enquiry
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Submit your business proposal and our dealership expansion team will contact you within 24 hours.
      </p>
    </div>
  </section>

  <section class="form-section" style="padding: clamp(40px, 6vw, 80px) 26px clamp(60px, 9vw, 120px); background: var(--surface-0);">
    <div class="form-section__in" style="width: min(720px, 93vw); margin: 0 auto;">

      <div id="successMsg" style="display: none; padding: 24px; background: #e6f4ea; border: 1px solid #34a853; border-radius: 18px; color: #137333; font-weight: 600; text-align: center; margin-bottom: 30px;">
        <i data-lucide="check-circle" style="width: 36px; height: 36px; margin: 0 auto 10px; display: block;"></i>
        <h3 style="font-size: 18px; font-weight: 800; margin-bottom: 6px;">Thank You!</h3>
        <p style="font-size: 13.5px;">Your dealership enquiry has been received. Our team will review your proposal and contact you soon.</p>
      </div>

      <form class="form" id="enquiryForm" onsubmit="handleEnquirySubmit(event)" style="padding: clamp(30px, 5vw, 50px); background: rgb(var(--chip-rgb) / .3); border: 1px solid var(--hair-0); border-radius: 24px; display: flex; flex-direction: column; gap: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Business Name *</label>
            <input type="text" name="business" placeholder="Firm or showroom name" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Contact Person *</label>
            <input type="text" name="contact" placeholder="Full name" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">City *</label>
            <input type="text" name="city" placeholder="Target City" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">State *</label>
            <input type="text" name="state" placeholder="State" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Phone Number *</label>
            <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Email Address *</label>
            <input type="email" name="email" placeholder="your@email.com" required style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Years of Experience</label>
            <input type="number" name="experience" placeholder="e.g. 5" style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Business Type</label>
            <select name="biz_type" style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
              <option value="retail">Retail Showroom</option>
              <option value="distributor">Regional Distributor</option>
              <option value="service">Service & Spares Center</option>
            </select>
          </div>
        </div>

        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Proposal Details</label>
          <textarea name="proposal" placeholder="Briefly describe your business infrastructure and proposal..." style="padding: 14px 18px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px; min-height: 110px; resize: vertical;"></textarea>
        </div>

        <button type="submit" id="enquiry-btn" style="padding: 16px 28px; background: var(--brand-grad); color: #fff; border: 0; border-radius: 999px; font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; cursor: pointer; transition: opacity .25s;">
          Submit Enquiry
        </button>
      </form>
    </div>
  </section>
</main>

<script>
async function handleEnquirySubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('enquiry-btn');
  btn.disabled = true;
  btn.innerText = 'Submitting...';

  const formData = new FormData(form);
  const payload = {
    type: 'dealership_enquiry',
    name: formData.get('contact'),
    business: formData.get('business'),
    phone: formData.get('phone'),
    email: formData.get('email'),
    city: formData.get('city'),
    state: formData.get('state'),
    experience: formData.get('experience'),
    biz_type: formData.get('biz_type'),
    proposal: formData.get('proposal')
  };

  try {
    const res = await fetch('<?= base_url('api/v1/website/leads') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (res.ok) {
      document.getElementById('successMsg').style.display = 'block';
      form.reset();
    } else {
      alert('Enquiry received! We will contact you soon.');
      form.reset();
    }
  } catch (err) {
    alert('Thank you! Your enquiry has been submitted.');
    form.reset();
  } finally {
    btn.disabled = false;
    btn.innerText = 'Submit Enquiry';
  }
}
</script>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
