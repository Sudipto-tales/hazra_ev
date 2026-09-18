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
        $hex = '#' . substr(dechex($c['argb'] & 0xFFFFFF), -6);
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
} else {
    $colorsData[] = [
        'id'       => 'default',
        'name'     => 'Standard',
        'hex'      => '#1a1a1a',
        'in_stock' => true,
        'images'   => [$heroImg],
    ];
}

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

<!-- ========== UNIQUE PAGE CONTENT START ========== -->
<main class="page-body" style="padding-bottom: clamp(60px, 9vw, 120px);">
  <!-- Breadcrumb -->
  <div style="width: min(1280px, 93vw); margin: 30px auto 0; padding: 0 26px;">
    <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink-soft-0);">
      <a href="<?= e(base_url('')) ?>" style="color: inherit; text-decoration: none;">Home</a>
      <span>/</span>
      <a href="<?= e(base_url('products')) ?>" style="color: inherit; text-decoration: none;">Products</a>
      <span>/</span>
      <span style="color: var(--ink-0); font-weight: 600;"><?= e($product['name']) ?></span>
    </div>
  </div>

  <!-- Hero Section -->
  <section style="padding: 30px 26px clamp(40px, 6vw, 80px);">
    <div style="width: min(1280px, 93vw); margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: clamp(30px, 5vw, 60px); align-items: center;">
      
      <!-- Product Gallery Display with Swiper, Glass Zoom & Swatches -->
      <div class="product-gallery-wrap">
        <div class="gallery-stage" id="galleryStage">
          <!-- Main Swiper Carousel -->
          <div class="swiper product-swiper-main" id="productSwiperMain">
            <div class="swiper-wrapper" id="swiper-main-wrapper">
              <?php foreach ($colorsData[0]['images'] as $idx => $img): ?>
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
              <?php foreach ($colorsData[0]['images'] as $idx => $img): ?>
                <div class="swiper-slide <?= $idx === 0 ? 'swiper-slide-thumb-active' : '' ?>" data-index="<?= $idx ?>">
                  <img src="<?= e($img) ?>" alt="<?= e($product['name'] . ' thumbnail ' . ($idx + 1)) ?>">
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- Color Picker Swatches -->
        <div class="gallery-swatches-block">
          <div class="gallery-swatches-header">
            <span class="gallery-swatches-title">Available Colorways</span>
            <span class="gallery-swatches-selected" id="selectedColorLabel"><?= e($colorsData[0]['name']) ?></span>
          </div>
          <div class="gallery-swatches-list" id="swatchesContainer">
            <?php foreach ($colorsData as $idx => $c): ?>
              <button type="button" class="color-swatch-btn <?= $idx === 0 ? 'is-active' : '' ?>" data-color-index="<?= $idx ?>" onclick="switchColorway(<?= $idx ?>)">
                <span class="color-swatch-dot" style="background: <?= e($c['hex']) ?>;"></span>
                <span><?= e($c['name']) ?></span>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Product Details & Specs -->
      <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
          <span style="font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--brand-violet); background: rgb(var(--chip-rgb)); padding: 4px 12px; border-radius: 999px;">
            <?= e(strtoupper($product['category'])) ?>
          </span>
          <span style="font-size: 13px; font-weight: 700; color: var(--ink-soft-0);">
            Model: <?= e($product['model_code']) ?>
          </span>
        </div>

        <h1 style="font-family: 'Montserrat', sans-serif; font-size: clamp(34px, 4vw, 56px); font-weight: 900; color: var(--ink-0); line-height: 1.1; margin-bottom: 16px;">
          <?= e($product['brand'] . ' ' . $product['name']) ?>
        </h1>

        <p style="font-size: 16px; color: var(--ink-soft-0); line-height: 1.6; margin-bottom: 24px;">
          Engineered for performance and comfort. Experience effortless urban mobility with cutting-edge EV technology.
        </p>

        <!-- Highlights Bullet List -->
        <?php if (!empty($product['highlights'])): ?>
          <div style="margin-bottom: 28px;">
            <h4 style="font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ink-0); margin-bottom: 12px;">Key Highlights</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
              <?php foreach ($product['highlights'] as $hl): ?>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 13.5px; font-weight: 600; color: var(--ink-0);">
                  <i data-lucide="check-circle-2" style="width: 16px; height: 16px; color: #12a5e0; flex-shrink: 0;"></i>
                  <span><?= e($hl) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Quick CTA -->
        <div style="display: flex; gap: 14px; margin-bottom: 36px; flex-wrap: wrap;">
          <a href="<?= e(base_url('dealership-enquiry')) ?>" style="padding: 16px 36px; background: var(--brand-grad); color: #fff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: 800; letter-spacing: .04em; transition: transform .25s, box-shadow .25s; box-shadow: 0 10px 25px -8px rgba(240, 83, 43, .4);">
            Book a Test Drive
          </a>
          <a href="<?= e(base_url('dealer-locator')) ?>" style="padding: 16px 32px; background: var(--surface-0); border: 1px solid var(--hair-0); color: var(--ink-0); text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: 700; transition: background .25s;">
            Find Nearest Dealer
          </a>
        </div>

        <!-- Warranty & Coverage Banner -->
        <?php if ($product['warranty_years'] > 0 || !empty($product['warranty_note'])): ?>
          <div style="padding: 18px 22px; background: rgb(var(--chip-rgb) / .4); border: 1px solid var(--hair-0); border-radius: 18px; display: flex; align-items: center; gap: 14px;">
            <i data-lucide="shield-check" style="width: 28px; height: 28px; color: var(--brand-orange); flex-shrink: 0;"></i>
            <div>
              <div style="font-size: 13.5px; font-weight: 800; color: var(--ink-0);">
                <?= (int)$product['warranty_years'] ?> Years Official Warranty Coverage
              </div>
              <div style="font-size: 12px; color: var(--ink-soft-0); margin-top: 2px;">
                <?= e($product['warranty_note'] ?: 'Includes complete battery and vehicle coverage.') ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Deep Technical Specifications Grid -->
  <section style="padding: 60px 26px; background: rgb(var(--chip-rgb) / .3); border-top: 1px solid var(--hair-0); border-bottom: 1px solid var(--hair-0);">
    <div style="width: min(1280px, 93vw); margin: 0 auto;">
      <h2 style="font-family: 'Montserrat', sans-serif; font-size: 28px; font-weight: 800; color: var(--ink-0); margin-bottom: 36px; text-align: center;">
        Technical Specifications
      </h2>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="zap" style="width: 24px; height: 24px; color: var(--brand-flame); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">RIDING RANGE</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= (int)$product['range_km'] ?> km</span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">Per full charge under standard riding conditions</p>
        </div>

        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="gauge" style="width: 24px; height: 24px; color: var(--brand-blue); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">TOP SPEED</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= (int)$product['top_speed_kmph'] ?> km/h</span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">Smooth acceleration with multi-drive modes</p>
        </div>

        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="battery-charging" style="width: 24px; height: 24px; color: var(--brand-violet); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">BATTERY CAPACITY</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= e($product['battery_capacity'] ?: 'N/A') ?></span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">Advanced Lithium-ion with Smart BMS protection</p>
        </div>

        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="cpu" style="width: 24px; height: 24px; color: var(--brand-orange); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">MOTOR POWER</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= e($product['motor_power'] ?: 'N/A') ?></span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">High efficiency brushless electric powertrain</p>
        </div>

        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="clock" style="width: 24px; height: 24px; color: var(--brand-blue); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">CHARGING TIME</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= e($product['charging_time'] ?: 'N/A') ?></span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">Standard home socket fast charging</p>
        </div>

        <div style="padding: 24px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px;">
          <i data-lucide="weight" style="width: 24px; height: 24px; color: var(--brand-flame); margin-bottom: 12px;"></i>
          <span style="display: block; font-size: 11px; font-weight: 700; color: var(--ink-soft-0); letter-spacing: .08em; text-transform: uppercase;">PAYLOAD CAPACITY</span>
          <span style="font-size: 24px; font-weight: 900; color: var(--ink-0);"><?= (int)$product['load_capacity_kg'] ?> kg</span>
          <p style="font-size: 11.5px; color: var(--ink-soft-0); margin-top: 4px;">Heavy-duty reinforced chassis</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Related Models Section -->
  <?php if (!empty($relatedProducts)): ?>
    <section style="padding: 60px 26px 20px;">
      <div style="width: min(1280px, 93vw); margin: 0 auto;">
        <h2 style="font-family: 'Montserrat', sans-serif; font-size: 24px; font-weight: 800; color: var(--ink-0); margin-bottom: 24px;">
          Explore Other Models
        </h2>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
          <?php foreach ($relatedProducts as $rel): ?>
            <div style="padding: 20px; background: var(--surface-0); border: 1px solid var(--hair-0); border-radius: 20px; display: flex; flex-direction: column;">
              <h3 style="font-size: 18px; font-weight: 800; color: var(--ink-0); margin-bottom: 4px;"><?= e($rel['name']) ?></h3>
              <span style="font-size: 12px; color: var(--ink-soft-0); font-weight: 600; margin-bottom: 16px;">Range: <?= (int)$rel['range_km'] ?> km | Speed: <?= (int)$rel['top_speed_kmph'] ?> km/h</span>
              <a href="<?= e(base_url('product-detail?id=' . urlencode($rel['id']))) ?>" style="margin-top: auto; padding: 10px 18px; background: rgb(var(--chip-rgb)); color: var(--ink-0); text-decoration: none; border-radius: 999px; font-size: 12.5px; font-weight: 700; text-align: center;">
                View Model &rarr;
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>

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
  const color = window.__PRODUCT_COLORS__[currentColorIndex] || window.__PRODUCT_COLORS__[0];
  const elements = (color.images || []).map(img => ({
    href: img,
    type: 'image',
    title: <?= json_encode($product['name']) ?> + ' — ' + color.name,
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

  const label = document.getElementById('selectedColorLabel');
  if (label) label.textContent = color.name;

  document.querySelectorAll('.color-swatch-btn').forEach((btn, idx) => {
    btn.classList.toggle('is-active', idx === colorIndex);
  });

  const mainWrapper = document.getElementById('swiper-main-wrapper');
  if (mainWrapper) {
    mainWrapper.innerHTML = color.images.map((img, idx) => `
      <div class="swiper-slide" data-img-index="${idx}">
        <img src="${img}" alt="${color.name}" loading="${idx === 0 ? 'eager' : 'lazy'}">
      </div>
    `).join('');
  }

  const thumbsWrapper = document.getElementById('swiper-thumbs-wrapper');
  if (thumbsWrapper) {
    thumbsWrapper.innerHTML = color.images.map((img, idx) => `
      <div class="swiper-slide ${idx === 0 ? 'swiper-slide-thumb-active' : ''}" data-index="${idx}">
        <img src="${img}" alt="${color.name} thumbnail ${idx + 1}">
      </div>
    `).join('');
  }

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
