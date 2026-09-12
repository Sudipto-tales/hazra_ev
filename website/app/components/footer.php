<!-- ══════════ FLOATING ACTION DOCK ══════════ -->
<aside class="dock" aria-label="Quick actions">
  <a class="dock__i" href="<?= e(base_url('#test-ride')) ?>" data-dock="test-drive" aria-label="Book a test drive">
    <i data-lucide="bike"></i>
    <span class="dock__lb">Test Drive</span>
  </a>

  <a class="dock__i" href="<?= e(base_url('dealership-enquiry')) ?>" data-dock="dealership" aria-label="Dealership enquiry">
    <i data-lucide="store"></i>
    <span class="dock__lb">Dealership</span>
  </a>

  <a class="dock__i" href="tel:+919829016542" aria-label="Call Hazra Electrical Bike">
    <i data-lucide="phone"></i>
    <span class="dock__lb">Call Us</span>
  </a>

  <a class="dock__i" href="https://wa.me/919829016542" target="_blank" rel="noopener noreferrer"
     aria-label="Chat on WhatsApp (opens in a new tab)">
    <i data-lucide="message-circle"></i>
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
            <li><i data-lucide="phone"></i><a href="tel:+919829016542">+91 98290 16542</a></li>
            <li><i data-lucide="mail"></i><a href="mailto:info@hazraev.com">info@hazraev.com</a></li>
            <li><i data-lucide="map-pin"></i><span>Sahibabad Industrial Area, Ghaziabad, Uttar Pradesh 201010</span></li>
          </ul>
        </div>

        <!-- Navigation columns -->
        <nav class="foot__cols" aria-label="Footer">
          <div class="fcol">
            <h4>Electric Scooters</h4>
            <a href="<?= e(base_url('product-detail?slug=chalo-1000-v2')) ?>">CHALO 1000 V2</a>
            <a href="<?= e(base_url('product-detail?slug=chalo-smart-pro')) ?>">CHALO SMART PRO</a>
            <a href="<?= e(base_url('product-detail?slug=chalo-smart-plus')) ?>">CHALO SMART PLUS</a>
            <a href="<?= e(base_url('product-detail?slug=chalo-neo')) ?>">CHALO NEO</a>
            <a href="<?= e(base_url('products')) ?>">Compare models</a>
            <a href="<?= e(base_url('products')) ?>">Book test ride</a>
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
          <a href="#" class="foot__social-link foot__social--instagram" aria-label="Instagram" title="Instagram">
            <i data-lucide="instagram"></i>
          </a>
          <a href="#" class="foot__social-link foot__social--facebook" aria-label="Facebook" title="Facebook">
            <i data-lucide="facebook"></i>
          </a>
          <a href="#" class="foot__social-link foot__social--youtube" aria-label="YouTube" title="YouTube">
            <i data-lucide="youtube"></i>
          </a>
          <a href="#" class="foot__social-link foot__social--whatsapp" aria-label="WhatsApp" title="WhatsApp">
            <i data-lucide="message-circle"></i>
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

<script src="<?= e(base_url('assets/js/script.js')) ?>"></script>
</body>
</html>
