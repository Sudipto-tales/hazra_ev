<?php
App::render('head', [
    'pageTitle'       => 'Dealer Locator — Hazra Electrical Bike',
    'pageDescription' => 'Find the nearest Hazra dealer, book a test ride, and start your electric journey today.',
    'extraCss'        => [
        'assets/vendor/leaflet/leaflet.css',
        'assets/css/styles/pages/dealer-locator.css?v=' . filemtime(__BASEDIR__ . '/assets/css/styles/pages/dealer-locator.css')
    ],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<section class="dl-hero">
  <div class="dl-hero__in">
    <p class="dl-hero__tag">Dealer Network</p>
    <h1 class="dl-hero__title">Find Your Nearest<br>Hazra Dealer</h1>
    <p class="dl-hero__lead">Experience the ride before you buy. Locate authorized dealers across India, book a test ride, and start your electric journey.</p>
    <div class="dl-stats" id="stats">
      <div class="dl-stat"><div class="dl-stat__num">&mdash;</div><div class="dl-stat__label">Dealers</div></div>
      <div class="dl-stat"><div class="dl-stat__num">&mdash;</div><div class="dl-stat__label">Cities</div></div>
      <div class="dl-stat"><div class="dl-stat__num">&mdash;</div><div class="dl-stat__label">States</div></div>
    </div>
  </div>
</section>

<section class="dl-collage">
  <div class="dl-collage__in">
    <div class="dl-collage__head"><h2 class="dl-collage__title">Our showrooms &amp; community</h2></div>
    <div class="dl-collage__grid">
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80" alt="EV showroom" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=600&q=80" alt="Electric scooter" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1593941707882-a5bba14938c7?w=600&q=80" alt="City charging" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=600&q=80" alt="Rider on road" loading="lazy"></div>
      <div class="dl-collage__item"><img src="https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=600&q=80" alt="Modern dealership" loading="lazy"></div>
    </div>
  </div>
</section>

<section class="dl-locator">
  <div class="dl-locator__in">
    <div class="dl-locator__head">
      <p class="dl-locator__eyebrow">Locate</p>
      <h2 class="dl-locator__title">Dealers near you</h2>
    </div>
    <div class="dl-shell">
      <aside class="dl-panel">
        <h3 class="dl-panel__title"><i data-lucide="sliders-horizontal"></i> Filters</h3>
        <div class="dl-group"><label class="dl-group__label" for="dealerSearch">Search dealers</label><div class="dl-field"><input id="dealerSearch" type="search" placeholder="Dealer, city or PIN code"></div></div>
        <button type="button" class="dl-apply" id="useLocation">Use my location</button>
        <p id="locationStatus" class="dl-status" role="status"></p>
        <div class="dl-group">
          <label class="dl-group__label" for="stateSelect">State</label>
          <div class="dl-field"><select id="stateSelect"><option value="">All states</option></select></div>
        </div>
        <div class="dl-group">
          <label class="dl-group__label" for="districtSelect">District</label>
          <div class="dl-field"><select id="districtSelect" disabled><option value="">All districts</option></select></div>
        </div>
        <div class="dl-group">
          <span class="dl-group__label">Dealer type</span>
          <div class="dl-chips" id="typeChips">
            <button class="dl-chip is-on" data-type="all">All</button>
            <button class="dl-chip" data-type="showroom">Showroom</button>
            <button class="dl-chip" data-type="service">Service</button>
          </div>
        </div>
        <div class="dl-stats-box" id="stateStats">
          <div class="dl-stats-box__item"><div class="dl-stats-box__num" id="statDealers">0</div><div class="dl-stats-box__label">Dealers</div></div>
          <div class="dl-stats-box__item"><div class="dl-stats-box__num" id="statCities">0</div><div class="dl-stats-box__label">Cities</div></div>
        </div>
        <button type="button" class="dl-apply" id="applyFilter">Apply Filters</button><button type="button" class="dl-reset" id="resetFilters">Reset filters</button>
      </aside>
      <div class="dl-results"><p id="resultCount" class="dl-status" role="status">Dealer locations</p><button type="button" id="retryDealers" class="dl-reset" hidden>Retry loading</button><div class="dl-float-cards" id="floatCards" aria-label="Matching dealers"></div></div>
      <div class="dl-map-area">
        <div id="map" aria-label="Dealer locations map"></div>
        <p id="mapStatus" class="dl-map-status" role="status"></p>
      </div>
    </div>
  </div>
</section>

<section class="dl-news" id="dealer-news">
  <div class="dl-news__in">
    <div class="dl-news__head">
      <div>
        <p class="dl-news__eyebrow">Dealer News</p>
        <h2 class="dl-news__title">Updates from the network</h2>
      </div>
      <a class="dl-news__link" href="<?= e(base_url('blog')) ?>">View all <i data-lucide="arrow-up-right"></i></a>
    </div>
    <div class="dl-posts">
      <a class="dl-post" href="<?= e(base_url('blog')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Expansion</span>
          <h3 class="dl-post__t">New showrooms open across Maharashtra &amp; Karnataka</h3>
          <p class="dl-post__d">Eight new touchpoints this quarter, bringing test rides and service closer to riders in Pune, Thane and Bengaluru.</p>
          <span class="dl-post__go">Read <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <a class="dl-post" href="<?= e(base_url('blog')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1571068316344-75bc76f77890?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Partnership</span>
          <h3 class="dl-post__t">Why EV dealerships are a high-growth local business</h3>
          <p class="dl-post__d">Demand signals, service revenue and local trust — what actually makes a two-wheeler EV counter work.</p>
          <span class="dl-post__go">Read <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <a class="dl-post" href="<?= e(base_url('become-a-dealer')) ?>">
        <div class="dl-post__media"><img src="https://images.unsplash.com/photo-1568605117035-3bdf3e3f0f0a?w=600&q=80" alt="" loading="lazy"></div>
        <div class="dl-post__body">
          <span class="dl-post__cat">Opportunity</span>
          <h3 class="dl-post__t">Become a Hazra dealer — applications open for Q4</h3>
          <p class="dl-post__d">Join a network built on transparent range figures, strong service support and real rider demand.</p>
          <span class="dl-post__go">Apply <i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
    </div>
  </div>
</section>

<script src="<?= e(base_url('assets/vendor/leaflet/leaflet.js')) ?>"></script>
<script>
window.HAZRA_DEALER_MAP = <?= json_encode(array_merge(require __BASEDIR__ . '/config/dealer-map.php', ['locationsUrl' => base_url('api/v1/website/dealer-locations')]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e(base_url('assets/js/dealer-locator.js?v=' . filemtime(__BASEDIR__ . '/assets/js/dealer-locator.js'))) ?>"></script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
