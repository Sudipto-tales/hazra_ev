<?php
App::render('head', ['pageTitle' => 'Terms & Conditions | Hazra Electrical Bike', 'extraCss' => ['assets/css/styles/pages/legal.css']]);
App::render('header', ['isStickyOnly' => true]);
?>
<main class="legal-page">
  <p class="legal-eyebrow">Hazra Electrical Bike</p>
  <h1>Terms &amp; Conditions</h1>
  <p>Updated 2 October 2026</p>
  <section><h2>Using our website and employee app</h2>
    <p>Provide accurate information in enquiries, warranty applications and work reports. Employee accounts are intended for authorised employees. Keep your sign-in details private and contact your administrator if you suspect someone else has accessed your account.</p>
  </section>
  <section><h2>Product information and enquiries</h2>
    <p>Product pages show model specifications, colour options and photographs. Confirm the specification, availability, price and purchase terms for your chosen model with the dealership before purchasing. Sending an enquiry or requesting a test ride is not a purchase confirmation.</p>
  </section>
  <section><h2>Warranty applications</h2>
    <p>Review the eligibility, coverage and registration information on our <a href="<?= e(base_url('warranty-free')) ?>">free warranty</a> and <a href="<?= e(base_url('warranty-paid')) ?>">paid extended warranty</a> pages. A submitted application is subject to verification; it does not by itself confirm coverage or payment.</p>
  </section>
  <section><h2>Employee account deletion</h2>
    <p>Employees can submit a deletion request in the app settings. The account will be deleted within 30 days of the request, or sooner after administrator approval. Deletion permanently removes access and anonymises the current account while preserving operational history and an administrator-only deletion record. Read the <a href="<?= e(base_url('privacy-policy')) ?>">Privacy Policy</a> for details.</p>
  </section>
  <section><h2>Support</h2>
    <p>For questions about products, registration or these terms, use our <a href="<?= e(base_url('contact')) ?>">contact page</a>. Employees should contact their administrator for employment or work-record questions.</p>
  </section>
</main>
<?php App::render('footer'); ?>
