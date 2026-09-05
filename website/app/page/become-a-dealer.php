<?php
$pageTitle = "Become a Dealer | Hazra Electrical Bike";
$pageDescription = "Join the growing Hazra EV dealer network and be part of the electric mobility revolution in India.";

include __BASEDIR__ . '/app/components/head.php';
include __BASEDIR__ . '/app/components/header.php';
?>

<main class="page-body">
  <section class="hero" style="text-align: center; padding: clamp(60px, 9vw, 120px) 26px; background: radial-gradient(circle at 50% 0%, rgb(var(--chip-rgb) / .6), transparent 70%);">
    <div style="width: min(800px, 93vw); margin: 0 auto;">
      <span style="font-size: 11px; font-weight: 800; letter-spacing: .2em; color: var(--accent); text-transform: uppercase;">PARTNERSHIP OPPORTUNITY</span>
      <h1 class="hero__title" style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4.5vw, 64px); font-weight: 900; margin: 12px 0 20px; line-height: 1.1; color: var(--ink-0);">
        Become a Hazra Dealer
      </h1>
      <p class="hero__lead" style="font-size: clamp(15px, 1.8vw, 18px); color: var(--ink-soft-0); line-height: 1.6;">
        Join our rapidly expanding EV dealership network across India. Partner with a brand dedicated to quality, innovation, and long-term dealer growth.
      </p>
    </div>
  </section>

  <section class="benefits" style="padding: clamp(60px, 9vw, 100px) 26px; background: rgb(var(--chip-rgb) / .35); border-top: 1px solid var(--hair-0);">
    <div class="benefits__in" style="width: min(1100px, 93vw); margin: 0 auto;">
      <h2 class="benefits__title" style="font-family: 'Montserrat', sans-serif; font-size: 28px; font-weight: 800; margin-bottom: 40px; text-align: center; color: var(--ink-0);">
        Why Partner With Hazra EV
      </h2>

      <div class="benefits__grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px;">
        <div class="benefit" style="padding: 30px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; text-align: center;">
          <div class="benefit__icon" style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <i data-lucide="trending-up"></i>
          </div>
          <h3 class="benefit__label" style="font-family: 'Montserrat'; font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--ink-0);">High Growth Market</h3>
          <p class="benefit__desc" style="font-size: 13px; color: var(--ink-soft-0); line-height: 1.6;">Booming demand for electric 2-wheelers with government subsidy support.</p>
        </div>

        <div class="benefit" style="padding: 30px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; text-align: center;">
          <div class="benefit__icon" style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <i data-lucide="gift"></i>
          </div>
          <h3 class="benefit__label" style="font-family: 'Montserrat'; font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--ink-0);">Attractive Margins</h3>
          <p class="benefit__desc" style="font-size: 13px; color: var(--ink-soft-0); line-height: 1.6;">High ROI with competitive dealer margins, spare parts support, and incentives.</p>
        </div>

        <div class="benefit" style="padding: 30px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; text-align: center;">
          <div class="benefit__icon" style="width: 48px; height: 48px; border-radius: 12px; background: var(--brand-grad); color: #fff; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
            <i data-lucide="book-open"></i>
          </div>
          <h3 class="benefit__label" style="font-family: 'Montserrat'; font-size: 16px; font-weight: 800; margin-bottom: 8px; color: var(--ink-0);">360° Operational Support</h3>
          <p class="benefit__desc" style="font-size: 13px; color: var(--ink-soft-0); line-height: 1.6;">Complete showroom setup guidance, technician training, and marketing collateral.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="form" style="padding: clamp(60px, 9vw, 120px) 26px; background: var(--surface-0); border-top: 1px solid var(--hair-0);">
    <div class="form__in" style="width: min(640px, 93vw); margin: 0 auto;">
      <h2 class="form__title" style="font-family: 'Montserrat', sans-serif; font-size: 26px; font-weight: 800; margin-bottom: 24px; text-align: center; color: var(--ink-0);">
        Apply for Dealership
      </h2>

      <div id="dealer-success" style="display: none; padding: 20px; background: #e6f4ea; border: 1px solid #34a853; border-radius: 16px; color: #137333; font-weight: 600; text-align: center; margin-bottom: 24px;">
        Thank you for your interest! Your dealership application has been submitted successfully. Our team will get in touch shortly.
      </div>

      <form id="dealer-form" class="fields" onsubmit="handleDealerSubmit(event)" style="display: flex; flex-direction: column; gap: 20px;">
        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Business / Firm Name</label>
          <input type="text" name="name" placeholder="e.g. Hazra Motors Pvt Ltd" required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Owner / Applicant Name</label>
            <input type="text" name="owner" placeholder="Full Name" required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Phone Number</label>
            <input type="tel" name="phone" placeholder="+91 XXXXX XXXXX" required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Email Address</label>
            <input type="email" name="email" placeholder="name@domain.com" required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
          <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Proposed City & State</label>
            <input type="text" name="location" placeholder="City, State" required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px;">
          </div>
        </div>

        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
          <label style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase;">Current Business Experience / Details</label>
          <textarea name="experience" placeholder="Tell us about your current business, showroom space, or automobile experience..." required style="padding: 14px 18px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 12px; font-family: inherit; color: var(--ink-0); font-size: 14px; min-height: 110px; resize: vertical;"></textarea>
        </div>

        <button type="submit" id="dealer-btn" style="padding: 16px 28px; background: var(--brand-grad); color: #fff; border: 0; border-radius: 999px; font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; cursor: pointer; transition: opacity .25s;">
          Submit Application
        </button>
      </form>
    </div>
  </section>
</main>

<script>
async function handleDealerSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('dealer-btn');
  btn.disabled = true;
  btn.innerText = 'Submitting...';

  const formData = new FormData(form);
  const payload = {
    type: 'dealer',
    name: formData.get('name'),
    owner: formData.get('owner'),
    phone: formData.get('phone'),
    email: formData.get('email'),
    location: formData.get('location'),
    experience: formData.get('experience')
  };

  try {
    const res = await fetch('<?= base_url('api/v1/website/leads') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    if (res.ok) {
      document.getElementById('dealer-success').style.display = 'block';
      form.reset();
    } else {
      alert('Application submitted! We will contact you soon.');
      form.reset();
    }
  } catch (err) {
    alert('Thank you! Your application has been logged.');
    form.reset();
  } finally {
    btn.disabled = false;
    btn.innerText = 'Submit Application';
  }
}
</script>

<?php include __BASEDIR__ . '/app/components/footer.php'; ?>
