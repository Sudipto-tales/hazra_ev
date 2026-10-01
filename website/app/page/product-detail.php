<?php
// Determine requested product ID or model code
$reqId = $_GET['id'] ?? $_GET['model'] ?? $_GET['slug'] ?? '';

$product = null;
$relatedProducts = [];

try {
    if (function_exists('db_fetch_one')) {
        if ($reqId !== '') {
            $product = db_fetch_one("SELECT * FROM products WHERE (id = ? OR model_code = ? OR name = ? OR slug = ?) AND active = 1", [$reqId, $reqId, $reqId, $reqId]);
        }
        if (!$product) {
            $product = db_fetch_one("SELECT * FROM products WHERE active = 1 ORDER BY updated_at DESC LIMIT 1");
        }

        if ($product) {
            $colors = db_fetch_all("SELECT * FROM product_colors WHERE product_id = ? ORDER BY position, name", [$product['id']]);
            $colorImages = [];
            if ($colors) {
                $cids = array_column($colors, 'id');
                $cph = implode(',', array_fill(0, count($cids), '?'));
                foreach (db_fetch_all("SELECT * FROM product_color_images WHERE color_id IN ({$cph}) ORDER BY position", $cids) as $img) {
                    $colorImages[$img['color_id']][] = $img['url'];
                }
            }

            foreach ($colors as &$c) {
                $c['images'] = $colorImages[$c['id']] ?? [];
            }
            unset($c);

            $product['colors'] = $colors;
            $product['highlights'] = json_decode($product['highlights'] ?? '[]', true) ?? [];

            // Related products
            $relatedProducts = db_fetch_all(
                "SELECT * FROM products WHERE id != ? AND active = 1 ORDER BY updated_at DESC LIMIT 3",
                [$product['id']]
            );
        }
    }
} catch (Throwable $e) {
    error_log("Error loading product detail: " . $e->getMessage());
}

if (!$product) {
    App::render('head', [
        'pageTitle'       => 'Product Not Found | Hazra Electrical Bike',
        'pageDescription' => 'The requested electric scooter product could not be found.',
    ]);
    App::render('header', ['isStickyOnly' => true]);
    echo '<div style="padding: 100px 26px; text-align: center;"><h2>Product not found</h2><a href="' . e(base_url('products')) . '">Back to catalogue</a></div>';
    App::render('footer');
    exit;
}

$pageTitle = $product['brand'] . ' ' . $product['name'] . " — Hazra Electrical Bike";
$pageDescription = "Experience the " . $product['name'] . " with " . $product['range_km'] . "km range, " . $product['top_speed_kmph'] . "km/h top speed, and premium build quality.";

$activeColor = $product['colors'][0] ?? null;
$rawHero = ($activeColor && !empty($activeColor['images'])) ? $activeColor['images'][0] : ($product['hero_image'] ?? 'assets/scutie_light.webp');
$heroImg = ($rawHero && (str_starts_with($rawHero, 'http') || str_starts_with($rawHero, '/'))) ? $rawHero : base_url($rawHero);

$colorsData = [];
if (!empty($product['colors'])) {
    foreach ($product['colors'] as $c) {
        $hex = '#' . str_pad(dechex($c['argb'] & 0xFFFFFF), 6, '0', STR_PAD_LEFT);
        $imgs = [];
        if (!empty($c['images'])) {
            foreach ($c['images'] as $u) {
                $imgs[] = ($u && (str_starts_with($u, 'http') || str_starts_with($u, '/'))) ? $u : base_url($u);
            }
        }
        if (empty($imgs)) {
            $imgs[] = $heroImg;
        }
        $colorsData[] = [
            'id'       => $c['id'],
            'name'     => $c['name'],
            'hex'      => $hex,
            'in_stock' => (bool) ($c['in_stock'] ?? 1),
            'images'   => $imgs,
        ];
    }
}
$initialImages = !empty($colorsData[0]['images']) ? $colorsData[0]['images'] : [$heroImg];

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => [
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css',
        'assets/css/styles/product.css'
    ],
]);

App::render('header', ['isStickyOnly' => true]);
?>

<?php
$specItems = [];
foreach ([['range_km', 'Riding range', ' km'], ['top_speed_kmph', 'Top speed', ' km/h'], ['battery_capacity', 'Battery capacity', ''], ['motor_power', 'Motor power', ''], ['charging_time', 'Charging time', ''], ['load_capacity_kg', 'Payload capacity', ' kg']] as [$key, $label, $unit]) {
    if (!empty($product[$key])) $specItems[] = ['label' => $label, 'value' => $product[$key] . $unit];
}
?>
<main class="pd-page">
  <nav class="pd-breadcrumb pd-container" aria-label="Breadcrumb"><a href="<?= e(base_url('')) ?>">Home</a><span>/</span><a href="<?= e(base_url('products')) ?>">Products</a><span>/</span><span><?= e($product['name']) ?></span></nav>
  <nav class="pd-progress" aria-label="Page sections"><a href="#overview" aria-label="Overview" class="is-active"></a><a href="#showcase" aria-label="Product showcase"></a><a href="#specifications" aria-label="Specifications"></a><a href="#test-ride" aria-label="Book a test ride"></a></nav>
  <section class="pd-hero pd-container" id="overview">
    <div class="pd-hero-copy" data-reveal>
      <span class="pd-kicker"><?= e($product['brand']) ?> / ELECTRIC MOBILITY</span>
      <h1>Move<br>beyond <span>ordinary.</span></h1>
      <div class="pd-model"><?= e($product['name']) ?><span><?= e($product['category']) ?> &middot; <?= e($product['model_code']) ?></span></div>
      <p>Engineered for performance and comfort. Experience effortless urban mobility with cutting-edge EV technology.</p>
      <div class="pd-actions"><a class="pd-button" href="#test-ride">Book a test ride <span>&#8599;</span></a><a class="pd-button pd-button-ghost" href="#specifications">Explore the details <span>&#8599;</span></a></div>
      <a class="pd-scroll" href="#showcase"><span class="pd-mouse"></span> Scroll to explore</a>
    </div>
    <div class="pd-hero-art" data-reveal><span class="pd-edition">THE NEXT CHAPTER OF YOUR EVERYDAY</span><div class="pd-orbit"></div><img data-product-image src="<?= e($heroImg) ?>" alt="<?= e($product['brand'] . ' ' . $product['name']) ?>" fetchpriority="high"><span class="pd-art-label">01 / <?= e($product['model_code']) ?></span></div>
  </section>
  <section class="pd-showcase pd-container" id="showcase">
    <div class="pd-section-heading" data-reveal><div><span class="pd-kicker">MEET YOUR NEXT RIDE</span><h2><?= e($product['name']) ?>.<br>Every angle. Every detail.</h2></div><p>Take a closer look. Choose your color and explore the full view.</p></div>
    <div class="pd-stage-watermark" aria-hidden="true"><?= e($product['name']) ?></div>
    <div class="pd-gallery" data-reveal>
      <div class="product-gallery-wrap">
        <div class="gallery-stage" id="galleryStage">
          <!-- Main Swiper Carousel -->
          <div class="swiper product-swiper-main" id="productSwiperMain">
            <div class="swiper-wrapper" id="swiper-main-wrapper">
              <?php foreach ($initialImages as $idx => $img): ?>
                <div class="swiper-slide" data-img-index="<?= $idx ?>">
                  <img src="<?= e($img) ?>" alt="<?= e($product['name']) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>">
                </div>
              <?php endforeach; ?>
            </div>
            
            <!-- Navigation Arrows -->
            <button type="button" class="gallery-nav-btn gallery-nav-prev" id="galleryPrevBtn" aria-label="Previous image">
              <i data-lucide="chevron-left"></i>
            </button>
            <button type="button" class="gallery-nav-btn gallery-nav-next" id="galleryNextBtn" aria-label="Next image">
              <i data-lucide="chevron-right"></i>
            </button>

            <!-- Lightbox Expand Button -->
            <button type="button" class="gallery-expand-btn" id="galleryExpandBtn" aria-label="Full screen view" title="Click to view full screen">
              <i data-lucide="maximize-2" style="width: 14px; height: 14px;"></i>
              <span>Full View</span>
            </button>

            <!-- Glass Hover Magnifier Lens -->
            <div class="glass-magnifier-lens" id="glassMagnifierLens"></div>
          </div>

          <!-- Pagination dots -->
          <div class="swiper-pagination product-swiper-pagination" id="galleryPagination"></div>
        </div>

        <!-- Thumbnails Strip -->
        <div class="gallery-thumbs-row">
          <div class="swiper product-swiper-thumbs" id="productSwiperThumbs">
            <div class="swiper-wrapper" id="swiper-thumbs-wrapper">
              <?php foreach ($initialImages as $idx => $img): ?>
                <div class="swiper-slide <?= $idx === 0 ? 'swiper-slide-thumb-active' : '' ?>" data-index="<?= $idx ?>">
                  <img src="<?= e($img) ?>" alt="<?= e($product['name'] . ' thumbnail ' . ($idx + 1)) ?>">
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Color Picker Swatches -->
        <?php if (!empty($colorsData)): ?>
          <div class="gallery-swatches-block">
            <div class="gallery-swatches-header">
              <span class="gallery-swatches-title">Available Colorways</span>
              <span class="gallery-swatches-selected" id="selectedColorLabel"><?= e($colorsData[0]['name'] ?? '') ?></span>
            </div>
            <div class="gallery-swatches-list" id="swatchesContainer">
              <?php foreach ($colorsData as $idx => $c): ?>
                <button type="button" class="color-swatch-btn <?= $idx === 0 ? 'is-active' : '' ?>" data-color-index="<?= $idx ?>" aria-pressed="<?= $idx === 0 ? 'true' : 'false' ?>" onclick="switchColorway(<?= $idx ?>)">
                  <span class="color-swatch-dot" style="background: <?= e($c['hex']) ?>;"></span>
                  <span><?= e($c['name']) ?><?= !$c['in_stock'] ? ' (Out of stock)' : '' ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>

    </div>
    <div class="pd-metrics" data-reveal><?php foreach (array_slice($specItems, 0, 3) as $spec): ?><div><strong><?= e($spec['value']) ?></strong><span><?= e($spec['label']) ?></span></div><?php endforeach; ?></div>
  </section>
  <?php if (!empty($product['range_km'])): ?>
  <section class="pd-range pd-container" data-reveal><span class="pd-kicker">GO THE DISTANCE / RANGE</span><div class="pd-range-row"><strong><?= (int)$product['range_km'] ?><small>km</small></strong><div><h2>More possibilities.<br>On a single charge.</h2><details><summary>About riding range <span>+</span></summary><p>Range is measured per full charge under standard conditions. Actual range varies with riding speed, rider weight, terrain, weather and battery condition.</p></details></div></div></section>
  <?php endif; ?>
  <section class="pd-cinematic" data-reveal><div class="pd-container"><div class="pd-cinematic-copy"><span class="pd-kicker">MADE FOR YOUR EVERYDAY</span><h2>Designed for<br>modern mobility.</h2><a href="#features">Discover the details &#8599;</a></div><img data-product-image src="<?= e($heroImg) ?>" alt="<?= e($product['name']) ?> electric vehicle" loading="lazy"></div><span class="pd-road" aria-hidden="true"></span></section>
  <section class="pd-container pd-section" id="features"><div class="pd-section-heading" data-reveal><div><span class="pd-kicker">THOUGHTFULLY ENGINEERED</span><h2>The details make<br>the difference.</h2></div><div class="pd-feature-controls"><button type="button" data-feature-step="-1" aria-label="Previous features">&#8592;</button><button type="button" data-feature-step="1" aria-label="Next features">&#8594;</button></div></div>
    <div class="pd-features" id="featureTrack"><?php $featureLabels = !empty($product['highlights']) ? $product['highlights'] : array_column($specItems, 'label'); foreach ($featureLabels as $idx => $hl): ?><article class="pd-feature" data-reveal><div class="pd-feature-image pd-crop-<?= $idx % 4 ?>"><img data-product-image src="<?= e($heroImg) ?>" alt="<?= e($product['name']) ?> detail" loading="lazy"><span><?= str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) ?></span></div><h3><?= e($hl) ?></h3></article><?php endforeach; ?></div>
  </section>
  <section class="pd-spec-section" id="specifications"><div class="pd-container pd-section"><div class="pd-section-heading" data-reveal><div><span class="pd-kicker">PERFORMANCE, AT A GLANCE</span><h2>Everything you<br>need to know.</h2></div><p>The complete specifications for <?= e($product['brand'] . ' ' . $product['name']) ?>.</p></div><div class="pd-spec-grid" data-reveal><?php foreach ($specItems as $spec): ?><div><span><?= e($spec['label']) ?></span><strong><?= e($spec['value']) ?></strong></div><?php endforeach; ?><?php if (empty($specItems)): ?><p>Specifications for this model will be updated soon.</p><?php endif; ?></div>
    <div class="pd-ownership" data-reveal><div><span class="pd-kicker">RIDE WITH CONFIDENCE</span><h2>Support for the<br>road ahead.</h2></div><div><?php if (!empty($product['warranty_years'])): ?><strong><?= (int)$product['warranty_years'] ?> <?= (int)$product['warranty_years'] === 1 ? 'year' : 'years' ?> official warranty</strong><?php endif; ?><?php if (!empty($product['warranty_note'])): ?><p><?= e($product['warranty_note']) ?></p><?php endif; ?><?php if (!empty($product['rating'])): ?><p>Product rating: <?= e($product['rating']) ?> / 5</p><?php endif; ?><a class="pd-text-link" href="<?= e(base_url('dealer-locator')) ?>">Find your nearest dealer &#8599;</a></div></div>
  </div></section>
  <?php if (!empty($relatedProducts)): ?><section class="pd-container pd-section"><div class="pd-section-heading" data-reveal><div><span class="pd-kicker">FIND YOUR FIT</span><h2>More ways to move.</h2></div><a class="pd-text-link" href="<?= e(base_url('products')) ?>">View all models &#8599;</a></div><div class="pd-related"><?php foreach ($relatedProducts as $rel): $relUrl = !empty($rel['slug']) ? base_url('product-detail?slug=' . urlencode($rel['slug'])) : base_url('product-detail?id=' . urlencode($rel['id'])); ?><a href="<?= e($relUrl) ?>" data-reveal><span class="pd-kicker"><?= e($rel['brand']) ?></span><h3><?= e($rel['name']) ?></h3><p><?php if (!empty($rel['range_km'])): ?><?= (int)$rel['range_km'] ?> km range<?php endif; ?><?php if (!empty($rel['top_speed_kmph'])): ?> &middot; <?= (int)$rel['top_speed_kmph'] ?> km/h<?php endif; ?></p><span class="pd-text-link">View model &#8599;</span></a><?php endforeach; ?></div></section><?php endif; ?>
  <section class="pd-booking pd-container" id="test-ride" data-reveal><div><span class="pd-kicker">YOUR NEXT CHAPTER STARTS HERE</span><h2>Experience <?= e($product['name']) ?>.</h2><p>Leave your details. Our team will help arrange your test ride.</p></div><form id="productTestRide" action="<?= e(base_url('api/v1/website/leads')) ?>" method="post"><input type="hidden" name="type" value="test_drive"><input type="hidden" name="product_id" value="<?= e($product['id']) ?>"><input type="hidden" name="model" value="<?= e($product['name']) ?>"><label>Full name<input name="name" autocomplete="name" placeholder="Your name" required maxlength="120"></label><label>Phone number<input name="phone" type="tel" autocomplete="tel" placeholder="Your mobile number" required pattern="\+?[0-9]{10,15}" maxlength="20"></label><label>City / State<input name="city" autocomplete="address-level2" placeholder="Where you ride" required maxlength="120"></label><button class="pd-button" type="submit">Book a test ride <span>&#8599;</span></button><p id="rideStatus" role="status" aria-live="polite"></p></form></section>
</main>
<script src="<?= e(base_url('assets/js/product-detail.js')) ?>" defer></script>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
<script>
window.__PRODUCT_COLORS__ = <?= json_encode($colorsData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let currentColorIndex = 0;
let mainSwiper = null;
let thumbsSwiper = null;
let galleryLightbox = null;
const ZOOM_LEVEL = 2.2;

document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
  initSwipers();
  initLightbox();
  initGlassMagnifier();
});

function initSwipers() {
  if (typeof Swiper === 'undefined') return;
  thumbsSwiper = new Swiper('#productSwiperThumbs', {
    slidesPerView: 'auto',
    spaceBetween: 10,
    freeMode: true,
    watchSlidesProgress: true,
  });

  mainSwiper = new Swiper('#productSwiperMain', {
    slidesPerView: 1,
    spaceBetween: 20,
    speed: 400,
    grabCursor: true,
    keyboard: {
      enabled: true,
    },
    navigation: {
      nextEl: '#galleryNextBtn',
      prevEl: '#galleryPrevBtn',
    },
    pagination: {
      el: '#galleryPagination',
      clickable: true,
    },
    thumbs: {
      swiper: thumbsSwiper,
    },
    on: {
      slideChange: () => {
        updateLensBackground();
      }
    }
  });
}

function initLightbox() {
  if (galleryLightbox) {
    try { galleryLightbox.destroy(); } catch (e) {}
  }
  if (typeof GLightbox === 'undefined') return;
  const color = window.__PRODUCT_COLORS__[currentColorIndex] || {name: '', images: <?= json_encode($initialImages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>};
  const elements = (color.images || []).map(img => ({
    href: img,
    type: 'image',
    title: <?= json_encode($product['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> + ' — ' + color.name,
  }));

  galleryLightbox = GLightbox({
    elements: elements,
    touchNavigation: true,
    loop: true,
    zoomable: true,
    autoplayVideos: false
  });

  const stage = document.getElementById('productSwiperMain');
  if (stage && !stage._lbWired) {
    stage._lbWired = true;
    stage.addEventListener('click', (e) => {
      if (e.target.closest('.gallery-nav-btn')) return;
      if (galleryLightbox) {
        galleryLightbox.openAt(mainSwiper ? mainSwiper.activeIndex : 0);
      }
    });

    const expandBtn = document.getElementById('galleryExpandBtn');
    if (expandBtn) {
      expandBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (galleryLightbox) {
          galleryLightbox.openAt(mainSwiper ? mainSwiper.activeIndex : 0);
        }
      });
    }
  }
}

function initGlassMagnifier() {
  const stage = document.getElementById('galleryStage');
  const lens = document.getElementById('glassMagnifierLens');
  if (!stage || !lens) return;

  const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
  if (isTouch) {
    lens.style.display = 'none';
    return;
  }

  stage.addEventListener('mouseenter', () => {
    updateLensBackground();
    lens.style.display = 'block';
  });

  stage.addEventListener('mouseleave', () => {
    lens.style.display = 'none';
  });

  stage.addEventListener('mousemove', (e) => {
    const rect = stage.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    const lensWidth = lens.offsetWidth || 160;
    const lensHeight = lens.offsetHeight || 160;

    lens.style.left = (x - lensWidth / 2) + 'px';
    lens.style.top = (y - lensHeight / 2) + 'px';

    const bgX = (x * ZOOM_LEVEL) - (lensWidth / 2);
    const bgY = (y * ZOOM_LEVEL) - (lensHeight / 2);

    lens.style.backgroundPosition = `-${bgX}px -${bgY}px`;
    lens.style.backgroundSize = `${rect.width * ZOOM_LEVEL}px ${rect.height * ZOOM_LEVEL}px`;
  });
}

function updateLensBackground() {
  const lens = document.getElementById('glassMagnifierLens');
  if (!lens) return;
  const activeSlideImg = document.querySelector('#productSwiperMain .swiper-slide-active img');
  if (activeSlideImg) {
    lens.style.backgroundImage = `url("${activeSlideImg.src}")`;
  }
}

function switchColorway(colorIndex) {
  const colors = window.__PRODUCT_COLORS__;
  if (!colors || !colors[colorIndex]) return;

  currentColorIndex = colorIndex;
  const color = colors[colorIndex];

  document.querySelectorAll('[data-product-image]').forEach(img => { img.src = color.images[0]; });
  const label = document.getElementById('selectedColorLabel');
  if (label) label.textContent = color.name + (color.in_stock ? '' : ' (Out of stock)');

  document.querySelectorAll('.color-swatch-btn').forEach((btn, idx) => {
    btn.classList.toggle('is-active', idx === colorIndex);
    btn.setAttribute('aria-pressed', String(idx === colorIndex));
  });

  const populateSlides = (wrapperId, thumbnails) => {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;
    wrapper.replaceChildren(...color.images.map((src, idx) => {
      const slide = document.createElement('div');
      slide.className = 'swiper-slide' + (thumbnails && idx === 0 ? ' swiper-slide-thumb-active' : '');
      slide.dataset[thumbnails ? 'index' : 'imgIndex'] = idx;
      const img = document.createElement('img');
      img.src = src;
      img.alt = color.name + (thumbnails ? ' thumbnail ' + (idx + 1) : '');
      img.loading = idx === 0 ? 'eager' : 'lazy';
      slide.append(img);
      return slide;
    }));
  };
  populateSlides('swiper-main-wrapper', false);
  populateSlides('swiper-thumbs-wrapper', true);

  if (thumbsSwiper) {
    thumbsSwiper.update();
    thumbsSwiper.slideTo(0, 0);
  }
  if (mainSwiper) {
    mainSwiper.update();
    mainSwiper.slideTo(0, 0);
  }

  initLightbox();
  setTimeout(updateLensBackground, 100);
  if (window.lucide) lucide.createIcons();
}
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
