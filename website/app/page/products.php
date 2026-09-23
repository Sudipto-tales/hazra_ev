<?php
App::render('head', [
    'pageTitle'       => 'Products — Hazra Electrical Bike',
    'pageDescription' => 'Browse the full Hazra Electrical Bike lineup. Filter by range, price, battery and series.',
    'extraCss'        => ['assets/css/styles/pages/products.css'],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<!-- SHOWCASE -->
<section class="pl-show">
  <div class="wrap pl-show__grid">
    <a class="pl-show__hero" href="<?= e(base_url('product-detail?slug=chalo-1000-v2')) ?>">
      <img src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Hazra signature scooter">
      <div class="pl-show__hero-copy">
        <p class="eyebrow"><i class="sq"></i>SIGNATURE</p>
        <h1>Signature<br>of the road</h1>
        <span class="pl-show__cta">Explore CHALO 1000 V2 <i data-lucide="arrow-up-right"></i></span>
      </div>
    </a>
    <a class="pl-show__promo" href="<?= e(base_url('index#test-ride')) ?>">
      <p class="pl-show__promo-kicker">This month</p>
      <h2>Book a free<br>test ride</h2>
      <p>Feel the torque map on your own route.</p>
      <span class="btn btn--ink">Schedule <i data-lucide="bike"></i></span>
    </a>
    <button type="button" class="pl-show__tile" data-filter-speed="city">
      <img src="<?= e(base_url('assets/storm.webp')) ?>" alt="City scooters">
      <span>City / Daily</span>
    </button>
    <!-- <button type="button" class="pl-show__tile" data-filter-speed="high">
      <img src="<?= e(base_url('assets/dark_scutie.webp')) ?>" alt="Performance scooters">
      <span>High speed</span>
    </button> -->
  </div>
</section>

<!-- TOOLBAR + FILTERS + GRID -->
<section class="pl-main">
  <div class="wrap">
    <header class="pl-head">
      <div>
        <p class="eyebrow"><i class="sq"></i>HAZRA LINEUP</p>
        <h2 class="sec__title">Products that<br><span class="hl">earn the ride.</span></h2>
      </div>
      <p class="pl-count" id="plCount">Showing all models</p>
    </header>

    <div class="pl-toolbar">
      <div class="pl-search" id="plSearchWrap">
        <i data-lucide="search" class="pl-search__icon"></i>
        <input type="search" id="plSearch" placeholder="Search by name, series, battery, range…" autocomplete="off" enterkeyhint="search">
        <button type="button" class="pl-search__clear" id="plSearchClear" hidden aria-label="Clear search"><i data-lucide="x"></i></button>
        <div class="pl-suggest" id="plSuggest" hidden role="listbox"></div>
      </div>

      <button type="button" class="pl-filter-toggle" id="plFilterToggle" aria-expanded="false" aria-controls="plFilters">
        <i data-lucide="sliders-horizontal"></i>
        <span>Filters</span>
        <b class="pl-filter-badge" id="plFilterBadge" hidden>0</b>
      </button>

      <label class="pl-sort">
        <span>Sort</span>
        <select id="plSort">
          <option value="featured">Featured</option>
          <option value="price-asc">Price: low to high</option>
          <option value="price-desc">Price: high to low</option>
          <option value="range-desc">Range: high to low</option>
          <option value="rating-desc">Top rated</option>
        </select>
      </label>
    </div>

    <div class="pl-chips" id="plChips" hidden></div>

    <div class="pl-layout">
      <div class="pl-filters-backdrop" id="plFiltersBackdrop" hidden></div>
      <aside class="pl-filters" id="plFilters" aria-label="Product filters">
        <div class="pl-filters__head">
          <h3>Filters</h3>
          <button type="button" class="pl-filters__clear" id="plClearAll">Clear all</button>
        </div>

        <fieldset class="pl-fg">
          <legend>Price (ex-showroom)</legend>
          <div class="pl-range-inputs">
            <label>Min ₹ <input type="number" id="priceMin" min="0" max="200000" step="1000" placeholder="0"></label>
            <label>Max ₹ <input type="number" id="priceMax" min="0" max="200000" step="1000" placeholder="150000"></label>
          </div>
          <input type="range" id="priceMaxSlider" min="40000" max="150000" step="1000" value="150000">
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Range (km)</legend>
          <div class="pl-range-inputs">
            <label>Min <input type="number" id="rangeMin" min="0" max="200" step="5" placeholder="0"></label>
            <label>Max <input type="number" id="rangeMax" min="0" max="200" step="5" placeholder="200"></label>
          </div>
          <div class="pl-pills" data-group="rangePreset">
            <button type="button" data-rmin="0" data-rmax="80">&lt; 80 km</button>
            <button type="button" data-rmin="80" data-rmax="110">80–110</button>
            <button type="button" data-rmin="110" data-rmax="200">110+</button>
          </div>
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Series</legend>
          <div class="pl-checks" id="filterSeries"></div>
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Battery</legend>
          <div class="pl-checks">
            <label><input type="checkbox" name="battery" value="graphene"> Graphene</label>
            <label><input type="checkbox" name="battery" value="li-ion"> Li-ion</label>
          </div>
        </fieldset>

        <fieldset class="pl-fg">
          <legend>Speed class</legend>
          <div class="pl-checks">
            <label><input type="checkbox" name="speed" value="high"> High speed</label>
            <label><input type="checkbox" name="speed" value="city"> City / low speed</label>
          </div>
        </fieldset>
      </aside>

      <div class="pl-grid-wrap">
        <div class="coll__grid pl-grid" id="plGrid"></div>
        <div class="pl-empty" id="plEmpty" hidden>
          <p>No models match these filters.</p>
          <button type="button" class="btn btn--ghost" id="plEmptyClear">Clear filters</button>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const baseUrl = <?= json_encode(rtrim(base_url(''), '/')) ?>;

  const CATALOG = [
    {
      id: 'hazra-striker', slug: 'hazra-striker', name: 'Hazra Striker', series: 'Hazra',
      company: 'Hazra', rangeKm: 80, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 23000, priceTo: 37000,
      rating: 4.8, reviews: 192, color: '#111111',
      badge: '1 Year Comprehensive Vehicle Warranty',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b923,000", "label": "Price w/o Battery"}, {"price": "\u20b937,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_9.png',
      tags: ["hazra", "striker", "li-ion"]
    },
    {
      id: 'hazra-wind', slug: 'hazra-wind', name: 'Hazra Wind', series: 'Hazra',
      company: 'Hazra', rangeKm: 85, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 24000, priceTo: 38000,
      rating: 4.8, reviews: 192, color: '#4a4a4a',
      badge: '1yr',
      chips: ["85 km Range", "Low Speed"],
      variants: [{"price": "\u20b924,000", "label": "Price w/o Battery"}, {"price": "\u20b938,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_12.png',
      tags: ["hazra", "wind", "li-ion"]
    },
    {
      id: 'hazra-wind-pro', slug: 'hazra-wind-pro', name: 'Hazra Wind Pro', series: 'Hazra',
      company: 'Hazra', rangeKm: 90, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 25000, priceTo: 39000,
      rating: 4.8, reviews: 192, color: '#1c1c1c',
      badge: '1yr',
      chips: ["90 km Range", "Low Speed"],
      variants: [{"price": "\u20b925,000", "label": "Price w/o Battery"}, {"price": "\u20b939,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_13.png',
      tags: ["hazra", "wind", "li-ion", "pro"]
    },
    {
      id: 'hazra-soul-pro', slug: 'hazra-soul-pro', name: 'Hazra Soul Pro', series: 'Hazra',
      company: 'Hazra', rangeKm: 100, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 32000, priceTo: 46000,
      rating: 4.8, reviews: 192, color: '#0d47a1',
      badge: '1yr',
      chips: ["100 km Range", "High Speed"],
      variants: [{"price": "\u20b932,000", "label": "Price w/o Battery"}, {"price": "\u20b946,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_14.png',
      tags: ["hazra", "pro", "li-ion", "soul"]
    },
    {
      id: 'hazra-max-e4', slug: 'hazra-max-e4', name: 'Hazra Max E4', series: 'Hazra',
      company: 'Hazra', rangeKm: 110, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 37000, priceTo: 51000,
      rating: 4.8, reviews: 192, color: '#37474f',
      badge: '1yr',
      chips: ["110 km Range", "High Speed"],
      variants: [{"price": "\u20b937,000", "label": "Price w/o Battery"}, {"price": "\u20b951,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_15.png',
      tags: ["hazra", "e4", "li-ion", "max"]
    },
    {
      id: 'hazra-spark', slug: 'hazra-spark', name: 'Hazra Spark', series: 'Hazra',
      company: 'Hazra', rangeKm: 110, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 37000, priceTo: 51000,
      rating: 4.8, reviews: 192, color: '#76ff03',
      badge: '1yr',
      chips: ["110 km Range", "High Speed"],
      variants: [{"price": "\u20b937,000", "label": "Price w/o Battery"}, {"price": "\u20b951,000", "label": "Price with Battery"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_16.png',
      tags: ["hazra", "spark", "li-ion"]
    },
    {
      id: 'chalo-1000-v2', slug: 'chalo-1000-v2', name: 'Hazra CHALO 1000 V2', series: 'Hazra',
      company: 'Hazra', rangeKm: 100, speed: 'high',
      battery: ["graphene"],
      priceFrom: 61062, priceTo: 70221,
      rating: 4.8, reviews: 192, color: '#7b2ff7',
      badge: '4 Years Comprehensive Warranty',
      chips: ["100 km Range", "High Speed"],
      variants: [{"price": "\u20b961,062", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_9.png',
      tags: ["chalo", "hazra", "v2", "1000", "graphene"]
    },
    {
      id: 'chalo-smart-pro', slug: 'chalo-smart-pro', name: 'Hazra CHALO SMART PRO', series: 'Hazra',
      company: 'Hazra', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 79400, priceTo: 91310,
      rating: 4.9, reviews: 196, color: '#241640',
      badge: '4yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b979,400", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_14.png',
      tags: ["pro", "chalo", "li-ion", "hazra", "smart"]
    },
    {
      id: 'chalo-smart-eco', slug: 'chalo-smart-eco', name: 'Hazra CHALO SMART ECO', series: 'Hazra',
      company: 'Hazra', rangeKm: 130, speed: 'city',
      battery: ["graphene"],
      priceFrom: 54900, priceTo: 63134,
      rating: 4.7, reviews: 188, color: '#f0532b',
      badge: '3 Years Battery Warranty',
      chips: ["130 km Range", "Low Speed"],
      variants: [{"price": "\u20b954,900", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_12.png',
      tags: ["chalo", "eco", "hazra", "graphene", "smart"]
    },
    {
      id: 'chalo-smart-plus', slug: 'chalo-smart-plus', name: 'Hazra CHALO SMART PLUS', series: 'Hazra',
      company: 'Hazra', rangeKm: 85, speed: 'city',
      battery: ["graphene"],
      priceFrom: 49750, priceTo: 57212,
      rating: 4.6, reviews: 184, color: '#a41fbf',
      badge: '3 Years Vehicle Warranty',
      chips: ["85 km Range", "Low Speed"],
      variants: [{"price": "\u20b949,750", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_13.png',
      tags: ["chalo", "hazra", "plus", "graphene", "smart"]
    },
    {
      id: 'chalo-neo', slug: 'chalo-neo', name: 'Hazra CHALO NEO', series: 'Hazra',
      company: 'Hazra', rangeKm: 50, speed: 'city',
      battery: ["graphene"],
      priceFrom: 42400, priceTo: 48759,
      rating: 4.5, reviews: 180, color: '#f7941d',
      badge: '2 Years Warranty',
      chips: ["50 km Range", "Low Speed"],
      variants: [{"price": "\u20b942,400", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_9.png',
      tags: ["hazra", "chalo", "neo", "graphene"]
    },
    {
      id: 'nja-7', slug: 'nja-7', name: 'Hazra NJA-7', series: 'Hazra',
      company: 'Hazra', rangeKm: 140, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 104900, priceTo: 120634,
      rating: 5.0, reviews: 200, color: '#7b2ff7',
      badge: '4 Years Warranty on Powertrain',
      chips: ["140 km Range", "High Speed"],
      variants: [{"price": "\u20b91,04,900", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_16.png',
      tags: ["hazra", "nja-7", "li-ion"]
    },
    {
      id: 'dynamo-x1', slug: 'dynamo-x1', name: 'Dynamo X1', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 80, speed: 'city',
      battery: ["graphene"],
      priceFrom: 46000, priceTo: 52899,
      rating: 4.5, reviews: 180, color: '#263238',
      badge: '2yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b946,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_9.png',
      tags: ["x1", "dynamo", "graphene"]
    },
    {
      id: 'dynamo-dual', slug: 'dynamo-dual', name: 'Dynamo Dual', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 85, speed: 'city',
      battery: ["graphene"],
      priceFrom: 48000, priceTo: 55199,
      rating: 4.5, reviews: 180, color: '#4a4a4a',
      badge: '2yr',
      chips: ["85 km Range", "Low Speed"],
      variants: [{"price": "\u20b948,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_12.png',
      tags: ["dynamo", "graphene", "dual"]
    },
    {
      id: 'dynamo-infinity-plus', slug: 'dynamo-infinity-plus', name: 'Dynamo Infinity Plus', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 95, speed: 'high',
      battery: ["graphene"],
      priceFrom: 55000, priceTo: 63249,
      rating: 4.6, reviews: 184, color: '#0d47a1',
      badge: '2yr',
      chips: ["95 km Range", "High Speed"],
      variants: [{"price": "\u20b955,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_13.png',
      tags: ["graphene", "plus", "dynamo", "infinity"]
    },
    {
      id: 'dynamo-xplore', slug: 'dynamo-xplore', name: 'Dynamo Xplore', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 95, speed: 'high',
      battery: ["graphene"],
      priceFrom: 56000, priceTo: 64399,
      rating: 4.6, reviews: 184, color: '#305223',
      badge: '2yr',
      chips: ["95 km Range", "High Speed"],
      variants: [{"price": "\u20b956,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_14.png',
      tags: ["xplore", "dynamo", "graphene"]
    },
    {
      id: 'dynamo-smiley', slug: 'dynamo-smiley', name: 'Dynamo Smiley', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 100, speed: 'high',
      battery: ["graphene"],
      priceFrom: 59000, priceTo: 67850,
      rating: 4.6, reviews: 184, color: '#c62828',
      badge: '2yr',
      chips: ["100 km Range", "High Speed"],
      variants: [{"price": "\u20b959,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_15.png',
      tags: ["smiley", "dynamo", "graphene"]
    },
    {
      id: 'dynamo-x2', slug: 'dynamo-x2', name: 'Dynamo X2', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 100, speed: 'high',
      battery: ["graphene"],
      priceFrom: 60000, priceTo: 69000,
      rating: 4.7, reviews: 188, color: '#00838f',
      badge: '2yr',
      chips: ["100 km Range", "High Speed"],
      variants: [{"price": "\u20b960,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/hazra_broucher_6_scooter_16.png',
      tags: ["dynamo", "graphene", "x2"]
    },
    {
      id: 'dynamo-x3t', slug: 'dynamo-x3t', name: 'Dynamo X3T', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 100, speed: 'high',
      battery: ["graphene"],
      priceFrom: 60000, priceTo: 69000,
      rating: 4.6, reviews: 184, color: '#37474f',
      badge: '2yr',
      chips: ["100 km Range", "High Speed"],
      variants: [{"price": "\u20b960,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_6.png',
      tags: ["x3t", "dynamo", "graphene"]
    },
    {
      id: 'dynamo-vibe', slug: 'dynamo-vibe', name: 'Dynamo Vibe', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 105, speed: 'high',
      battery: ["graphene"],
      priceFrom: 62000, priceTo: 71300,
      rating: 4.7, reviews: 188, color: '#00838f',
      badge: '2yr',
      chips: ["105 km Range", "High Speed"],
      variants: [{"price": "\u20b962,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_7.png',
      tags: ["dynamo", "graphene", "vibe"]
    },
    {
      id: 'dynamo-xplore-2-0', slug: 'dynamo-xplore-2-0', name: 'Dynamo Xplore 2.0', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 105, speed: 'high',
      battery: ["graphene"],
      priceFrom: 62000, priceTo: 71300,
      rating: 4.7, reviews: 188, color: '#37474f',
      badge: '2yr',
      chips: ["105 km Range", "High Speed"],
      variants: [{"price": "\u20b962,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_8.png',
      tags: ["xplore", "dynamo", "graphene", "2.0"]
    },
    {
      id: 'dynamo-neon', slug: 'dynamo-neon', name: 'Dynamo Neon', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 110, speed: 'high',
      battery: ["graphene"],
      priceFrom: 63000, priceTo: 72450,
      rating: 4.7, reviews: 188, color: '#76ff03',
      badge: '2yr',
      chips: ["110 km Range", "High Speed"],
      variants: [{"price": "\u20b963,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_9.png',
      tags: ["dynamo", "graphene", "neon"]
    },
    {
      id: 'dynamo-lima', slug: 'dynamo-lima', name: 'Dynamo Lima', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 110, speed: 'high',
      battery: ["graphene"],
      priceFrom: 63000, priceTo: 72450,
      rating: 4.6, reviews: 184, color: '#f5f5f5',
      badge: '2yr',
      chips: ["110 km Range", "High Speed"],
      variants: [{"price": "\u20b963,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_10.png',
      tags: ["dynamo", "graphene", "lima"]
    },
    {
      id: 'dynamo-xl-loader', slug: 'dynamo-xl-loader', name: 'Dynamo XL Loader', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 120, speed: 'city',
      battery: ["graphene"],
      priceFrom: 62000, priceTo: 71300,
      rating: 4.5, reviews: 180, color: '#4a4a4a',
      badge: '2yr',
      chips: ["120 km Range", "Low Speed"],
      variants: [{"price": "\u20b962,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_11.png',
      tags: ["xl", "graphene", "dynamo", "loader"]
    },
    {
      id: 'dynamo-3x1-handi', slug: 'dynamo-3x1-handi', name: 'Dynamo 3X1 Handi', series: 'Dynamo',
      company: 'Dynamo', rangeKm: 90, speed: 'city',
      battery: ["graphene"],
      priceFrom: 72000, priceTo: 82800,
      rating: 4.4, reviews: 176, color: '#ffffff',
      badge: '2yr',
      chips: ["90 km Range", "Low Speed"],
      variants: [{"price": "\u20b972,000", "label": "Price"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_22.png',
      tags: ["3x1", "handi", "dynamo", "graphene"]
    },
    {
      id: 'black-panther-e10-plus', slug: 'black-panther-e10-plus', name: 'Economic Black Panther E10+', series: 'Economic',
      company: 'Economic', rangeKm: 80, speed: 'city',
      battery: ["nmc"],
      priceFrom: 24999, priceTo: 48499,
      rating: 4.5, reviews: 180, color: '#1a1a1a',
      badge: '2 Yr NMC Battery Warranty',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b924,999", "label": "Price w/o Battery"}, {"price": "\u20b938,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_8.png',
      tags: ["panther", "economic", "nmc", "black", "e10+"]
    },
    {
      id: 'black-panther-e10-pro', slug: 'black-panther-e10-pro', name: 'Economic Black Panther E10 Pro', series: 'Economic',
      company: 'Economic', rangeKm: 80, speed: 'city',
      battery: ["nmc"],
      priceFrom: 25999, priceTo: 49499,
      rating: 4.5, reviews: 180, color: '#212121',
      badge: '2yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b925,999", "label": "Price w/o Battery"}, {"price": "\u20b939,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_10.png',
      tags: ["pro", "panther", "economic", "nmc", "black", "e10"]
    },
    {
      id: 'black-panther-e9-plus', slug: 'black-panther-e9-plus', name: 'Economic Black Panther E9+', series: 'Economic',
      company: 'Economic', rangeKm: 80, speed: 'city',
      battery: ["nmc"],
      priceFrom: 26999, priceTo: 50499,
      rating: 4.5, reviews: 180, color: '#263238',
      badge: '2yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b926,999", "label": "Price w/o Battery"}, {"price": "\u20b940,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_11.png',
      tags: ["panther", "economic", "nmc", "black", "e9+"]
    },
    {
      id: 'black-panther-e8-neo', slug: 'black-panther-e8-neo', name: 'Economic Black Panther E8 Neo', series: 'Economic',
      company: 'Economic', rangeKm: 80, speed: 'city',
      battery: ["nmc"],
      priceFrom: 31999, priceTo: 55499,
      rating: 4.5, reviews: 180, color: '#000000',
      badge: '2yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b931,999", "label": "Price w/o Battery"}, {"price": "\u20b945,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_12.png',
      tags: ["panther", "economic", "neo", "nmc", "black", "e8"]
    },
    {
      id: 'black-panther-e12', slug: 'black-panther-e12', name: 'Economic Black Panther E12', series: 'Economic',
      company: 'Economic', rangeKm: 80, speed: 'city',
      battery: ["nmc"],
      priceFrom: 33999, priceTo: 57499,
      rating: 4.6, reviews: 184, color: '#37474f',
      badge: '2yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b933,999", "label": "Price w/o Battery"}, {"price": "\u20b947,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_13.png',
      tags: ["panther", "economic", "nmc", "black", "e12"]
    },
    {
      id: 'black-panther-e8', slug: 'black-panther-e8', name: 'Economic Black Panther E8', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 35999, priceTo: 66499,
      rating: 4.6, reviews: 184, color: '#212121',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b935,999", "label": "Price w/o Battery"}, {"price": "\u20b955,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_14.png',
      tags: ["panther", "economic", "nmc", "black", "e8"]
    },
    {
      id: 'black-panther-e8-plus', slug: 'black-panther-e8-plus', name: 'Economic Black Panther E8+', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 36999, priceTo: 67499,
      rating: 4.6, reviews: 184, color: '#263238',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b936,999", "label": "Price w/o Battery"}, {"price": "\u20b956,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_15.png',
      tags: ["panther", "economic", "nmc", "black", "e8+"]
    },
    {
      id: 'black-panther-e7', slug: 'black-panther-e7', name: 'Economic Black Panther E7', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 37999, priceTo: 68499,
      rating: 4.6, reviews: 184, color: '#b71c1c',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b937,999", "label": "Price w/o Battery"}, {"price": "\u20b957,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_16.png',
      tags: ["panther", "e7", "economic", "nmc", "black"]
    },
    {
      id: 'black-panther-e5', slug: 'black-panther-e5', name: 'Economic Black Panther E5', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 38999, priceTo: 69499,
      rating: 4.6, reviews: 184, color: '#0d47a1',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b938,999", "label": "Price w/o Battery"}, {"price": "\u20b958,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_17.png',
      tags: ["panther", "e5", "economic", "nmc", "black"]
    },
    {
      id: 'black-panther-e3', slug: 'black-panther-e3', name: 'Economic Black Panther E3', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 39999, priceTo: 70499,
      rating: 4.6, reviews: 184, color: '#37474f',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b939,999", "label": "Price w/o Battery"}, {"price": "\u20b959,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_18.png',
      tags: ["panther", "economic", "nmc", "black", "e3"]
    },
    {
      id: 'black-panther-e4', slug: 'black-panther-e4', name: 'Economic Black Panther E4', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 40999, priceTo: 71499,
      rating: 4.6, reviews: 184, color: '#1c1c1c',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b940,999", "label": "Price w/o Battery"}, {"price": "\u20b960,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_20.png',
      tags: ["e4", "panther", "economic", "nmc", "black"]
    },
    {
      id: 'black-panther-e7-pro', slug: 'black-panther-e7-pro', name: 'Economic Black Panther E7 Pro', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'high',
      battery: ["nmc"],
      priceFrom: 40999, priceTo: 71499,
      rating: 4.7, reviews: 188, color: '#1a1a1a',
      badge: '2yr',
      chips: ["90 km Range", "High Speed"],
      variants: [{"price": "\u20b940,999", "label": "Price w/o Battery"}, {"price": "\u20b960,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_21.png',
      tags: ["pro", "panther", "e7", "economic", "nmc", "black"]
    },
    {
      id: 'black-panther-e11', slug: 'black-panther-e11', name: 'Economic Black Panther E11', series: 'Economic',
      company: 'Economic', rangeKm: 90, speed: 'city',
      battery: ["nmc"],
      priceFrom: 41999, priceTo: 72499,
      rating: 4.5, reviews: 180, color: '#ffffff',
      badge: '2yr',
      chips: ["90 km Range", "Low Speed"],
      variants: [{"price": "\u20b941,999", "label": "Price w/o Battery"}, {"price": "\u20b961,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_22.png',
      tags: ["panther", "economic", "nmc", "black", "e11"]
    },
    {
      id: 'black-panther-e14', slug: 'black-panther-e14', name: 'Economic Black Panther E14', series: 'Economic',
      company: 'Economic', rangeKm: 70, speed: 'high',
      battery: ["nmc"],
      priceFrom: 51999, priceTo: 82499,
      rating: 4.7, reviews: 188, color: '#212121',
      badge: '2yr',
      chips: ["70 km Range", "High Speed"],
      variants: [{"price": "\u20b951,999", "label": "Price w/o Battery"}, {"price": "\u20b971,999", "label": "Price with NMC"}],
      image: baseUrl + '/assets/scooters/economic_nmc_2_yrs_bp_pricelist_july26_scooter_23.png',
      tags: ["panther", "economic", "e14", "nmc", "black"]
    },
    {
      id: 'oleant-g-pro-single-light', slug: 'oleant-g-pro-single-light', name: 'Oleant G-PRO (Single Light)', series: 'Oleant',
      company: 'Oleant', rangeKm: 80, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 53896, priceTo: 61980,
      rating: 4.5, reviews: 180, color: '#b0bec5',
      badge: '1yr',
      chips: ["80 km Range", "Low Speed"],
      variants: [{"price": "\u20b953,896", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_1.png',
      tags: ["oleant", "li-ion", "light)", "(single", "g-pro"]
    },
    {
      id: 'oleant-g-one-double-light', slug: 'oleant-g-one-double-light', name: 'Oleant G-ONE (Double Light)', series: 'Oleant',
      company: 'Oleant', rangeKm: 100, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 57613, priceTo: 66254,
      rating: 4.5, reviews: 180, color: '#4a4a4a',
      badge: '1yr',
      chips: ["100 km Range", "Low Speed"],
      variants: [{"price": "\u20b957,613", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_2.png',
      tags: ["oleant", "li-ion", "light)", "g-one", "(double"]
    },
    {
      id: 'oleant-alexa-round-light', slug: 'oleant-alexa-round-light', name: 'Oleant ALEXA (Round Light)', series: 'Oleant',
      company: 'Oleant', rangeKm: 100, speed: 'city',
      battery: ["li-ion"],
      priceFrom: 69384, priceTo: 79791,
      rating: 4.6, reviews: 184, color: '#f5f5f5',
      badge: '1yr',
      chips: ["100 km Range", "Low Speed"],
      variants: [{"price": "\u20b969,384", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_3.png',
      tags: ["oleant", "li-ion", "light)", "alexa", "(round"]
    },
    {
      id: 'oleant-alexa-plus-cs2', slug: 'oleant-alexa-plus-cs2', name: 'Oleant ALEXA+ (Activa CS2)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 73101, priceTo: 84066,
      rating: 4.6, reviews: 184, color: '#c62828',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b973,101", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_4.png',
      tags: ["oleant", "alexa+", "li-ion", "(activa", "cs2)"]
    },
    {
      id: 'oleant-alexa-pro-cs3', slug: 'oleant-alexa-pro-cs3', name: 'Oleant ALEXA-Pro (CS3)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 50000, priceTo: 60000,
      rating: 4.7, reviews: 188, color: '#00838f',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b950,000", "label": "Standard"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_5.png',
      tags: ["oleant", "li-ion", "alexa-pro", "(cs3)"]
    },
    {
      id: 'oleant-e4-adheera', slug: 'oleant-e4-adheera', name: 'Oleant E4 (Adheera)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#212121',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_6.png',
      tags: ["e4", "oleant", "li-ion", "(adheera)"]
    },
    {
      id: 'oleant-xj2', slug: 'oleant-xj2', name: 'Oleant XJ2', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#263238',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_7.png',
      tags: ["oleant", "li-ion", "xj2"]
    },
    {
      id: 'oleant-hg-jali', slug: 'oleant-hg-jali', name: 'Oleant HG (Square Light Jali)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#000000',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_8.png',
      tags: ["light", "oleant", "li-ion", "jali)", "(square", "hg"]
    },
    {
      id: 'oleant-fh', slug: 'oleant-fh', name: 'Oleant FH', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#c62828',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_9.png',
      tags: ["oleant", "fh", "li-ion"]
    },
    {
      id: 'oleant-jh-bmw', slug: 'oleant-jh-bmw', name: 'Oleant JH (BMW)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.7, reviews: 188, color: '#00838f',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_10.png',
      tags: ["jh", "oleant", "(bmw)", "li-ion"]
    },
    {
      id: 'oleant-2w-loader', slug: 'oleant-2w-loader', name: 'Oleant 2W Loader', series: 'Oleant',
      company: 'Oleant', rangeKm: 150, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 86160, priceTo: 99083,
      rating: 4.5, reviews: 180, color: '#4a4a4a',
      badge: '1yr',
      chips: ["150 km Range", "High Speed"],
      variants: [{"price": "\u20b986,160", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_11.png',
      tags: ["oleant", "li-ion", "loader", "2w"]
    },
    {
      id: 'oleant-tank-chetek', slug: 'oleant-tank-chetek', name: 'Oleant TANK (Chetek)', series: 'Oleant',
      company: 'Oleant', rangeKm: 120, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#305223',
      badge: '1yr',
      chips: ["120 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_12.png',
      tags: ["(chetek)", "oleant", "li-ion", "tank"]
    },
    {
      id: 'oleant-qtec-q7', slug: 'oleant-qtec-q7', name: 'Oleant QTEC (Q7)', series: 'Oleant',
      company: 'Oleant', rangeKm: 130, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#b0bec5',
      badge: '1yr',
      chips: ["130 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_14.png',
      tags: ["oleant", "qtec", "(q7)", "li-ion"]
    },
    {
      id: 'oleant-ola', slug: 'oleant-ola', name: 'Oleant OLA', series: 'Oleant',
      company: 'Oleant', rangeKm: 130, speed: 'high',
      battery: ["li-ion"],
      priceFrom: 76248, priceTo: 87685,
      rating: 4.6, reviews: 184, color: '#ffffff',
      badge: '1yr',
      chips: ["130 km Range", "High Speed"],
      variants: [{"price": "\u20b976,248", "label": "Dealer Sale Price"}],
      image: baseUrl + '/assets/scooters/oleant_updated_scooter_15.png',
      tags: ["oleant", "ola", "li-ion"]
    }
  ];

  const state = {
    q: '', priceMin: null, priceMax: null, rangeMin: null, rangeMax: null,
    series: [], battery: [], speed: [], sort: 'featured'
  };

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  const PARAMS = {
    q: 'q', priceMin: 'priceMin', priceMax: 'priceMax', rangeMin: 'rangeMin', rangeMax: 'rangeMax',
    series: 'series', battery: 'battery', speed: 'speed', sort: 'sort'
  };

  function parseList(v) { return v ? v.split(',').map(s => s.trim()).filter(Boolean) : []; }
  function numOrNull(v) { if (v == null || v === '') return null; const n = Number(v); return Number.isFinite(n) ? n : null; }

  function readURL() {
    const p = new URLSearchParams(location.search);
    state.q = p.get(PARAMS.q) || '';
    state.priceMin = numOrNull(p.get(PARAMS.priceMin));
    state.priceMax = numOrNull(p.get(PARAMS.priceMax));
    state.rangeMin = numOrNull(p.get(PARAMS.rangeMin));
    state.rangeMax = numOrNull(p.get(PARAMS.rangeMax));
    state.series = parseList(p.get(PARAMS.series));
    state.battery = parseList(p.get(PARAMS.battery));
    state.speed = parseList(p.get(PARAMS.speed));
    state.sort = p.get(PARAMS.sort) || 'featured';
  }

  function writeURL(replace = true) {
    const p = new URLSearchParams();
    if (state.q) p.set(PARAMS.q, state.q);
    if (state.priceMin != null) p.set(PARAMS.priceMin, String(state.priceMin));
    if (state.priceMax != null) p.set(PARAMS.priceMax, String(state.priceMax));
    if (state.rangeMin != null) p.set(PARAMS.rangeMin, String(state.rangeMin));
    if (state.rangeMax != null) p.set(PARAMS.rangeMax, String(state.rangeMax));
    if (state.series.length) p.set(PARAMS.series, state.series.join(','));
    if (state.battery.length) p.set(PARAMS.battery, state.battery.join(','));
    if (state.speed.length) p.set(PARAMS.speed, state.speed.join(','));
    if (state.sort && state.sort !== 'featured') p.set(PARAMS.sort, state.sort);

    const qs = p.toString();
    const url = qs ? `${location.pathname}?${qs}` : location.pathname;
    if (replace) history.replaceState({ filters: { ...state } }, '', url);
    else history.pushState({ filters: { ...state } }, '', url);
  }

  function matches(item) {
    if (state.q) {
      const hay = [item.name, item.series, item.company, ...(item.tags || []), ...(item.battery || []), ...(item.chips || [])].join(' ').toLowerCase();
      const tokens = state.q.toLowerCase().split(/\s+/).filter(Boolean);
      if (!tokens.every(t => hay.includes(t))) return false;
    }
    if (state.priceMin != null && item.priceTo < state.priceMin) return false;
    if (state.priceMax != null && item.priceFrom > state.priceMax) return false;
    if (state.rangeMin != null && item.rangeKm < state.rangeMin) return false;
    if (state.rangeMax != null && item.rangeKm > state.rangeMax) return false;
    if (state.series.length && !state.series.includes(item.series)) return false;
    if (state.battery.length && !state.battery.some(b => item.battery.includes(b))) return false;
    if (state.speed.length && !state.speed.includes(item.speed)) return false;
    return true;
  }

  function sortList(list) {
    const arr = [...list];
    switch (state.sort) {
      case 'price-asc': return arr.sort((a, b) => a.priceFrom - b.priceFrom);
      case 'price-desc': return arr.sort((a, b) => b.priceFrom - a.priceFrom);
      case 'range-desc': return arr.sort((a, b) => b.rangeKm - a.rangeKm);
      case 'rating-desc': return arr.sort((a, b) => b.rating - a.rating);
      default: return arr;
    }
  }

  function filtered() { return sortList(CATALOG.filter(matches)); }

  function cardHTML(p) {
    const vars = p.variants.map(v => `<button class="var" type="button"><b>${v.price}</b><span>${v.label}</span></button>`).join('');
    const chips = p.chips.map((c, i) => `<span><i data-lucide="${i === 0 ? 'battery-charging' : 'zap'}"></i>${c}</span>`).join('');
    const detailUrl = `${baseUrl}/product-detail?slug=${p.slug}`;
    const testRideUrl = `${baseUrl}/index#test-ride`;

    return `
<article class="card is-in" style="--c:${p.color}" data-id="${p.id}">
  <div class="card__media">
    <span class="card__badge"><i data-lucide="shield-check"></i>${p.badge}</span>
    <span class="card__360">360°</span>
    <img class="card__img" src="${p.image}" alt="${p.name}" loading="lazy">
    <i class="card__wash"></i>
    <i class="card__shine"></i>
  </div>
  <div class="card__body">
    <div class="card__rate"><i data-lucide="star"></i><b>${p.rating.toFixed(1)}</b><span>· ${p.reviews} reviews</span></div>
    <h3 class="card__name">${p.name}</h3>
    <div class="card__chips">${chips}</div>
    <div class="card__colors" role="group" aria-label="Available colours">
      <button class="sw is-on" style="--c:${p.color}" type="button" aria-label="Colour"></button>
    </div>
    <div class="card__price">
      <p class="card__plabel">Ex-showroom — starts at</p>
      <div class="card__vars">${vars}</div>
      <p class="card__note">*Without GST</p>
    </div>
    <div class="card__acts">
      <a class="btn btn--ghost" href="${detailUrl}">Explore</a>
      <a class="btn btn--ink" href="${testRideUrl}"><span>Test Ride</span><i data-lucide="bike"></i></a>
    </div>
  </div>
</article>`;
  }

  function renderGrid() {
    const list = filtered();
    const grid = $('#plGrid');
    const empty = $('#plEmpty');
    const count = $('#plCount');

    if (!list.length) {
      grid.innerHTML = '';
      empty.hidden = false;
      count.textContent = 'No models found';
    } else {
      empty.hidden = true;
      grid.innerHTML = list.map(cardHTML).join('');
      count.textContent = list.length === CATALOG.length
        ? `Showing all ${list.length} models`
        : `Showing ${list.length} of ${CATALOG.length} models`;
      if (window.lucide) lucide.createIcons({ nodes: grid.querySelectorAll('[data-lucide]') });
    }
    renderChips();
    updateBadge();
  }

  function activeFilterCount() {
    let n = 0;
    if (state.q) n++;
    if (state.priceMin != null || state.priceMax != null) n++;
    if (state.rangeMin != null || state.rangeMax != null) n++;
    n += state.series.length + state.battery.length + state.speed.length;
    return n;
  }

  function updateBadge() {
    const b = $('#plFilterBadge');
    const n = activeFilterCount();
    if (n) { b.hidden = false; b.textContent = String(n); }
    else b.hidden = true;
  }

  function renderChips() {
    const wrap = $('#plChips');
    const chips = [];
    if (state.q) chips.push({ key: 'q', label: `“${state.q}”` });
    if (state.priceMin != null || state.priceMax != null) {
      const a = state.priceMin != null ? `₹${state.priceMin.toLocaleString('en-IN')}` : '0';
      const b = state.priceMax != null ? `₹${state.priceMax.toLocaleString('en-IN')}` : '∞';
      chips.push({ key: 'price', label: `Price ${a}–${b}` });
    }
    if (state.rangeMin != null || state.rangeMax != null) {
      chips.push({ key: 'range', label: `Range ${state.rangeMin ?? 0}–${state.rangeMax ?? '∞'} km` });
    }
    state.series.forEach(s => chips.push({ key: 'series', value: s, label: s }));
    state.battery.forEach(s => chips.push({ key: 'battery', value: s, label: s }));
    state.speed.forEach(s => chips.push({ key: 'speed', value: s, label: s === 'high' ? 'High speed' : 'City' }));

    if (!chips.length) { wrap.hidden = true; wrap.innerHTML = ''; return; }
    wrap.hidden = false;
    wrap.innerHTML = chips.map(c =>
      `<button type="button" class="pl-chip" data-chip-key="${c.key}" data-chip-value="${c.value || ''}">${c.label} <i data-lucide="x"></i></button>`
    ).join('') + `<button type="button" class="pl-chip pl-chip--clear" data-chip-key="all">Clear all</button>`;
    if (window.lucide) lucide.createIcons({ nodes: wrap.querySelectorAll('[data-lucide]') });
  }

  function syncControlsFromState() {
    $('#plSearch').value = state.q;
    $('#plSearchClear').hidden = !state.q;
    $('#plSort').value = state.sort;
    $('#priceMin').value = state.priceMin ?? '';
    $('#priceMax').value = state.priceMax ?? '';
    if (state.priceMax != null) $('#priceMaxSlider').value = state.priceMax;
    $('#rangeMin').value = state.rangeMin ?? '';
    $('#rangeMax').value = state.rangeMax ?? '';

    $$('input[name="battery"]').forEach(el => { el.checked = state.battery.includes(el.value); });
    $$('input[name="speed"]').forEach(el => { el.checked = state.speed.includes(el.value); });
    $$('#filterSeries input').forEach(el => { el.checked = state.series.includes(el.value); });
  }

  function readControlsToState() {
    state.q = $('#plSearch').value.trim();
    state.sort = $('#plSort').value;
    state.priceMin = numOrNull($('#priceMin').value);
    state.priceMax = numOrNull($('#priceMax').value);
    state.rangeMin = numOrNull($('#rangeMin').value);
    state.rangeMax = numOrNull($('#rangeMax').value);
    state.battery = $$('input[name="battery"]:checked').map(el => el.value);
    state.speed = $$('input[name="speed"]:checked').map(el => el.value);
    state.series = $$('#filterSeries input:checked').map(el => el.value);
  }

  function apply({ push = false } = {}) {
    readControlsToState();
    writeURL(!push);
    renderGrid();
    $('#plSearchClear').hidden = !state.q;
  }

  function clearAll() {
    state.q = ''; state.priceMin = null; state.priceMax = null; state.rangeMin = null; state.rangeMax = null;
    state.series = []; state.battery = []; state.speed = []; state.sort = 'featured';
    syncControlsFromState();
    writeURL(true);
    renderGrid();
    $('#plSuggest').hidden = true;
  }

  function removeChip(key, value) {
    if (key === 'all') return clearAll();
    if (key === 'q') state.q = '';
    if (key === 'price') { state.priceMin = null; state.priceMax = null; }
    if (key === 'range') { state.rangeMin = null; state.rangeMax = null; }
    if (key === 'series') state.series = state.series.filter(s => s !== value);
    if (key === 'battery') state.battery = state.battery.filter(s => s !== value);
    if (key === 'speed') state.speed = state.speed.filter(s => s !== value);
    syncControlsFromState();
    writeURL(true);
    renderGrid();
  }

  function renderSuggest(q) {
    const box = $('#plSuggest');
    if (!q || q.length < 1) { box.hidden = true; box.innerHTML = ''; return; }
    const tokens = q.toLowerCase().split(/\s+/).filter(Boolean);
    const hits = CATALOG.filter(item => {
      const hay = [item.name, item.series, item.company, ...(item.tags || [])].join(' ').toLowerCase();
      return tokens.every(t => hay.includes(t));
    }).slice(0, 6);
    if (!hits.length) {
      box.innerHTML = `<div class="pl-suggest__empty">No matches for “${q}”</div>`;
      box.hidden = false;
      return;
    }
    box.innerHTML = hits.map(h => `
      <button type="button" class="pl-suggest__row" data-slug="${h.slug}" role="option">
        <img src="${h.image}" alt="">
        <span><b>${h.name}</b><small>${h.rangeKm} km · from ₹${h.priceFrom.toLocaleString('en-IN')}</small></span>
      </button>`).join('');
    box.hidden = false;
  }

  function buildSeriesFilters() {
    const series = [...new Set(CATALOG.map(c => c.series))];
    $('#filterSeries').innerHTML = series.map(s => `<label><input type="checkbox" name="series" value="${s}"> ${s}</label>`).join('');
  }

  function bind() {
    let t = null;
    $('#plSearch').addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        state.q = $('#plSearch').value.trim();
        renderSuggest(state.q);
        apply();
      }, 160);
    });
    $('#plSearch').addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        $('#plSuggest').hidden = true;
        $('#plSearch').blur();
      }
    });
    $('#plSearchClear').addEventListener('click', () => {
      state.q = ''; $('#plSearch').value = ''; $('#plSuggest').hidden = true; apply();
    });
    $('#plSuggest').addEventListener('click', e => {
      const row = e.target.closest('[data-slug]');
      if (!row) return;
      location.href = `${baseUrl}/product-detail?slug=${row.dataset.slug}`;
    });
    document.addEventListener('click', e => {
      if (!$('#plSearchWrap').contains(e.target)) $('#plSuggest').hidden = true;
    });

    ['priceMin', 'priceMax', 'rangeMin', 'rangeMax'].forEach(id => {
      $(`#${id}`).addEventListener('change', () => apply());
    });
    $('#priceMaxSlider').addEventListener('input', e => { $('#priceMax').value = e.target.value; });
    $('#priceMaxSlider').addEventListener('change', () => apply());

    $$('[data-group="rangePreset"] button').forEach(btn => {
      btn.addEventListener('click', () => {
        state.rangeMin = Number(btn.dataset.rmin);
        state.rangeMax = Number(btn.dataset.rmax);
        syncControlsFromState(); writeURL(true); renderGrid();
      });
    });

    $('#filterSeries').addEventListener('change', () => apply());
    $$('input[name="battery"], input[name="speed"]').forEach(el => el.addEventListener('change', () => apply()));
    $('#plSort').addEventListener('change', () => apply());

    $('#plClearAll').addEventListener('click', clearAll);
    $('#plEmptyClear').addEventListener('click', clearAll);
    $('#plChips').addEventListener('click', e => {
      const chip = e.target.closest('[data-chip-key]');
      if (!chip) return;
      removeChip(chip.dataset.chipKey, chip.dataset.chipValue);
    });

    const backdrop = $('#plFiltersBackdrop');
    const setFiltersOpen = (open) => {
      $('#plFilters').classList.toggle('is-open', open);
      $('#plFilterToggle').setAttribute('aria-expanded', open ? 'true' : 'false');
      if (backdrop) {
        backdrop.hidden = !open;
        backdrop.classList.toggle('is-on', open);
      }
      document.body.style.overflow = open && window.innerWidth <= 960 ? 'hidden' : '';
    };
    $('#plFilterToggle').addEventListener('click', () => setFiltersOpen(!$('#plFilters').classList.contains('is-open')));
    backdrop?.addEventListener('click', () => setFiltersOpen(false));

    $$('[data-filter-speed]').forEach(btn => {
      btn.addEventListener('click', () => {
        state.speed = [btn.dataset.filterSpeed];
        syncControlsFromState(); writeURL(true); renderGrid();
        $('#plFilters').classList.add('is-open');
      });
    });

    window.addEventListener('popstate', () => {
      readURL(); syncControlsFromState(); renderGrid();
    });
  }

  buildSeriesFilters();
  readURL();
  syncControlsFromState();
  bind();
  renderGrid();

  // Dynamically load live products from API
  fetch(baseUrl + '/api/v1/website/products')
    .then(r => r.json())
    .then(res => {
      if (res.data && Array.isArray(res.data) && res.data.length > 0) {
        const liveProducts = res.data.map(p => ({
          id: p.slug || p.id,
          slug: p.slug || p.id,
          name: p.name || 'Hazra Scooter',
          series: p.series || 'CHALO',
          company: 'Hazra',
          rangeKm: Number(p.range_km || p.rangeKm || 100),
          speed: p.speed_type || p.speed || 'high',
          battery: Array.isArray(p.battery) ? p.battery : [p.battery_type || 'graphene'],
          priceFrom: Number(p.price_from || p.priceFrom || p.price || 50000),
          priceTo: Number(p.price_to || p.priceTo || p.price || 70000),
          rating: Number(p.rating || 4.8),
          reviews: Number(p.reviews || 100),
          color: p.color || '#7b2ff7',
          badge: p.badge || 'Warranty Guaranteed',
          chips: [`${p.range_km || 100} km Range`, p.speed_type === 'city' ? 'Low Speed' : 'High Speed'],
          variants: Array.isArray(p.variants) && p.variants.length ? p.variants : [
            { price: `₹${Number(p.price_from || p.price || 50000).toLocaleString('en-IN')}`, label: p.battery_type || 'Standard' }
          ],
          image: p.image || p.avatar || (baseUrl + '/assets/scutie_light.webp'),
          tags: [p.name, p.series, p.battery_type].filter(Boolean)
        }));
        CATALOG.length = 0;
        CATALOG.push(...liveProducts);
        buildSeriesFilters();
        renderGrid();
      }
    })
    .catch(() => {});

  if (window.lucide) lucide.createIcons();
});
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
