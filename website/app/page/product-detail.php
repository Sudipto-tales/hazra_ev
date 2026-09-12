<?php
// Determine requested product ID or model code
$reqId = $_GET['id'] ?? $_GET['model'] ?? $_GET['slug'] ?? '';

$product = null;
$relatedProducts = [];

try {
    if (function_exists('db_fetch_one')) {
        if ($reqId !== '') {
            $product = db_fetch_one("SELECT * FROM products WHERE (id = ? OR model_code = ? OR name = ?) AND active = 1", [$reqId, $reqId, $reqId]);
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

App::render('head', [
    'pageTitle'       => $pageTitle,
    'pageDescription' => $pageDescription,
    'extraCss'        => ['assets/css/styles/product.css'],
]);

App::render('header', ['isStickyOnly' => true]);

$activeColor = $product['colors'][0] ?? null;
$heroImg = ($activeColor && !empty($activeColor['images'])) ? $activeColor['images'][0] : base_url('assets/scutie_light.png');
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
      
      <!-- Product Gallery Display -->
      <div>
        <div style="position: relative; width: 100%; height: clamp(320px, 45vh, 480px); background: radial-gradient(circle at 50% 50%, rgb(var(--chip-rgb) / .7), var(--surface-0)); border: 1px solid var(--hair-0); border-radius: 32px; display: grid; place-items: center; overflow: hidden; box-shadow: 0 20px 40px -15px rgba(0,0,0,.08);">
          <img id="main-scooter-img" src="<?= e($heroImg) ?>" alt="<?= e($product['name']) ?>" style="max-width: 85%; max-height: 85%; object-fit: contain; transition: transform .4s var(--ease), opacity .3s;">
        </div>

        <!-- Color Picker & Gallery Thumbnails -->
        <?php if (!empty($product['colors'])): ?>
          <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 12px;">
            <span style="font-size: 12px; font-weight: 700; color: var(--ink-soft-0); text-transform: uppercase; letter-spacing: .08em;">Available Colorways</span>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
              <?php foreach ($product['colors'] as $idx => $c): ?>
                <?php
                  $hex = '#' . substr(dechex($c['argb'] & 0xFFFFFF), -6);
                  $cImg = !empty($c['images']) ? $c['images'][0] : $heroImg;
                ?>
                <button onclick="selectColor('<?= e($cImg) ?>', '<?= e($c['name']) ?>', this)"
                        style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 999px; border: 2px solid <?= $idx === 0 ? 'var(--ink-0)' : 'var(--hair-0)' ?>; background: var(--surface-0); cursor: pointer; transition: all .25s;">
                  <span style="width: 16px; height: 16px; border-radius: 50%; background: <?= $hex ?>; display: inline-block;"></span>
                  <span style="font-size: 13px; font-weight: 600; color: var(--ink-0);"><?= e($c['name']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();
});

function selectColor(imgUrl, colorName, btn) {
  const img = document.getElementById('main-scooter-img');
  if (img) {
    img.style.opacity = '0';
    setTimeout(() => {
      img.src = imgUrl;
      img.style.opacity = '1';
    }, 200);
  }
  btn.parentElement.querySelectorAll('button').forEach(b => b.style.borderColor = 'var(--hair-0)');
  btn.style.borderColor = 'var(--ink-0)';
}
</script>
<!-- ========== UNIQUE PAGE CONTENT END ========== -->

<?php App::render('footer'); ?>
