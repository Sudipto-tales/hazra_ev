<!-- ══════════ FLOATING ACTION DOCK ══════════ -->
<aside class="dock" id="floatingDock" aria-label="Quick actions">
  <a class="dock__i" href="<?= e(base_url('#test-ride')) ?>" data-dock="test-drive" aria-label="Book a test drive">
    <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="18.5" cy="17.5" r="3.5"/><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="15" cy="5" r="1"/><path d="M12 17.5V14l-3-3 4-3 2 3h2"/>
    </svg>
    <span class="dock__lb">Test Drive</span>
  </a>

  <a class="dock__i" href="<?= e(base_url('become-a-dealer')) ?>" data-dock="dealership" aria-label="Become a dealer">
    <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/>
    </svg>
    <span class="dock__lb">Dealership</span>
  </a>

  <a class="dock__i" href="tel:+919002921509" aria-label="Call Hazra Electrical Bike">
    <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
    </svg>
    <span class="dock__lb">Call Us</span>
  </a>

  <a class="dock__i" href="https://wa.me/919002921509" target="_blank" rel="noopener noreferrer"
     aria-label="Chat on WhatsApp (opens in a new tab)">
    <svg class="dock__icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
    </svg>
    <span class="dock__lb">WhatsApp</span>
  </a>
</aside>

<!-- ══════════ FOOTER ══════════ -->
<footer class="foot" id="contact">

  <!-- Wave cut effect at the top -->
  <div class="foot__wave"></div>

  <!-- Background with theme support -->
  <div class="foot__bg foot__bg--light" aria-hidden="true"></div>
  <div class="foot__bg foot__bg--dark" aria-hidden="true"></div>
  <i class="foot__veil"></i>

  <div class="wrap foot__in">

    <!-- Main content grid: Brand + Navigation left, Social media right -->
    <div class="foot__main">

      <!-- Left column: Brand and navigation -->
      <div class="foot__content">

        <!-- Brand section -->
        <div class="foot__brand">
          <img class="foot__logo" src="<?= e(base_url('assets/hazraev.png')) ?>" alt="Hazra Electrical Bike">
          <p class="foot__tag">
            Premium electric scooters designed for smarter, cleaner and more
            affordable mobility.
          </p>
          <ul class="foot__contact">
            <li>
              <i data-lucide="phone"></i>
              <a href="tel:+919002921509">+91 90029 21509</a>
            </li>
            <li>
              <i data-lucide="phone"></i>
              <a href="tel:+919242752316">+91 92427 52316</a>
            </li>
            <li>
              <i data-lucide="mail"></i>
              <a href="mailto:info@hazraev.com">info@hazraev.com</a>
            </li>
            <li>
              <i data-lucide="map-pin"></i>
              <span>Ghordourchati More, Below LIC Division Office, Bardhaman, West Bengal 713103</span>
            </li>
          </ul>
        </div>

        <!-- Navigation columns -->
        <nav class="foot__cols" aria-label="Footer">
          <div class="fcol">
            <h4>Electric Scooters</h4>
            <a href="<?= e(base_url('products')) ?>">Hazra Lineup</a>
            <a href="<?= e(base_url('products')) ?>">Dynamo Lineup</a>
            <a href="<?= e(base_url('products')) ?>">Black Panther</a>
            <a href="<?= e(base_url('products')) ?>">Oleant Lineup</a>
            <a href="<?= e(base_url('products')) ?>">Compare models</a>
            <a href="<?= e(base_url('index#test-ride')) ?>">Book test ride</a>
          </div>
          <div class="fcol">
            <h4>Buy</h4>
            <a href="<?= e(base_url('products')) ?>">Book a scooter</a>
            <a href="<?= e(base_url('products')) ?>">EMI calculator</a>
            <a href="<?= e(base_url('products')) ?>">Charging</a>
            <a href="<?= e(base_url('products')) ?>">Download brochure</a>
            <a href="<?= e(base_url('dealer-locator')) ?>">Locate dealers</a>
          </div>
          <div class="fcol">
            <h4>Ownership</h4>
            <a href="<?= e(base_url('products')) ?>">Running cost calculator</a>
            <a href="<?= e(base_url('products')) ?>">Range confidence</a>
            <a href="<?= e(base_url('products')) ?>">Accessories</a>
            <a href="<?= e(base_url('warranty-free')) ?>">Battery warranty</a>
            <a href="<?= e(base_url('battery-use')) ?>">Battery use</a>
          </div>
          <div class="fcol">
            <h4>Company</h4>
            <a href="<?= e(base_url('our-story')) ?>">Our story</a>
            <a href="<?= e(base_url('gallery')) ?>">Gallery</a>
            <a href="<?= e(base_url('blog')) ?>">Blog</a>
            <a href="<?= e(base_url('ev-future')) ?>">News & Insights</a>
            <a href="<?= e(base_url('career')) ?>">Careers</a>
            <a href="<?= e(base_url('become-a-dealer')) ?>">Become a dealer</a>
          </div>
          <div class="fcol">
            <h4>Support</h4>
            <a href="<?= e(base_url('our-story')) ?>">FAQs</a>
            <a href="<?= e(base_url('contact')) ?>">Contact us</a>
            <a href="<?= e(base_url('dealership-enquiry')) ?>">Register complaint</a>
            <a href="<?= e(base_url('warranty-free')) ?>">Warranty registration</a>
            <a href="<?= e(base_url('warranty-paid')) ?>">Paid warranty</a>
          </div>
        </nav>
      </div>

      <!-- Right column: Social media section -->
      <aside class="foot__social-section">
        <h3 class="foot__social-title">Follow us</h3>
        <div class="foot__socials">

          <!-- Instagram -->
          <a href="https://www.instagram.com/hazraelectrical/"
             class="foot__social-link foot__social--instagram"
             target="_blank" rel="noopener noreferrer"
             aria-label="Instagram" title="Instagram">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/>
              <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
              <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
            </svg>
          </a>

          <!-- Facebook -->
          <a href="https://www.facebook.com/hazraelectricalbike/"
             class="foot__social-link foot__social--facebook"
             target="_blank" rel="noopener noreferrer"
             aria-label="Facebook" title="Facebook">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
            </svg>
          </a>

          <!-- WhatsApp -->
          <a href="https://wa.me/919002921509"
             class="foot__social-link foot__social--whatsapp"
             target="_blank" rel="noopener noreferrer"
             aria-label="WhatsApp" title="WhatsApp">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
          </a>

          <!-- Google Maps / Location -->
          <a href="https://www.google.com/maps/place/Hazra+Electrical+Bike/@23.2198619,87.8848343,17z"
             class="foot__social-link foot__social--map"
             target="_blank" rel="noopener noreferrer"
             aria-label="Google Maps" title="Location">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
          </a>

        </div>
      </aside>
    </div>

    <!-- Footer base: Legal and copyright -->
    <div class="foot__base">
      <p>&copy; <?= date('Y') ?> Hazra Electrical Bike. Specs, finance options and availability vary by city and dealer.</p>
      <div class="foot__legal">
        <a href="#">Privacy</a><a href="#">Terms</a><a href="#">Cookies</a>
      </div>
    </div>

  </div>
</footer>

<?php $jsVer = defined('__BASEDIR__') && file_exists(__BASEDIR__ . '/assets/js/script.js') ? filemtime(__BASEDIR__ . '/assets/js/script.js') : time(); ?>
<script src="<?= e(base_url('assets/js/script.js')) ?>?v=<?= $jsVer ?>"></script>
<script>
  if (window.lucide && typeof window.lucide.createIcons === 'function') {
    window.lucide.createIcons();
  }
</script>
</body>
</html>
