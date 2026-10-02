<?php
App::render('head', ['pageTitle' => 'Privacy Policy | Hazra Electrical Bike', 'extraCss' => ['assets/css/styles/pages/legal.css']]);
App::render('header', ['isStickyOnly' => true]);
?>
<main class="legal-page">
  <p class="legal-eyebrow">Hazra Electrical Bike</p>
  <h1>Privacy Policy</h1>
  <p>Updated 2 October 2026</p>
  <section><h2>Information used by our services</h2>
    <p>Our website forms collect the contact details and information you provide for enquiries, test rides, dealership applications and warranty registrations. The employee app uses account and employment details, work sessions, location records, attendance, visit reports and any photos or sales information submitted through the app.</p>
  </section>
  <section><h2>Employee location and work records</h2>
    <p>Location permissions support work tracking, routes, stops and company visits. Work information is available to authorised administrators to manage field activity and review reports. The app provides tracking and permission information in Profile and Settings.</p>
  </section>
  <section><h2>Account deletion requests</h2>
    <p>Employees can request account deletion from Settings → Delete account. A request has a fixed 30-day deadline and an administrator may approve it sooner. After deletion the account cannot sign in, its current name becomes Unknown and its status becomes Deleted.</p>
    <p>Deletion preserves the employee ID and existing operational history, including attendance, reports, sales and routes. A restricted deletion record keeps the request, the employee information recorded at submission and the deletion outcome for administrators. Account deletion does not erase all historical business records or information already submitted in those records.</p>
  </section>
  <section id="cookies"><h2>Cookies and local storage</h2>
    <p>The website uses browser storage for preferences and form progress, and sessions for administrative access. The mobile app stores sign-in credentials as tokens and app preferences on the device. Clearing browser or app storage may reset preferences or require you to sign in again.</p>
  </section>
  <section><h2>Questions about your information</h2>
    <p>Use our <a href="<?= e(base_url('contact')) ?>">contact page</a> for privacy questions. Employees can also contact their administrator about account details and work records.</p>
  </section>
</main>
<?php App::render('footer'); ?>
