<?php
App::render('head', [
    'pageTitle'       => 'Hazra Opens 50 New Dealership Points Across East India — News',
    'pageDescription' => 'Expansion strengthens service coverage and brings electric two-wheelers closer to riders in West Bengal, Odisha, Bihar and the North-East.',
    'extraCss'        => ['assets/css/styles/pages/news-single.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="hero">
  <p class="eyebrow" style="margin-bottom:12px">News · Company</p>
  <h1 class="hero__title" style="font-size:clamp(26px,4.5vw,44px)">Hazra Opens 50 New Dealership Points Across East India</h1>
  <p class="hero__lead">
    Expansion strengthens service coverage and brings electric two-wheelers closer to riders in West Bengal, Odisha, Bihar and the North-East.
  </p>
</section>

<section class="news">
  <div class="news__layout">

    <article class="story">
      <div class="story__hero">
        <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Hazra dealership expansion">
        <span class="story__badge">Press Release</span>
      </div>
      <div class="story__body">
        <div class="story__meta">
          <time datetime="2026-09-02">2 September 2026</time>
          <span>·</span>
          <span>Kolkata</span>
          <span>·</span>
          <span>4 min read</span>
        </div>
        <h1 class="story__title">Hazra Opens 50 New Dealership Points Across East India</h1>
        <p class="story__deck">
          The company will add authorised dealership and service locations across four states and the North-Eastern region, aiming to cut average travel time to a Hazra point for thousands of riders.
        </p>

        <div class="story__prose">
          <p>
            Hazra Electrical Bike today announced the opening of 50 new dealership and service points across East India. The network expansion covers key cities and district towns in West Bengal, Odisha, Bihar, Jharkhand and selected locations in the North-East.
          </p>

          <div class="story__keyfacts">
            <div>
              <strong>50</strong>
              <span>New points</span>
            </div>
            <div>
              <strong>4+</strong>
              <span>States covered</span>
            </div>
            <div>
              <strong>Q4</strong>
              <span>2026 target</span>
            </div>
          </div>

          <p>
            The move is part of a broader plan to make ownership of an electric two-wheeler as straightforward as owning a conventional scooter. Every new location will offer sales, test rides, battery care guidance and authorised service.
          </p>

          <h2>Why East India, why now</h2>
          <p>
            Demand for electric scooters in the region has grown steadily over the last two years. Riders cite rising fuel costs, shorter urban trips and improving charging options as the main reasons they are ready to switch. At the same time, many potential buyers still travel long distances to find a trusted showroom or service centre.
          </p>
          <p>
            By placing dealership points closer to where people live and work, Hazra aims to remove that friction. The company will also run local rider clinics and battery education sessions at the new outlets.
          </p>

          <h2>What customers can expect</h2>
          <ul>
            <li>Full product range including CHALO and NJA models</li>
            <li>On-site test rides and transparent pricing</li>
            <li>Authorised warranty registration and service</li>
            <li>Genuine spare parts and battery support</li>
          </ul>

          <p>
            Further details on exact city lists and opening dates will be published on the dealer locator page in the coming weeks. Existing customers in the region can already use the nearest new point for service bookings once it goes live.
          </p>

          <p>
            “Electric mobility only becomes real when the product, the service and the people are within reach,” said a company spokesperson. “These 50 points are another step toward that everyday reality.”
          </p>
        </div>

        <div class="story__footer">
          <div class="story__share">
            <a href="#" aria-label="Share on X"><i data-lucide="twitter"></i></a>
            <a href="#" aria-label="Share on LinkedIn"><i data-lucide="linkedin"></i></a>
            <a href="#" aria-label="Share on Facebook"><i data-lucide="facebook"></i></a>
            <a href="#" aria-label="Copy link"><i data-lucide="link"></i></a>
          </div>
          <a href="<?= e(base_url('blog')) ?>" style="font-size:13px;font-weight:700;color:var(--brand-violet);display:inline-flex;align-items:center;gap:6px">
            More news &amp; stories <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
          </a>
        </div>
      </div>
    </article>

    <aside class="news-aside">
      <div class="news-aside__card">
        <h4>Latest news</h4>
        <div class="latest">
          <a href="<?= e(base_url('news-single')) ?>">
            <time>28 Aug 2026</time>
            <strong>Hazra partners with regional charging network</strong>
            <span>New public charging locations for riders</span>
          </a>
          <a href="<?= e(base_url('news-single')) ?>">
            <time>15 Aug 2026</time>
            <strong>CHALO 1000 V2 range update announced</strong>
            <span>Software + battery software improvements</span>
          </a>
          <a href="<?= e(base_url('news-single')) ?>">
            <time>02 Aug 2026</time>
            <strong>Summer service camps across 12 cities</strong>
            <span>Free check-ups for existing owners</span>
          </a>
        </div>
      </div>

      <div class="news-aside__card cta-card">
        <h4>Become a dealer</h4>
        <p>Join the Hazra network and bring electric mobility to your city.</p>
        <a href="<?= e(base_url('become-a-dealer')) ?>">
          Apply now <i data-lucide="arrow-up-right" style="width:14px;height:14px"></i>
        </a>
      </div>
    </aside>

  </div>
</section>

<section class="footer" style="padding: 48px 0; text-align: center;">
  <p style="color: var(--ink-soft-0); margin-bottom: 24px;">
    Stay updated with company news and product launches
  </p>
  <a class="footer__link" href="<?= e(base_url('index')) ?>">
    <span>Back to home</span>
    <i data-lucide="arrow-right"></i>
  </a>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
