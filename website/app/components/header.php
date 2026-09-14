<?php
$isStickyOnly = $isStickyOnly ?? false;
$headerClass = $isStickyOnly ? 'sticky-bar is-page is-visible' : 'header';
$headerId = $isStickyOnly ? 'stickyBar' : '';

// Active route resolution
$currentRoute = class_exists('RouteManager') ? RouteManager::resolveRoute() : 'default';

function nav_active($route, $current) {
    if (($route === 'index' || $route === 'default') && ($current === 'default' || $current === 'index')) return 'is-on';
    return ($current === $route) ? 'is-on' : '';
}

$navProducts = [];
try {
    if (function_exists('db_fetch_all')) {
        $navProducts = db_fetch_all("SELECT id, name, category, top_speed_kmph, hero_image, slug FROM products WHERE active = 1 ORDER BY updated_at DESC LIMIT 6");
    }
} catch (Throwable $e) {}

if (empty($navProducts)) {
    $navProducts = [
        ['id' => 'chalo-1000-v2', 'name' => 'CHALO 1000 V2', 'slug' => 'chalo-1000-v2', 'top_speed_kmph' => 65, 'hero_image' => 'assets/scutie_light.webp'],
        ['id' => 'chalo-smart-pro', 'name' => 'CHALO SMART PRO', 'slug' => 'chalo-smart-pro', 'top_speed_kmph' => 45, 'hero_image' => 'assets/dark_scutie.webp'],
        ['id' => 'chalo-smart-plus', 'name' => 'CHALO SMART PLUS', 'slug' => 'chalo-smart-plus', 'top_speed_kmph' => 45, 'hero_image' => 'assets/scutie_light.webp'],
        ['id' => 'chalo-smart-eco', 'name' => 'CHALO SMART ECO', 'slug' => 'chalo-smart-eco', 'top_speed_kmph' => 40, 'hero_image' => 'assets/dark_scutie.webp'],
        ['id' => 'chalo-neo', 'name' => 'CHALO NEO', 'slug' => 'chalo-neo', 'top_speed_kmph' => 35, 'hero_image' => 'assets/scutie_light.webp'],
        ['id' => 'nja-7', 'name' => 'NJA ~ 7', 'slug' => 'nja-7', 'top_speed_kmph' => 40, 'hero_image' => 'assets/storm.png']
    ];
}
?>
<!-- ══════════ NAVBAR ══════════ -->
<header class="<?= $headerClass ?>" <?= $headerId ? 'id="'.$headerId.'"' : '' ?> <?= $isStickyOnly ? 'aria-hidden="false"' : '' ?>>
  <div class="<?= $isStickyOnly ? 'sticky-bar__inner' : 'header__top' ?>">
    <a class="brand <?= $isStickyOnly ? 'sticky-bar__brand' : '' ?>" href="<?= e(base_url('index')) ?>" aria-label="Hazra Electrical Bike — home">
      <img class="brand__mark" src="<?= e(base_url('assets/hazraev.png')) ?>" alt="Hazra Electrical Bike">
      <span class="brand__txt">Hazra<b>Electrical Bike</b></span>
    </a>

    <?php if (!$isStickyOnly): ?>
    <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="nav">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav" id="nav">
    <?php else: ?>
    <button class="burger" id="stickyBurger" aria-label="Open menu" aria-expanded="false" aria-controls="stickyNav">
      <span></span><span></span><span></span>
    </button>
    <nav class="nav sticky-bar__nav" id="stickyNav">
    <?php endif; ?>
      
      <a href="<?= e(base_url('index')) ?>" class="nav__i <?= nav_active('index', $currentRoute) ?>">Home</a>

      <div class="nav__grp">
        <button class="nav__i nav__i--t <?= in_array($currentRoute, ['our-story', 'career', 'faq', 'gallery']) ? 'is-on' : '' ?>" aria-expanded="false">
          About <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('our-story')) ?>" class="nav__s <?= nav_active('our-story', $currentRoute) ?>">Our Story</a>
          <a href="<?= e(base_url('career')) ?>" class="nav__s <?= nav_active('career', $currentRoute) ?>">Careers</a>
          <a href="<?= e(base_url('gallery')) ?>" class="nav__s <?= nav_active('gallery', $currentRoute) ?>">Gallery</a>
          <a href="<?= e(base_url('index#faq')) ?>" class="nav__s">FAQ</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t <?= in_array($currentRoute, ['products', 'product-detail']) ? 'is-on' : '' ?>" aria-expanded="false">
          Products <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu nav__menu--products">
          <div class="nav__products-header">
            <span class="nav__products-title">Scooter Collection</span>
            <a href="<?= e(base_url('products')) ?>" class="nav__products-all">View All Models &rarr;</a>
          </div>
          <div class="nav__product-grid">
            <?php foreach ($navProducts as $np): 
              $imgSrc = !empty($np['hero_image']) ? base_url($np['hero_image']) : base_url('assets/scutie_light.webp');
              $isHigh = ((int)($np['top_speed_kmph'] ?? 0)) >= 55;
              $productUrl = !empty($np['slug']) 
                ? base_url('product-detail?slug=' . urlencode($np['slug'])) 
                : base_url('product-detail?id=' . urlencode($np['id']));
            ?>
              <a href="<?= e($productUrl) ?>" class="nav__product">
                <div class="nav__product-img-wrap">
                  <img class="nav__product-img" src="<?= e($imgSrc) ?>" alt="<?= e($np['name']) ?>" loading="lazy">
                </div>
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
        <button class="nav__i nav__i--t <?= in_array($currentRoute, ['contest', 'blog', 'ev-future']) ? 'is-on' : '' ?>" aria-expanded="false">
          Social <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('contest')) ?>" class="nav__s <?= nav_active('contest', $currentRoute) ?>">Reels Contest</a>
          <a href="<?= e(base_url('blog')) ?>" class="nav__s <?= nav_active('blog', $currentRoute) ?>">Blog</a>
          <a href="<?= e(base_url('index#news')) ?>" class="nav__s">News</a>
          <a href="<?= e(base_url('ev-future')) ?>" class="nav__s <?= nav_active('ev-future', $currentRoute) ?>">EV Future</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t <?= in_array($currentRoute, ['dealer-locator', 'become-a-dealer']) ? 'is-on' : '' ?>" aria-expanded="false">
          Dealers <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('dealer-locator')) ?>" class="nav__s <?= nav_active('dealer-locator', $currentRoute) ?>">Locate Dealers</a>
          <a href="<?= e(base_url('become-a-dealer')) ?>" class="nav__s <?= nav_active('become-a-dealer', $currentRoute) ?>">Become a Dealer</a>
        </div>
      </div>

      <div class="nav__grp">
        <button class="nav__i nav__i--t <?= in_array($currentRoute, ['warranty-free', 'warranty-paid']) ? 'is-on' : '' ?>" aria-expanded="false">
          Service <i data-lucide="chevron-down" class="nav__cv"></i>
        </button>
        <div class="nav__menu">
          <a href="<?= e(base_url('warranty-free')) ?>" class="nav__s <?= nav_active('warranty-free', $currentRoute) ?>">Warranty (Free)</a>
          <a href="<?= e(base_url('warranty-paid')) ?>" class="nav__s <?= nav_active('warranty-paid', $currentRoute) ?>">Warranty (Paid)</a>
        </div>
      </div>

      <a href="<?= e(base_url('contact')) ?>" class="nav__i <?= nav_active('contact', $currentRoute) ?>">Contact</a>
    </nav>

    <?php if ($isStickyOnly): ?>
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
    <?php else: ?>
    <button class="theme" id="theme" aria-label="Toggle theme">
      <i data-lucide="sun-medium" class="theme__sun"></i>
      <i data-lucide="moon" class="theme__moon"></i>
    </button>
    <?php endif; ?>
  </div>
</header>
