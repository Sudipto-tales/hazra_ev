<?php
$navProducts = [];
try {
    if (function_exists('db_fetch_all')) {
        $navProducts = db_fetch_all("SELECT id, name, category, top_speed_kmph, hero_image, slug FROM products WHERE active = 1 AND hero_image IS NOT NULL AND hero_image != '' ORDER BY is_featured DESC, updated_at DESC LIMIT 6");
    }
} catch (Throwable $e) {}

if (empty($navProducts)) {
    $navProducts = [
        ['id' => 'striker', 'name' => 'Striker', 'slug' => 'hazra-striker', 'top_speed_kmph' => 45, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_9.png'],
        ['id' => 'soul-pro', 'name' => 'Soul Pro', 'slug' => 'hazra-soul-pro', 'top_speed_kmph' => 55, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_14.png'],
        ['id' => 'wind-pro', 'name' => 'Wind Pro', 'slug' => 'hazra-wind-pro', 'top_speed_kmph' => 50, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_13.png'],
        ['id' => 'wind', 'name' => 'Wind', 'slug' => 'hazra-wind', 'top_speed_kmph' => 45, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_12.png'],
        ['id' => 'max-e4', 'name' => 'Max E4', 'slug' => 'hazra-max-e4', 'top_speed_kmph' => 55, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_15.png'],
        ['id' => 'spark', 'name' => 'Spark', 'slug' => 'hazra-spark', 'top_speed_kmph' => 55, 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_16.png'],
    ];
}

// Featured products (is_featured = 1)
$featuredProducts = [];
try {
    if (function_exists('db_fetch_all')) {
        $featuredProducts = db_fetch_all("
            SELECT id, name, model_code, slug, hero_image, range_km, top_speed_kmph, 
                   rating, warranty_years, battery_capacity, motor_power, 
                   category, is_featured, featured_order
            FROM products 
            WHERE active = 1 AND is_featured = 1 
            ORDER BY featured_order ASC, updated_at DESC 
            LIMIT 6
        ");
    }
} catch (Throwable $e) {}

// Fallback featured products if none returned from DB
if (empty($featuredProducts)) {
    $featuredProducts = [
        ['id' => 'striker', 'name' => 'Striker', 'model_code' => 'HZ-STR', 'slug' => 'hazra-striker', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_9.png', 'range_km' => 80, 'top_speed_kmph' => 45, 'rating' => 4.8, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '60V / 30Ah Li-ion', 'motor_power' => '1200W BLDC'],
        ['id' => 'soul-pro', 'name' => 'Soul Pro', 'model_code' => 'HZ-SOULPRO', 'slug' => 'hazra-soul-pro', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_14.png', 'range_km' => 100, 'top_speed_kmph' => 55, 'rating' => 4.9, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '72V / 35Ah Li-ion', 'motor_power' => '1500W BLDC'],
        ['id' => 'wind-pro', 'name' => 'Wind Pro', 'model_code' => 'HZ-WNDPRO', 'slug' => 'hazra-wind-pro', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_13.png', 'range_km' => 90, 'top_speed_kmph' => 50, 'rating' => 4.8, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '60V / 32Ah Li-ion', 'motor_power' => '1200W BLDC'],
        ['id' => 'wind', 'name' => 'Wind', 'model_code' => 'HZ-WND', 'slug' => 'hazra-wind', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_12.png', 'range_km' => 85, 'top_speed_kmph' => 45, 'rating' => 4.7, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '60V / 28Ah Li-ion', 'motor_power' => '1000W BLDC'],
        ['id' => 'max-e4', 'name' => 'Max E4', 'model_code' => 'HZ-MAXE4', 'slug' => 'hazra-max-e4', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_15.png', 'range_km' => 110, 'top_speed_kmph' => 55, 'rating' => 4.8, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '72V / 40Ah Li-ion', 'motor_power' => '1800W BLDC'],
        ['id' => 'spark', 'name' => 'Spark', 'model_code' => 'HZ-SPARK', 'slug' => 'hazra-spark', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_16.png', 'range_km' => 110, 'top_speed_kmph' => 55, 'rating' => 4.8, 'warranty_years' => 1, 'category' => 'scooty', 'battery_capacity' => '72V / 40Ah Li-ion', 'motor_power' => '1800W BLDC'],
    ];
}

// Latest 6 products for Collection section
$collectionProducts = [];
try {
    if (function_exists('db_fetch_all')) {
        $collectionProducts = db_fetch_all("
            SELECT id, name, slug, hero_image, range_km, top_speed_kmph, 
                   rating, warranty_years, battery_capacity, motor_power, 
                   category
            FROM products 
            WHERE active = 1 
            ORDER BY updated_at DESC 
            LIMIT 6
        ");
    }
} catch (Throwable $e) {}

// Fallback to hardcoded if no products in DB
if (empty($collectionProducts)) {
    $collectionProducts = [
        ['id' => 'chalo-1000-v2', 'name' => 'CHALO 1000 V2', 'slug' => 'chalo-1000-v2', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_9.png', 'range_km' => 100, 'top_speed_kmph' => 65, 'rating' => 4.8, 'warranty_years' => 4, 'category' => 'scooty', 'battery_capacity' => '60V / 32Ah Graphene', 'motor_power' => '1200W'],
        ['id' => 'chalo-smart-pro', 'name' => 'CHALO SMART PRO', 'slug' => 'chalo-smart-pro', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_14.png', 'range_km' => 120, 'top_speed_kmph' => 70, 'rating' => 4.9, 'warranty_years' => 4, 'category' => 'scooty', 'battery_capacity' => '72V / 40Ah Li-ion', 'motor_power' => '1500W'],
        ['id' => 'chalo-smart-eco', 'name' => 'CHALO SMART ECO', 'slug' => 'chalo-smart-eco', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_12.png', 'range_km' => 130, 'top_speed_kmph' => 45, 'rating' => 4.7, 'warranty_years' => 3, 'category' => 'scooty', 'battery_capacity' => '60V / 32Ah Graphene', 'motor_power' => '1000W'],
        ['id' => 'chalo-smart-plus', 'name' => 'CHALO SMART PLUS', 'slug' => 'chalo-smart-plus', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_13.png', 'range_km' => 85, 'top_speed_kmph' => 45, 'rating' => 4.6, 'warranty_years' => 3, 'category' => 'scooty', 'battery_capacity' => '48V / 28Ah Graphene', 'motor_power' => '1000W'],
        ['id' => 'chalo-neo', 'name' => 'CHALO NEO', 'slug' => 'chalo-neo', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_15.png', 'range_km' => 50, 'top_speed_kmph' => 40, 'rating' => 4.5, 'warranty_years' => 4, 'category' => 'scooty', 'battery_capacity' => '48V / 24Ah Graphene', 'motor_power' => '800W'],
        ['id' => 'nja-7', 'name' => 'NJA-7', 'slug' => 'nja-7', 'hero_image' => 'assets/scooters/hazra_broucher_6_scooter_16.png', 'range_km' => 140, 'top_speed_kmph' => 75, 'rating' => 5.0, 'warranty_years' => 4, 'category' => 'bike', 'battery_capacity' => '72V / 45Ah Li-ion', 'motor_power' => '2000W'],
    ];
}

// Fetch colors for all products in both sections
$allProductIds = array_filter(array_unique(array_merge(
    array_column($featuredProducts, 'id'),
    array_column($collectionProducts, 'id')
)));

$productColorsMap = [];
if (!empty($allProductIds) && function_exists('db_fetch_all')) {
    try {
        $cph = implode(',', array_fill(0, count($allProductIds), '?'));
        $colorRows = db_fetch_all("
            SELECT product_id, name, argb, position 
            FROM product_colors 
            WHERE product_id IN ($cph) 
            ORDER BY position ASC, name ASC
        ", array_values($allProductIds));
        foreach ($colorRows as $cr) {
            $productColorsMap[$cr['product_id']][] = $cr;
        }
    } catch (Throwable $e) {}
}

if (!function_exists('renderHomeProductCard')) {
    function renderHomeProductCard($p, $productColorsMap) {
        $pId = $p['id'] ?? '';
        $colors = $productColorsMap[$pId] ?? [];
        if (empty($colors)) {
            $colors = [
                ['name' => 'Matte Black', 'argb' => -14671840],
                ['name' => 'Ocean Blue',  'argb' => -15555040],
                ['name' => 'Pearl White', 'argb' => -1050216],
            ];
        }
        $primaryColorHex = sprintf('#%06x', ((int)($colors[0]['argb'] ?? 0)) & 0xFFFFFF);
        $imgSrc = !empty($p['hero_image']) ? $p['hero_image'] : 'assets/scooters/hazra_broucher_6_scooter_9.png';
        $isHigh = ((int)($p['top_speed_kmph'] ?? 0)) >= 50;
        $productUrl = !empty($p['slug']) 
            ? base_url('product-detail?slug=' . urlencode($p['slug'])) 
            : base_url('product-detail?id=' . urlencode($p['id'] ?? ''));
        $rating = !empty($p['rating']) && (float)$p['rating'] > 0 ? number_format((float)$p['rating'], 1) : '4.8';
        $reviewsCount = 100 + (abs(crc32((string)$pId)) % 250);
        $warrantyYears = (int)($p['warranty_years'] ?? 3);
        if ($warrantyYears <= 0) $warrantyYears = 3;
        ?>
        <article class="card" style="--c:<?= e($primaryColorHex) ?>">
          <div class="card__media">
            <span class="card__badge"><i data-lucide="shield-check"></i><?= $warrantyYears ?> Years Warranty</span>
            <span class="card__360">360&deg;</span>
            <img class="card__img" src="<?= e(base_url($imgSrc)) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <i class="card__wash"></i>
            <i class="card__shine"></i>
          </div>
          <div class="card__body">
            <div class="card__rate"><i data-lucide="star"></i><b><?= e($rating) ?></b><span>· <?= $reviewsCount ?> reviews</span></div>
            <h3 class="card__name"><?= e($p['name']) ?></h3>
            <div class="card__chips">
              <?php if (!empty($p['range_km'])): ?>
                <span><i data-lucide="battery-charging"></i><?= (int)$p['range_km'] ?> km Range</span>
              <?php endif; ?>
              <span><i data-lucide="<?= $isHigh ? 'zap' : 'feather' ?>"></i><?= $isHigh ? 'High Speed' : 'Low Speed' ?></span>
            </div>
            <div class="card__colors" role="group" aria-label="Available colours">
              <?php foreach ($colors as $cIdx => $c): 
                $cHex = sprintf('#%06x', ((int)$c['argb']) & 0xFFFFFF);
              ?>
                <button class="sw <?= $cIdx === 0 ? 'is-on' : '' ?>" style="--c:<?= e($cHex) ?>" aria-label="<?= e($c['name'] ?? 'Colour') ?>" title="<?= e($c['name'] ?? 'Colour') ?>"></button>
              <?php endforeach; ?>
            </div>
            <div class="card__price">
              <p class="card__plabel">Ex&#8209;showroom &mdash; starts at</p>
              <div class="card__vars">
                <button class="var"><b><?= !empty($p['battery_capacity']) ? e($p['battery_capacity']) : 'Standard Pack' ?></b><span>Battery Spec</span></button>
                <button class="var"><b><?= !empty($p['motor_power']) ? e($p['motor_power']) : 'BLDC Motor' ?></b><span>Motor Power</span></button>
              </div>
              <p class="card__note">*Without GST</p>
            </div>
            <div class="card__acts">
              <a class="btn btn--ghost" href="<?= e($productUrl) ?>">Explore</a>
              <a class="btn btn--ink" href="#test-ride"><span>Test Ride</span><i data-lucide="bike"></i></a>
            </div>
          </div>
        </article>
        <?php
    }
}

App::render('head', [
    'pageTitle' => 'Hazra Electrical Bike — Follow Elegant',
    'pageDescription' => 'Hazra Electrical Bike — electric scooters built for everyday Indian riding. Book a test drive or find a dealership near you.'
]);
?>

<!-- ══════════ HERO ══════════ -->
<!-- .scroll is the tall driver; .stage sticks inside it, so scrolling
     feeds the zoom instead of moving to another section -->
<div class="scroll" id="scroll">
<section class="stage">
  <div class="stage__bg"></div>
  <div class="stage__veil"></div>

  <main class="canvas" id="canvas">

    <!-- photo sits underneath; white plates notch into it -->
    <figure class="photo">
      <img class="photo__img photo__img--light" src="<?= e(base_url('assets/scutie_light.webp')) ?>" alt="Hazra Electrical Bike in daylight">
      <img class="photo__img photo__img--dark"  src="<?= e(base_url('assets/dark_scutie.webp')) ?>"  alt="Hazra Electrical Bike at night" aria-hidden="true">
      <div class="photo__shade"></div>

      <div class="pill pill--b reveal-pop"><i data-lucide="gauge"></i><span>120&nbsp;km/hour</span></div>

      <div class="vtag">
        <span class="vtag__txt">HAZRA ELECTRICAL BIKE</span>
        <span class="vtag__dot"><i data-lucide="arrow-up-right"></i></span>
      </div>

      <div class="glass">
        <div class="glass__row"><b>Hazra</b><b>Electrical</b></div>
        <div class="glass__row"><b>Retro</b><b>Elegant</b></div>
        <div class="glass__row"><b>Energy</b><b>Speed</b></div>
      </div>
    </figure>

    <!-- floats outside the photo mask so it can straddle the edge -->
    <div class="pill pill--a reveal-pop"><i data-lucide="wind"></i><span>Soft&nbsp;/&nbsp;Touch</span></div>

    <!-- ── white plate: top-left nav ── -->
    <header class="topbar">
      <a class="brand" href="<?= e(base_url()) ?>" aria-label="Hazra Electrical Bike — home">
        <img class="brand__mark" src="<?= e(base_url('assets/hazraev.png')) ?>" alt="Hazra Electrical Bike">
        <span class="brand__txt">Hazra<b>Electrical Bike</b></span>
      </a>

      <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="nav">
        <span></span><span></span><span></span>
      </button>

      <nav class="nav" id="nav">
        <a href="<?= e(base_url()) ?>" class="nav__i is-on">Home</a>

        <div class="nav__grp">
          <button class="nav__i nav__i--t" aria-expanded="false">
            About <i data-lucide="chevron-down" class="nav__cv"></i>
          </button>
          <div class="nav__menu">
            <a href="<?= e(base_url('our-story')) ?>" class="nav__s">Our Story</a>
            <a href="<?= e(base_url('career')) ?>"    class="nav__s">Career</a>
            <a href="#faq"       class="nav__s">FAQ</a>
          </div>
        </div>

        <div class="nav__grp">
          <button class="nav__i nav__i--t" aria-expanded="false">
            Products <i data-lucide="chevron-down" class="nav__cv"></i>
          </button>
          <div class="nav__menu nav__menu--products">
            <div class="nav__products-header">
              <span class="nav__products-title">Scooter Collection</span>
              <a href="<?= e(base_url('products')) ?>" class="nav__products-all">View All Models &rarr;</a>
            </div>
            <div class="nav__product-grid">
              <?php foreach ($navProducts as $np): 
                $imgSrc = !empty($np['hero_image']) ? base_url($np['hero_image']) : base_url('assets/scooters/hazra_broucher_6_scooter_9.png');
                $isHigh = ((int)($np['top_speed_kmph'] ?? 0)) >= 50;
                $productUrl = !empty($np['slug']) 
                  ? base_url('product-detail?slug=' . urlencode($np['slug'])) 
                  : base_url('product-detail?id=' . urlencode($np['id'] ?? ''));
              ?>
                <a href="<?= e($productUrl) ?>" class="nav__product">
                  <div class="nav__product-img-wrap"><img class="nav__product-img" src="<?= e($imgSrc) ?>" alt="<?= e($np['name']) ?>" loading="lazy"></div>
                  <span class="nav__product-name"><?= e($np['name']) ?></span>
                  <span class="nav__product-badge <?= $isHigh ? 'is-high' : 'is-low' ?>">
                    <?= $isHigh ? 'High Speed' : 'Low Speed' ?>
                  </span>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="nav__grp">
          <button class="nav__i nav__i--t" aria-expanded="false">
            Social <i data-lucide="chevron-down" class="nav__cv"></i>
          </button>
          <div class="nav__menu">
            <a href="<?= e(base_url('contest')) ?>"     class="nav__s">Contest <em>Reels Contest</em></a>
            <a href="<?= e(base_url('blog')) ?>"        class="nav__s">Blog</a>
            <a href="#news"        class="nav__s">News</a>
            <a href="<?= e(base_url('battery-use')) ?>" class="nav__s">Battery Use</a>
            <a href="<?= e(base_url('ev-future')) ?>"   class="nav__s">EV Future</a>
          </div>
        </div>

        <div class="nav__grp">
          <button class="nav__i nav__i--t" aria-expanded="false">
            Dealers <i data-lucide="chevron-down" class="nav__cv"></i>
          </button>
          <div class="nav__menu">
            <a href="<?= e(base_url('dealer-locator')) ?>"  class="nav__s">Dealer Locator</a>
            <a href="<?= e(base_url('become-a-dealer')) ?>" class="nav__s">Become a Dealer</a>
          </div>
        </div>

        <div class="nav__grp">
          <button class="nav__i nav__i--t" aria-expanded="false">
            Service <i data-lucide="chevron-down" class="nav__cv"></i>
          </button>
          <div class="nav__menu nav__menu--wide">
            <a href="<?= e(base_url('warranty-free')) ?>" class="nav__s">Free Warranty Registration</a>
            <a href="<?= e(base_url('warranty-paid')) ?>" class="nav__s">Paid Warranty Registration</a>
          </div>
        </div>
      </nav>

      <button class="theme" id="theme" aria-label="Toggle theme">
        <i data-lucide="sun-medium" class="theme__sun"></i>
        <i data-lucide="moon" class="theme__moon"></i>
      </button>
    </header>

    <!-- ── white plate: top-right tab (notches the photo) ── -->
    <div class="tab">
      <a href="<?= e(base_url('products')) ?>" class="cart" aria-label="Browse products"><i data-lucide="shopping-bag"></i></a>
      <a href="<?= e(base_url('contact')) ?>" class="contact" style="display:inline-flex; align-items:center; justify-content:center; text-decoration:none;">CONTACT US</a>
    </div>

    <!-- ── headline ── -->
    <div class="copy">
      <h1 class="title reveal-up">
        <span class="l">Feel True</span>
        <span class="l">
          <span class="faces">
            <img src="https://i.pravatar.cc/80?u=11" alt="">
            <img src="https://i.pravatar.cc/80?u=22" alt="">
            <img src="https://i.pravatar.cc/80?u=33" alt="">
          </span>
          Comfort With
        </span>
        <span class="l">Hazra Electrical Bike</span>
        <span class="l">Follow Elegant</span>
      </h1>

      <div class="meter">
        <span class="meter__rule"></span>
        <span class="meter__n" id="count">00</span>
        <span class="meter__rule meter__rule--long"></span>
      </div>

      <p class="blurb"><i class="blurb__sq"></i>
        HAZRA ELECTRICAL BIKE IS AN ENVIRONMENTALLY FRIENDLY CHOICE WITH A
        MOTOR THAT IS EFFICIENT IN ENERGY CONSUMPTION.
      </p>
    </div>

    <!-- ── white plate: bottom shelf (notches the photo) ── -->
    <footer class="shelf">
      <div class="pager"><b>01</b><span>/02</span></div>

      <p class="shelf__meta"><i class="shelf__sq"></i>DAY &amp; NIGHT FINISH</p>

      <div class="arrows">
        <button class="arrow" data-dir="-1" aria-label="Previous finish"><i data-lucide="arrow-left"></i></button>
        <button class="arrow" data-dir="1" aria-label="Next finish"><i data-lucide="arrow-right"></i></button>
      </div>
    </footer>

    <!-- ── inverse fillets: the interlock ── -->
    <i class="fillet fillet--tab"></i>
    <i class="fillet fillet--shelf"></i>

    <!-- ── scroll cue ── -->
    <div class="cue"><span>SCROLL TO ZOOM</span><i data-lucide="chevrons-down"></i></div>

  </main>
</section>
</div>

<!-- ══════════ STICKY NAVBAR ══════════ 
     Full-width glass bar; appears after hero scroll.
     Hides on scroll-down, shows on scroll-up or idle. -->
<header class="sticky-bar" id="stickyBar" aria-hidden="true">
  <div class="sticky-bar__inner">
    <a class="brand sticky-bar__brand" href="<?= e(base_url()) ?>" aria-label="Hazra Electrical Bike — home">
      <img class="brand__mark" src="<?= e(base_url('assets/hazraev.png')) ?>" alt="Hazra Electrical Bike">
      <span class="brand__txt">Hazra<b>Electrical Bike</b></span>
    </a>

    <button class="burger" id="stickyBurger" aria-label="Open menu" aria-expanded="false" aria-controls="stickyNav">
      <span></span><span></span><span></span>
    </button>

    <nav class="nav sticky-bar__nav" id="stickyNav">
      <a href="<?= e(base_url()) ?>" class="nav__i is-on">Home</a>

      <div class="nav__grp">
        <button class="nav__i nav__i--t" aria-expanded="false">
          About <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('our-story')) ?>" class="nav__s">Our Story</a>
          <a href="<?= e(base_url('career')) ?>"    class="nav__s">Career</a>
          <a href="#faq"       class="nav__s">FAQ</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t" aria-expanded="false">
          Products <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu nav__menu--products">
          <div class="nav__products-header">
            <span class="nav__products-title">Scooter Collection</span>
            <a href="<?= e(base_url('products')) ?>" class="nav__products-all">View All Models &rarr;</a>
          </div>
          <div class="nav__product-grid">
              <?php foreach ($navProducts as $np): 
                $imgSrc = !empty($np['hero_image']) ? base_url($np['hero_image']) : base_url('assets/scooters/hazra_broucher_6_scooter_9.png');
                $isHigh = ((int)($np['top_speed_kmph'] ?? 0)) >= 50;
                $productUrl = !empty($np['slug']) 
                  ? base_url('product-detail?slug=' . urlencode($np['slug'])) 
                  : base_url('product-detail?id=' . urlencode($np['id'] ?? ''));
              ?>
                <a href="<?= e($productUrl) ?>" class="nav__product">
                  <div class="nav__product-img-wrap"><img class="nav__product-img" src="<?= e($imgSrc) ?>" alt="<?= e($np['name']) ?>" loading="lazy"></div>
                  <span class="nav__product-name"><?= e($np['name']) ?></span>
                  <span class="nav__product-badge <?= $isHigh ? 'is-high' : 'is-low' ?>">
                    <?= $isHigh ? 'High Speed' : 'Low Speed' ?>
                  </span>
                </a>
              <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t" aria-expanded="false">
          Social <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('contest')) ?>"     class="nav__s">Contest <em>Reels Contest</em></a>
          <a href="<?= e(base_url('blog')) ?>"        class="nav__s">Blog</a>
          <a href="#news"        class="nav__s">News</a>
          <a href="<?= e(base_url('battery-use')) ?>" class="nav__s">Battery Use</a>
          <a href="<?= e(base_url('ev-future')) ?>"   class="nav__s">EV Future</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t" aria-expanded="false">
          Dealers <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('dealer-locator')) ?>"    class="nav__s">Locate Dealers</a>
          <a href="<?= e(base_url('become-a-dealer')) ?>"   class="nav__s">Become a Dealer</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t" aria-expanded="false">
          Service <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('warranty-free')) ?>"     class="nav__s">Warranty (Free)</a>
          <a href="<?= e(base_url('warranty-paid')) ?>"     class="nav__s">Warranty (Paid)</a>
          <a href="<?= e(base_url('battery-use')) ?>"       class="nav__s">Battery Care</a>
        </div>
      </div>

      <a href="<?= e(base_url('admin')) ?>" class="nav__i">Admin</a>
      <a href="<?= e(base_url('contact')) ?>" class="nav__i">Contact</a>
    </nav>

    <div class="sticky-bar__end">
      <button class="theme" id="stickyTheme">
        <i class="theme__sun" data-lucide="sun"></i>
        <i class="theme__moon" data-lucide="moon"></i>
      </button>
      <a href="<?= e(base_url('contact')) ?>" class="sticky-bar__cta" aria-label="Contact us">
        <i data-lucide="shopping-bag"></i>
        <span>CONTACT US</span>
      </a>
    </div>
  </div>
</header>


<!-- ══════════ ABOUT ══════════ -->
<section class="about" id="about">
  <div class="wrap">

    <header class="sec__head">
      <p class="eyebrow"><i class="sq"></i>ABOUT HAZRA ELECTRICAL BIKE</p>
      <h2 class="sec__title reveal-up">
        Built in the city,<br><span class="hl">tuned for the long way home.</span>
      </h2>
    </header>

    <div class="about__grid">

      <figure class="mosaic" id="mosaic">
        <video class="mosaic__v" id="mosaicVideo"
               muted loop playsinline preload="none"
               aria-label="Hazra Electrical Bike on the road">
          <source src="<?= e(base_url('assets/about.mp4')) ?>" type="video/mp4">
        </video>

        <!-- tiles injected by script.js -->
        <div class="mosaic__grid" aria-hidden="true"></div>

        <button class="mosaic__play" id="mosaicPlay" aria-label="Play reel">
          <i data-lucide="play"></i>
        </button>
        <figcaption class="mosaic__cap"><i class="sq"></i>THE 2026 REEL &mdash; 01:12</figcaption>
      </figure>

      <div class="about__copy">
        <p class="about__lead reveal-up">
          Hazra Electrical Bike started as one frame, one battery and a stubborn idea &mdash;
          that an electric scooter should feel like something you <em>want</em> to
          ride, not something you settle for.
        </p>
        <p class="about__text reveal-up">
          Every model shares the same spine: a swappable pack, a torque map written
          for stop&#8209;start traffic, and a body pressed in one piece so there is
          nothing to rattle loose. We build them a few hundred at a time, we service
          what we sell, and we publish the range figures we actually measured.
        </p>

        <ul class="stats">
          <li class="stat"><b class="stat__n" data-to="12">0</b><span>Years on the road</span></li>
          <li class="stat"><b class="stat__n" data-to="48" data-suffix="+">0</b><span>Service points</span></li>
          <li class="stat"><b class="stat__n" data-to="26" data-suffix="k">0</b><span>Riders delivered</span></li>
          <li class="stat"><b class="stat__n" data-to="130" data-suffix=" km">0</b><span>Best-case range</span></li>
        </ul>

        <a class="btn btn--ink" href="#collection">
          <span>See the collection</span><i data-lucide="arrow-right"></i>
        </a>
      </div>

    </div>
  </div>
</section>




<!-- ══════════ COLLECTION ══════════ -->
<section class="coll" id="collection">
  <div class="wrap">

    <header class="sec__head sec__head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>THE COLLECTION</p>
        <h2 class="sec__title reveal-up">Where the range<br><span class="hl">meets the road.</span></h2>
      </div>
      <a class="link" href="<?= e(base_url('products')) ?>">View complete collection<i data-lucide="arrow-up-right"></i></a>
    </header>

    <div class="coll__grid" id="collGrid">
      <?php foreach ($collectionProducts as $p): ?>
        <?php renderHomeProductCard($p, $productColorsMap); ?>
      <?php endforeach; ?>
    </div>

    <p class="coll__foot"><i class="sq"></i>
      Prices are indicative and vary by configuration and location.
    </p>
  </div>
</section>


<!-- ══════════ WHY CHOOSE ══════════ -->
<section class="why" id="why">
  <div class="wrap">

    <div class="why__grid">

      <header class="why__rail">
        <p class="eyebrow"><i class="sq"></i>WHY HAZRA ELECTRICAL BIKE</p>
        <h2 class="sec__title reveal-up">
          Premium where<br><span class="hl">it matters most.</span>
        </h2>
        <p class="why__lead reveal-up">
          Clean design, stable range, quiet power and service confidence &mdash;
          tuned for everyday Indian riding, not for a spec sheet.
        </p>
        <a class="btn btn--ink why__cta" href="#collection">
          <span>Start with a test ride</span><i data-lucide="bike"></i>
        </a>
      </header>

      <ol class="why__list">

        <li class="why__item">
          <span class="why__n">01</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="wind"></i>Smooth Control</h3>
            <p class="why__d">Quiet riding tuned for city traffic and everyday control.</p>
          </div>
        </li>

        <li class="why__item">
          <span class="why__n">02</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="battery-charging"></i>Range Confidence</h3>
            <p class="why__d">Li&#8209;ion and lead&#8209;graphene packs, both charged from a wall socket.</p>
          </div>
        </li>

        <li class="why__item">
          <span class="why__n">03</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="shield-check"></i>Built Tough</h3>
            <p class="why__d">Durable frame, confident braking, dealer&#8209;backed service readiness.</p>
          </div>
        </li>

        <li class="why__item">
          <span class="why__n">04</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="armchair"></i>Daily Comfort</h3>
            <p class="why__d">Comfortable ergonomics and simple controls across the model family.</p>
          </div>
        </li>

        <li class="why__item">
          <span class="why__n">05</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="map-pin"></i>Dealer Network</h3>
            <p class="why__d">City&#8209;wise test rides and a dealership structure that keeps growing.</p>
          </div>
        </li>

        <li class="why__item">
          <span class="why__n">06</span>
          <div class="why__body">
            <h3 class="why__t"><i data-lucide="sparkles"></i>Premium Finish</h3>
            <p class="why__d">Projector LED, hydraulic suspension, modern city&#8209;ready detailing.</p>
          </div>
        </li>

      </ol>
    </div>
  </div>

  <!-- keyword ticker ── the track is duplicated so the loop has no seam -->
  <div class="ticker" aria-hidden="true">
    <div class="ticker__track">
      <span>Easy charging</span><i class="sq"></i>
      <span>Dealer network</span><i class="sq"></i>
      <span>Warranty support</span><i class="sq"></i>
      <span>Test ride</span><i class="sq"></i>
      <span>Everyday range</span><i class="sq"></i>
      <span>Quiet power</span><i class="sq"></i>
    </div>
    <div class="ticker__track">
      <span>Easy charging</span><i class="sq"></i>
      <span>Dealer network</span><i class="sq"></i>
      <span>Warranty support</span><i class="sq"></i>
      <span>Test ride</span><i class="sq"></i>
      <span>Everyday range</span><i class="sq"></i>
      <span>Quiet power</span><i class="sq"></i>
    </div>
  </div>
</section>


<!-- ══════════ FEATURES ══════════ -->
<?php
$numFeatured = count($featuredProducts);
$countWords = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight'];
$wayWord = $countWords[$numFeatured] ?? $numFeatured;
$driveHeight = max($numFeatured, 3) * 105;
$arcStep = $numFeatured > 4 ? '18deg' : '23deg';
?>
<section class="feat" id="features" style="--arc-step: <?= $arcStep ?>;">
  <div class="feat__drive" id="featDrive" style="height: <?= $driveHeight ?>vh;">
    <div class="feat__pin">
      <div class="feat__window">

        <header class="feat__head">
          <p class="eyebrow"><i class="sq"></i>THE FEATURE WINDOW</p>
          <h2 class="feat__title reveal-up">One frame,<br><span class="hl"><?= e($wayWord) ?> ways to ride.</span></h2>
        </header>

        <ul class="feat__rail" aria-hidden="true">
          <li class="spec">
            <i class="spec__ico" data-lucide="route"></i>
            <div class="spec__co">
              <span class="spec__k">Range</span>
              <span class="spec__vs">
                <?php foreach ($featuredProducts as $idx => $fp): ?>
                  <b style="--n:<?= $idx ?>"><?= (int)($fp['range_km'] ?? 100) ?><em>km</em></b>
                <?php endforeach; ?>
              </span>
            </div>
          </li>
          <li class="spec">
            <i class="spec__ico" data-lucide="gauge"></i>
            <div class="spec__co">
              <span class="spec__k">Top speed</span>
              <span class="spec__vs">
                <?php foreach ($featuredProducts as $idx => $fp): ?>
                  <b style="--n:<?= $idx ?>"><?= (int)($fp['top_speed_kmph'] ?? 55) ?><em>km/h</em></b>
                <?php endforeach; ?>
              </span>
            </div>
          </li>
          <li class="spec">
            <i class="spec__ico" data-lucide="battery-charging"></i>
            <div class="spec__co">
              <span class="spec__k">Battery</span>
              <span class="spec__vs">
                <?php foreach ($featuredProducts as $idx => $fp): 
                  $bat = !empty($fp['battery_capacity']) ? trim($fp['battery_capacity']) : '60V 30Ah';
                  if (preg_match('/(\d+V)[^\d]*(\d+Ah)/i', $bat, $bm)) {
                      $bv = $bm[1];
                      $bah = strtoupper($bm[2]);
                  } else {
                      $parts = explode(' ', $bat, 2);
                      $bv = $parts[0] ?? '60V';
                      $bah = strtoupper($parts[1] ?? '30AH');
                  }
                ?>
                  <b style="--n:<?= $idx ?>"><?= e($bv) ?><em><?= e($bah) ?></em></b>
                <?php endforeach; ?>
              </span>
            </div>
          </li>
        </ul>

        <!-- the product deck -->
        <div class="feat__deck" id="featDeck">
          <?php foreach ($featuredProducts as $idx => $fp): 
            $colors = $productColorsMap[$fp['id']] ?? [];
            $colorHex = !empty($colors[0]['argb']) ? sprintf('#%06x', ((int)$colors[0]['argb']) & 0xFFFFFF) : '#2563eb';
            $colorName = !empty($colors[0]['name']) ? $colors[0]['name'] : 'Signature';
            $imgSrc = !empty($fp['hero_image']) ? $fp['hero_image'] : 'assets/scooters/hazra_broucher_6_scooter_9.png';
            $batDisplay = !empty($fp['battery_capacity']) ? trim($fp['battery_capacity']) : '60V 30Ah';
          ?>
            <article class="fslide" style="--n:<?= $idx ?>;--c:<?= e($colorHex) ?>">
              <div class="fslide__media">
                <i class="fslide__halo"></i>
                <i class="fslide__floor"></i>
                <img class="fslide__img" src="<?= e(base_url($imgSrc)) ?>" alt="<?= e($fp['name']) ?> in <?= e($colorName) ?>" <?= $idx > 0 ? 'loading="lazy"' : '' ?> decoding="async">
              </div>
              <div class="fslide__body">
                <h3 class="fslide__name"><?= e($fp['name']) ?></h3>
                <p class="fslide__chips">
                  <span><?= (int)($fp['range_km'] ?? 100) ?> km</span>
                  <span><?= (int)($fp['top_speed_kmph'] ?? 55) ?> km/h</span>
                  <span><?= e($batDisplay) ?></span>
                </p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <!-- right edge pagination -->
        <ol class="feat__dots" id="featDots">
          <?php foreach ($featuredProducts as $idx => $fp): ?>
            <li><button class="fdot" style="--n:<?= $idx ?>" data-go="<?= $idx ?>" aria-label="<?= e($fp['name']) ?>"></button></li>
          <?php endforeach; ?>
        </ol>

        <!-- bottom-left SKU / bottom-right finish, both swap on --fi -->
        <p class="feat__item">
          <?php foreach ($featuredProducts as $idx => $fp): 
            $itemCode = !empty($fp['model_code']) ? $fp['model_code'] : ('ITEM: ' . (10009600 + $idx + 1));
            if (strpos($itemCode, 'ITEM:') !== 0 && strpos($itemCode, 'MODEL:') !== 0) {
                $itemCode = 'MODEL: ' . $itemCode;
            }
          ?>
            <span class="swap" style="--n:<?= $idx ?>"><?= e($itemCode) ?></span>
          <?php endforeach; ?>
        </p>

        <p class="feat__finish">
          <?php foreach ($featuredProducts as $idx => $fp): 
            $colors = $productColorsMap[$fp['id']] ?? [];
            $colorName = !empty($colors[0]['name']) ? strtoupper($colors[0]['name']) : 'SIGNATURE FINISH';
          ?>
            <span class="swap" style="--n:<?= $idx ?>"><?= e($colorName) ?></span>
          <?php endforeach; ?>
        </p>

        <!-- arc dial: ticks sit still, the name ring rotates under the cursor -->
        <div class="feat__arc" aria-hidden="true">
          <i class="arc__cursor"></i>
          <div class="arc__ticks" id="arcTicks"></div>
          <div class="arc__ring">
            <?php foreach ($featuredProducts as $idx => $fp): ?>
              <b class="arc__name" style="--n:<?= $idx ?>"><?= e($fp['name']) ?></b>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</section>


<!-- ══════════ PERFORMANCE STATS ══════════ -->
<section class="perf" id="performance">
  <div class="wrap">

    <header class="sec__head sec__head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>PERFORMANCE</p>
        <h2 class="sec__title reveal-up">Engineered for everyday<br><span class="hl">performance.</span></h2>
      </div>
      <p class="perf__lead reveal-up">
        Real range on real roads, a charge that fits a working day, and a dealer
        network close enough to matter. The confidence stays where it belongs
        &mdash; under the rider.
      </p>
    </header>

    <ol class="perf__grid">
      <li class="perf__cell" style="--i:0">
        <b class="perf__n"><span class="perf__v" data-to="200">0</span><em>Km</em></b>
        <h3 class="perf__t">Top Range</h3>
        <p class="perf__d">Distance built for long city commutes and daily riding.</p>
      </li>
      <li class="perf__cell" style="--i:1">
        <b class="perf__n"><span class="perf__v" data-to="5" data-prefix="4&ndash;">0</span><em>Hours</em></b>
        <h3 class="perf__t">Charging Time</h3>
        <p class="perf__d">Quick recharge that fits between two ordinary errands.</p>
      </li>
      <li class="perf__cell" style="--i:2">
        <b class="perf__n"><span class="perf__v" data-to="400" data-suffix="+">0</span></b>
        <h3 class="perf__t">Dealers Pan India</h3>
        <p class="perf__d">Dealer&#8209;backed ownership confidence for daily Indian riding.</p>
      </li>
      <li class="perf__cell" style="--i:3">
        <b class="perf__n"><span class="perf__v" data-to="5" data-prefix="Up to ">0</span><em>Years</em></b>
        <h3 class="perf__t">Warranty</h3>
        <p class="perf__d">Backed by durable engineering and after&#8209;sales support.</p>
      </li>
    </ol>

  </div>
</section>


<!-- ══════════ NEWS ══════════ -->
<section class="news" id="news">
  <div class="wrap">

    <header class="sec__head sec__head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>NEWSROOM</p>
        <h2 class="sec__title reveal-up">
          Smart battery technology.<br>
          Confident performance.<br>
          <span class="hl">Designed for everyday India.</span>
        </h2>
      </div>
      <a class="link" href="<?= e(base_url('blog')) ?>">All newsroom stories<i data-lucide="arrow-up-right"></i></a>
    </header>

    <div class="bento">

      <article class="bento__c bento__c--lead" style="--pos:38% 62%;--tint:var(--brand-violet)">
        <img class="bento__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_14.png')) ?>" alt="Battery pack detail">
        <i class="bento__scrim"></i>
        <span class="bento__tag"><i data-lucide="battery-charging"></i>Battery</span>
        <div class="bento__body">
          <h3 class="bento__t">Range Confidence</h3>
          <p class="bento__d">Battery support built for daily Indian roads.</p>
        </div>
      </article>

      <article class="bento__c bento__c--wide" style="--pos:72% 34%;--tint:var(--brand-blue)">
        <img class="bento__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_16.png')) ?>" alt="Cockpit and controls">
        <i class="bento__scrim"></i>
        <span class="bento__tag"><i data-lucide="zap"></i>Control</span>
        <div class="bento__body">
          <h3 class="bento__t">Smooth Control</h3>
          <p class="bento__d">Quiet, predictable riding for daily city traffic.</p>
        </div>
      </article>

      <article class="bento__c" style="--pos:24% 78%;--tint:var(--brand-magenta)">
        <img class="bento__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_12.png')) ?>" alt="Dealer handover">
        <i class="bento__scrim"></i>
        <span class="bento__tag"><i data-lucide="map-pin"></i>Network</span>
        <div class="bento__body">
          <h3 class="bento__t">Dealer Support</h3>
          <p class="bento__d">Built around service reach, diagnostics and local ownership confidence.</p>
        </div>
      </article>

      <article class="bento__c" style="--pos:84% 66%;--tint:var(--brand-flame)">
        <img class="bento__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_9.png')) ?>" alt="Suspension and warranty seal">
        <i class="bento__scrim"></i>
        <span class="bento__tag"><i data-lucide="shield-check"></i>Warranty</span>
        <div class="bento__body">
          <h3 class="bento__t">Ride Protection</h3>
          <p class="bento__d">Durable engineering, dependable warranty, safer everyday mobility.</p>
        </div>
      </article>

    </div>
  </div>
</section>


<!-- ══════════ BOOK A TEST RIDE ══════════ -->
<section class="ride" id="test-ride">
  <div class="wrap">
    <div class="ride__grid">

      <figure class="ride__media reveal-up">
        <img class="ride__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_14.png')) ?>" alt="Hazra EV scooter">
        <i class="ride__wash"></i>
        <figcaption class="ride__offer">
          <b>Season offer</b>
          <span>Free first service &middot; on&#8209;road support</span>
        </figcaption>
      </figure>

      <div class="ride__panel reveal-up">
        <p class="eyebrow"><i class="sq"></i>BOOK A TEST RIDE</p>
        <h2 class="sec__title ride__title">Check out the offers <span class="hl">you need.</span></h2>
        <p class="ride__note">Fifteen minutes on the seat answers more than any spec sheet.</p>

        <form class="ride__form" id="rideForm" novalidate>
          <label class="field">
            <span class="field__l">Full name</span>
            <input class="field__i" type="text" name="name" autocomplete="name" placeholder="Your name" required>
          </label>
          <label class="field">
            <span class="field__l">Mobile number</span>
            <input class="field__i" type="tel" name="phone" inputmode="numeric" autocomplete="tel"
                   pattern="[0-9]{10}" placeholder="10&#8209;digit number" required>
          </label>
          <label class="field">
            <span class="field__l">City</span>
            <input class="field__i" type="text" name="city" autocomplete="address-level2" placeholder="Where you ride" required>
          </label>

          <button class="btn btn--ink ride__submit" type="submit">
            <span>Submit lead</span><i data-lucide="arrow-right"></i>
          </button>

          <p class="ride__consent">
            By submitting, you agree to be contacted by Hazra EV or an authorised partner.
          </p>
          <p class="ride__ok" id="rideOk" role="status" aria-live="polite"></p>
        </form>
      </div>

    </div>
  </div>
</section>


<!-- ══════════ FAQ ══════════ -->
<section class="faq" id="faq">
  <div class="wrap">

    <div class="faq__grid">

      <div class="faq__rail">
        <p class="eyebrow"><i class="sq"></i>FAQ</p>
        <h2 class="sec__title reveal-up">Frequently asked <span class="hl">questions.</span></h2>
        <p class="faq__lead reveal-up">
          Quick answers on scooters, ownership, charging and dealership support.
        </p>
        <a class="link faq__all" href="<?= e(base_url('contact')) ?>">View all questions<i data-lucide="arrow-up-right"></i></a>
      </div>

      <ul class="faq__list">
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Which scooter suits daily city commuting?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>CHALO SMART PRO is the steady city pick. The right model still comes down to four things: daily distance, where you charge, rider load, and how much comfort you want on a broken road.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>How much do I save against a petrol scooter?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>It moves with fuel price, distance, maintenance and riding style. Most daily commuters land between &#8377;1,400 and &#8377;2,600 a month; the savings calculator gives you a monthly and an annual figure from your own numbers.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Can I charge at home?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Yes. Every model is designed for everyday charging off a normal household socket with the approved charger. A full charge takes 4&ndash;5 hours, which is an overnight, not a plan.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>How do I book a test ride?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Use any Book Test Ride button on this page. The team connects you to the closest Hazra EV touchpoint, usually the same day.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Can I become a dealer?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Yes. Submit the dealer enquiry with your city, state and business details. Applications are reviewed against existing network coverage in your area.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Where do I see all the models?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>The collection above compares range, charging time, warranty and price across the line, and every model page carries a brochure download.</p>
          </div></div>
        </li>
        <li class="faq__item">
          <button class="faq__q" type="button" aria-expanded="false">
            <span>Is EMI or finance available?</span>
            <i class="faq__ico" data-lucide="plus"></i>
          </button>
          <div class="faq__panel"><div class="faq__a">
            <p>Finance partners support flexible ownership across most cities. Approval and terms follow the partner's own process &mdash; your dealer runs it with you at the counter.</p>
          </div></div>
        </li>
      </ul>

    </div>
  </div>
</section>


<!-- ══════════ INSIGHTS ══════════ -->
<section class="ins" id="insights">
  <div class="wrap">

    <header class="sec__head sec__head--row">
      <div>
        <p class="eyebrow"><i class="sq"></i>INSIGHTS</p>
        <h2 class="sec__title reveal-up">Insights shaping the future<br>of <span class="hl">electric mobility.</span></h2>
      </div>
      <a class="link" href="<?= e(base_url('blog')) ?>">View blog<i data-lucide="arrow-up-right"></i></a>
    </header>

    <div class="ins__grid">
      <?php
      $homeBlogs = db_fetch_all(
          "SELECT * FROM posts WHERE type = 'blog' AND status = 'published' ORDER BY is_featured DESC, published_at DESC LIMIT 3"
      );
      $tints = ['var(--brand-violet)', 'var(--brand-blue)', 'var(--brand-flame)'];
      $defaultImages = [
          'assets/scooters/hazra_broucher_6_scooter_16.png',
          'assets/scooters/hazra_broucher_6_scooter_13.png',
          'assets/scooters/hazra_broucher_6_scooter_15.png'
      ];
      if (!empty($homeBlogs)):
        foreach ($homeBlogs as $i => $hb):
          $tint = $tints[$i % count($tints)];
          $img = !empty($hb['cover_image']) ? img_url($hb['cover_image']) : base_url($defaultImages[$i % 3]);
      ?>
      <a class="post" href="<?= e(base_url("blog/{$hb['slug']}")) ?>" style="--i:<?= $i ?>;--pos:30% 58%;--tint:<?= $tint ?>">
        <figure class="post__media">
          <img class="post__img" src="<?= e($img) ?>" alt="<?= e($hb['title']) ?>" onerror="this.onerror=null;this.src='<?= e(base_url($defaultImages[$i % 3])) ?>';">
          <i class="post__wash"></i>
        </figure>
        <div class="post__body">
          <span class="post__cat"><?= e(ucfirst($hb['category'] ?? 'EV Trends')) ?></span>
          <h3 class="post__t"><?= e($hb['title']) ?></h3>
          <p class="post__d"><?= e($hb['excerpt']) ?></p>
          <span class="post__go">Read<i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <?php
        endforeach;
      else:
      ?>
      <a class="post" href="<?= e(base_url('blog/battery-care-101')) ?>" style="--i:0;--pos:30% 58%;--tint:var(--brand-violet)">
        <figure class="post__media">
          <img class="post__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_16.png')) ?>" alt="EV Trends">
          <i class="post__wash"></i>
        </figure>
        <div class="post__body">
          <span class="post__cat">EV Trends</span>
          <h3 class="post__t">The Future of Urban Mobility Is Electric, Local, and Connected</h3>
          <p class="post__d">Indian cities are moving to lighter, smarter two&#8209;wheelers &mdash; and the shift is happening street by street, not in a press release.</p>
          <span class="post__go">Read<i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>

      <a class="post" href="<?= e(base_url('blog/city-riding-secrets')) ?>" style="--i:1;--pos:66% 44%;--tint:var(--brand-blue)">
        <figure class="post__media">
          <img class="post__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_13.png')) ?>" alt="Battery Care">
          <i class="post__wash"></i>
        </figure>
        <div class="post__body">
          <span class="post__cat">Ownership</span>
          <h3 class="post__t">Battery Care Guide for Everyday EV Scooter Riders</h3>
          <p class="post__d">Six routines that add years to a pack &mdash; charge windows, storage heat, and the one habit most riders get wrong.</p>
          <span class="post__go">Read<i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>

      <a class="post" href="<?= e(base_url('blog/the-bardhaman-story')) ?>" style="--i:2;--pos:20% 70%;--tint:var(--brand-flame)">
        <figure class="post__media">
          <img class="post__img" src="<?= e(base_url('assets/scooters/hazra_broucher_6_scooter_15.png')) ?>" alt="Dealership Growth">
          <i class="post__wash"></i>
        </figure>
        <div class="post__body">
          <span class="post__cat">Dealership</span>
          <h3 class="post__t">Why EV Dealerships Are a High&#8209;Growth Local Business</h3>
          <p class="post__d">Demand signals, service revenue and local trust &mdash; what actually makes a two&#8209;wheeler EV counter work.</p>
          <span class="post__go">Read<i data-lucide="arrow-up-right"></i></span>
        </div>
      </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php App::render('footer'); ?>
